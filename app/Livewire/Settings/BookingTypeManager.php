<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\BookingType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Services\BookingTypeService;
use App\Services\TenantContext;
use Livewire\Component;

class BookingTypeManager extends Component
{
    public ?Clinic $clinic = null;

    // Create modal
    public bool $showCreateModal = false;
    public string $newCode = '';
    public string $newName = '';
    public string $newConsultationMode = 'CLINIC_PHYSICAL';
    public bool $newIsPublicBookable = true;
    public int $newDisplayOrder = 100;

    // Edit modal
    public bool $showEditModal = false;
    public ?int $editingId = null;
    public string $editName = '';
    public string $editConsultationMode = 'CLINIC_PHYSICAL';
    public bool $editIsPublicBookable = true;
    public bool $editIsActive = true;
    public int $editDisplayOrder = 100;

    // Doctor availability modal
    public bool $showDoctorAvailabilityModal = false;
    public ?int $selectedDoctorId = null;
    public array $doctorAvailabilityMap = []; // [booking_type_id => bool]

    public function mount(): void
    {
        $this->clinic = TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
    }

    // -----------------------------------------------------------------------
    // Create
    // -----------------------------------------------------------------------

    public function openCreateModal(): void
    {
        $this->reset(['newCode', 'newName', 'newConsultationMode', 'newIsPublicBookable', 'newDisplayOrder']);
        $this->newConsultationMode = 'CLINIC_PHYSICAL';
        $this->newIsPublicBookable = true;
        $this->newDisplayOrder = 100;
        $this->showCreateModal = true;
    }

    public function createBookingType(BookingTypeService $service): void
    {
        $this->validate([
            'newCode'              => 'required|string|max:60|regex:/^[A-Z0-9_]+$/i',
            'newName'              => 'required|string|min:2|max:100',
            'newConsultationMode'  => 'required|in:CLINIC_PHYSICAL,ONLINE,VISITING_CONSULTANCY',
            'newDisplayOrder'      => 'required|integer|min:1|max:999',
        ]);

        $clinicId = $this->clinic->id;

        $bt = $service->createCustomType([
            'code'               => strtoupper(trim($this->newCode)),
            'name'               => trim($this->newName),
            'consultation_mode'  => $this->newConsultationMode,
            'is_public_bookable' => $this->newIsPublicBookable,
            'display_order'      => $this->newDisplayOrder,
        ], $clinicId);

        AuditLog::log('BOOKING_TYPE_CREATED', 'BOOKING_TYPE', $bt->id, [
            'code' => $bt->code,
            'name' => $bt->name,
        ]);

        $this->showCreateModal = false;
        session()->flash('success', "Booking type '{$bt->name}' ({$bt->code}) created successfully.");
    }

    // -----------------------------------------------------------------------
    // Edit
    // -----------------------------------------------------------------------

    public function openEditModal(int $bookingTypeId): void
    {
        $bt = BookingType::where('clinic_id', $this->clinic->id)->findOrFail($bookingTypeId);

        $this->editingId           = $bt->id;
        $this->editName            = $bt->name;
        $this->editConsultationMode = $bt->consultation_mode;
        $this->editIsPublicBookable = $bt->is_public_bookable;
        $this->editIsActive        = $bt->is_active;
        $this->editDisplayOrder    = $bt->display_order;
        $this->showEditModal       = true;
    }

    public function saveEdit(BookingTypeService $service): void
    {
        $this->validate([
            'editName'            => 'required|string|min:2|max:100',
            'editConsultationMode'=> 'required|in:CLINIC_PHYSICAL,ONLINE,VISITING_CONSULTANCY',
            'editDisplayOrder'    => 'required|integer|min:1|max:999',
        ]);

        $bt = BookingType::where('clinic_id', $this->clinic->id)->findOrFail($this->editingId);

        $service->updateType($bt, [
            'name'               => $this->editName,
            'consultation_mode'  => $this->editConsultationMode,
            'is_public_bookable' => $this->editIsPublicBookable,
            'is_active'          => $this->editIsActive,
            'display_order'      => $this->editDisplayOrder,
        ]);

        AuditLog::log('BOOKING_TYPE_UPDATED', 'BOOKING_TYPE', $bt->id, [
            'name'      => $this->editName,
            'is_active' => $this->editIsActive,
        ]);

        $this->showEditModal = false;
        session()->flash('success', "Booking type '{$bt->name}' updated.");
    }

    // -----------------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------------

    public function deleteBookingType(int $bookingTypeId, BookingTypeService $service): void
    {
        $bt = BookingType::where('clinic_id', $this->clinic->id)->findOrFail($bookingTypeId);

        $service->deleteType($bt);

        AuditLog::log('BOOKING_TYPE_DELETED', 'BOOKING_TYPE', $bookingTypeId, ['code' => $bt->code]);
        session()->flash('success', "Booking type '{$bt->name}' deleted.");
    }

    // -----------------------------------------------------------------------
    // Doctor Availability Modal
    // -----------------------------------------------------------------------

    public function openDoctorAvailabilityModal(int $doctorId, BookingTypeService $service): void
    {
        $this->selectedDoctorId = $doctorId;

        $bookingTypes = BookingType::forClinic($this->clinic->id)->active()->ordered()->get();

        $this->doctorAvailabilityMap = [];
        foreach ($bookingTypes as $bt) {
            $avail = \App\Models\DoctorBookingTypeAvailability::where('clinic_id', $this->clinic->id)
                ->where('doctor_id', $doctorId)
                ->where('booking_type_id', $bt->id)
                ->first();

            $this->doctorAvailabilityMap[$bt->id] = $avail ? $avail->is_enabled : $bt->is_system;
        }

        $this->showDoctorAvailabilityModal = true;
    }

    public function saveDoctorAvailability(BookingTypeService $service): void
    {
        abort_unless($this->selectedDoctorId, 422);

        // Convert string keys (Livewire JS bridge) to int
        $map = [];
        foreach ($this->doctorAvailabilityMap as $btId => $enabled) {
            $map[(int) $btId] = (bool) $enabled;
        }

        $service->syncDoctorAvailabilities($this->clinic->id, $this->selectedDoctorId, $map);

        AuditLog::log('DOCTOR_BOOKING_AVAILABILITY_UPDATED', 'DOCTOR', $this->selectedDoctorId, [
            'availability_map' => $map,
        ]);

        $this->showDoctorAvailabilityModal = false;
        session()->flash('success', 'Doctor booking availability updated.');
    }

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------

    public function render()
    {
        $clinicId = $this->clinic->id;

        $bookingTypes = BookingType::forClinic($clinicId)
            ->with(['doctorAvailabilities.doctor'])
            ->ordered()
            ->get();

        $doctors = Doctor::where('clinic_id', $clinicId)->where('is_active', true)->get();

        return view('livewire.settings.booking-type-manager', [
            'bookingTypes' => $bookingTypes,
            'doctors'      => $doctors,
        ])->layout('components.layouts.app');
    }
}
