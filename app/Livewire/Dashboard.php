<?php

namespace App\Livewire;

use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\Visit;
use App\Services\ClinicScheduleService;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public string $currentDate;
    public string $formattedDate;
    public string $activeSessionType = 'MORNING';
    public ?Clinic $clinic = null;
    public ?Doctor $doctor = null;

    public int $todayPatientsCount = 0;
    public float $consultationRevenue = 0.00;
    public float $diagnosticRevenue = 0.00;
    public float $totalCollections = 0.00;

    public ?CashSession $activeCashSession = null;

    public function mount(ClinicScheduleService $scheduleService): void
    {
        $today = Carbon::today();
        $this->currentDate = $today->toDateString();
        $this->formattedDate = $today->format('l, d M Y');

        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
        if ($this->clinic) {
            $this->doctor = Doctor::where('clinic_id', $this->clinic->id)->first();
            $this->activeSessionType = $scheduleService->getCurrentOrNextSessionType($this->clinic, $this->currentDate);
        }

        $this->loadMetrics();
    }

    public function loadMetrics(): void
    {
        if (!$this->clinic) {
            return;
        }

        // 1. Today's patients
        $this->todayPatientsCount = Appointment::where('clinic_id', $this->clinic->id)
            ->whereDate('appointment_date', $this->currentDate)
            ->count();

        // 2. Consultation Revenue today
        $this->consultationRevenue = (float) Payment::where('clinic_id', $this->clinic->id)
            ->whereNotNull('consultation_bill_id')
            ->where('status', 'SETTLED')
            ->whereDate('settled_at', $this->currentDate)
            ->sum('amount');

        // 3. Diagnostic Revenue today
        $this->diagnosticRevenue = (float) Payment::where('clinic_id', $this->clinic->id)
            ->whereNotNull('diagnostic_bill_id')
            ->where('status', 'SETTLED')
            ->whereDate('settled_at', $this->currentDate)
            ->sum('amount');

        // 4. Total Collections today
        $this->totalCollections = round($this->consultationRevenue + $this->diagnosticRevenue, 2);

        // Active Cash Session
        $this->activeCashSession = CashSession::where('clinic_id', $this->clinic->id)
            ->whereDate('session_date', $this->currentDate)
            ->where('status', 'OPEN')
            ->latest('id')
            ->first();
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'doctor' => $this->doctor,
            'clinic' => $this->clinic,
            'currentDate' => $this->currentDate,
            'formattedDate' => $this->formattedDate,
            'activeSessionType' => $this->activeSessionType,
            'todayPatientsCount' => $this->todayPatientsCount,
            'consultationRevenue' => $this->consultationRevenue,
            'diagnosticRevenue' => $this->diagnosticRevenue,
            'totalCollections' => $this->totalCollections,
            'activeCashSession' => $this->activeCashSession,
        ])->layout('components.layouts.app', ['title' => 'Operational Dashboard']);
    }
}
