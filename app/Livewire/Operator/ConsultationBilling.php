<?php

namespace App\Livewire\Operator;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Visit;
use App\Services\BillModificationService;
use App\Services\ConsultationBillingService;
use App\Services\PaymentService;
use App\Services\RefundService;
use App\Services\TenantContext;
use App\Services\VisitService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ConsultationBilling extends Component
{
    use WithPagination;

    // View Navigation Tabs: 'new', 'history', 'doctor_pads'
    public string $activeTab = 'new';

    // New Billing Workspace fields
    public ?int $appointmentId = null;
    public ?Appointment $appointment = null;
    public ?Visit $visit = null;
    public ?ConsultationBill $bill = null;
    public ?Payment $payment = null;

    // Payment settlement form
    public string $paymentMethod = 'CASH'; // CASH or UPI
    public ?int $selectedBankAccountId = null;
    public bool $soundboxConfirmed = false;
    public string $referenceNumber = '';
    public float $discountAmount = 0.00;
    public float $waiverAmount = 0.00;

    // --- History Tab Filters ---
    public string $searchPatient = '';
    public string $searchBillNumber = '';
    public string $searchToken = '';
    public ?int $filterDoctorId = null;
    public string $filterMode = '';
    public string $filterPaymentMethod = '';
    public string $filterStatus = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    // --- Doctor Pad History Filters ---
    public string $dpSearchPatient = '';
    public ?int $dpDoctorId = null;
    public string $dpDateFrom = '';
    public string $dpDateTo = '';

    // --- Detail Modal ---
    public bool $showDetailModal = false;
    public ?int $selectedBillId = null;
    public ?ConsultationBill $detailBill = null;

    // --- Audit Modal ---
    public bool $showAuditModal = false;
    public array $auditEntries = [];

    // --- Modification / Refund Modal ---
    public bool $showModifyModal = false;
    public ?ConsultationBill $modifyBill = null;
    public string $modificationType = 'PARTIAL_REFUND'; // DISCOUNT_AFTER_SETTLEMENT, PARTIAL_REFUND, FULL_REFUND, BILL_CORRECTION
    public float $modifyRefundAmount = 0.00;
    public string $modifyRefundMethod = 'CASH'; // CASH or UPI
    public ?int $modifyBankAccountId = null;
    public string $modifyReferenceNumber = '';
    public string $modifyReason = '';
    public ?int $modifyDoctorInstructionBy = null;
    public string $modifyDoctorRecommendation = '';
    public bool $confirmModify = false;

    // Pending bill edit fields
    public float $editGrossAmount = 0.00;
    public float $editDiscountAmount = 0.00;
    public float $editWaiverAmount = 0.00;
    public string $editWaiverType = '';

    // Admin Process Refund Modal
    public bool $showProcessRefundModal = false;
    public ?Refund $activeRefundToProcess = null;
    public string $processRefundMethod = 'CASH';
    public ?int $processBankAccountId = null;
    public string $processReferenceNumber = '';

    public function mount(?int $appointmentId = null, ?ConsultationBillingService $billingService = null, ?VisitService $visitService = null): void
    {
        $this->dateFrom = Carbon::now()->toDateString();
        $this->dateTo = Carbon::now()->toDateString();
        $this->dpDateFrom = Carbon::now()->toDateString();
        $this->dpDateTo = Carbon::now()->toDateString();

        if ($appointmentId) {
            $this->loadAppointment($appointmentId, $billingService, $visitService);
        }

        $defaultBank = BankAccount::where('is_active', true)->first();
        if ($defaultBank) {
            $this->selectedBankAccountId = $defaultBank->id;
            $this->modifyBankAccountId = $defaultBank->id;
            $this->processBankAccountId = $defaultBank->id;
        }

        if (auth()->check() && (auth()->user()->doctor_id || auth()->user()->role === 'DOCTOR')) {
            $docId = auth()->user()->doctor_id;
            if ($docId) {
                $this->dpDoctorId = $docId;
                $this->filterDoctorId = $docId;
            }
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->searchPatient = '';
        $this->searchBillNumber = '';
        $this->searchToken = '';
        if (auth()->check() && (auth()->user()->doctor_id || auth()->user()->role === 'DOCTOR') && auth()->user()->doctor_id) {
            $this->filterDoctorId = auth()->user()->doctor_id;
        } else {
            $this->filterDoctorId = null;
        }
        $this->filterMode = '';
        $this->filterPaymentMethod = '';
        $this->filterStatus = '';
        $this->dateFrom = Carbon::now()->toDateString();
        $this->dateTo = Carbon::now()->toDateString();
        $this->resetPage();
    }

    public function resetDpFilters(): void
    {
        $this->dpSearchPatient = '';
        if (auth()->check() && (auth()->user()->doctor_id || auth()->user()->role === 'DOCTOR') && auth()->user()->doctor_id) {
            $this->dpDoctorId = auth()->user()->doctor_id;
        } else {
            $this->dpDoctorId = null;
        }
        $this->dpDateFrom = Carbon::now()->toDateString();
        $this->dpDateTo = Carbon::now()->toDateString();
        $this->resetPage();
    }

    public function loadAppointment(int $id, ?ConsultationBillingService $billingService = null, ?VisitService $visitService = null): void
    {
        $billingService = $billingService ?? app(ConsultationBillingService::class);
        $visitService = $visitService ?? app(VisitService::class);

        $this->appointmentId = $id;
        $clinicId = TenantContext::clinicId();
        $this->appointment = Appointment::where('clinic_id', $clinicId)->with(['patient', 'visit.vital', 'doctor'])->findOrFail($id);

        if (auth()->check() && (auth()->user()->doctor_id || auth()->user()->role === 'DOCTOR')) {
            $docId = auth()->user()->doctor_id;
            if ($docId && $this->appointment->doctor_id !== $docId) {
                abort(403, 'Unauthorized access to appointment for another doctor.');
            }
        }

        $this->visit = $this->appointment->visit ?? $visitService->markArrival($this->appointment);
        $this->bill = $billingService->createOrGetBill($this->visit, $this->discountAmount, $this->waiverAmount);

        $this->payment = Payment::where('consultation_bill_id', $this->bill->id)
            ->where('status', 'SETTLED')
            ->first();

        $this->activeTab = 'new';
    }

    public function settlePayment(PaymentService $paymentService): void
    {
        if (!$this->bill) return;

        $idempotencyKey = 'PAY-C-APP-' . $this->appointment->id . '-' . Str::random(10);

        try {
            $this->payment = $paymentService->settleConsultationPayment($this->bill, [
                'payment_method' => $this->paymentMethod,
                'bank_account_id' => $this->selectedBankAccountId,
                'soundbox_confirmed' => $this->soundboxConfirmed,
                'reference_number' => $this->referenceNumber,
                'amount' => $this->bill->net_amount,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->bill = $this->bill->fresh();
            session()->flash('success', "Payment of ₹" . number_format($this->payment->amount, 2) . " settled successfully ({$this->paymentMethod})!");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function viewBillDetail(int $billId): void
    {
        $clinicId = TenantContext::clinicId();
        $this->selectedBillId = $billId;
        $this->detailBill = ConsultationBill::where('clinic_id', $clinicId)->with([
            'patient',
            'doctor',
            'clinic',
            'appointment.visit.vital',
            'payment.receipt',
            'refunds.processedBy',
            'refunds.approvedBy',
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
        $clinicId = TenantContext::clinicId();
        $this->selectedBillId = $billId;
        $bill = ConsultationBill::where('clinic_id', $clinicId)->findOrFail($billId);

        $entries = AuditLog::with('user')
            ->where(function ($q) use ($bill) {
                $q->where('entity_type', ConsultationBill::class)
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
        $clinicId = TenantContext::clinicId();
        $this->modifyBill = ConsultationBill::where('clinic_id', $clinicId)->with(['payment.receipt', 'patient', 'doctor'])->findOrFail($billId);
        $this->selectedBillId = $billId;
        $this->modifyReason = '';
        $this->modifyDoctorRecommendation = '';
        $this->modifyDoctorInstructionBy = $this->modifyBill->doctor_id;
        $this->confirmModify = false;

        if ($this->modifyBill->status === 'PENDING') {
            $this->modificationType = 'BILL_CORRECTION';
            $this->editGrossAmount = (float) $this->modifyBill->gross_amount;
            $this->editDiscountAmount = (float) $this->modifyBill->discount_amount;
            $this->editWaiverAmount = (float) $this->modifyBill->waiver_amount;
            $this->editWaiverType = $this->modifyBill->waiver_type ?? '';
        } else {
            // Settled bill
            $this->modificationType = 'PARTIAL_REFUND';
            $refundable = $this->modifyBill->payment ? app(RefundService::class)->getRemainingRefundableAmount($this->modifyBill->payment) : 0.00;
            $this->modifyRefundAmount = min(100.00, $refundable);
            $this->modifyRefundMethod = $this->modifyBill->payment?->payment_method ?? 'CASH';
        }

        $this->showModifyModal = true;
    }

    public function closeModifyModal(): void
    {
        $this->showModifyModal = false;
        $this->modifyBill = null;
        $this->confirmModify = false;
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

    public function submitModification(BillModificationService $modService, RefundService $refundService): void
    {
        if (!$this->modifyBill) return;

        $user = auth()->user();

        // 1. Pending bill modification
        if ($this->modifyBill->status === 'PENDING') {
            try {
                $modService->modifyPendingConsultationBill($this->modifyBill, [
                    'gross_amount' => $this->editGrossAmount,
                    'discount_amount' => $this->editDiscountAmount,
                    'waiver_amount' => $this->editWaiverAmount,
                    'waiver_type' => $this->editWaiverType,
                    'reason' => $this->modifyReason,
                ], $user);

                session()->flash('success', "Pending consultation bill #{$this->modifyBill->bill_number} modified successfully.");
                $this->closeModifyModal();
            } catch (\Exception $e) {
                session()->flash('error', $e->getMessage());
            }
            return;
        }

        // 2. Settled bill modification (Refund / Adjustment)
        $payment = $this->modifyBill->payment;
        if (!$payment) {
            session()->flash('error', "No settled payment record found for this bill.");
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
                'consultation_bill_id' => $this->modifyBill->id,
                'diagnostic_bill_id' => null,
                'patient_id' => $this->modifyBill->patient_id,
                'refund_type' => $this->modificationType === 'DISCOUNT_AFTER_SETTLEMENT' ? 'REFUND_AS_DISCOUNT' : ($this->modifyRefundAmount >= $refundable ? 'FULL' : 'PARTIAL'),
                'refund_amount' => $this->modifyRefundAmount,
                'refund_method' => $this->modifyRefundMethod,
                'bank_account_id' => $this->modifyRefundMethod === 'UPI' ? $this->modifyBankAccountId : null,
                'reference_number' => $this->modifyRefundMethod === 'UPI' ? $this->modifyReferenceNumber : null,
                'reason' => $this->modifyReason,
                'doctor_instruction_by' => $this->modifyDoctorInstructionBy,
            ];

            // If user is Admin, they can settle directly or request
            if ($user && $user->isAdmin()) {
                $refund = $refundService->processDirectRefund($refundData, $user);
                session()->flash('success', "Refund of ₹" . number_format($refund->refund_amount, 2) . " processed successfully! (#{$refund->refund_number})");
            } else {
                // Operator or Doctor: Creates a REQUESTED refund for Admin approval
                $refund = $refundService->requestRefund($refundData, $user);
                session()->flash('success', "Refund request of ₹" . number_format($refund->refund_amount, 2) . " submitted for Administrator approval. (#{$refund->refund_number})");
            }

            $this->closeModifyModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openProcessRefundModal(int $refundId): void
    {
        $this->activeRefundToProcess = Refund::findOrFail($refundId);
        $this->processRefundMethod = $this->activeRefundToProcess->refund_method;
        $this->processBankAccountId = $this->activeRefundToProcess->bank_account_id ?? BankAccount::where('is_active', true)->first()?->id;
        $this->processReferenceNumber = $this->activeRefundToProcess->reference_number ?? '';
        $this->showProcessRefundModal = true;
    }

    public function closeProcessRefundModal(): void
    {
        $this->showProcessRefundModal = false;
        $this->activeRefundToProcess = null;
    }

    public function executeAdminProcessRefund(RefundService $refundService): void
    {
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            session()->flash('error', "Only administrators are authorized to process refunds.");
            return;
        }

        if (!$this->activeRefundToProcess) return;

        try {
            $overrides = [
                'refund_method' => $this->processRefundMethod,
                'bank_account_id' => $this->processRefundMethod === 'UPI' ? $this->processBankAccountId : null,
                'reference_number' => $this->processRefundMethod === 'UPI' ? $this->processReferenceNumber : null,
            ];

            $refundService->processRefund($this->activeRefundToProcess, $user, $overrides);
            session()->flash('success', "Refund #{$this->activeRefundToProcess->refund_number} settled successfully!");
            $this->closeProcessRefundModal();

            if ($this->showDetailModal && $this->selectedBillId) {
                $this->viewBillDetail($this->selectedBillId);
            }
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $clinic = TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $bankAccounts = BankAccount::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->get();
        $doctors = Doctor::where('clinic_id', $clinic->id)->get();

        // 1. History Tab Query
        $historyQuery = ConsultationBill::where('clinic_id', $clinic->id)
            ->with(['patient', 'doctor', 'payment.receipt', 'refunds'])
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
        if (!empty($this->searchPatient)) {
            $p = trim($this->searchPatient);
            $historyQuery->whereHas('patient', function ($q) use ($p) {
                $q->where('name', 'LIKE', "%{$p}%")
                    ->orWhere('patient_id', 'LIKE', "%{$p}%")
                    ->orWhere('mobile', 'LIKE', "%{$p}%");
            });
        }
        if (!empty($this->searchToken)) {
            $t = trim($this->searchToken);
            $historyQuery->whereHas('appointment', function ($q) use ($t) {
                $q->where('formatted_token', $t)->orWhere('token_number', (int) $t);
            });
        }
        if ($this->filterDoctorId) {
            $historyQuery->where('doctor_id', $this->filterDoctorId);
        }
        if (!empty($this->filterMode)) {
            $historyQuery->where('consultation_mode', $this->filterMode);
        }
        if (!empty($this->filterStatus)) {
            $historyQuery->where('status', $this->filterStatus);
        }
        if (!empty($this->filterPaymentMethod)) {
            $historyQuery->whereHas('payment', fn($q) => $q->where('payment_method', $this->filterPaymentMethod));
        }

        $historyBills = $this->activeTab === 'history' ? $historyQuery->paginate(12) : null;

        // 2. Doctor Pad History Query
        $dpQuery = Appointment::where('clinic_id', $clinic->id)
            ->with(['patient', 'doctor', 'visit.vital', 'consultationBill', 'prescriptionPadTemplate'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('token_number');

        if (!empty($this->dpDateFrom)) {
            $dpQuery->whereDate('appointment_date', '>=', $this->dpDateFrom);
        }
        if (!empty($this->dpDateTo)) {
            $dpQuery->whereDate('appointment_date', '<=', $this->dpDateTo);
        }
        if ($this->dpDoctorId) {
            $dpQuery->where('doctor_id', $this->dpDoctorId);
        }
        if (!empty($this->dpSearchPatient)) {
            $p = trim($this->dpSearchPatient);
            $dpQuery->whereHas('patient', function ($q) use ($p) {
                $q->where('name', 'LIKE', "%{$p}%")
                    ->orWhere('patient_id', 'LIKE', "%{$p}%")
                    ->orWhere('mobile', 'LIKE', "%{$p}%");
            });
        }

        $doctorPadAppointments = $this->activeTab === 'doctor_pads' ? $dpQuery->paginate(12) : null;

        // 3. Recent Appointments for New Billing
        $recentAppointments = Appointment::where('clinic_id', $clinic->id)
            ->with(['patient', 'visit.vital'])
            ->whereDate('appointment_date', now()->toDateString())
            ->whereIn('status', ['BOOKED', 'ARRIVED'])
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('livewire.operator.consultation-billing', [
            'clinic' => $clinic,
            'bankAccounts' => $bankAccounts,
            'doctors' => $doctors,
            'recentAppointments' => $recentAppointments,
            'historyBills' => $historyBills,
            'doctorPadAppointments' => $doctorPadAppointments,
        ])->layout('components.layouts.app', ['title' => 'Consultation Billing & History']);
    }
}
