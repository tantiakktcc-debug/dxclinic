<?php

namespace App\Livewire\Reports;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Services\ReportService;
use Carbon\Carbon;
use Livewire\Component;

class DiagnosticReportView extends Component
{
    public string $activeTab = 'session'; // 'session', 'day'
    public string $period = 'daily';
    public string $startDate = '';
    public string $endDate = '';
    public string $doctorId = 'ALL';
    public string $sessionType = 'ALL';
    public string $paymentStatus = 'ALL';

    public array $doctors = [];

    protected $queryString = [
        'activeTab' => ['except' => 'session'],
        'period' => ['except' => 'daily'],
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'doctorId' => ['except' => 'ALL'],
        'sessionType' => ['except' => 'ALL'],
        'paymentStatus' => ['except' => 'ALL'],
    ];

    public function mount(?string $tab = 'session'): void
    {
        $this->activeTab = $tab ?? 'session';
        $today = Carbon::today()->toDateString();
        $this->startDate = $today;
        $this->endDate = $today;

        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');
        $this->doctors = Doctor::where('clinic_id', $clinic->id)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        if ($tab === 'session') {
            $this->period = 'daily';
        }
    }

    public function exportCsv(ReportService $reportService)
    {
        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $report = $reportService->getDiagnosticReport($clinic, [
            'period' => $this->period,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'doctor_id' => $this->doctorId,
            'session_type' => $this->sessionType,
            'payment_status' => $this->paymentStatus,
        ]);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Diagnostic_Report_' . date('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Date',
                'Patient ID',
                'Patient Name',
                'Diagnostic Test/Package',
                'Amount',
                'Doctor',
                'Session',
                'Payment Status',
                'Payment Mode',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['patient_id'],
                    $row['patient_name'],
                    $row['diagnostic_item'],
                    $row['amount'],
                    $row['doctor'],
                    $row['session'],
                    $row['payment_status'],
                    $row['payment_mode'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['', '', '', 'TOTAL COLLECTION', $report['total_collection']]);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function render(ReportService $reportService)
    {
        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');
        $reportData = $reportService->getDiagnosticReport($clinic, [
            'period' => $this->period,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'doctor_id' => $this->doctorId,
            'session_type' => $this->sessionType,
            'payment_status' => $this->paymentStatus,
        ]);

        return view('livewire.reports.diagnostic-report-view', [
            'clinic' => $clinic,
            'report' => $reportData,
        ])->layout('components.layouts.app', ['title' => 'Diagnostic Laboratory Reports']);
    }
}
