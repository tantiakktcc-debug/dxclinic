<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        protected ReceiptService $receiptService
    ) {}

    /**
     * Settle payment for a Consultation Bill (Section 58, 59, 78)
     */
    public function settleConsultationPayment(ConsultationBill $bill, array $data): Payment
    {
        return DB::transaction(function () use ($bill, $data) {
            $bill = ConsultationBill::where('id', $bill->id)->lockForUpdate()->first();

            // Idempotency check: if bill already paid, return existing payment
            if ($bill->status === 'PAID') {
                $existingPayment = Payment::where('consultation_bill_id', $bill->id)
                    ->where('status', 'SETTLED')
                    ->first();
                if ($existingPayment) {
                    return $existingPayment;
                }
            }

            if ($bill->status === 'CANCELLED') {
                throw new InvalidArgumentException("Cannot pay a cancelled consultation bill.");
            }

            $paymentMethod = strtoupper(trim($data['payment_method'] ?? 'CASH'));
            $amount = (float) ($data['amount'] ?? $bill->net_amount);
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey) {
                $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            // Resolve Cash Session
            $cashSessionId = $data['cash_session_id'] ?? null;
            if (!$cashSessionId) {
                $activeSession = CashSession::where('clinic_id', $bill->clinic_id)
                    ->where('status', 'OPEN')
                    ->latest('id')
                    ->first();
                $cashSessionId = $activeSession?->id;
            }

            $bankAccountId = null;
            $soundboxConfirmed = false;

            if ($paymentMethod === 'UPI') {
                // Section 58: Manual UPI requires soundbox confirmation & bank account selection
                if (empty($data['bank_account_id'])) {
                    throw new InvalidArgumentException("Bank account selection is required for UPI payments.");
                }
                $bankAccountId = (int) $data['bank_account_id'];
                $bankAccount = BankAccount::findOrFail($bankAccountId);
                $soundboxConfirmed = (bool) ($data['soundbox_confirmed'] ?? false);
                if (!$soundboxConfirmed) {
                    throw new InvalidArgumentException("Physical Soundbox confirmation is required to settle UPI payment (Section 58).");
                }
            }

            $paymentNumber = 'PAY-C-' . date('YmdHis') . '-' . strtoupper(Str::random(4));

            $payment = Payment::create([
                'clinic_id' => $bill->clinic_id,
                'payment_number' => $paymentNumber,
                'cash_session_id' => $cashSessionId,
                'consultation_bill_id' => $bill->id,
                'diagnostic_bill_id' => null,
                'payment_method' => $paymentMethod,
                'bank_account_id' => $bankAccountId,
                'amount' => $amount,
                'status' => 'SETTLED',
                'soundbox_confirmed' => $soundboxConfirmed,
                'reference_number' => $data['reference_number'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'settled_at' => Carbon::now(),
            ]);

            $bill->update(['status' => 'PAID']);

            // Create balanced double-entry Journal (Section 58, 59, 74)
            $this->createAccountingJournalForPayment($payment, '4001', 'Consultation Income');

            // Generate receipt (Section 60)
            $this->receiptService->generateReceipt($payment, 'CONSULTATION', $bill->patient_id);

            // Realtime Live Queue refresh on payment settlement (Section 45)
            $appointment = $bill->appointment ?? $bill->visit?->appointment;
            if ($appointment) {
                DB::afterCommit(function () use ($appointment) {
                    event(\App\Events\QueueUpdated::fromAppointment($appointment, 'PAYMENT_SETTLED'));
                });
            }

            return $payment;
        });
    }

    /**
     * Settle payment for a Diagnostic Bill (Section 68, 78)
     */
    public function settleDiagnosticPayment(DiagnosticBill $bill, array $data): Payment
    {
        return DB::transaction(function () use ($bill, $data) {
            $bill = DiagnosticBill::where('id', $bill->id)->lockForUpdate()->first();

            // Idempotency check: if bill already paid, return existing payment
            if ($bill->status === 'PAID') {
                $existingPayment = Payment::where('diagnostic_bill_id', $bill->id)
                    ->where('status', 'SETTLED')
                    ->first();
                if ($existingPayment) {
                    return $existingPayment;
                }
            }

            if ($bill->status === 'CANCELLED') {
                throw new InvalidArgumentException("Cannot pay a cancelled diagnostic bill.");
            }

            $paymentMethod = strtoupper(trim($data['payment_method'] ?? 'CASH'));
            $amount = (float) ($data['amount'] ?? $bill->net_amount);
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey) {
                $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $cashSessionId = $data['cash_session_id'] ?? null;
            if (!$cashSessionId) {
                $activeSession = CashSession::where('clinic_id', $bill->clinic_id)
                    ->where('status', 'OPEN')
                    ->latest('id')
                    ->first();
                $cashSessionId = $activeSession?->id;
            }

            $bankAccountId = null;
            $soundboxConfirmed = false;

            if ($paymentMethod === 'UPI') {
                if (empty($data['bank_account_id'])) {
                    throw new InvalidArgumentException("Bank account selection is required for UPI payments.");
                }
                $bankAccountId = (int) $data['bank_account_id'];
                $bankAccount = BankAccount::findOrFail($bankAccountId);
                $soundboxConfirmed = (bool) ($data['soundbox_confirmed'] ?? false);
                if (!$soundboxConfirmed) {
                    throw new InvalidArgumentException("Physical Soundbox confirmation is required to settle UPI payment (Section 58, 68).");
                }
            }

            $paymentNumber = 'PAY-D-' . date('YmdHis') . '-' . strtoupper(Str::random(4));

            $payment = Payment::create([
                'clinic_id' => $bill->clinic_id,
                'payment_number' => $paymentNumber,
                'cash_session_id' => $cashSessionId,
                'consultation_bill_id' => null,
                'diagnostic_bill_id' => $bill->id,
                'payment_method' => $paymentMethod,
                'bank_account_id' => $bankAccountId,
                'amount' => $amount,
                'status' => 'SETTLED',
                'soundbox_confirmed' => $soundboxConfirmed,
                'reference_number' => $data['reference_number'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'settled_at' => Carbon::now(),
            ]);

            $bill->update(['status' => 'PAID']);

            // Ensure downstream laboratory order exists and is ready for sample collection (Revision 36)
            try {
                app(\App\Services\DiagnosticLabService::class)->createOrderFromBill($bill);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Diagnostic lab order sync on payment settlement: " . $e->getMessage());
            }

            // Create balanced double-entry Journal (Section 68, 74)
            $this->createAccountingJournalForPayment($payment, '4002', 'Diagnostic Income');

            // Generate receipt (Section 69)
            $this->receiptService->generateReceipt($payment, 'DIAGNOSTIC', $bill->patient_id);

            return $payment;
        });
    }

    /**
     * Create double-entry journal for settled payment
     */
    protected function createAccountingJournalForPayment(Payment $payment, string $incomeLedgerCode, string $incomeLedgerName): Journal
    {
        // 1. Resolve Income Account
        $incomeLedger = LedgerAccount::firstOrCreate(
            ['code' => $incomeLedgerCode],
            [
                'name' => $incomeLedgerName,
                'type' => 'INCOME',
                'subtype' => 'OPERATING_REVENUE',
                'is_system' => true,
            ]
        );

        // 2. Resolve Debit Account (Cash 1001 or Bank ledger)
        if ($payment->payment_method === 'CASH') {
            $debitLedger = LedgerAccount::firstOrCreate(
                ['code' => '1001'],
                [
                    'name' => 'Cash in Hand',
                    'type' => 'ASSET',
                    'subtype' => 'CURRENT_ASSET',
                    'is_system' => true,
                ]
            );
            $desc = "Cash payment for {$payment->payment_number}";
        } else {
            $bankAccount = $payment->bankAccount;
            $debitLedger = $bankAccount->ledgerAccount;
            $desc = "UPI payment into {$bankAccount->bank_name} for {$payment->payment_number}";
        }

        $journalNumber = 'JRN-' . date('YmdHis') . '-' . strtoupper(Str::random(4));
        $journal = Journal::create([
            'journal_number' => $journalNumber,
            'date' => Carbon::now()->toDateString(),
            'description' => $desc,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);

        // Dr Cash / Bank
        JournalEntry::create([
            'journal_id' => $journal->id,
            'ledger_account_id' => $debitLedger->id,
            'debit' => $payment->amount,
            'credit' => 0.00,
            'notes' => 'Dr Asset on payment receipt',
        ]);

        // Cr Income
        JournalEntry::create([
            'journal_id' => $journal->id,
            'ledger_account_id' => $incomeLedger->id,
            'debit' => 0.00,
            'credit' => $payment->amount,
            'notes' => 'Cr Income on payment receipt',
        ]);

        if (!$journal->isBalanced()) {
            throw new \RuntimeException("Payment journal is unbalanced.");
        }

        return $journal;
    }
}
