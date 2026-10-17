<?php

namespace App\Livewire;

use App\Models\Clinic;
use App\Models\Doctor;
use Livewire\Component;

class ClinicDoctorManager extends Component
{
    // Clinic fields
    public ?int $clinicId = null;
    public string $clinicName = '';
    public string $clinicAddress = '';
    public string $clinicCity = '';
    public string $clinicPhone = '';
    public string $clinicEmail = '';
    public ?int $defaultDoctorId = null;
    public int $maxAdvanceBookingDays = 1;

    // Doctor fields
    public ?int $doctorId = null;
    public string $doctorName = '';
    public string $doctorQualification = '';
    public string $doctorRegistration = '';
    public string $doctorSpecialization = '';
    public string $doctorContact = '';
    public bool $doctorIsActive = true;
    public bool $customSchedule = false;
    public string $morningStart = '09:00';
    public string $morningEnd = '11:00';
    public string $eveningStart = '17:00';
    public string $eveningEnd = '19:00';
    public string $sundayStart = '09:00';
    public string $sundayEnd = '11:00';

    public bool $consultationPhysical = true;
    public bool $consultationOnline = true;
    public bool $consultationVisiting = false;

    // Doctor Booking Policy form state
    public ?int $policyDoctorId = null;
    public string $policyEffectiveFrom = '';
    public string $policyEffectiveTo = '';
    public int $policyMaxAdvanceDays = 6;
    public bool $showPolicyModal = false;

    // Doctor Leave form state
    public ?int $leaveDoctorId = null;
    public string $leaveStartDate = '';
    public string $leaveEndDate = '';
    public string $leaveSessionType = '';
    public string $leaveReason = '';
    public bool $showLeaveModal = false;

    // UI state
    public bool $clinicEditMode = false;
    public bool $doctorEditMode = false;
    public bool $showDoctorAddForm = false;

    // All doctors
    public $doctors = [];

    public function mount(): void
    {
        $this->loadClinic();
        $this->loadDoctors();
    }

    protected function loadClinic(): void
    {
        $clinic = \App\Services\TenantContext::clinic();
        if ($clinic) {
            $this->clinicId = $clinic->id;
            $this->clinicName = $clinic->name;
            $this->clinicAddress = $clinic->address ?? '';
            $this->clinicCity = $clinic->city ?? '';
            $this->clinicPhone = $clinic->phone ?? '';
            $this->clinicEmail = $clinic->email ?? '';
            $this->defaultDoctorId = $clinic->default_doctor_id;
            $this->maxAdvanceBookingDays = (int) ($clinic->max_advance_booking_days ?? 1);
        }
    }

    protected function loadDoctors(): void
    {
        if ($this->clinicId) {
            $this->doctors = Doctor::with(['consultationTypes', 'bookingPolicies', 'leaves'])
                ->where('clinic_id', $this->clinicId)
                ->orderBy('name')
                ->get()
                ->toArray();
        }
    }

    public function enableClinicEdit(): void
    {
        $this->clinicEditMode = true;
    }

    public function cancelClinicEdit(): void
    {
        $this->clinicEditMode = false;
        $this->loadClinic();
    }

