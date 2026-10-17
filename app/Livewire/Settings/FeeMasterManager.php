<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Services\BookingTypeService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class FeeMasterManager extends Component
{
    public ?Clinic $clinic = null;
    public string $search = '';
    public string $selectedDoctorFilter = 'ALL';

    // Doctor Fee Configuration Matrix (Revision 45)
    public array $doctorFeeInputs = [];

    // Create Modal
    public bool $showCreateModal = false;
    public ?int $newDoctorId = null;
    public string $newCode = '';
    public string $newName = '';
    public float $newAmount = 0.00;
    public bool $newIsActive = true;

    // Edit Modal
    public bool $showEditModal = false;
    public ?int $editFeeId = null;
    public ?int $editDoctorId = null;
    public string $editCode = '';
    public string $editName = '';
    public float $editAmount = 0.00;
    public bool $editIsActive = true;

    public function mount(): void
    {
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
        $this->loadDoctorFeeInputs();
    }

    public function updatedSelectedDoctorFilter(): void
    {
        $this->loadDoctorFeeInputs();
    }

    public function loadDoctorFeeInputs(): void
    {
        $this->doctorFeeInputs = [];
        if (is_numeric($this->selectedDoctorFilter) && $this->clinic) {
            $doctorId = (int) $this->selectedDoctorFilter;
            $matrix = app(BookingTypeService::class)->getDoctorBookingTypeMatrix($this->clinic->id, $doctorId);
            foreach ($matrix as $item) {
                $this->doctorFeeInputs[$item['booking_type_id']] = $item['fee_amount'] !== null
                    ? number_format($item['fee_amount'], 2, '.', '')
                    : '';
            }
        }
    }

    public function saveDoctorFee(int $bookingTypeId, BookingTypeService $service): void
    {
        if (!is_numeric($this->selectedDoctorFilter) || !$this->clinic) {
            return;
        }

        $doctorId = (int) $this->selectedDoctorFilter;
        $rawAmount = $this->doctorFeeInputs[$bookingTypeId] ?? null;

        if ($rawAmount === null || $rawAmount === '' || !is_numeric($rawAmount) || (float) $rawAmount < 0) {
            $this->addError("doctorFeeInputs.{$bookingTypeId}", "Please enter a valid non-negative fee amount.");
            return;
        }

        $amount = (float) $rawAmount;
        $fee = $service->setDoctorFee($this->clinic->id, $doctorId, $bookingTypeId, $amount, true);

        $this->loadDoctorFeeInputs();
        session()->flash('success', "Fee for '{$fee->name}' updated to ₹" . number_format($amount, 2));
    }

    public function openCreateModal(): void
    {
        $this->reset(['newDoctorId', 'newCode', 'newName', 'newAmount', 'newIsActive']);
        $this->newIsActive = true;
        if (is_numeric($this->selectedDoctorFilter)) {
            $this->newDoctorId = (int) $this->selectedDoctorFilter;
        }
        $this->showCreateModal = true;
    }

    public function createFee(): void
    {
        $clinicId = $this->clinic?->id ?? 1;

        $this->validate([
            'newDoctorId' => 'nullable|exists:doctors,id',
            'newCode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('fee_masters', 'code')->where(function ($query) use ($clinicId) {
                    return $query->where('clinic_id', $clinicId)
                                 ->where('doctor_id', $this->newDoctorId ?: null);
                }),
            ],
            'newName' => 'required|string|min:2|max:100',
            'newAmount' => 'required|numeric|min:0',
        ]);

        $fee = FeeMaster::create([
            'clinic_id' => $clinicId,
            'doctor_id' => $this->newDoctorId ?: null,
            'code' => strtoupper(trim($this->newCode)),
            'name' => trim($this->newName),
            'amount' => $this->newAmount,
            'is_active' => $this->newIsActive,
        ]);

        AuditLog::log('FEE_MASTER_CREATED', 'FEE_MASTER', $fee->id, [
            'name' => $fee->name,
            'code' => $fee->code,
            'amount' => $fee->amount,
            'doctor_id' => $fee->doctor_id,
        ]);

        $this->showCreateModal = false;
        $this->loadDoctorFeeInputs();
        session()->flash('success', "Fee '{$fee->name}' created successfully.");
    }

    public function openEditModal(int $id): void
    {
        $clinicId = $this->clinic?->id ?? 1;
        $fee = FeeMaster::where('clinic_id', $clinicId)->findOrFail($id);
        $this->editFeeId = $fee->id;
        $this->editDoctorId = $fee->doctor_id;
        $this->editCode = $fee->code;
        $this->editName = $fee->name;
        $this->editAmount = (float) $fee->amount;
        $this->editIsActive = (bool) $fee->is_active;
        $this->showEditModal = true;
    }

    public function updateFee(): void
    {
        $clinicId = $this->clinic?->id ?? 1;

        $this->validate([
            'editDoctorId' => 'nullable|exists:doctors,id',
            'editCode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('fee_masters', 'code')->ignore($this->editFeeId)->where(function ($query) use ($clinicId) {
                    return $query->where('clinic_id', $clinicId)
                                 ->where('doctor_id', $this->editDoctorId ?: null);
                }),
            ],
            'editName' => 'required|string|min:2|max:100',
            'editAmount' => 'required|numeric|min:0',
        ]);

        $fee = FeeMaster::where('clinic_id', $clinicId)->findOrFail($this->editFeeId);
        $fee->update([
            'doctor_id' => $this->editDoctorId ?: null,
            'code' => strtoupper(trim($this->editCode)),
            'name' => trim($this->editName),
            'amount' => $this->editAmount,
            'is_active' => $this->editIsActive,
        ]);

        AuditLog::log('FEE_MASTER_UPDATED', 'FEE_MASTER', $fee->id, [
            'name' => $fee->name,
            'amount' => $fee->amount,
            'doctor_id' => $fee->doctor_id,
        ]);

        $this->showEditModal = false;
        $this->loadDoctorFeeInputs();
        session()->flash('success', "Fee '{$fee->name}' updated successfully.");
    }

    public function toggleStatus(int $id): void
    {
        $clinicId = $this->clinic?->id ?? 1;
        $fee = FeeMaster::where('clinic_id', $clinicId)->findOrFail($id);
        $fee->update(['is_active' => !$fee->is_active]);

        AuditLog::log('FEE_MASTER_STATUS_TOGGLED', 'FEE_MASTER', $fee->id, ['is_active' => $fee->is_active]);
        $this->loadDoctorFeeInputs();
        session()->flash('success', "Status for '{$fee->name}' updated.");
    }

    public function render()
    {
        $query = FeeMaster::with('doctor')->orderBy('name');

        if ($this->clinic) {
            $query->where('clinic_id', $this->clinic->id);
        }

        if ($this->selectedDoctorFilter === 'GLOBAL') {
            $query->whereNull('doctor_id');
        } elseif (is_numeric($this->selectedDoctorFilter)) {
            $query->where('doctor_id', (int) $this->selectedDoctorFilter);
        }

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        $allDoctors = Doctor::where('clinic_id', $this->clinic?->id)->orderBy('name')->get();

        $selectedDoctor = null;
        $doctorMatrix = [];
        if (is_numeric($this->selectedDoctorFilter) && $this->clinic) {
            $selectedDoctor = Doctor::where('clinic_id', $this->clinic->id)->find((int) $this->selectedDoctorFilter);
            if ($selectedDoctor) {
                $fullMatrix = app(BookingTypeService::class)->getDoctorBookingTypeMatrix($this->clinic->id, $selectedDoctor->id);
                // Section 18: Show ONLY booking types mapped to that doctor
                $doctorMatrix = array_values(array_filter($fullMatrix, fn($m) => $m['is_enabled']));
            }
        }

        return view('livewire.settings.fee-master-manager', [
            'fees'           => $query->get(),
            'doctors'        => $allDoctors,
            'selectedDoctor' => $selectedDoctor,
            'doctorMatrix'   => $doctorMatrix,
        ])->layout('components.layouts.app', ['title' => 'Fee Master']);
    }
}
