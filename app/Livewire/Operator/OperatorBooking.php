<?php

namespace App\Livewire\Operator;

use App\Models\Appointment;
use App\Models\BookingType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\BookingEligibilityService;
use App\Services\BookingTypeService;
use App\Services\ClinicClock;
use App\Services\ClinicScheduleService;
use App\Services\PatientIdentityService;
use App\Services\PersonNameNormalizer;
use App\Services\PhoneNumberNormalizer;
use App\Services\TokenSlipService;
use Carbon\Carbon;
use Livewire\Component;

class OperatorBooking extends Component
{
    // Search existing patient
    public string $searchQuery = '';
    public array $searchResults = [];
    public ?int $selectedPatientId = null;
    public ?Patient $selectedPatient = null;
    public bool $isNewPatientMode = true;

    // Duplicate detection state
    public array $duplicateCandidates = [];
    public bool $allowExplicitNewPatientWithDuplicateMobile = false;

    // Patient Form Fields
    public string $name = '';
    public string $age = '';
    public string $sex = 'MALE';
    public string $mobile = '';
    public string $address = '';
    public string $city = 'Varanasi';

    // Appointment Form Fields
    public string $consultation_mode = 'CLINIC_PHYSICAL';
    public ?int $selected_doctor_id = null;
    public ?int $selected_booking_type_id = null;
    public string $appointment_date = '';
    public string $session_type = 'MORNING';

    // UI & State
    public array $available_sessions = [];
    public array $active_doctors = [];
    public array $available_booking_types = [];
    public ?Appointment $bookedAppointment = null;
    public bool $bookingSuccess = false;
    public string $todayDate = '';
    public string $tomorrowDate = '';
    public bool $noSessionsRemainingToday = false;

    protected function rules(): array
    {
        $clinic = \App\Services\TenantContext::clinic();
        $maxDays = $clinic?->max_advance_booking_days ?? 1;
        $allowedDates = [];
        for ($i = 0; $i <= $maxDays; $i++) {
            $allowedDates[] = ClinicClock::today()->addDays($i)->toDateString();
        }
        $dateRule = 'required|date|in:' . implode(',', $allowedDates);

        $clinicId = \App\Services\TenantContext::clinicId();
        $doctorRule = [
            'required',
            'integer',
            \Illuminate\Validation\Rule::exists('doctors', 'id')->where(fn($q) => $q->where('clinic_id', $clinicId)),
        ];

        $bookingTypeRule = ['nullable', 'integer'];
        if (!empty($this->available_booking_types)) {
            $allowedTypeIds = array_column($this->available_booking_types, 'id');
            $bookingTypeRule = ['required', 'integer', \Illuminate\Validation\Rule::in($allowedTypeIds)];
        }

        if ($this->selectedPatientId) {
            return [
                'selected_doctor_id' => $doctorRule,
                'selected_booking_type_id' => $bookingTypeRule,
                'consultation_mode' => 'required|in:CLINIC_PHYSICAL,ONLINE,VISITING_CONSULTANCY',
                'appointment_date' => $dateRule,
                'session_type' => 'required|string',
            ];
        }

        return [
            'name' => 'required|string|min:2|max:100',
            'age' => 'required|integer|min:0|max:130',
            'sex' => 'required|in:MALE,FEMALE,OTHER',
            'mobile' => 'required|string|min:10|max:20',
            'address' => 'required|string|min:2|max:255',
            'city' => 'required|string|min:2|max:100',
            'consultation_mode' => 'required|in:CLINIC_PHYSICAL,ONLINE,VISITING_CONSULTANCY',
            'selected_doctor_id' => $doctorRule,
            'selected_booking_type_id' => $bookingTypeRule,
            'appointment_date' => $dateRule,
            'session_type' => 'required|string',
        ];
    }

    public function getEnabledConsultationModesProperty(): array
    {
        if (!$this->selected_doctor_id) {
            return ['CLINIC_PHYSICAL', 'ONLINE'];
        }
        $doctor = Doctor::find($this->selected_doctor_id);
        $modes = $doctor ? $doctor->enabledConsultationModes() : [];
        return !empty($modes) ? $modes : ['CLINIC_PHYSICAL', 'ONLINE'];
    }

