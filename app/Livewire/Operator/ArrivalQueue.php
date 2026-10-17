<?php

namespace App\Livewire\Operator;

use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\ClinicClock;
use App\Services\ClinicScheduleService;
use App\Services\PatientIdentityService;
use App\Services\TokenSlipService;
use App\Services\VisitService;
use App\Services\VitalService;
use Carbon\Carbon;
use Livewire\Component;

class ArrivalQueue extends Component
{
    public string $filterDate = '';
    public string $filterSession = 'ALL';
    public string $searchQuery = '';

    // Prominent Patient Arrival Check-in Search (Rule 17)
    public string $arrivalSearchQuery = '';
    public ?Appointment $searchedAppointment = null;
    public ?Appointment $activeWorkflowAppointment = null;
    public bool $arrivalSearchPerformed = false;

    // Quick Walk-in or Telephonic Registration Modal
    public bool $showNewBookingModal = false;
    public string $bookingSource = 'CLINIC'; // CLINIC or TELEPHONIC
    public string $newPatientName = '';
    public string $newPatientAge = '';
    public string $newPatientSex = 'MALE';
    public string $newPatientMobile = '';
    public string $newPatientAddress = '';
    public string $newPatientCity = 'Varanasi';
    public string $newBookingSession = 'MORNING';
    public ?int $newBookingDoctorId = null;

    // Vitals Modal
    public bool $showVitalsModal = false;
    public ?Visit $selectedVisit = null;
    public ?Appointment $selectedAppointment = null;
    public ?float $weight = null;
    public ?float $height = null;
    public ?int $bp_systolic = null;
    public ?int $bp_diastolic = null;
    public ?int $pulse = null;
    public ?int $spo2 = null;
    public ?float $temperature = 98.4;
    public bool $fever = false;
    public ?int $respiratory_rate = null;
    public ?float $rbs = null;

    // Complete Profile Modal at Arrival
    public bool $showProfileCompleteModal = false;
    public ?Appointment $completingAppointment = null;
    public string $editSex = 'MALE';
    public string $editAddress = '';
    public string $editCity = 'Varanasi';
    public ?string $public_slug = null;

    public function mount(?string $public_slug = null): void
    {
        $this->public_slug = $public_slug;
        $requestedDate = request()->query('date');
        $this->filterDate = $requestedDate ? Carbon::parse($requestedDate)->toDateString() : ClinicClock::today()->toDateString();
        $this->initDefaultSession();
    }

    public function searchPatientForArrival(): void
    {
        $this->arrivalSearchPerformed = true;
        $q = trim($this->arrivalSearchQuery);
        if (empty($q)) {
            $this->searchedAppointment = null;
            return;
        }

        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $query = Appointment::where('clinic_id', $clinic->id)
            ->with(['patient', 'visit.vital', 'visit.consultationBill.payment'])
            ->where(function ($sub) use ($q) {
                $sub->where('formatted_token', $q)
                    ->orWhere('token_number', (int) $q)
                    ->orWhereHas('patient', function ($pq) use ($q) {
                        $pq->where('patient_id', $q)
                            ->orWhere('legacy_alias', $q)
                            ->orWhere('mobile', $q)
                            ->orWhere('name', 'LIKE', "%{$q}%");
                    });
            })
            ->whereDate('appointment_date', $this->filterDate);

        if (auth()->check() && (auth()->user()->doctor_id || auth()->user()->role === 'DOCTOR')) {
            $doctorId = auth()->user()->doctor_id;
            if ($doctorId) {
                $query->where('doctor_id', $doctorId);
            }
        }

        $this->searchedAppointment = $query->first();
    }

    public function clearArrivalSearch(): void
    {
        $this->arrivalSearchQuery = '';
        $this->searchedAppointment = null;
        $this->arrivalSearchPerformed = false;
    }

    public function selectWorkflowAppointment(int $appointmentId): void
    {
        $clinicId = \App\Services\TenantContext::clinicId();
        $query = Appointment::where('clinic_id', $clinicId)
            ->with(['patient', 'visit.vital', 'visit.consultationBill.payment']);
        if (auth()->check() && (auth()->user()->doctor_id || auth()->user()->role === 'DOCTOR')) {
            $doctorId = auth()->user()->doctor_id;
            if ($doctorId) {
                $query->where('doctor_id', $doctorId);
            }
        }
        $this->activeWorkflowAppointment = $query->findOrFail($appointmentId);
    }

    public function initDefaultSession(): void
    {
        $clinic = \App\Services\TenantContext::clinic();
        if ($clinic) {
            $scheduleService = app(ClinicScheduleService::class);
            $sessions = $scheduleService->getSessionsForDate($clinic, $this->filterDate);
            $this->newBookingSession = array_key_first($sessions) ?? 'MORNING';

            $activeDocs = \App\Models\Doctor::where('clinic_id', $clinic->id)->where('is_active', true)->get();
            if ($activeDocs->count() === 1) {
                $this->newBookingDoctorId = $activeDocs->first()->id;
            } elseif ($clinic->default_doctor_id && $activeDocs->contains('id', $clinic->default_doctor_id)) {
                $this->newBookingDoctorId = $clinic->default_doctor_id;
            } elseif ($activeDocs->count() > 0) {
                $this->newBookingDoctorId = $activeDocs->first()->id;
            }
        }
    }

