<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\Doctor;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptRevision;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RefundService
{
    /**
     * Minimum valid characters for a mandatory reason
     */
    const MIN_REASON_LENGTH = 10;

    /**
     * Calculate remaining refundable amount for a settled Payment
     * Derived strictly: original settled payment - SUM(PROCESSED refunds)
     */
    public function getRemainingRefundableAmount(Payment $payment): float
    {
        $processedRefundsSum = (float) Refund::where('original_payment_id', $payment->id)
            ->where('status', 'PROCESSED')
            ->sum('refund_amount');

        return max(0.00, round((float) $payment->amount - $processedRefundsSum, 2));
    }

    /**
     * Validate mandatory reason
     */
    public function validateReason(?string $reason): string
    {
        $trimmed = trim($reason ?? '');
        if (strlen($trimmed) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException(
                "A mandatory meaningful reason of at least " . self::MIN_REASON_LENGTH . " characters is required."
            );
        }

        $lower = strtolower($trimmed);
        if (in_array($lower, ['edit', 'change', 'mistake', 'ok', 'refund', 'modify', 'correction', 'test', 'error'])) {
            throw new InvalidArgumentException(
                "Generic reason '{$trimmed}' is not acceptable. Please provide a clear and specific explanation."
            );
        }

        return $trimmed;
    }

    /**
     * Create a refund request (REQUESTED status, Operator or Doctor recommendation)
     */
    public function requestRefund(array $data, ?User $user = null): Refund
    {
        $reason = $this->validateReason($data['reason'] ?? null);
        $user = $user ?? auth()->user();

        return DB::transaction(function () use ($data, $reason, $user) {
            $payment = Payment::with(['consultationBill', 'diagnosticBill'])->findOrFail($data['payment_id']);

            if ($payment->status !== 'SETTLED') {
                throw new InvalidArgumentException("Refunds can only be requested for settled payments.");
            }

            $refundAmount = round((float) ($data['refund_amount'] ?? 0), 2);
            if ($refundAmount <= 0) {
                throw new InvalidArgumentException("Refund amount must be strictly greater than zero.");
            }

            $refundable = $this->getRemainingRefundableAmount($payment);
            if ($refundAmount > $refundable) {
                throw new InvalidArgumentException(
                    "Requested refund (₹" . number_format($refundAmount, 2) . ") exceeds remaining refundable amount (₹" . number_format($refundable, 2) . ")."
                );
            }

            $consultationBillId = $data['consultation_bill_id'] ?? $payment->consultation_bill_id;
            $diagnosticBillId = $data['diagnostic_bill_id'] ?? $payment->diagnostic_bill_id;

            if ($consultationBillId && $diagnosticBillId) {
                throw new InvalidArgumentException("Refund cannot be attached to both consultation and diagnostic bills simultaneously.");
            }
            if (!$consultationBillId && !$diagnosticBillId) {
                throw new InvalidArgumentException("Refund must be linked to a consultation or diagnostic bill.");
            }

            $refundMethod = strtoupper(trim($data['refund_method'] ?? 'CASH'));
            if (!in_array($refundMethod, ['CASH', 'UPI'], true)) {
                throw new InvalidArgumentException("Invalid refund settlement method. Must be CASH or UPI.");
            }

            $refundType = $data['refund_type'] ?? ($refundAmount >= $refundable ? 'FULL' : 'PARTIAL');

            // Doctor instruction handling
            $doctorInstructionBy = $data['doctor_instruction_by'] ?? null;
            $doctorInstructionAt = $doctorInstructionBy ? Carbon::now() : null;

            $refundNumber = 'REF-REQ-' . date('YmdHis') . '-' . strtoupper(Str::random(4));

            $refund = Refund::create([
                'clinic_id' => $payment->clinic_id,
                'refund_number' => $refundNumber,
                'original_payment_id' => $payment->id,
                'consultation_bill_id' => $consultationBillId,
                'diagnostic_bill_id' => $diagnosticBillId,
                'patient_id' => $data['patient_id'] ?? $payment->patient->id,
                'refund_type' => $refundType,
                'refund_amount' => $refundAmount,
                'refund_method' => $refundMethod,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'cash_session_id' => $data['cash_session_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'reason' => $reason,
                'requested_by' => $user?->id,
                'requested_at' => Carbon::now(),
                'doctor_instruction_by' => $doctorInstructionBy,
                'doctor_instruction_at' => $doctorInstructionAt,
                'status' => 'REQUESTED',
            ]);

            AuditLog::log(
                action: 'REFUND_REQUESTED',
                entityType: Refund::class,
                entityId: $refund->id,
                newValues: $refund->toArray(),
                oldValues: null,
                reason: $reason,
                changeCategory: 'REFUND',
                financialImpact: "Requested refund of ₹" . number_format($refundAmount, 2),
                linkedRefundId: $refund->id
            );

            return $refund;
        });
    }

    /**
     * Approve a refund request (Admin action)
     */
    public function approveRefund(Refund $refund, User $user): Refund
    {
        if (!$user->isAdmin()) {
            throw new AuthorizationException("Only an Administrator can approve refunds.");
        }

        if ($refund->status !== 'REQUESTED') {
            throw new InvalidArgumentException("Only REQUESTED refunds can be approved.");
        }

        return DB::transaction(function () use ($refund, $user) {
            $refund->update([
                'status' => 'APPROVED',
                'approved_by' => $user->id,
                'approved_at' => Carbon::now(),
            ]);

            AuditLog::log(
                action: 'REFUND_APPROVED',
                entityType: Refund::class,
                entityId: $refund->id,
                newValues: ['status' => 'APPROVED', 'approved_by' => $user->id],
                oldValues: ['status' => 'REQUESTED'],
                reason: "Refund approved by administrator",
                changeCategory: 'REFUND',
                financialImpact: "Approved refund of ₹" . number_format($refund->refund_amount, 2),
                linkedRefundId: $refund->id
            );

            return $refund;
        });
    }

    /**
     * Reject a refund request (Admin action)
     */
    public function rejectRefund(Refund $refund, User $user, string $rejectionReason): Refund
    {
        if (!$user->isAdmin()) {
            throw new AuthorizationException("Only an Administrator can reject refunds.");
        }

        if ($refund->status !== 'REQUESTED' && $refund->status !== 'APPROVED') {
            throw new InvalidArgumentException("Only REQUESTED or APPROVED refunds can be rejected.");
        }

        $reason = $this->validateReason($rejectionReason);

        return DB::transaction(function () use ($refund, $user, $reason) {
            $oldStatus = $refund->status;
            $refund->update([
                'status' => 'REJECTED',
                'rejected_by' => $user->id,
                'rejected_at' => Carbon::now(),
                'rejection_reason' => $reason,
            ]);

            AuditLog::log(
                action: 'REFUND_REJECTED',
                entityType: Refund::class,
                entityId: $refund->id,
                newValues: ['status' => 'REJECTED', 'rejected_by' => $user->id, 'rejection_reason' => $reason],
                oldValues: ['status' => $oldStatus],
                reason: $reason,
                changeCategory: 'REFUND',
                financialImpact: "Rejected refund of ₹" . number_format($refund->refund_amount, 2),
                linkedRefundId: $refund->id
            );

            return $refund;
        });
    }

    /**
     * Process and settle an approved or direct refund (Admin action)
     * Generates:
     * 1. Balanced reversal journal entry (Dr Revenue, Cr Cash/Bank)
     * 2. ReceiptRevision record if receipt exists
     * 3. Comprehensive AuditLog
     * 4. Updates bill status to REFUNDED if remaining net retained reaches zero
     */
    public function processRefund(Refund $refund, User $user, array $settlementOverrides = []): Refund
    {
        if (!$user->isAdmin()) {
            throw new AuthorizationException("Only an Administrator can process and settle refunds.");
        }

        return DB::transaction(function () use ($refund, $user, $settlementOverrides) {
            // Lock payment to prevent race conditions
            $payment = Payment::where('id', $refund->original_payment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== 'SETTLED') {
                throw new InvalidArgumentException("Payment must be SETTLED to process a refund.");
            }

            // Concurrency check: recalculate remaining refundable amount
            $alreadyProcessed = (float) Refund::where('original_payment_id', $payment->id)
                ->where('status', 'PROCESSED')
                ->where('id', '!=', $refund->id)
                ->sum('refund_amount');

            $remainingRefundable = max(0.00, round((float) $payment->amount - $alreadyProcessed, 2));

            if ($refund->refund_amount > $remainingRefundable) {
                throw new InvalidArgumentException(
                    "Race-condition prevention: Refund amount (₹" . number_format($refund->refund_amount, 2) . ") exceeds remaining refundable amount (₹" . number_format($remainingRefundable, 2) . ")."
                );
            }

            // Settlement method overrides or defaults
            $refundMethod = strtoupper($settlementOverrides['refund_method'] ?? $refund->refund_method);
            if (!in_array($refundMethod, ['CASH', 'UPI'], true)) {
                throw new InvalidArgumentException("Refund settlement method must be CASH or UPI.");
            }

            $bankAccountId = null;
            $cashSessionId = null;
            $referenceNumber = null;

            if ($refundMethod === 'CASH') {
                $cashSessionId = $settlementOverrides['cash_session_id'] ?? $refund->cash_session_id;
                if (!$cashSessionId) {
                    $activeSession = CashSession::where('clinic_id', $payment->clinic_id)
                        ->where('status', 'OPEN')
                        ->latest('id')
                        ->first();
                    $cashSessionId = $activeSession?->id;
                }
                if (!$cashSessionId) {
                    throw new InvalidArgumentException("An active OPEN cash session is required to settle a CASH refund.");
                }
            } else {
                // UPI
                $bankAccountId = $settlementOverrides['bank_account_id'] ?? $refund->bank_account_id;
                $referenceNumber = trim($settlementOverrides['reference_number'] ?? $refund->reference_number ?? '');

                if (!$bankAccountId) {
                    throw new InvalidArgumentException("A bank account must be selected to process a UPI refund.");
                }
                if (empty($referenceNumber)) {
                    throw new InvalidArgumentException("A transaction/reference number is mandatory for UPI refund processing.");
                }
            }

            // Generate authoritative final refund number if needed
            $finalRefundNumber = 'REF-' . date('YmdHis') . '-' . strtoupper(Str::random(4));

            $refund->update([
                'refund_number' => $finalRefundNumber,
                'refund_method' => $refundMethod,
                'bank_account_id' => $bankAccountId,
                'cash_session_id' => $cashSessionId,
                'reference_number' => $referenceNumber,
                'processed_by' => $user->id,
                'processed_at' => Carbon::now(),
                'status' => 'PROCESSED',
            ]);

            // Determine if bill is now fully refunded
            $newRetainedAmount = max(0.00, round($remainingRefundable - (float) $refund->refund_amount, 2));

            if ($refund->consultation_bill_id) {
                $cBill = ConsultationBill::find($refund->consultation_bill_id);
                if ($cBill && $newRetainedAmount <= 0.00) {
                    $cBill->update(['status' => 'REFUNDED']);
                }

                // Realtime Live Queue refresh on refund (Section 45)
                $appointment = $cBill?->appointment ?? $cBill?->visit?->appointment;
                if ($appointment) {
                    DB::afterCommit(function () use ($appointment) {
                        event(\App\Events\QueueUpdated::fromAppointment($appointment, 'PAYMENT_REFUNDED'));
                    });
                }
            } elseif ($refund->diagnostic_bill_id) {
                $dBill = DiagnosticBill::find($refund->diagnostic_bill_id);
                if ($dBill && $newRetainedAmount <= 0.00) {
                    $dBill->update(['status' => 'REFUNDED']);
                }
            }

            // 1. Post balanced double-entry accounting journal
            $this->createAccountingJournalForRefund($refund);

            // 2. Create ReceiptRevision if a receipt exists
            $this->createReceiptRevisionForRefund($refund, $user, $newRetainedAmount);

            // 3. Log comprehensive audit record
            AuditLog::log(
                action: 'REFUND_PROCESSED',
                entityType: Refund::class,
                entityId: $refund->id,
                newValues: $refund->fresh()->toArray(),
                oldValues: ['status' => 'REQUESTED'],
                reason: $refund->reason,
                changeCategory: 'REFUND',
                financialImpact: "Processed refund of ₹" . number_format($refund->refund_amount, 2) . " via {$refundMethod}. Net retained: ₹" . number_format($newRetainedAmount, 2),
                linkedRefundId: $refund->id
            );

            return $refund->fresh();
        });
    }

    /**
     * Direct atomic refund creation & settlement (for authorized Administrator)
     */
    public function processDirectRefund(array $data, User $user): Refund
    {
        if (!$user->isAdmin()) {
            throw new AuthorizationException("Only an Administrator can directly process and settle refunds.");
        }

        $refund = $this->requestRefund($data, $user);
        $this->approveRefund($refund, $user);
        return $this->processRefund($refund, $user, $data);
    }

    /**
     * Create balanced double-entry journal for processed refund
     *
     * CASH Consultation: Dr 4001 Consultation Income, Cr 1001 Cash
     * UPI Consultation: Dr 4001 Consultation Income, Cr Bank Account Ledger
     * CASH Diagnostic: Dr 4002 Diagnostic Income, Cr 1001 Cash
     * UPI Diagnostic: Dr 4002 Diagnostic Income, Cr Bank Account Ledger
     */
    protected function createAccountingJournalForRefund(Refund $refund): Journal
    {
        // 1. Resolve Revenue Reversal Account (Debit)
        $isConsultation = !empty($refund->consultation_bill_id);
        $incomeCode = $isConsultation ? '4001' : '4002';
        $incomeName = $isConsultation ? 'Consultation Income' : 'Diagnostic Income';

        $debitLedger = LedgerAccount::firstOrCreate(
            ['code' => $incomeCode],
            [
                'name' => $incomeName,
                'type' => 'INCOME',
                'subtype' => 'OPERATING_REVENUE',
                'is_system' => true,
            ]
        );

        // 2. Resolve Payment Settlement Account (Credit: Cash or Bank)
        if ($refund->refund_method === 'CASH') {
            $creditLedger = LedgerAccount::firstOrCreate(
                ['code' => '1001'],
                [
                    'name' => 'Cash in Hand',
                    'type' => 'ASSET',
                    'subtype' => 'CURRENT_ASSET',
                    'is_system' => true,
                ]
            );
            $desc = "Cash refund for {$refund->refund_number} (Ref original payment #{$refund->originalPayment->payment_number})";
        } else {
            $bankAccount = BankAccount::findOrFail($refund->bank_account_id);
            $creditLedger = $bankAccount->ledgerAccount;
            $desc = "UPI refund from {$bankAccount->bank_name} for {$refund->refund_number} (Ref: {$refund->reference_number})";
        }

        $journalNumber = 'JRN-REF-' . date('YmdHis') . '-' . strtoupper(Str::random(4));
        $journal = Journal::create([
            'journal_number' => $journalNumber,
            'date' => Carbon::now()->toDateString(),
            'description' => $desc,
            'reference_type' => Refund::class,
            'reference_id' => $refund->id,
        ]);

        // Dr Income (Reversal of revenue)
        JournalEntry::create([
            'journal_id' => $journal->id,
            'ledger_account_id' => $debitLedger->id,
            'debit' => $refund->refund_amount,
            'credit' => 0.00,
            'notes' => 'Dr Revenue Reversal on refund',
        ]);

        // Cr Cash / Bank (Reduction of asset)
        JournalEntry::create([
            'journal_id' => $journal->id,
            'ledger_account_id' => $creditLedger->id,
            'debit' => 0.00,
            'credit' => $refund->refund_amount,
            'notes' => 'Cr Asset on refund disbursement',
        ]);

        if (!$journal->isBalanced()) {
            throw new \RuntimeException("Refund accounting journal is unbalanced.");
        }

        return $journal;
    }

    /**
     * Create an immutable ReceiptRevision row
     */
    protected function createReceiptRevisionForRefund(Refund $refund, User $user, float $newRetainedAmount): ?ReceiptRevision
    {
        $receipt = Receipt::where('payment_id', $refund->original_payment_id)->first();
        if (!$receipt) {
            return null;
        }

        $maxRevision = (int) ReceiptRevision::where('receipt_id', $receipt->id)->max('revision_number');
        $revisionNumber = $maxRevision + 1;

        $previousRetained = (float) $receipt->amount - (float) Refund::where('original_payment_id', $refund->original_payment_id)
            ->where('status', 'PROCESSED')
            ->where('id', '!=', $refund->id)
            ->sum('refund_amount');

        return ReceiptRevision::create([
            'receipt_id' => $receipt->id,
            'revision_number' => $revisionNumber,
            'old_values' => [
                'receipt_amount' => (float) $receipt->amount,
                'previously_retained' => $previousRetained,
                'status' => 'SETTLED',
            ],
            'new_values' => [
                'receipt_amount' => (float) $receipt->amount,
                'refund_amount' => (float) $refund->refund_amount,
                'refund_method' => $refund->refund_method,
                'refund_number' => $refund->refund_number,
                'net_retained' => $newRetainedAmount,
            ],
            'reason' => $refund->reason,
            'user_id' => $user->id,
            'financial_impact' => "Refund of ₹" . number_format($refund->refund_amount, 2) . " via {$refund->refund_method}. Effective retained amount: ₹" . number_format($newRetainedAmount, 2),
            'linked_refund_id' => $refund->id,
            'created_at' => Carbon::now(),
        ]);
    }
}