    protected $messages = [
        'appointment_date.in' => 'Appointments may only be booked for allowed clinic dates.',
        'selected_booking_type_id.required' => 'Please select a valid booking type for the attending doctor.',
        'selected_booking_type_id.in' => 'Selected booking type is not enabled or configured for this doctor.',
    ];

    public function mount(ClinicScheduleService $scheduleService): void
    {
        $this->todayDate = ClinicClock::today()->toDateString();
        $this->tomorrowDate = ClinicClock::tomorrow()->toDateString();

        $this->loadDoctors();
        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');
        $doctor = $this->selected_doctor_id ? Doctor::where('clinic_id', $clinic->id)->find($this->selected_doctor_id) : null;
        $todaySessions = $scheduleService->getBookableSessionsForDate($clinic, $this->todayDate, $doctor);
        if (empty($todaySessions)) {
            $this->appointment_date = $this->tomorrowDate;
            $this->noSessionsRemainingToday = true;
        } else {
            $this->appointment_date = $this->todayDate;
            $this->noSessionsRemainingToday = false;
        }

        $this->updateAvailableSessions($scheduleService);
    }

    public function loadDoctors(): void
    {
        $clinic = \App\Services\TenantContext::clinic();
        if ($clinic) {
            $this->active_doctors = Doctor::where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->toArray();

            // Single active doctor: auto-select
            if (count($this->active_doctors) === 1) {
                $this->selected_doctor_id = $this->active_doctors[0]['id'];
            } elseif ($clinic->default_doctor_id && collect($this->active_doctors)->contains('id', $clinic->default_doctor_id)) {
                $this->selected_doctor_id = $clinic->default_doctor_id;
            } elseif (count($this->active_doctors) > 0) {
                $this->selected_doctor_id = $this->active_doctors[0]['id'];
            }
        }
        $this->loadBookingTypes();
    }

    public function loadBookingTypes(): void
    {
        $clinic = \App\Services\TenantContext::clinic();
        if (!$clinic) {
            $this->available_booking_types = [];
            return;
        }

        $btService = app(BookingTypeService::class);

        if ($this->selected_doctor_id) {
            // Revision 45: Strictly load types mapped to this doctor with an active fee
            $types = $btService->getEnabledForDoctor($this->selected_doctor_id, $clinic->id, requireActiveFee: true);
        } else {
            $types = collect();
        }

        $this->available_booking_types = $types->toArray();

        // Auto-select first booking type if none selected or invalid
        $availableIds = array_column($this->available_booking_types, 'id');
        if ((!$this->selected_booking_type_id || !in_array($this->selected_booking_type_id, $availableIds)) && !empty($this->available_booking_types)) {
            $this->selected_booking_type_id = $this->available_booking_types[0]['id'];
            if (!empty($this->available_booking_types[0]['consultation_mode'])) {
                $this->consultation_mode = $this->available_booking_types[0]['consultation_mode'];
            }
        } elseif (empty($this->available_booking_types)) {
            $this->selected_booking_type_id = null;
        }
    }

    public function updatedSelectedBookingTypeId(): void
    {
        if ($this->selected_booking_type_id) {
            $bt = BookingType::find($this->selected_booking_type_id);
            if ($bt && $bt->consultation_mode) {
                $this->consultation_mode = $bt->consultation_mode;
            }
        }
    }

    public function updatedConsultationMode(): void
    {
        if (!empty($this->available_booking_types)) {
            foreach ($this->available_booking_types as $bt) {
                if ($bt['consultation_mode'] === $this->consultation_mode) {
                    $this->selected_booking_type_id = $bt['id'];
                    break;
                }
            }
        }
    }

    public function updatedSearchQuery(): void
    {
        $q = trim($this->searchQuery);
        if (strlen($q) < 2) {
            $this->searchResults = [];
            return;
        }

        $clinic = \App\Services\TenantContext::clinic();
        if (!$clinic) {
            return;
        }

        $cleanDigits = preg_replace('/[^0-9]/', '', $q);

        $this->searchResults = Patient::where('clinic_id', $clinic->id)
            ->where(function ($query) use ($q, $cleanDigits) {
                $query->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('patient_id', 'LIKE', "%{$q}%")
                    ->orWhere('legacy_alias', 'LIKE', "%{$q}%");
                if (strlen($cleanDigits) >= 4) {
                    $query->orWhere('mobile', 'LIKE', "%{$cleanDigits}%");
                }
            })
            ->limit(8)
            ->get()
            ->toArray();
    }