    public function saveClinic(): void
    {
        $this->validate([
            'clinicName' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}\s&.,\'-]+$/u'],
            'clinicAddress' => ['nullable', 'string', 'max:500', 'regex:/^[\p{L}\p{N}\s,.\/#\(\)\'-]+$/u'],
            'clinicCity' => ['nullable', 'string', 'max:100', 'regex:/^[\p{L}\s,.\'-]+$/u'],
            'clinicPhone' => ['nullable', 'string', 'max:30'],
            'clinicEmail' => ['nullable', 'email', 'max:255'],
            'defaultDoctorId' => ['nullable', 'exists:doctors,id'],
            'maxAdvanceBookingDays' => ['required', 'integer', 'min:0', 'max:90'],
        ]);

        $phone = null;
        if (!empty(trim($this->clinicPhone))) {
            if (!\App\Services\PhoneNumberNormalizer::isValid($this->clinicPhone)) {
                $this->addError('clinicPhone', \App\Services\PhoneNumberNormalizer::ERROR_MESSAGE);
                return;
            }
            $phone = \App\Services\PhoneNumberNormalizer::normalize($this->clinicPhone);
        }

        $clinic = Clinic::find($this->clinicId);
        if ($clinic) {
            $clinic->update([
                'name' => trim(preg_replace('/\s+/u', ' ', $this->clinicName)),
                'address' => trim($this->clinicAddress),
                'city' => trim($this->clinicCity),
                'phone' => $phone ?? trim($this->clinicPhone),
                'email' => !empty($this->clinicEmail) ? strtolower(trim($this->clinicEmail)) : null,
                'default_doctor_id' => $this->defaultDoctorId,
                'max_advance_booking_days' => $this->maxAdvanceBookingDays,
            ]);
            session()->flash('success', 'Clinic information, default doctor, and advance booking window updated successfully.');
        }

        $this->clinicEditMode = false;
    }

    public function openPolicyModal(int $doctorId): void
    {
        $this->policyDoctorId = $doctorId;
        $this->policyEffectiveFrom = \App\Services\ClinicClock::today()->toDateString();
        $this->policyEffectiveTo = \App\Services\ClinicClock::today()->addDays(30)->toDateString();
        $this->policyMaxAdvanceDays = 6;
        $this->showPolicyModal = true;
    }

    public function closePolicyModal(): void
    {
        $this->showPolicyModal = false;
        $this->policyDoctorId = null;
    }

    public function saveDoctorPolicy(): void
    {
        $this->validate([
            'policyDoctorId' => ['required', 'exists:doctors,id'],
            'policyEffectiveFrom' => ['required', 'date'],
            'policyEffectiveTo' => ['required', 'date', 'after_or_equal:policyEffectiveFrom'],
            'policyMaxAdvanceDays' => ['required', 'integer', 'min:0', 'max:90'],
        ]);

        \App\Models\DoctorBookingPolicy::create([
            'clinic_id' => $this->clinicId,
            'doctor_id' => $this->policyDoctorId,
            'effective_from' => $this->policyEffectiveFrom,
            'effective_to' => $this->policyEffectiveTo,
            'max_advance_booking_days' => $this->policyMaxAdvanceDays,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Doctor advance booking policy created successfully.');
        $this->closePolicyModal();
        $this->loadDoctors();
    }

    public function deleteDoctorPolicy(int $policyId): void
    {
        $policy = \App\Models\DoctorBookingPolicy::where('clinic_id', $this->clinicId)->findOrFail($policyId);
        $policy->delete();
        session()->flash('success', 'Doctor advance booking policy deleted.');
        $this->loadDoctors();
    }

    public function openLeaveModal(int $doctorId): void
    {
        $this->leaveDoctorId = $doctorId;
        $this->leaveStartDate = \App\Services\ClinicClock::today()->toDateString();
        $this->leaveEndDate = \App\Services\ClinicClock::today()->toDateString();
        $this->leaveSessionType = '';
        $this->leaveReason = '';
        $this->showLeaveModal = true;
    }

    public function closeLeaveModal(): void
    {
        $this->showLeaveModal = false;
        $this->leaveDoctorId = null;
    }

    public function saveDoctorLeave(): void
    {
        $this->validate([
            'leaveDoctorId' => ['required', 'exists:doctors,id'],
            'leaveStartDate' => ['required', 'date'],
            'leaveEndDate' => ['required', 'date', 'after_or_equal:leaveStartDate'],
            'leaveSessionType' => ['nullable', 'string', 'in:MORNING,EVENING,SUNDAY_SINGLE,'],
            'leaveReason' => ['nullable', 'string', 'max:255'],
        ]);

        \App\Models\DoctorLeave::create([
            'clinic_id' => $this->clinicId,
            'doctor_id' => $this->leaveDoctorId,
            'start_date' => $this->leaveStartDate,
            'end_date' => $this->leaveEndDate,
            'session_type' => !empty($this->leaveSessionType) ? strtoupper($this->leaveSessionType) : null,
            'reason' => !empty($this->leaveReason) ? trim($this->leaveReason) : null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Doctor leave registered successfully.');
        $this->closeLeaveModal();
        $this->loadDoctors();
    }

    public function deleteDoctorLeave(int $leaveId): void
    {
        $leave = \App\Models\DoctorLeave::where('clinic_id', $this->clinicId)->findOrFail($leaveId);
        $leave->delete();
        session()->flash('success', 'Doctor leave removed.');
        $this->loadDoctors();
    }

    public function setDefaultDoctor(int $doctorId): void
    {
        $doctor = Doctor::where('clinic_id', $this->clinicId)->findOrFail($doctorId);
        if (!$doctor->is_active) {
            session()->flash('error', 'Cannot set an inactive doctor as default booking doctor.');
            return;
        }

        $clinic = Clinic::find($this->clinicId);
        if ($clinic) {
            $clinic->update(['default_doctor_id' => $doctorId]);
            $this->defaultDoctorId = $doctorId;
            session()->flash('success', "Dr. {$doctor->name} is now the Default Booking Doctor.");
        }
    }

    public function toggleDoctorStatus(int $doctorId): void
    {
        $doctor = Doctor::where('clinic_id', $this->clinicId)->findOrFail($doctorId);
        $newStatus = !$doctor->is_active;

        if ($newStatus) {
            $clinic = Clinic::find($this->clinicId);
            if ($clinic && !$clinic->canAddDoctor()) {
                session()->flash('error', "You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat.");
                return;
            }
        }

        // If deactivating the default doctor, clear default doctor
        if (!$newStatus && $this->defaultDoctorId === $doctorId) {
            $clinic = Clinic::find($this->clinicId);
            $clinic?->update(['default_doctor_id' => null]);
            $this->defaultDoctorId = null;
        }

        $doctor->update(['is_active' => $newStatus]);
        $statusStr = $newStatus ? 'activated' : 'deactivated';
        session()->flash('success', "Dr. {$doctor->name} has been {$statusStr}.");

        $this->loadDoctors();
    }

    public function editDoctor(int $doctorId): void
    {
        $doctor = Doctor::where('clinic_id', $this->clinicId)->find($doctorId);
        if ($doctor) {
            $this->doctorId = $doctor->id;
            $this->doctorName = $doctor->name;
            $this->doctorQualification = $doctor->qualification;
            $this->doctorRegistration = $doctor->registration_number;
            $this->doctorSpecialization = $doctor->specialization ?? '';
            $this->doctorContact = $doctor->contact_number ?? '';
            $this->doctorIsActive = (bool) $doctor->is_active;

            $types = \App\Models\DoctorConsultationType::where('doctor_id', $doctor->id)->pluck('is_enabled', 'consultation_mode')->toArray();
            if (empty($types)) {
                $this->consultationPhysical = true;
                $this->consultationOnline = true;
                $this->consultationVisiting = false;
            } else {
                $this->consultationPhysical = (bool) ($types['CLINIC_PHYSICAL'] ?? false);
                $this->consultationOnline = (bool) ($types['ONLINE'] ?? false);
                $this->consultationVisiting = (bool) ($types['VISITING_CONSULTANCY'] ?? false);
            }

            if (!empty($doctor->schedule_config)) {
                $this->customSchedule = true;
                $conf = $doctor->schedule_config;
                $this->morningStart = $conf['monday']['morning'][0] ?? '08:00';
                $this->morningEnd = $conf['monday']['morning'][1] ?? '09:00';
                $this->eveningStart = $conf['monday']['evening'][0] ?? '17:00';
                $this->eveningEnd = $conf['monday']['evening'][1] ?? '19:00';
                $this->sundayStart = $conf['sunday']['single'][0] ?? '09:00';
                $this->sundayEnd = $conf['sunday']['single'][1] ?? '11:00';
            } else {
                $this->customSchedule = false;
                $this->morningStart = '08:00';
                $this->morningEnd = '09:00';
                $this->eveningStart = '17:00';
                $this->eveningEnd = '19:00';
                $this->sundayStart = '09:00';
                $this->sundayEnd = '11:00';
            }

            $this->doctorEditMode = true;
            $this->showDoctorAddForm = false;
        }
    }

    public function showAddDoctor(): void
    {
        $this->resetDoctorForm();
        $this->showDoctorAddForm = true;
        $this->doctorEditMode = false;
    }

    protected function resetDoctorForm(): void
    {
        $this->doctorId = null;
        $this->doctorName = '';
        $this->doctorQualification = '';
        $this->doctorRegistration = '';
        $this->doctorSpecialization = '';
        $this->doctorContact = '';
        $this->doctorIsActive = true;
        $this->customSchedule = false;
        $this->consultationPhysical = true;
        $this->consultationOnline = true;
        $this->consultationVisiting = false;
        $this->morningStart = '08:00';
        $this->morningEnd = '09:00';
        $this->eveningStart = '17:00';
        $this->eveningEnd = '19:00';
        $this->sundayStart = '09:00';
        $this->sundayEnd = '11:00';
    }

    public function cancelDoctorEdit(): void
    {
        $this->doctorEditMode = false;
        $this->showDoctorAddForm = false;
        $this->resetDoctorForm();
    }

    public function saveDoctor(): void
    {
        $rules = [
            'doctorName' => ['required', 'string', 'max:255'],
            'doctorQualification' => ['required', 'string', 'max:255'],
            'doctorRegistration' => ['required', 'string', 'max:100'],
            'doctorSpecialization' => ['nullable', 'string', 'max:255'],
            'doctorContact' => ['nullable', 'string', 'max:30'],
        ];

        if ($this->customSchedule) {
            $rules['morningStart'] = ['required', 'string'];
            $rules['morningEnd'] = ['required', 'string'];
            $rules['eveningStart'] = ['required', 'string'];
            $rules['eveningEnd'] = ['required', 'string'];
            $rules['sundayStart'] = ['required', 'string'];
            $rules['sundayEnd'] = ['required', 'string'];
        }

        $this->validate($rules);

        if (!\App\Services\PersonNameNormalizer::isValid($this->doctorName)) {
            $this->addError('doctorName', \App\Services\PersonNameNormalizer::ERROR_MESSAGE);
            return;
        }
        $normalizedName = \App\Services\PersonNameNormalizer::normalize($this->doctorName);

        $contact = null;
        if (!empty(trim($this->doctorContact))) {
            if (!\App\Services\PhoneNumberNormalizer::isValid($this->doctorContact)) {
                $this->addError('doctorContact', \App\Services\PhoneNumberNormalizer::ERROR_MESSAGE);
                return;
            }
            $contact = \App\Services\PhoneNumberNormalizer::normalize($this->doctorContact);
        }

        $scheduleConfig = null;
        if ($this->customSchedule) {
            $scheduleConfig = [
                'monday' => ['morning' => [$this->morningStart, $this->morningEnd], 'evening' => [$this->eveningStart, $this->eveningEnd]],
                'tuesday' => ['morning' => [$this->morningStart, $this->morningEnd], 'evening' => [$this->eveningStart, $this->eveningEnd]],
                'wednesday' => ['morning' => [$this->morningStart, $this->morningEnd], 'evening' => [$this->eveningStart, $this->eveningEnd]],
                'thursday' => ['morning' => [$this->morningStart, $this->morningEnd], 'evening' => [$this->eveningStart, $this->eveningEnd]],
                'friday' => ['morning' => [$this->morningStart, $this->morningEnd], 'evening' => [$this->eveningStart, $this->eveningEnd]],
                'saturday' => ['morning' => [$this->morningStart, $this->morningEnd], 'evening' => [$this->eveningStart, $this->eveningEnd]],
                'sunday' => ['single' => [$this->sundayStart, $this->sundayEnd]],
            ];
        }

        $data = [
            'name' => $normalizedName,
            'qualification' => trim($this->doctorQualification),
            'registration_number' => trim($this->doctorRegistration),
            'specialization' => trim($this->doctorSpecialization),
            'contact_number' => $contact,
            'is_active' => $this->doctorIsActive,
            'schedule_config' => $scheduleConfig,
        ];

        if ($this->doctorId) {
            $existing = Doctor::where('clinic_id', $this->clinicId)->find($this->doctorId);
            if ($existing && !$existing->is_active && $this->doctorIsActive) {
                $clinic = Clinic::find($this->clinicId);
                if ($clinic && !$clinic->canAddDoctor()) {
                    $this->addError('doctorCapacity', "You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat.");
                    return;
                }
            }
            $existing?->update($data);
            $targetDocId = $this->doctorId;
            session()->flash('success', "Doctor profile updated: Dr. {$data['name']}");
        } else {
            $clinic = Clinic::find($this->clinicId);
            if ($clinic && !$clinic->canAddDoctor()) {
                $this->addError('doctorCapacity', "You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat.");
                return;
            }

            $data['clinic_id'] = $this->clinicId;
            $newDoc = Doctor::create($data);
            $targetDocId = $newDoc->id;
            
            // If this is the only active doctor, make default automatically
            $activeCount = Doctor::where('clinic_id', $this->clinicId)->where('is_active', true)->count();
            if ($activeCount === 1) {
                Clinic::find($this->clinicId)?->update(['default_doctor_id' => $newDoc->id]);
                $this->defaultDoctorId = $newDoc->id;
            }

            session()->flash('success', "New doctor added: Dr. {$data['name']}");
        }

        // Save consultation types availability
        $modes = [
            'CLINIC_PHYSICAL' => $this->consultationPhysical,
            'ONLINE' => $this->consultationOnline,
            'VISITING_CONSULTANCY' => $this->consultationVisiting,
        ];
        foreach ($modes as $mode => $enabled) {
            \App\Models\DoctorConsultationType::updateOrCreate(
                [
                    'clinic_id' => $this->clinicId,
                    'doctor_id' => $targetDocId,
                    'consultation_mode' => $mode,
                ],
                [
                    'is_enabled' => $enabled,
                ]
            );
        }
        app(\App\Services\BookingTypeService::class)->seedForNewDoctor($targetDocId, $this->clinicId);

        $this->doctorEditMode = false;
        $this->showDoctorAddForm = false;
        $this->resetDoctorForm();
        $this->loadDoctors();
    }

    public function render()
    {
        $clinic = Clinic::find($this->clinicId);
        $doctorCapacity = $clinic ? $clinic->totalDoctorCapacity() : 1;
        $activeDoctorCount = $clinic ? $clinic->activeDoctorCount() : 0;
        $availableDoctorSeats = $clinic ? $clinic->availableDoctorSeats() : 0;
        $canAddDoctor = $clinic ? $clinic->canAddDoctor() : true;

        return view('livewire.clinic-doctor-manager', [
            'clinicEditMode' => $this->clinicEditMode,
            'doctorEditMode' => $this->doctorEditMode,
            'showDoctorAddForm' => $this->showDoctorAddForm,
            'doctors' => $this->doctors,
            'defaultDoctorId' => $this->defaultDoctorId,
            'clinicName' => $this->clinicName,
            'clinicAddress' => $this->clinicAddress,
            'clinicCity' => $this->clinicCity,
            'clinicPhone' => $this->clinicPhone,
            'clinicEmail' => $this->clinicEmail,
            'doctorId' => $this->doctorId,
            'doctorName' => $this->doctorName,
            'doctorQualification' => $this->doctorQualification,
            'doctorRegistration' => $this->doctorRegistration,
            'doctorSpecialization' => $this->doctorSpecialization,
            'doctorContact' => $this->doctorContact,
            'doctorIsActive' => $this->doctorIsActive,
            'consultationPhysical' => $this->consultationPhysical,
            'consultationOnline' => $this->consultationOnline,
            'consultationVisiting' => $this->consultationVisiting,
            'doctorCapacity' => $doctorCapacity,
            'activeDoctorCount' => $activeDoctorCount,
            'availableDoctorSeats' => $availableDoctorSeats,
            'canAddDoctor' => $canAddDoctor,
        ])->layout('components.layouts.app', ['title' => 'Clinic & Doctor Management']);
    }
}
