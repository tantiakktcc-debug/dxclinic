<?php

namespace App\Livewire\Lab;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\DiagnosticOrder;
use App\Models\DiagnosticOrderItem;
use App\Models\DiagnosticReport;
use App\Models\DiagnosticResult;
use App\Models\DiagnosticSpecimen;
use App\Services\DiagnosticLabService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class DiagnosticLabWorklist extends Component
{
    use WithPagination;

    // Active Navigation Tab: 'worklist', 'samples', 'reports'
    public string $activeTab = 'worklist';

    // Search and Filters
    public string $search = '';
    public string $filterStatus = 'ALL';
    public string $filterPriority = 'ALL';
    public string $dateFrom = '';
    public string $dateTo = '';

    // Modals visibility
    public bool $showCollectModal = false;
    public bool $showRejectModal = false;
    public bool $showRecollectModal = false;
    public bool $showResultEntryModal = false;
    public bool $showReleaseModal = false;
    public bool $showCorrectionModal = false;
    public bool $showAuditModal = false;
    public bool $showDetailModal = false;

    // Selected Entities
    public ?int $selectedOrderId = null;
    public ?DiagnosticOrder $selectedOrder = null;
    public ?int $selectedSpecimenId = null;
    public ?DiagnosticSpecimen $selectedSpecimen = null;
    public ?int $selectedResultId = null;
    public ?DiagnosticResult $selectedResult = null;

    // Form inputs for Sample Collection
    public string $specimenType = 'Blood';
    public string $collectionNotes = '';

    // Form inputs for Rejection / Recollection
    public string $rejectionReason = '';
    public string $recollectionNotes = '';

    // Form inputs for Result Entry: [order_item_id => ['result_value' => '', 'remarks' => '']]
    public array $resultInputs = [];

    // Form inputs for Release
    public string $reportNotes = '';

    // Form inputs for Correction
    public string $correctionNewValue = '';
    public string $correctionReason = '';

    // Audit entries
    public array $auditEntries = [];

    protected $queryString = [
        'activeTab' => ['except' => 'worklist'],
        'search' => ['except' => ''],
        'filterStatus' => ['except' => 'ALL'],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function mount(): void
    {
        $today = Carbon::today()->toDateString();
        if (empty($this->dateFrom)) {
            $this->dateFrom = $today;
        }
        if (empty($this->dateTo)) {
            $this->dateTo = $today;
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = 'ALL';
        $this->filterPriority = 'ALL';
        $this->dateFrom = Carbon::today()->toDateString();
        $this->dateTo = Carbon::today()->toDateString();
        $this->resetPage();
    }

    // =========================================================================
    // MODAL HANDLERS
    // =========================================================================

    public function openCollectModal(int $orderId): void
    {
        $clinicId = TenantContext::clinicId();
        $this->selectedOrderId = $orderId;
        $this->selectedOrder = DiagnosticOrder::where('clinic_id', $clinicId)
            ->with(['patient', 'items.diagnosticItem', 'specimens'])
            ->findOrFail($orderId);

        $firstItem = $this->selectedOrder->items->first();
        $this->specimenType = $firstItem?->specimen_type ?? ($firstItem?->diagnosticItem?->specimen_type ?? 'Blood');
        $this->collectionNotes = '';
        $this->showCollectModal = true;
    }

    public function closeCollectModal(): void
    {
        $this->showCollectModal = false;
        $this->selectedOrderId = null;
        $this->selectedOrder = null;
    }

    public function collectSample(DiagnosticLabService $labService): void
    {
        if (!$this->selectedOrder) return;

        try {
            $user = auth()->user();
            $specimen = $labService->collectSpecimen(
                $this->selectedOrder,
                [
                    'specimen_type' => $this->specimenType,
                    'notes' => $this->collectionNotes,
                ],
                $user
            );

            session()->flash('success', "Specimen collected successfully: {$specimen->specimen_identifier}");
            $this->closeCollectModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openRejectModal(int $specimenId): void
    {
        $clinicId = TenantContext::clinicId();
        $this->selectedSpecimenId = $specimenId;
        $this->selectedSpecimen = DiagnosticSpecimen::where('clinic_id', $clinicId)
            ->with(['order.patient'])
            ->findOrFail($specimenId);
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal(): void
    {
        $this->showRejectModal = false;
        $this->selectedSpecimenId = null;
        $this->selectedSpecimen = null;
        $this->rejectionReason = '';
    }

    public function rejectSample(DiagnosticLabService $labService): void
    {
        if (!$this->selectedSpecimen) return;

        $this->validate([
            'rejectionReason' => 'required|string|min:5|max:500',
        ]);

        try {
            $labService->rejectSpecimen($this->selectedSpecimen, $this->rejectionReason, auth()->user());
            session()->flash('success', "Specimen #{$this->selectedSpecimen->specimen_identifier} marked as REJECTED.");
            $this->closeRejectModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openRecollectModal(int $specimenId): void
    {
        $clinicId = TenantContext::clinicId();
        $this->selectedSpecimenId = $specimenId;
        $this->selectedSpecimen = DiagnosticSpecimen::where('clinic_id', $clinicId)
            ->with(['order.patient'])
            ->findOrFail($specimenId);
        $this->recollectionNotes = '';
        $this->showRecollectModal = true;
    }

    public function closeRecollectModal(): void
    {
        $this->showRecollectModal = false;
        $this->selectedSpecimenId = null;
        $this->selectedSpecimen = null;
        $this->recollectionNotes = '';
    }

    public function recollectSample(DiagnosticLabService $labService): void
    {
        if (!$this->selectedSpecimen) return;

        try {
            $newSpecimen = $labService->recollectSpecimen(
                $this->selectedSpecimen,
                $this->recollectionNotes,
                auth()->user()
            );

            session()->flash('success', "New specimen collected: {$newSpecimen->specimen_identifier}");
            $this->closeRecollectModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openResultEntry(int $orderId): void
    {
        $clinicId = TenantContext::clinicId();
        $this->selectedOrderId = $orderId;
        $this->selectedOrder = DiagnosticOrder::where('clinic_id', $clinicId)
            ->with(['patient', 'items.diagnosticItem', 'results'])
            ->findOrFail($orderId);

        $this->resultInputs = [];
        foreach ($this->selectedOrder->items as $item) {
            $existingResult = $this->selectedOrder->results
                ->where('diagnostic_order_item_id', $item->id)
                ->where('status', '!=', 'CORRECTED')
                ->first();

            $this->resultInputs[$item->id] = [
                'order_item_id' => $item->id,
                'parameter_name' => $item->test_name,
                'result_type' => $item->diagnosticItem?->result_type ?? 'NUMERIC',
                'result_value' => $existingResult?->result_value ?? '',
                'unit' => $item->diagnosticItem?->unit ?? '',
                'reference_range' => $item->diagnosticItem?->reference_range ?? '',
                'remarks' => $existingResult?->remarks ?? '',
            ];
        }

        $this->showResultEntryModal = true;
    }

    public function closeResultEntryModal(): void
    {
        $this->showResultEntryModal = false;
        $this->selectedOrderId = null;
        $this->selectedOrder = null;
        $this->resultInputs = [];
    }

    public function saveResults(DiagnosticLabService $labService): void
    {
        if (!$this->selectedOrder) return;

        $payload = [];
        foreach ($this->resultInputs as $itemInput) {
            if (trim($itemInput['result_value']) !== '') {
                $payload[] = $itemInput;
            }
        }

        if (empty($payload)) {
            session()->flash('error', "Please enter at least one test result value.");
            return;
        }

        try {
            $labService->enterResults($this->selectedOrder, $payload, auth()->user());
            session()->flash('success', "Laboratory results saved successfully for Order #{$this->selectedOrder->order_number}.");
            $this->closeResultEntryModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function verifyResults(int $orderId, DiagnosticLabService $labService): void
    {
        $clinicId = TenantContext::clinicId();
        $order = DiagnosticOrder::where('clinic_id', $clinicId)->findOrFail($orderId);

        try {
            $labService->verifyResults($order, auth()->user());
            session()->flash('success', "Results verified by " . auth()->user()->name . ".");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openReleaseModal(int $orderId): void
    {
        $clinicId = TenantContext::clinicId();
        $this->selectedOrderId = $orderId;
        $this->selectedOrder = DiagnosticOrder::where('clinic_id', $clinicId)
            ->with(['patient', 'items', 'results' => fn($q) => $q->active()])
            ->findOrFail($orderId);
        $this->reportNotes = '';
        $this->showReleaseModal = true;
    }

    public function closeReleaseModal(): void
    {
        $this->showReleaseModal = false;
        $this->selectedOrderId = null;
        $this->selectedOrder = null;
        $this->reportNotes = '';
    }

    public function releaseReport(DiagnosticLabService $labService): void
    {
        if (!$this->selectedOrder) return;

        try {
            $report = $labService->releaseReport(
                $this->selectedOrder,
                auth()->user(),
                $this->reportNotes
            );

            session()->flash('success', "Diagnostic Report #{$report->report_number} released successfully!");
            $this->closeReleaseModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openCorrectionModal(int $resultId): void
    {
        $clinicId = TenantContext::clinicId();
        $this->selectedResultId = $resultId;
        $this->selectedResult = DiagnosticResult::where('clinic_id', $clinicId)
            ->with(['order.patient', 'diagnosticItem'])
            ->findOrFail($resultId);
        $this->correctionNewValue = $this->selectedResult->result_value;
        $this->correctionReason = '';
        $this->showCorrectionModal = true;
    }

    public function closeCorrectionModal(): void
    {
        $this->showCorrectionModal = false;
        $this->selectedResultId = null;
        $this->selectedResult = null;
        $this->correctionNewValue = '';
        $this->correctionReason = '';
    }

    public function submitCorrection(DiagnosticLabService $labService): void
    {
        if (!$this->selectedResult) return;

        $this->validate([
            'correctionNewValue' => 'required|string|max:255',
            'correctionReason' => 'required|string|min:10|max:1000',
        ]);

        try {
            $newResult = $labService->correctResult(
                $this->selectedResult,
                $this->correctionNewValue,
                $this->correctionReason,
                auth()->user()
            );

            session()->flash('success', "Result corrected (Version {$newResult->version}) with audit trail preserved.");
            $this->closeCorrectionModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function viewAuditHistory(int $orderId): void
    {
        $clinicId = TenantContext::clinicId();
        $this->selectedOrderId = $orderId;
        $this->selectedOrder = DiagnosticOrder::where('clinic_id', $clinicId)
            ->with(['patient', 'specimens'])
            ->findOrFail($orderId);

        $specimenIds = $this->selectedOrder->specimens->pluck('id')->toArray();

        $this->auditEntries = AuditLog::with('user')
            ->where(function ($q) use ($orderId, $specimenIds) {
                $q->where(function ($sq) use ($orderId) {
                    $sq->where('entity_type', DiagnosticOrder::class)->where('entity_id', $orderId);
                })
                ->orWhere(function ($sq) use ($specimenIds) {
                    $sq->where('entity_type', DiagnosticSpecimen::class)->whereIn('entity_id', $specimenIds);
                })
                ->orWhere(function ($sq) use ($orderId) {
                    $sq->where('entity_type', DiagnosticReport::class)
                       ->whereIn('entity_id', DiagnosticReport::where('diagnostic_order_id', $orderId)->pluck('id')->toArray());
                })
                ->orWhere(function ($sq) use ($orderId) {
                    $sq->where('entity_type', DiagnosticResult::class)
                       ->whereIn('entity_id', DiagnosticResult::where('diagnostic_order_id', $orderId)->pluck('id')->toArray());
                });
            })
            ->orderByDesc('id')
            ->get()
            ->toArray();

        $this->showAuditModal = true;
    }

    public function closeAuditModal(): void
    {
        $this->showAuditModal = false;
        $this->selectedOrderId = null;
        $this->selectedOrder = null;
        $this->auditEntries = [];
    }

    public function render()
    {
        $clinic = TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $clinicId = $clinic->id;

        // 1. Worklist Query
        $worklistQuery = DiagnosticOrder::where('clinic_id', $clinicId)
            ->with([
                'patient',
                'doctor',
                'diagnosticBill.payment',
                'items.diagnosticItem',
                'specimens',
                'report',
            ]);

        if ($this->search !== '') {
            $s = trim($this->search);
            $worklistQuery->where(function ($q) use ($s) {
                $q->where('order_number', 'LIKE', "%{$s}%")
                  ->orWhereHas('patient', function ($pq) use ($s) {
                      $pq->where('name', 'LIKE', "%{$s}%")
                         ->orWhere('patient_id', 'LIKE', "%{$s}%")
                         ->orWhere('mobile', 'LIKE', "%{$s}%");
                  })
                  ->orWhereHas('specimens', function ($sq) use ($s) {
                      $sq->where('specimen_identifier', 'LIKE', "%{$s}%");
                  });
            });
        }

        if ($this->filterStatus !== 'ALL') {
            $worklistQuery->where('status', $this->filterStatus);
        }

        if ($this->filterPriority !== 'ALL') {
            $worklistQuery->where('priority', $this->filterPriority);
        }

        if (!empty($this->dateFrom)) {
            $worklistQuery->whereDate('order_date', '>=', $this->dateFrom);
        }
        if (!empty($this->dateTo)) {
            $worklistQuery->whereDate('order_date', '<=', $this->dateTo);
        }

        $orders = $worklistQuery->orderByDesc('id')->paginate(15);

        // 2. Specimens Query (Samples tab)
        $specimensQuery = DiagnosticSpecimen::where('clinic_id', $clinicId)
            ->with(['order.patient', 'order.doctor', 'collectedBy', 'rejectedBy']);

        if ($this->search !== '') {
            $s = trim($this->search);
            $specimensQuery->where(function ($q) use ($s) {
                $q->where('specimen_identifier', 'LIKE', "%{$s}%")
                  ->orWhereHas('order', function ($oq) use ($s) {
                      $oq->where('order_number', 'LIKE', "%{$s}%")
                         ->orWhereHas('patient', function ($pq) use ($s) {
                             $pq->where('name', 'LIKE', "%{$s}%")
                                ->orWhere('patient_id', 'LIKE', "%{$s}%");
                         });
                  });
            });
        }

        if ($this->filterStatus !== 'ALL') {
            $specimensQuery->where('status', $this->filterStatus);
        }

        if (!empty($this->dateFrom)) {
            $specimensQuery->whereDate('created_at', '>=', $this->dateFrom);
        }
        if (!empty($this->dateTo)) {
            $specimensQuery->whereDate('created_at', '<=', $this->dateTo);
        }

        $specimens = $specimensQuery->orderByDesc('id')->paginate(15, ['*'], 'samplesPage');

        // 3. Reports Query (Reports tab)
        $reportsQuery = DiagnosticReport::where('clinic_id', $clinicId)
            ->with(['patient', 'doctor', 'order.items', 'releasedBy']);

        if ($this->search !== '') {
            $s = trim($this->search);
            $reportsQuery->where(function ($q) use ($s) {
                $q->where('report_number', 'LIKE', "%{$s}%")
                  ->orWhereHas('patient', function ($pq) use ($s) {
                      $pq->where('name', 'LIKE', "%{$s}%")
                         ->orWhere('patient_id', 'LIKE', "%{$s}%");
                  });
            });
        }

        if (!empty($this->dateFrom)) {
            $reportsQuery->whereDate('created_at', '>=', $this->dateFrom);
        }
        if (!empty($this->dateTo)) {
            $reportsQuery->whereDate('created_at', '<=', $this->dateTo);
        }

        $reports = $reportsQuery->orderByDesc('id')->paginate(15, ['*'], 'reportsPage');

        // Status counts for badge summary
        $counts = [
            'total' => DiagnosticOrder::where('clinic_id', $clinicId)->count(),
            'pending_sample' => DiagnosticOrder::where('clinic_id', $clinicId)->whereIn('status', ['ORDERED', 'SAMPLE_PENDING'])->count(),
            'collected' => DiagnosticOrder::where('clinic_id', $clinicId)->where('status', 'COLLECTED')->count(),
            'result_entered' => DiagnosticOrder::where('clinic_id', $clinicId)->where('status', 'RESULT_ENTERED')->count(),
            'verified' => DiagnosticOrder::where('clinic_id', $clinicId)->where('status', 'VERIFIED')->count(),
            'released' => DiagnosticOrder::where('clinic_id', $clinicId)->where('status', 'RELEASED')->count(),
        ];

        return view('livewire.lab.diagnostic-lab-worklist', [
            'orders' => $orders,
            'specimens' => $specimens,
            'reports' => $reports,
            'counts' => $counts,
            'userRole' => auth()->user()?->role ?? 'OPERATOR',
        ])->layout('components.layouts.app', ['title' => 'Diagnostic Laboratory Workflow']);
    }
}