    public function selectPatient(int $patientId): void
    {
        $clinic = \App\Services\TenantContext::clinic();
        $patient = $clinic ? Patient::where('clinic_id', $clinic->id)->find($patientId) : null;
        abort_unless($patient, 403, 'Unauthorized patient access.');

        if ($patient) {
            $this->selectedPatientId = $patient->id;
            $this->selectedPatient = $patient;
            $this->isNewPatientMode = false;
            $this->searchResults = [];
            $this->searchQuery = '';
            $this->duplicateCandidates = [];
            $this->allowExplicitNewPatientWithDuplicateMobile = false;
        }
    }

    public function switchToNewPatient(): void
    {
        $this->selectedPatientId = null;
        $this->selectedPatient = null;
        $this->isNewPatientMode = true;
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->duplicateCandidates = [];
        $this->allowExplicitNewPatientWithDuplicateMobile = false;
        $this->name = '';
        $this->age = '';
        $this->sex = 'MALE';
        $this->mobile = '';
        $this->address = '';
        $this->city = 'Varanasi';
    }

    public function switchToExistingPatient(): void
    {
        $this->isNewPatientMode = false;
        $this->selectedPatientId = null;
        $this->selectedPatient = null;
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->duplicateCandidates = [];
        $this->allowExplicitNewPatientWithDuplicateMobile = false;
    }

    public function updatedMobile(): void
    {
        if (PhoneNumberNormalizer::isValid($this->mobile)) {
            $this->mobile = PhoneNumberNormalizer::normalize($this->mobile);
        }

        $this->duplicateCandidates = [];
        $this->allowExplicitNewPatientWithDuplicateMobile = false;

        $clinic = \App\Services\TenantContext::clinic();
        if ($clinic && strlen(preg_replace('/[^0-9]/', '', $this->mobile)) >= 10) {
            $patientService = app(PatientIdentityService::class);
            $candidates = $patientService->findCandidatesByMobile($clinic->id, $this->mobile);
            if ($candidates->isNotEmpty()) {
                $this->duplicateCandidates = $candidates->toArray();
            }
        }
    }

    public function useExistingPatient(int $patientId): void
    {
        $this->selectPatient($patientId);
    }

    public function continueAsNewPatient(): void
    {
        $this->allowExplicitNewPatientWithDuplicateMobile = true;
        $this->duplicateCandidates = [];
    }

    public function updatedAppointmentDate(): void
    {
        $this->updateAvailableSessions(app(ClinicScheduleService::class));
    }

    public function updatedSelectedDoctorId(): void
    {
        $this->updateAvailableSessions(app(ClinicScheduleService::class));
        $this->loadBookingTypes();
        if (!in_array($this->consultation_mode, $this->enabledConsultationModes, true)) {
            $this->consultation_mode = $this->enabledConsultationModes[0] ?? 'CLINIC_PHYSICAL';
        }
    }

    public function updateAvailableSessions(?ClinicScheduleService $scheduleService = null, ?BookingEligibilityService $eligibilityService = null): void
    {
        $clinic = \App\Services\TenantContext::clinic();
        if ($clinic && !empty($this->appointment_date)) {
            $svc = $eligibilityService ?? app(BookingEligibilityService::class);
            $sessions = $svc->getAvailableSessions($clinic, $this->appointment_date, $this->selected_doctor_id);
            $this->available_sessions = [];
            foreach ($sessions as $s) {
                $this->available_sessions[$s['session']] = [
                    'label' => $s['label'],
                    'start' => $s['start'],
                    'end' => $s['end'],
                    'booking_cutoff' => $s['booking_cutoff'],
                ];
            }
            if (!array_key_exists($this->session_type, $this->available_sessions)) {
                $this->session_type = array_key_first($this->available_sessions) ?? '';
            }
        }
    }