    public function markArrived(int $appointmentId, VisitService $visitService): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // Check if patient profile has missing fields
        if (empty($appointment->patient->sex) || empty($appointment->patient->address) || empty($appointment->patient->city)) {
            $this->completingAppointment = $appointment;
            $this->editSex = $appointment->patient->sex ?? 'MALE';
            $this->editAddress = $appointment->patient->address ?? '';
            $this->editCity = $appointment->patient->city ?? 'Varanasi';
            $this->showProfileCompleteModal = true;
            return;
        }

        // Idempotent arrival & Visit resolution (Section 52, 53, 55)
        $visit = $visitService->markArrival($appointment);
        $this->activeWorkflowAppointment = $appointment->fresh(['patient', 'visit.vital', 'visit.consultationBill.payment']);
        if ($this->searchedAppointment && $this->searchedAppointment->id === $appointment->id) {
            $this->searchedAppointment = $this->activeWorkflowAppointment;
        }

        session()->flash('success', "Patient {$appointment->patient->name} marked ARRIVED (Token: {$appointment->formatted_token})");
    }

    public function saveProfileAndArrive(VisitService $visitService): void
    {
        if (!$this->completingAppointment) return;

        $updates = [
            'sex' => $this->editSex,
            'address' => $this->editAddress,
            'city' => $this->editCity,
        ];

        $visit = $visitService->markArrival($this->completingAppointment, $updates);
        $this->activeWorkflowAppointment = $this->completingAppointment->fresh(['patient', 'visit.vital', 'visit.consultationBill.payment']);
        $this->showProfileCompleteModal = false;
        $this->completingAppointment = null;

        session()->flash('success', "Profile updated and patient marked ARRIVED.");
    }

    public function openVitalsModal(int $appointmentId, VisitService $visitService): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // Ensure visit exists
        $visit = $appointment->visit ?? $visitService->markArrival($appointment);

        $this->selectedAppointment = $appointment;
        $this->selectedVisit = $visit;

        if ($visit->vital) {
            $this->weight = $visit->vital->weight;
            $this->height = $visit->vital->height;
            $this->bp_systolic = $visit->vital->bp_systolic;
            $this->bp_diastolic = $visit->vital->bp_diastolic;
            $this->pulse = $visit->vital->pulse;
            $this->spo2 = $visit->vital->spo2;
            $this->temperature = $visit->vital->temperature;
            $this->fever = (bool) $visit->vital->fever;
            $this->respiratory_rate = $visit->vital->respiratory_rate;
            $this->rbs = $visit->vital->rbs;
        } else {
            $this->reset(['weight', 'height', 'bp_systolic', 'bp_diastolic', 'pulse', 'spo2', 'respiratory_rate', 'rbs']);
            $this->temperature = 98.4;
            $this->fever = false;
        }

        $this->showVitalsModal = true;
    }

    public function saveVitals(VitalService $vitalService): void
    {
        if (!$this->selectedVisit) return;

        $vitalService->recordVitals($this->selectedVisit, [
            'weight' => $this->weight,
            'height' => $this->height,
            'bp_systolic' => $this->bp_systolic,
            'bp_diastolic' => $this->bp_diastolic,
            'pulse' => $this->pulse,
            'spo2' => $this->spo2,
            'temperature' => $this->temperature,
            'fever' => $this->fever,
            'respiratory_rate' => $this->respiratory_rate,
            'rbs' => $this->rbs,
        ]);

        $this->activeWorkflowAppointment = $this->selectedAppointment->fresh(['patient', 'visit.vital', 'visit.consultationBill.payment']);
        if ($this->searchedAppointment && $this->searchedAppointment->id === $this->selectedAppointment->id) {
            $this->searchedAppointment = $this->activeWorkflowAppointment;
        }

        $this->showVitalsModal = false;
        session()->flash('success', "Vitals recorded for Token {$this->selectedAppointment->formatted_token}.");
    }

    public function createWalkinOrTelephone(PatientIdentityService $patientService, AppointmentService $appointmentService): void
    {
        $this->validate([
            'newPatientName' => 'required|string|min:3|max:100',
            'newPatientAge' => 'required|integer|min:1|max:120',
            'newPatientSex' => 'required|in:MALE,FEMALE,OTHER',
            'newPatientMobile' => 'required|string|min:10|max:15',
            'newPatientAddress' => 'required|string|min:2',
            'newPatientCity' => 'required|string|min:2',
            'bookingSource' => 'required|in:CLINIC,TELEPHONIC',
        ]);

        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        // Register patient
        $patient = $patientService->registerPatient([
            'clinic_id' => $clinic->id,
            'name' => $this->newPatientName,
            'age' => (int) $this->newPatientAge,
            'sex' => $this->newPatientSex,
            'mobile' => $this->newPatientMobile,
            'address' => $this->newPatientAddress,
            'city' => $this->newPatientCity,
        ]);

        // Check if eligible for telephone grace rebooking (Section 86)
        $graceParent = null;
        if ($this->bookingSource === 'TELEPHONIC') {
            $graceParent = $appointmentService->getEligibleTelephoneGraceAppointment($patient, $this->filterDate);
        }

        // Allocate common date-scoped token (Section 50)
        try {
            $appointment = $appointmentService->bookAppointment([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $this->newBookingDoctorId,
                'appointment_date' => $this->filterDate,
                'session_type' => $this->newBookingSession,
                'booking_source' => $this->bookingSource,
                'grace_parent_appointment_id' => $graceParent?->id,
            ]);

            $this->showNewBookingModal = false;
            $this->reset(['newPatientName', 'newPatientAge', 'newPatientMobile', 'newPatientAddress']);

            session()->flash('success', "New {$this->bookingSource} Token #{$appointment->formatted_token} allocated successfully!");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markSeen(int $appointmentId, VisitService $visitService): void
    {
        $user = auth()->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $appointment = Appointment::where('clinic_id', $clinic->id)->findOrFail($appointmentId);

        // Doctor role isolation: Doctor can only mark their own appointments as seen
        if ($user->role === 'DOCTOR' && (int) $appointment->doctor_id !== (int) $user->doctor_id) {
            abort(403, 'Unauthorized: Doctors may only mark their own assigned appointments as seen.');
        }

        // Authoritative consultation fee gate
        $feeStatus = app(\App\Services\FeeStatusResolver::class)->resolve($appointment);
        if ($feeStatus !== 'PAID') {
            abort(422, "Cannot mark patient as seen. Consultation fee status is {$feeStatus}. Consultation fee must be PAID first via Fee & Pad.");
        }

        $visitService->markSeen($appointment, $user);
        session()->flash('success', "Patient {$appointment->patient?->name} marked SEEN (Token: {$appointment->formatted_token})");
    }

    public function sendTokenSlipViaWhatsApp(int $appointmentId, ?TokenSlipService $tokenSlipService = null): void
    {
        $tokenSlipService = $tokenSlipService ?? app(TokenSlipService::class);
        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $user = auth()->user();
        $query = Appointment::where('clinic_id', $clinic->id)
            ->with(['patient', 'doctor', 'clinic']);

        if ($user && ($user->role === 'DOCTOR' || $user->doctor_id)) {
            $doctorId = $user->doctor_id;
            if ($doctorId) {
                $query->where('doctor_id', $doctorId);
            }
        }

        $appointment = $query->findOrFail($appointmentId);

        $result = $tokenSlipService->sendTokenSlipViaWhatsApp(
            $appointment,
            null,
            false,
            $user?->role ?? 'OPERATOR',
            $user?->id
        );

        if ($result['success'] ?? false) {
            session()->flash('success', "Token slip sent via WhatsApp to {$appointment->patient->name} (#{$appointment->formatted_token}).");
        } else {
            session()->flash('error', "Failed to send token slip: " . ($result['error'] ?? 'WhatsApp gateway unavailable.'));
        }
    }

    public function render()
    {
        $clinic = \App\Services\TenantContext::clinic();

        $query = Appointment::with([
            'patient',
            'doctor',
            'visit.vital',
            'visit.consultationBill.payments',
            'visit.consultationBill.refunds',
            'consultationBill.payments',
            'consultationBill.refunds',
        ])
            ->where('clinic_id', $clinic->id)
            ->whereDate('appointment_date', $this->filterDate)
            ->orderBy('token_number');

        if (auth()->check() && (auth()->user()->doctor_id || auth()->user()->role === 'DOCTOR')) {
            $doctorId = auth()->user()->doctor_id;
            if ($doctorId) {
                $query->where('doctor_id', $doctorId);
            }
        }

        if ($this->filterSession !== 'ALL') {
            $query->where('session_type', $this->filterSession);
        }

        if (!empty($this->searchQuery)) {
            $s = trim($this->searchQuery);
            $query->where(function ($q) use ($s) {
                $q->where('formatted_token', 'LIKE', "%{$s}%")
                    ->orWhereHas('patient', function ($pq) use ($s) {
                        $pq->where('name', 'LIKE', "%{$s}%")
                            ->orWhere('mobile', 'LIKE', "%{$s}%")
                            ->orWhere('patient_id', 'LIKE', "%{$s}%")
                            ->orWhere('legacy_alias', 'LIKE', "%{$s}%")
                            ->orWhere('city', 'LIKE', "%{$s}%");
                    });
            });
        }

        $appointments = $query->get();
        $activeDoctors = $clinic
            ? \App\Models\Doctor::where('clinic_id', $clinic->id)->where('is_active', true)->orderBy('name')->get()
            : collect();

        $scheduleService = app(\App\Services\ClinicScheduleService::class);
        $sessions = $clinic ? $scheduleService->getSessionsForDate($clinic, $this->filterDate) : [];

        return view('livewire.operator.arrival-queue', [
            'clinic' => $clinic,
            'appointments' => $appointments,
            'activeDoctors' => $activeDoctors,
            'sessions' => $sessions,
        ])->layout('components.layouts.app', ['title' => 'Patient Arrival & Queue']);
    }
}
