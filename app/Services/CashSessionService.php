<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\FundTransfer;
use App\Models\LedgerAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashSessionService
{
    public function __construct(
        protected ClinicScheduleService $clinicScheduleService,
        protected BankLedgerService $bankLedgerService
    ) {}

    /**
     * Get or suggest opening physical cash for a new session (Section 20, 27)
     */
    public function getSuggestedOpeningCash(Clinic $clinic, Carbon|string $date, string $sessionType): float
    {
        $dt = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();
        $sessionType = strtoupper($sessionType);

        if ($sessionType === 'EVENING') {
            // Evening opening cash normally equals morning session closing physical cash (Section 27)
            $morningSession = CashSession::where('clinic_id', $clinic->id)
                ->whereDate('session_date', $dt)
                ->where('session_type', 'MORNING')
                ->where('status', 'CLOSED')
                ->first();

            if ($morningSession && $morningSession->closing_physical_cash !== null) {
                return (float) $morningSession->closing_physical_cash;
            }
        }

        // For Morning or Sunday session, check previous day's closing session
        $previousSession = CashSession::where('clinic_id', $clinic->id)
            ->whereDate('session_date', '<', $dt)
            ->where('status', 'CLOSED')
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->first();

        if ($previousSession && $previousSession->closing_physical_cash !== null) {
            return (float) $previousSession->closing_physical_cash;
        }

        // For very first operational day, check Cash in Hand ledger balance (Section 20)
        $cashLedger = LedgerAccount::where('code', '1001')->first();
        if ($cashLedger) {
            return max(0.0, (float) $cashLedger->balance);
        }

        return 0.00;
    }

    /**
     * Open a cash session (Section 18, 20, 26, 27)
     */
    public function openSession(Clinic $clinic, Carbon|string $date, string $sessionType, float $openingPhysicalCash, ?User $user = null): CashSession
    {
        $dt = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();
        $sessionType = strtoupper(trim($sessionType));

        if (!$this->clinicScheduleService->isValidSession($clinic, $dt, $sessionType)) {
            throw new InvalidArgumentException("Session type '{$sessionType}' is not valid for date {$dt}.");
        }

        if ($openingPhysicalCash < 0) {
            throw new InvalidArgumentException("Opening physical cash cannot be negative.");
        }

        return DB::transaction(function () use ($clinic, $dt, $sessionType, $openingPhysicalCash, $user) {
            $existing = CashSession::where('clinic_id', $clinic->id)
                ->whereDate('session_date', $dt)
                ->where('session_type', $sessionType)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->status === 'OPEN') {
                    return $existing;
                }
                throw new InvalidArgumentException("Session {$sessionType} on {$dt} has already been closed.");
            }

            return CashSession::create([
                'clinic_id' => $clinic->id,
                'session_date' => $dt,
                'session_type' => $sessionType,
                'status' => 'OPEN',
                'opened_by' => $user?->id,
                'opened_at' => Carbon::now(),
                'opening_physical_cash' => $openingPhysicalCash,
                'expected_closing_cash' => $openingPhysicalCash,
            ]);
        });
    }

    /**
     * Close a cash session with physical cash count and mandatory variance reason (Section 25, 32, 33)
     */
    public function closeSession(CashSession $session, float $physicalClosingCash, ?string $varianceReason = null, ?User $user = null): CashSession
    {
        if ($physicalClosingCash < 0) {
            throw new InvalidArgumentException("Physical closing cash cannot be negative.");
        }

        return DB::transaction(function () use ($session, $physicalClosingCash, $varianceReason, $user) {
            $session = CashSession::where('id', $session->id)->lockForUpdate()->first();

            if ($session->status === 'CLOSED') {
                return $session; // Idempotent
            }

            // Derive authoritative expected cash from transactions (Section 16, 24, 31)
            $expectedCash = $session->computeExpectedClosingCash();
            $variance = round($physicalClosingCash - $expectedCash, 2);

            // Mandatory reason if variance is non-zero (Section 25, 33)
            if (abs($variance) >= 0.01 && empty(trim((string) $varianceReason))) {
                throw new InvalidArgumentException("Variance reason is REQUIRED when cash variance is non-zero (Variance: ₹{$variance}).");
            }

            $session->update([
                'status' => 'CLOSED',
                'closed_by' => $user?->id,
                'closed_at' => Carbon::now(),
                'expected_closing_cash' => $expectedCash,
                'closing_physical_cash' => $physicalClosingCash,
                'cash_variance' => $variance,
                'variance_reason' => abs($variance) >= 0.01 ? trim((string) $varianceReason) : null,
            ]);

            return $session->fresh();
        });
    }

    /**
     * Record Cash to Bank deposit (fund transfer) within a session (Section 15, 23, 30, 71)
     */
    public function recordCashToBankDeposit(CashSession $session, int $bankAccountId, float $amount, ?string $referenceNumber = null, ?string $notes = null): FundTransfer
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Deposit amount must be greater than zero.");
        }

        $bankAccount = $session->clinic->bankAccounts()->findOrFail($bankAccountId);
        $cashLedger = LedgerAccount::where('code', '1001')->firstOrFail();

        return DB::transaction(function () use ($session, $bankAccount, $cashLedger, $amount, $referenceNumber, $notes) {
            // Create FundTransfer record
            $transfer = FundTransfer::create([
                'clinic_id' => $session->clinic_id,
                'cash_session_id' => $session->id,
                'from_ledger_account_id' => $cashLedger->id,
                'to_ledger_account_id' => $bankAccount->ledger_account_id,
                'amount' => $amount,
                'transfer_date' => $session->session_date,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
            ]);

            // Create balanced double-entry Journal: Dr Bank, Cr Cash (Section 15, 71)
            $journalNumber = 'FT-' . date('Ymd') . '-' . rand(100, 999);
            $journal = \App\Models\Journal::create([
                'journal_number' => $journalNumber,
                'date' => $session->session_date,
                'description' => "Cash to Bank Deposit into {$bankAccount->bank_name} ({$bankAccount->account_number})",
                'reference_type' => FundTransfer::class,
                'reference_id' => $transfer->id,
            ]);

            \App\Models\JournalEntry::create([
                'journal_id' => $journal->id,
                'ledger_account_id' => $bankAccount->ledger_account_id,
                'debit' => $amount,
                'credit' => 0.00,
                'notes' => 'Dr Bank on cash deposit',
            ]);

            \App\Models\JournalEntry::create([
                'journal_id' => $journal->id,
                'ledger_account_id' => $cashLedger->id,
                'debit' => 0.00,
                'credit' => $amount,
                'notes' => 'Cr Cash on cash deposit',
            ]);

            if (!$journal->isBalanced()) {
                throw new \RuntimeException("Fund transfer journal is unbalanced.");
            }

            return $transfer;
        });
    }

    /**
     * Generate session summary report (Section 37)
     */
    public function getSessionSummary(CashSession $session): array
    {
        $expected = $session->status === 'CLOSED'
            ? (float) $session->expected_closing_cash
            : $session->computeExpectedClosingCash();

        $physical = $session->closing_physical_cash !== null ? (float) $session->closing_physical_cash : null;
        $variance = $physical !== null ? round($physical - $expected, 2) : 0.00;

        return [
            'session_id' => $session->id,
            'date' => $session->session_date->toDateString(),
            'type' => $session->session_type,
            'status' => $session->status,
            'opening_physical_cash' => (float) $session->opening_physical_cash,
            'cash_collection' => $session->actual_cash_receipts,
            'upi_collection' => $session->actual_upi_collection,
            'expenses' => $session->actual_cash_expenses,
            'cash_deposited_to_bank' => $session->actual_bank_cash_deposit,
            'expected_closing_cash' => $expected,
            'physical_closing_cash' => $physical,
            'variance' => $variance,
            'variance_reason' => $session->variance_reason,
        ];
    }
}