    public function bookOperatorToken(PatientIdentityService $patientService, AppointmentService $appointmentService, ClinicScheduleService $scheduleService): void
    {
        $this->validate();

        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        // Verify session is currently bookable (not expired)
        if (!$scheduleService->isSessionBookable($clinic, $this->appointment_date, $this->session_type)) {
            $this->addError('session_type', 'This clinic session has already ended. Please select another available session.');
            $this->updateAvailableSessions($scheduleService);
            return;
        }

        // 1. Resolve Patient
        if ($this->selectedPatientId) {
            $patient = Patient::where('clinic_id', $clinic->id)->findOrFail($this->selectedPatientId);
        } else {
            if (!PersonNameNormalizer::isValid($this->name)) {
                $this->addError('name', PersonNameNormalizer::ERROR_MESSAGE);
                return;
            }
            if (!PhoneNumberNormalizer::isValid($this->mobile)) {
                $this->addError('mobile', PhoneNumberNormalizer::ERROR_MESSAGE);
                return;
            }

            // Check duplicate candidates unless explicitly confirmed
            if (!$this->allowExplicitNewPatientWithDuplicateMobile) {
                $candidates = $patientService->findCandidatesByMobile($clinic->id, $this->mobile);
                if ($candidates->isNotEmpty()) {
                    $this->duplicateCandidates = $candidates->toArray();
                    $this->addError('mobile', 'An existing patient with this mobile number was found. Please choose "Use Existing Patient" or "Continue as New Patient".');
                    return;
                }
            }

            $patient = $patientService->registerPatient([
                'clinic_id' => $clinic->id,
                'name' => $this->name,
                'age' => (int) $this->age,
                'sex' => $this->sex,
                'mobile' => $this->mobile,
                'address' => $this->address,
                'city' => $this->city,
                'allow_explicit_duplicate' => $this->allowExplicitNewPatientWithDuplicateMobile,
            ]);
        }

        // 2. Book appointment with booking_source = CLINIC
        $appointment = $appointmentService->bookAppointment([
            'clinic_id'       => $clinic->id,
            'patient_id'      => $patient->id,
            'doctor_id'       => $this->selected_doctor_id,
            'appointment_date'=> $this->appointment_date,
            'session_type'    => $this->session_type,
            'booking_source'  => 'CLINIC',
            'consultation_mode' => $this->consultation_mode,
            'booking_type_id'   => $this->selected_booking_type_id ?: null,
        ]);

        $this->bookedAppointment = $appointment->load(['clinic', 'patient', 'doctor']);
        $this->bookingSuccess = true;
    }

    public function bookAnother(): void
    {
        $this->bookingSuccess = false;
        $this->bookedAppointment = null;
        $this->switchToNewPatient();
        $this->consultation_mode = 'CLINIC_PHYSICAL';
        $this->loadDoctors();
        $this->updateAvailableSessions(app(ClinicScheduleService::class));
    }

    public function sendTokenSlipViaWhatsApp(?TokenSlipService $tokenSlipService = null): void
    {
        $tokenSlipService = $tokenSlipService ?? app(TokenSlipService::class);
        if (!$this->bookingSuccess || !$this->bookedAppointment) {
            session()->flash('error', 'No active booking to send.');
            return;
        }

        $result = $tokenSlipService->sendTokenSlipViaWhatsApp(
            $this->bookedAppointment,
            null,
            false,
            auth()->user()?->role ?? 'OPERATOR',
            auth()->id()
        );

        if ($result['success'] ?? false) {
            session()->flash('success', "Token slip sent via WhatsApp to {$this->bookedAppointment->patient->name} (#{$this->bookedAppointment->formatted_token}).");
        } else {
            session()->flash('error', "Failed to send token slip: " . ($result['error'] ?? 'WhatsApp gateway unavailable.'));
        }
    }

    public function render()
    {
        $clinic = \App\Services\TenantContext::clinic();
        $physicalFee = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $clinic?->id, $this->selected_doctor_id);
        $onlineFee = FeeMaster::getFeeForMode('ONLINE', $clinic?->id, $this->selected_doctor_id);
        $visitingFee = FeeMaster::getFeeForMode('VISITING_CONSULTANCY', $clinic?->id, $this->selected_doctor_id);

        return view('livewire.operator.operator-booking', [
            'active_doctors'       => $this->active_doctors,
            'physicalFee'          => $physicalFee,
            'onlineFee'            => $onlineFee,
            'visitingFee'          => $visitingFee,
            'enabledModes'         => $this->enabledConsultationModes,
            'availableBookingTypes'=> $this->available_booking_types,
        ])->layout('components.layouts.app', ['title' => 'Operator Desk - Book Appointment']);
    }
}

