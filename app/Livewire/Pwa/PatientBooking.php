<?php

namespace App\Livewire\Pwa;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Services\AppointmentService;
use App\Services\BookingEligibilityService;
use App\Services\ClinicClock;
use App\Services\ClinicScheduleService;
use App\Services\PatientIdentityService;
use App\Services\TenantContext;
use App\Services\TokenSlipService;
use Carbon\Carbon;
use Livewire\Component;

class PatientBooking extends Component
{
    public ?string $public_slug = null;
    public ?string $clinic_code = null;
    public string $name = '';
    public string $age = '';
    public string $sex = 'MALE';
    public string $mobile = '';
    public string $address = '';
    public string $city = 'Varanasi';
    public string $appointment_date = '';
    public string $session_type = 'MORNING';
    public string $consultation_mode = 'CLINIC_PHYSICAL';
    public ?int $selected_doctor_id = null;
    public ?int $selected_booking_type_id = null;
    public array $available_booking_types = [];

    public array $available_sessions = [];
    public array $active_doctors = [];
    public ?Appointment $bookedAppointment = null;
    public bool $bookingSuccess = false;

    public function boot(): void
    {
        $this->ensureAuthoritativeClinic();
    }

    public function hydrate(): void
    {
        $this->ensureAuthoritativeClinic();
    }

    public function ensureAuthoritativeClinic(): ?Clinic
    {
        $slug = $this->public_slug ?: request()->route('public_slug');

        if (!empty($slug)) {
            $clinic = Clinic::where('public_slug', strtolower(trim((string) $slug)))->first();
            if ($clinic) {
                $this->public_slug = $clinic->public_slug;
                $this->clinic_code = $clinic->clinic_code;
                TenantContext::setClinic($clinic);
                return $clinic;
            }
            abort(404, 'Clinic not found.');
        }

        // Backward compatibility for direct component invocation passing clinic_code
        if (!empty($this->clinic_code)) {
            $clinic = Clinic::whereRaw('LOWER(clinic_code) = ?', [strtolower(trim($this->clinic_code))])->first();
            if ($clinic) {
                $this->public_slug = $clinic->public_slug;
                $this->clinic_code = $clinic->clinic_code;
                TenantContext::setClinic($clinic);
                return $clinic;
            }
            abort(404, 'Clinic not found.');
        }

        abort(404, 'Clinic not specified.');
    }

