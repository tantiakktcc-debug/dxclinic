<?php

namespace App\Livewire\Operator;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Clinic;
use App\Models\DiagnosticBill;
use App\Models\DiagnosticBillItem;
use App\Models\DiagnosticItem;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Visit;
use App\Services\BillModificationService;
use App\Services\DiagnosticBillingService;
use App\Services\PaymentService;
use App\Services\RefundService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class DiagnosticBilling extends Component
{
    use WithPagination;

    // View Navigation Tabs: 'new', 'history'
    public string $activeTab = 'new';

    // New Billing Workspace
    public ?int $patientId = null;
    public ?Patient $patient = null;
    public ?int $visitId = null;
    public ?Visit $visit = null;

    // Patient Search
    public string $patientSearchQuery = '';
    public array $patientSearchResults = [];

    // Search & Selected items
    public string $searchTerm = '';
    public array $selectedItems = [];
    public float $discountAmount = 0.00;
    public float $waiverAmount = 0.00;

    // Created bill & payment
    public ?DiagnosticBill $bill = null;
    public ?Payment $payment = null;

    // Payment fields
    public string $paymentMethod = 'CASH';
    public ?int $selectedBankAccountId = null;
    public bool $soundboxConfirmed = false;
    public string $referenceNumber = '';

    // --- History Tab Filters ---
    public string $filterPatient = '';
    public string $searchBillNumber = '';
    public ?int $filterDoctorId = null;
    public string $filterPaymentMethod = '';
    public string $filterStatus = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    // --- Detail Modal ---
    public bool $showDetailModal = false;
    public ?int $selectedBillId = null;
    public ?DiagnosticBill $detailBill = null;

    // --- Audit Modal ---
    public bool $showAuditModal = false;
    public array $auditEntries = [];

    // --- Modification / Refund / Supplementary Modal ---
    public bool $showModifyModal = false;
    public ?DiagnosticBill $modifyBill = null;
    public string $modificationType = 'PARTIAL_REFUND'; // 'PARTIAL_REFUND', 'FULL_REFUND', 'SUPPLEMENTARY_CHARGE', 'ITEM_CORRECTION'
    public float $modifyRefundAmount = 0.00;
    public string $modifyRefundMethod = 'CASH';
    public ?int $modifyBankAccountId = null;
    public string $modifyReferenceNumber = '';
    public string $modifyReason = '';
    public bool $confirmModify = false;

    // Supplementary Tests selection
    public string $supplementarySearchTerm = '';
    public array $supplementaryItems = []; // [ item_id => [ 'item_id' => ..., 'name' => ..., 'rate' => ..., 'quantity' => 1, 'amount' => ... ] ]

    public function mount(?int $patientId = null): void
    {
        $this->dateFrom = Carbon::now()->toDateString();
        $this->dateTo = Carbon::now()->toDateString();

        if ($patientId) {
            $this->selectPatient($patientId);
        }

        $clinicId = TenantContext::clinicId();
        $defaultBank = BankAccount::where('clinic_id', $clinicId)->where('is_active', true)->first();
        if ($defaultBank) {
            $this->selectedBankAccountId = $defaultBank->id;
            $this->modifyBankAccountId = $defaultBank->id;
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->filterPatient = '';
        $this->searchBillNumber = '';
        $this->filterDoctorId = null;
        $this->filterPaymentMethod = '';
        $this->filterStatus = '';
        $this->dateFrom = Carbon::now()->toDateString();
        $this->dateTo = Carbon::now()->toDateString();
        $this->resetPage();
    }

    public function searchPatient(): void
    {
        $this->performPatientSearch();
    }

    public function performPatientSearch(): void
    {
        $q = trim($this->patientSearchQuery);
        if (strlen($q) < 2) {
            $this->patientSearchResults = [];
            return;
        }

        $clinicId = TenantContext::clinicId();
        $this->patientSearchResults = Patient::where('clinic_id', $clinicId)
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('patient_id', 'LIKE', "%{$q}%")
                    ->orWhere('mobile', 'LIKE', "%{$q}%")
                    ->orWhereHas('appointments', function ($aq) use ($q) {
                        $aq->where('formatted_token', $q)
                            ->orWhere('token_number', (int) $q);
                    });
            })
            ->limit(8)
            ->get()
            ->toArray();
    }

    public function selectPatient(int $id): void
    {
        $clinicId = TenantContext::clinicId();
        $this->patientId = $id;
        $this->patient = Patient::where('clinic_id', $clinicId)
            ->with(['visits' => fn($q) => $q->latest()->limit(5)])
            ->findOrFail($id);
        $this->visit = $this->patient->visits->first();
        $this->visitId = $this->visit?->id;
        $this->patientSearchResults = [];
        $this->patientSearchQuery = '';
    }

    public function addItem(int $itemId): void
    {
        $clinicId = TenantContext::clinicId();
        $item = DiagnosticItem::where(fn($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', $clinicId))
            ->findOrFail($itemId);

        if (isset($this->selectedItems[$itemId])) {
            $this->selectedItems[$itemId]['quantity']++;
            $this->selectedItems[$itemId]['amount'] = round($this->selectedItems[$itemId]['quantity'] * $this->selectedItems[$itemId]['rate'], 2);
        } else {
            $this->selectedItems[$itemId] = [
                'item_id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'rate' => (float) $item->rate,
                'quantity' => 1,
                'amount' => (float) $item->rate,
            ];
        }
    }

    public function updateQuantity(int $itemId, int $qty): void
    {
        if ($qty <= 0) {
            $this->removeItem($itemId);
            return;
        }

        if (isset($this->selectedItems[$itemId])) {
            $this->selectedItems[$itemId]['quantity'] = $qty;
            $this->selectedItems[$itemId]['amount'] = round($qty * $this->selectedItems[$itemId]['rate'], 2);
        }
    }

    public function removeItem(int $itemId): void
    {
        unset($this->selectedItems[$itemId]);
    }

    public function getGrossTotalProperty(): float
    {
        return round(array_sum(array_column($this->selectedItems, 'amount')), 2);
    }

    public function getNetTotalProperty(): float
    {
        return max(0.0, round($this->grossTotal - $this->discountAmount - $this->waiverAmount, 2));
    }

    public function createBill(DiagnosticBillingService $billingService): void
    {
        if (!$this->patient) {
            session()->flash('error', 'Please select a patient first.');
            return;
        }

        if (empty($this->selectedItems)) {
            session()->flash('error', 'Select at least one diagnostic test or package.');
            return;
        }

        try {
            $itemsData = array_values($this->selectedItems);
            $this->bill = $billingService->createBill(
                $this->patient,
                $this->visit,
                $itemsData,
                $this->discountAmount,
                $this->waiverAmount
            );

            session()->flash('success', "Diagnostic Bill {$this->bill->bill_number} created successfully!");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function settlePayment(PaymentService $paymentService): void
    {
        if (!$this->bill) return;

        $idempotencyKey = 'PAY-D-BILL-' . $this->bill->id . '-' . Str::random(10);

        try {
            $this->payment = $paymentService->settleDiagnosticPayment($this->bill, [
                'payment_method' => $this->paymentMethod,
                'bank_account_id' => $this->selectedBankAccountId,
                'soundbox_confirmed' => $this->soundboxConfirmed,
                'reference_number' => $this->referenceNumber,
                'amount' => $this->bill->net_amount,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->bill = $this->bill->fresh();
            session()->flash('success', "Diagnostic payment of ₹" . number_format($this->payment->amount, 2) . " settled successfully!");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function resetBilling(): void
    {
        $this->bill = null;
        $this->payment = null;
        $this->selectedItems = [];
        $this->discountAmount = 0.00;
        $this->waiverAmount = 0.00;
    }

    public function viewBillDetail(int $billId): void
    {
        $this->selectedBillId = $billId;
        $this->detailBill = DiagnosticBill::with([
            'patient',
            'doctor',
            'clinic',
            'items.diagnosticItem',
            'payment.receipt',
            'refunds.processedBy',
            'refunds.requestedBy',
            'refunds.receiptRevisions',
        ])->findOrFail($billId);

        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->detailBill = null;
        $this->selectedBillId = null;
    }

    public function viewAuditHistory(int $billId): void
    {
        $this->selectedBillId = $billId;
        $bill = DiagnosticBill::findOrFail($billId);

        $entries = AuditLog::with('user')
            ->where(function ($q) use ($bill) {
                $q->where('entity_type', DiagnosticBill::class)
                    ->where('entity_id', $bill->id);
            })
            ->orWhere(function ($q) use ($bill) {
                $q->where('entity_type', Refund::class)
                    ->whereIn('entity_id', $bill->refunds()->pluck('id')->toArray());
            })
            ->orderByDesc('id')
            ->get();

        $this->auditEntries = $entries->toArray();
        $this->showAuditModal = true;
    }

    public function closeAuditModal(): void
    {
        $this->showAuditModal = false;
        $this->auditEntries = [];
    }

    public function openModifyModal(int $billId): void
    {
        $this->modifyBill = DiagnosticBill::with(['items', 'payment.receipt', 'patient', 'doctor'])->findOrFail($billId);
        $this->selectedBillId = $billId;
        $this->modifyReason = '';
        $this->confirmModify = false;
        $this->supplementaryItems = [];
        $this->supplementarySearchTerm = '';

        if ($this->modifyBill->status === 'PENDING') {
            $this->modificationType = 'ITEM_CORRECTION';
        } else {
            $this->modificationType = 'PARTIAL_REFUND';
            $refundable = $this->modifyBill->payment ? app(RefundService::class)->getRemainingRefundableAmount($this->modifyBill->payment) : 0.00;
            $this->modifyRefundAmount = min(200.00, $refundable);
            $this->modifyRefundMethod = $this->modifyBill->payment?->payment_method ?? 'CASH';
        }

        $this->showModifyModal = true;
    }

    public function closeModifyModal(): void
    {
        $this->showModifyModal = false;
        $this->modifyBill = null;
        $this->confirmModify = false;
        $this->supplementaryItems = [];
    }

    public function getCanSubmitRefundProperty(): bool
    {
        if (!$this->modifyBill || !$this->confirmModify) {
            return false;
        }

        if (strlen(trim($this->modifyReason)) < 10) {
            return false;
        }

        if ($this->modifyBill->status === 'PENDING') {
            return true;
        }

        if ($this->modificationType === 'SUPPLEMENTARY_BILL') {
            return !empty($this->supplementaryItems);
        }

        // Settled refund validation
        if ($this->modifyRefundAmount <= 0) {
            return false;
        }

        if ($this->modifyBill->payment) {
            $refundable = app(\App\Services\RefundService::class)->getRemainingRefundableAmount($this->modifyBill->payment);
            if ($this->modifyRefundAmount > $refundable) {
                return false;
            }
        }

        if ($this->modifyRefundMethod === 'UPI') {
            if (empty($this->modifyBankAccountId) || empty(trim($this->modifyReferenceNumber))) {
                return false;
            }
        }

        return true;
    }

    public function addSupplementaryItem(int $itemId): void
    {
        $clinicId = TenantContext::clinicId();
        $item = DiagnosticItem::where(fn($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', $clinicId))
            ->findOrFail($itemId);
        if (isset($this->supplementaryItems[$itemId])) {
            $this->supplementaryItems[$itemId]['quantity']++;
            $this->supplementaryItems[$itemId]['amount'] = round($this->supplementaryItems[$itemId]['quantity'] * $this->supplementaryItems[$itemId]['rate'], 2);
        } else {
            $this->supplementaryItems[$itemId] = [
                'item_id' => $item->id,
                'name' => $item->name,
                'rate' => (float) $item->rate,
                'quantity' => 1,
                'amount' => (float) $item->rate,
            ];
        }
    }

    public function removeSupplementaryItem(int $itemId): void
    {
        unset($this->supplementaryItems[$itemId]);
    }

    public function submitModification(BillModificationService $modService, RefundService $refundService): void
    {
        if (!$this->modifyBill) return;

        $user = auth()->user();

        // 1. Pending bill modification
        if ($this->modifyBill->status === 'PENDING') {
            try {
                // If items modified
                $itemsData = array_values($this->selectedItems);
                if (empty($itemsData)) {
                    // Use existing items if none selected in current session
                    $itemsData = $this->modifyBill->items->map(fn($it) => [
                        'item_id' => $it->diagnostic_item_id,
                        'rate' => (float) $it->rate,
                        'quantity' => (int) $it->quantity,
                    ])->toArray();
                }

                $modService->modifyPendingDiagnosticBill($this->modifyBill, $itemsData, null, $this->modifyReason, $user);
                session()->flash('success', "Pending diagnostic bill #{$this->modifyBill->bill_number} modified successfully.");
                $this->closeModifyModal();
            } catch (\Exception $e) {
                session()->flash('error', $e->getMessage());
            }
            return;
        }

        // 2. Supplementary Bill Creation (Charge Increase after settlement)
        if ($this->modificationType === 'SUPPLEMENTARY_CHARGE') {
            if (empty($this->supplementaryItems)) {
                session()->flash('error', "Please select at least one additional diagnostic item for the supplementary bill.");
                return;
            }

            try {
                $suppBill = $modService->createSupplementaryDiagnosticBill($this->modifyBill, array_values($this->supplementaryItems), $this->modifyReason, $user);
                session()->flash('success', "Supplementary Diagnostic Bill #{$suppBill->bill_number} created for ₹" . number_format($suppBill->net_amount, 2) . ". Ready for payment.");
                $this->closeModifyModal();
            } catch (\Exception $e) {
                session()->flash('error', $e->getMessage());
            }
            return;
        }

        // 3. Settled bill reduction / Refund
        $payment = $this->modifyBill->payment;
        if (!$payment) {
            session()->flash('error', "No settled payment record found for this diagnostic bill.");
            return;
        }

        try {
            $refundService->validateReason($this->modifyReason);

            if ($this->modifyRefundAmount <= 0) {
                throw new \InvalidArgumentException("Refund amount must be strictly greater than zero.");
            }

            $refundable = $refundService->getRemainingRefundableAmount($payment);
            if ($this->modifyRefundAmount > $refundable) {
                throw new \InvalidArgumentException("Refund amount (₹{$this->modifyRefundAmount}) exceeds remaining refundable amount (₹{$refundable}).");
            }

            $refundData = [
                'payment_id' => $payment->id,
                'consultation_bill_id' => null,
                'diagnostic_bill_id' => $this->modifyBill->id,
                'patient_id' => $this->modifyBill->patient_id,
                'refund_type' => $this->modifyRefundAmount >= $refundable ? 'FULL' : 'PARTIAL',
                'refund_amount' => $this->modifyRefundAmount,
                'refund_method' => $this->modifyRefundMethod,
                'bank_account_id' => $this->modifyRefundMethod === 'UPI' ? $this->modifyBankAccountId : null,
                'reference_number' => $this->modifyRefundMethod === 'UPI' ? $this->modifyReferenceNumber : null,
                'reason' => $this->modifyReason,
            ];

            if ($user && $user->isAdmin()) {
                $refund = $refundService->processDirectRefund($refundData, $user);
                session()->flash('success', "Diagnostic refund of ₹" . number_format($refund->refund_amount, 2) . " processed successfully! (#{$refund->refund_number})");
            } else {
                $refund = $refundService->requestRefund($refundData, $user);
                session()->flash('success', "Diagnostic refund request of ₹" . number_format($refund->refund_amount, 2) . " submitted for Administrator approval. (#{$refund->refund_number})");
            }

            $this->closeModifyModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(DiagnosticBillingService $billingService)
    {
        $clinicId = TenantContext::clinicId();
        $frequentItems = $billingService->getFrequentlyUsed(12, $clinicId);

        $searchResults = [];
        if (strlen(trim($this->searchTerm)) >= 2) {
            $s = trim($this->searchTerm);
            $searchResults = DiagnosticItem::where('is_active', true)
                ->where(fn($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', $clinicId))
                ->where(function ($q) use ($s) {
                    $q->where('name', 'LIKE', "%{$s}%")
                        ->orWhere('code', 'LIKE', "%{$s}%");
                })
                ->limit(15)
                ->get();
        }

        $supplementarySearchResults = [];
        if (strlen(trim($this->supplementarySearchTerm)) >= 2) {
            $s = trim($this->supplementarySearchTerm);
            $supplementarySearchResults = DiagnosticItem::where('is_active', true)
                ->where(fn($q) => $q->whereNull('clinic_id')->orWhere('clinic_id', $clinicId))
                ->where(function ($q) use ($s) {
                    $q->where('name', 'LIKE', "%{$s}%")
                        ->orWhere('code', 'LIKE', "%{$s}%");
                })
                ->limit(10)
                ->get();
        }

        $recentPatients = Patient::where('clinic_id', $clinicId)->orderByDesc('id')->limit(10)->get();
        $bankAccounts = BankAccount::where('clinic_id', $clinicId)->where('is_active', true)->get();
        $doctors = Doctor::where('clinic_id', $clinicId)->where('is_active', true)->get();

        // History Tab Query
        $historyQuery = DiagnosticBill::where('clinic_id', $clinicId)
            ->with(['patient', 'doctor', 'items.diagnosticItem', 'payment.receipt', 'refunds'])
            ->orderByDesc('id');

        if (!empty($this->dateFrom)) {
            $historyQuery->whereDate('bill_date', '>=', $this->dateFrom);
        }
        if (!empty($this->dateTo)) {
            $historyQuery->whereDate('bill_date', '<=', $this->dateTo);
        }
        if (!empty($this->searchBillNumber)) {
            $historyQuery->where('bill_number', 'LIKE', '%' . trim($this->searchBillNumber) . '%');
        }
        if (!empty($this->filterPatient)) {
            $p = trim($this->filterPatient);
            $historyQuery->whereHas('patient', function ($q) use ($p) {
                $q->where('name', 'LIKE', "%{$p}%")
                    ->orWhere('patient_id', 'LIKE', "%{$p}%")
                    ->orWhere('mobile', 'LIKE', "%{$p}%");
            });
        }
        if ($this->filterDoctorId) {
            $historyQuery->where('doctor_id', $this->filterDoctorId);
        }
        if (!empty($this->filterStatus)) {
            $historyQuery->where('status', $this->filterStatus);
        }
        if (!empty($this->filterPaymentMethod)) {
            $historyQuery->whereHas('payment', fn($q) => $q->where('payment_method', $this->filterPaymentMethod));
        }

        $historyBills = $this->activeTab === 'history' ? $historyQuery->paginate(12) : null;

        return view('livewire.operator.diagnostic-billing', [
            'frequentItems' => $frequentItems,
            'searchResults' => $searchResults,
            'supplementarySearchResults' => $supplementarySearchResults,
            'recentPatients' => $recentPatients,
            'bankAccounts' => $bankAccounts,
            'doctors' => $doctors,
            'historyBills' => $historyBills,
        ])->layout('components.layouts.app', ['title' => 'Diagnostic Billing & History']);
    }
}
