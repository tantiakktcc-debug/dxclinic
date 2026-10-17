<?php

namespace App\Livewire\Cash;

use App\Models\Clinic;
use App\Services\CashReconciliationService;
use Carbon\Carbon;
use Livewire\Component;

class DailyReportView extends Component
{
    public string $reportDate = '';

    public function mount(?string $date = null): void
    {
        $this->reportDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();
    }

    public function render(CashReconciliationService $reconciliationService)
    {
        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');
        $reportData = $reconciliationService->getDailyReport($clinic, $this->reportDate);

        return view('livewire.cash.daily-report-view', [
            'clinic' => $clinic,
            'report' => $reportData,
        ])->layout('components.layouts.app', ['title' => 'Daily Operational & Financial Report']);
    }
}