    protected function rules(): array
    {
        $clinic = $this->ensureAuthoritativeClinic();
        $clinicId = $clinic?->id;

        $bookingTypeRule = ['nullable', 'integer'];
        if (!empty($this->available_booking_types)) {
            $allowedTypeIds = array_column($this->available_booking_types, 'id');
            $bookingTypeRule = ['required', 'integer', \Illuminate\Validation\Rule::in($allowedTypeIds)];
        }

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                function ($attribute, $value, $fail) {
                    if (!\App\Services\PersonNameNormalizer::isValid((string) $value)) {
                        $fail(\App\Services\PersonNameNormalizer::ERROR_MESSAGE);
                    }
                },
            ],
            'age' => 'required|integer|min:0|max:130',
            'sex' => 'required|in:MALE,FEMALE,OTHER',
            'mobile' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!\App\Services\PhoneNumberNormalizer::isValid((string) $value)) {
                        $fail(\App\Services\PhoneNumberNormalizer::ERROR_MESSAGE);
                    }
                },
            ],
            'address' => 'required|string|min:2|max:255',
            'city' => 'required|string|min:2|max:100',
            'consultation_mode' => 'required|in:CLINIC_PHYSICAL,ONLINE,VISITING_CONSULTANCY',
            'selected_booking_type_id' => $bookingTypeRule,
            'appointment_date' => 'required|date|after_or_equal:today',
            'session_type' => 'required|string',
            'selected_doctor_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('doctors', 'id')->where(function ($query) use ($clinicId) {
                    if ($clinicId) {
                        $query->where('clinic_id', $clinicId);
                    }
                }),
            ],
        ];
    }

    public function mount(?string $public_slug = null, ?ClinicScheduleService $scheduleService = null, ?string $clinic_code = null): void
    {
        $this->public_slug = $public_slug;
        if (!empty($clinic_code) && empty($this->clinic_code)) {
            $this->clinic_code = $clinic_code;
        }
        $clinic = $this->ensureAuthoritativeClinic();

        if ($clinic) {
            $this->city = $clinic->city ?? 'Varanasi';
            if ($clinic->isDemoPaused()) {
                abort(503, 'The DxClinic demonstration environment is temporarily unavailable. Please try again later.');
            }
        }

        $this->appointment_date = Carbon::today('Asia/Kolkata')->toDateString();
        $this->loadDoctors();
        $this->loadBookingTypes();

        $svc = $scheduleService ?? app(ClinicScheduleService::class);
        $this->updateAvailableSessions($svc);

        // If no sessions are bookable for today (e.g. all expired at night),
        // auto-advance to tomorrow so the session selector is never empty on page load
        if (empty($this->available_sessions) && $clinic) {
            $tomorrow = Carbon::tomorrow('Asia/Kolkata')->toDateString();
            if (ClinicClock::isAllowedDate($tomorrow, $clinic->max_advance_booking_days)) {
                $this->appointment_date = $tomorrow;
                $this->updateAvailableSessions($svc);
            }
        }
    }

    public function updatedMobile(): void
    {
        if (\App\Services\PhoneNumberNormalizer::isValid($this->mobile)) {
            $this->mobile = \App\Services\PhoneNumberNormalizer::normalize($this->mobile);
        }
        $this->validateOnly('mobile');
    }

    public function updatedName(): void
    {
        $this->validateOnly('name');
    }

    public function updatedAge(): void
    {
        $this->validateOnly('age');
    }

    public function updatedAddress(): void
    {
        $this->validateOnly('address');
    }

    public function updatedCity(): void
    {
        $this->validateOnly('city');
    }

    public function loadDoctors(): void
    {
        $clinic = $this->ensureAuthoritativeClinic();
        if ($clinic) {
            $this->active_doctors = Doctor::where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->toArray();

            $activeDocIds = collect($this->active_doctors)->pluck('id')->all();

            // Single active doctor: auto-select
            if (count($this->active_doctors) === 1) {
                $this->selected_doctor_id = $this->active_doctors[0]['id'];
            } elseif ($this->selected_doctor_id && in_array($this->selected_doctor_id, $activeDocIds, true)) {
                // Keep currently selected valid doctor
            } elseif ($clinic->default_doctor_id && in_array($clinic->default_doctor_id, $activeDocIds, true)) {
                // Multi-doctor: preselect default doctor
                $this->selected_doctor_id = $clinic->default_doctor_id;
            } elseif (count($this->active_doctors) > 0) {
                $this->selected_doctor_id = $this->active_doctors[0]['id'];
            }
        }
    }

    public function getEnabledConsultationModesProperty(): array
    {
        if (!$this->selected_doctor_id) {
            return ['CLINIC_PHYSICAL', 'ONLINE'];
        }
        $clinic = $this->ensureAuthoritativeClinic();
        $doctor = Doctor::where('id', $this->selected_doctor_id)
            ->when($clinic, fn($q) => $q->where('clinic_id', $clinic->id))
            ->first();

        $modes = $doctor ? $doctor->enabledConsultationModes() : [];
        return !empty($modes) ? $modes : ['CLINIC_PHYSICAL', 'ONLINE'];
    }

    public function loadBookingTypes(): void
    {
        $clinic = $this->ensureAuthoritativeClinic();
        if (!$clinic) {
            $this->available_booking_types = [];
            return;
        }

        $btService = app(\App\Services\BookingTypeService::class);
        $types = $btService->getPublicBookableForClinic($clinic->id, $this->selected_doctor_id, requireActiveFee: true);
        $this->available_booking_types = $types->toArray();

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
            $bt = \App\Models\BookingType::find($this->selected_booking_type_id);
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

    public function updatedAppointmentDate(): void
    {
        $this->updateAvailableSessions(app(ClinicScheduleService::class));
    }

    public function updatedSelectedDoctorId(): void
    {
        $this->updateAvailableSessions(app(ClinicScheduleService::class));
        $this->loadBookingTypes();
        if ($this->selected_booking_type_id) {
            $bt = \App\Models\BookingType::find($this->selected_booking_type_id);
            if ($bt && $bt->consultation_mode) {
                $this->consultation_mode = $bt->consultation_mode;
            }
        }
        if (!in_array($this->consultation_mode, $this->enabledConsultationModes, true)) {
            $this->consultation_mode = $this->enabledConsultationModes[0] ?? 'CLINIC_PHYSICAL';
        }
    }

    public function updateAvailableSessions(?ClinicScheduleService $scheduleService = null, ?BookingEligibilityService $eligibilityService = null): void
    {
        $clinic = $this->ensureAuthoritativeClinic();
        if ($clinic && !empty($this->appointment_date)) {
            $eligibility = $eligibilityService ?? app(BookingEligibilityService::class);
            $sessions = $eligibility->getAvailableSessions($clinic, $this->appointment_date, $this->selected_doctor_id);
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
                $this->session_type = array_key_first($this->available_sessions) ?? 'MORNING';
            }
        }
    }

    public function bookToken(PatientIdentityService $patientService, AppointmentService $appointmentService): void
    {
        if ($this->bookingSuccess && $this->bookedAppointment) {
            return;
        }

        $this->validate();

        $clinic = $this->ensureAuthoritativeClinic();
        if (!$clinic) {
            $this->addError('general', 'Clinic configuration not available.');
            return;
        }

        if ($clinic->isDemoPaused()) {
            abort(503, 'The DxClinic demonstration environment is temporarily unavailable. Please try again later.');
        }

        if (empty($this->available_sessions) || !array_key_exists($this->session_type, $this->available_sessions)) {
            $this->addError('session_type', 'The selected session is not available for this date. Please select an available session.');
            return;
        }

        if (!\App\Services\PersonNameNormalizer::isValid($this->name)) {
            $this->addError('name', \App\Services\PersonNameNormalizer::ERROR_MESSAGE);
            return;
        }

        if (!\App\Services\PhoneNumberNormalizer::isValid($this->mobile)) {
            $this->addError('mobile', \App\Services\PhoneNumberNormalizer::ERROR_MESSAGE);
            return;
        }

        // Validate doctor belongs strictly to this clinic
        $doctor = Doctor::where('clinic_id', $clinic->id)->where('id', $this->selected_doctor_id)->first();
        if (!$doctor) {
            $this->addError('selected_doctor_id', 'Selected doctor is not available for this clinic.');
            return;
        }

        try {
            // Normalize mobile and name to canonical formats
            $normalizedMobile = \App\Services\PhoneNumberNormalizer::normalize($this->mobile);
            $normalizedName = \App\Services\PersonNameNormalizer::normalize($this->name);

            // Resolve or create patient scoped strictly to this clinic
            $patient = $patientService->resolvePatient([
                'clinic_id' => $clinic->id,
                'name' => $normalizedName,
                'age' => (int) $this->age,
                'sex' => $this->sex,
                'mobile' => $normalizedMobile,
                'address' => trim($this->address),
                'city' => trim($this->city),
            ]);

            // Idempotency check: if an online booking already exists for this patient, clinic, date & session, reuse it
            $existingAppointment = Appointment::where('clinic_id', $clinic->id)
                ->where('patient_id', $patient->id)
                ->whereDate('appointment_date', $this->appointment_date)
                ->where('session_type', $this->session_type)
                ->where('booking_source', 'ONLINE')
                ->whereIn('status', ['BOOKED', 'ARRIVED', 'ENGAGED'])
                ->latest('id')
                ->first();

            if ($existingAppointment) {
                $this->bookedAppointment = $existingAppointment->load(['clinic', 'patient', 'doctor']);
                $this->bookingSuccess = true;
                return;
            }

            // Book appointment with date-scoped common token atomically
            $appointment = $appointmentService->bookAppointment([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $this->selected_doctor_id,
                'appointment_date' => $this->appointment_date,
                'session_type' => $this->session_type,
                'booking_source' => 'ONLINE',
                'consultation_mode' => $this->consultation_mode,
                'booking_type_id' => $this->selected_booking_type_id ?: null,
            ]);

            $this->bookedAppointment = $appointment->load(['clinic', 'patient', 'doctor']);
            $this->bookingSuccess = true;
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'digit') || str_contains($msg, 'mobile') || str_contains($msg, 'phone') || str_contains($msg, 'limit')) {
                $this->addError('mobile', $msg);
            } elseif (str_contains($msg, 'name') || str_contains($msg, 'Name')) {
                $this->addError('name', $msg);
            } elseif (str_contains($msg, 'session') || str_contains($msg, 'Session') || str_contains($msg, 'ended')) {
                $this->addError('session_type', $msg);
            } elseif (str_contains($msg, 'date') || str_contains($msg, 'Date') || str_contains($msg, 'advance') || str_contains($msg, 'today or tomorrow')) {
                $this->addError('appointment_date', $msg);
            } elseif (str_contains($msg, 'doctor') || str_contains($msg, 'Doctor')) {
                $this->addError('selected_doctor_id', $msg);
            } elseif (str_contains($msg, 'booking type') || str_contains($msg, 'Booking type') || str_contains($msg, 'consultancy') || str_contains($msg, 'Fee') || str_contains($msg, 'fee')) {
                $this->addError('selected_booking_type_id', $msg);
            } elseif (str_contains($msg, 'age') || str_contains($msg, 'Age')) {
                $this->addError('age', $msg);
            } elseif (str_contains($msg, 'city') || str_contains($msg, 'City')) {
                $this->addError('city', $msg);
            } else {
                $this->addError('general', $msg);
            }
        } catch (\RuntimeException $e) {
            \Illuminate\Support\Facades\Log::warning('PWA Booking RuntimeException: ' . $e->getMessage(), [
                'clinic_id' => $clinic->id,
                'mobile' => $this->mobile,
            ]);
            $this->addError('general', $e->getMessage());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PWA Booking Unexpected Error: ' . $e->getMessage(), [
                'clinic_id' => $clinic->id,
                'exception' => $e,
            ]);
            $this->addError('general', 'Unable to complete your booking at this time. Please check your information or try again.');
        }
    }

    public function bookAnother(): void
    {
        $this->bookingSuccess = false;
        $this->bookedAppointment = null;
        $this->name = '';
        $this->age = '';
        $this->mobile = '';
        $this->address = '';
        $this->consultation_mode = 'CLINIC_PHYSICAL';
        $this->selected_booking_type_id = null;
        $this->resetErrorBag();
        $this->loadDoctors();
        $this->loadBookingTypes();
    }

    public function sendTokenSlipOnWhatsApp(?TokenSlipService $tokenSlipService = null): void
    {
        $tokenSlipService = $tokenSlipService ?? app(TokenSlipService::class);

        if (!$this->bookingSuccess || !$this->bookedAppointment) {
            $this->addError('general', 'No active booking to send.');
            return;
        }

        $clinic = $this->ensureAuthoritativeClinic();
        $appointment = Appointment::where('clinic_id', $clinic->id)
            ->with(['patient', 'doctor', 'clinic'])
            ->find($this->bookedAppointment->id);

        if (!$appointment) {
            $this->addError('general', 'Appointment record not found.');
            return;
        }

        $patientMobile = $appointment->patient?->mobile;
        if (empty($patientMobile) || !\App\Services\PhoneNumberNormalizer::isValid($patientMobile)) {
            $this->addError('general', 'WhatsApp number verification required.');
            return;
        }

        $result = $tokenSlipService->sendTokenSlipViaWhatsApp(
            $appointment,
            $patientMobile,
            false,
            'PWA',
            null
        );

        if ($result['success'] ?? false) {
            session()->flash('whatsapp_success', 'Token slip has been sent to your WhatsApp number (' . \App\Services\WhatsAppIdentityNormalizer::toDisplay($patientMobile) . ').');
        } else {
            session()->flash('whatsapp_error', 'Unable to deliver WhatsApp token slip at this moment. You can download the PDF above.');
        }
    }

    public function render()
    {
        $clinic = $this->ensureAuthoritativeClinic();
        $clinicId = $clinic?->id;

        $physicalFee = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $clinicId, $this->selected_doctor_id);
        $onlineFee = FeeMaster::getFeeForMode('ONLINE', $clinicId, $this->selected_doctor_id);
        $visitingFee = FeeMaster::getFeeForMode('VISITING_CONSULTANCY', $clinicId, $this->selected_doctor_id);

        return view('livewire.pwa.patient-booking', [
            'clinic' => $clinic,
            'active_doctors' => $this->active_doctors,
            'physicalFee' => $physicalFee,
            'onlineFee' => $onlineFee,
            'visitingFee' => $visitingFee,
            'enabledModes' => $this->enabledConsultationModes,
            'availableBookingTypes' => $this->available_booking_types,
        ])->layout('components.layouts.public-booking', ['title' => 'Patient Appointment & Token']);
    }
}
