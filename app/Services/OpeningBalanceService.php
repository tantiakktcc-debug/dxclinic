<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Clinic;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpeningBalanceService
{
    /**
     * Record clinic opening financial balances during onboarding (Section 5, 9, 10, 76)
     * Books opening balances strictly via Opening Balance Equity (3001).
     * NEVER creates fake income, fake payment, or fake receipt.
     */
    public function recordOpeningBalances(Clinic $clinic, float $openingCash, array $bankOpeningBalances, Carbon|string $date): Journal
    {
        $dt = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();

        if ($openingCash < 0) {
            throw new InvalidArgumentException("Opening cash cannot be negative.");
        }

        return DB::transaction(function () use ($clinic, $openingCash, $bankOpeningBalances, $dt) {
            // Get or create system Equity ledger: Opening Balance Equity (3001)
            $equityLedger = LedgerAccount::firstOrCreate(
                ['code' => '3001'],
                [
                    'name' => 'Opening Balance Equity',
                    'type' => 'EQUITY',
                    'subtype' => 'EQUITY',
                    'is_system' => true,
                    'description' => 'System equity account for opening balance offset',
                ]
            );

            // Get or create Cash In Hand ledger (1001)
            $cashLedger = LedgerAccount::firstOrCreate(
                ['code' => '1001'],
                [
                    'name' => 'Cash in Hand',
                    'type' => 'ASSET',
                    'subtype' => 'CURRENT_ASSET',
                    'is_system' => true,
                    'description' => 'Main cash in hand physical register',
                ]
            );

            $journalNumber = 'OB-' . date('Ymd', strtotime($dt)) . '-' . rand(100, 999);
            $journal = Journal::create([
                'journal_number' => $journalNumber,
                'date' => $dt,
                'description' => "Clinic Onboarding Opening Financial Balances for {$clinic->name}",
                'reference_type' => Clinic::class,
                'reference_id' => $clinic->id,
            ]);

            $totalDebits = 0.0;

            // 1. Debit Cash in Hand (if > 0)
            if ($openingCash > 0) {
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'ledger_account_id' => $cashLedger->id,
                    'debit' => $openingCash,
                    'credit' => 0.00,
                    'notes' => 'Opening Physical Cash in Hand',
                ]);
                $totalDebits += $openingCash;
            }

            // 2. Debit each Bank Account's linked ledger
            foreach ($bankOpeningBalances as $bankAccountId => $amount) {
                $bankAmount = (float) $amount;
                if ($bankAmount < 0) {
                    throw new InvalidArgumentException("Bank opening balance cannot be negative.");
                }

                if ($bankAmount > 0) {
                    $bankAccount = BankAccount::findOrFail($bankAccountId);
                    JournalEntry::create([
                        'journal_id' => $journal->id,
                        'ledger_account_id' => $bankAccount->ledger_account_id,
                        'debit' => $bankAmount,
                        'credit' => 0.00,
                        'notes' => "Opening Bank Balance for {$bankAccount->bank_name} ({$bankAccount->account_number})",
                    ]);
                    $totalDebits += $bankAmount;
                }
            }

            // 3. Credit Opening Balance Equity with the total opening assets
            if ($totalDebits > 0) {
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'ledger_account_id' => $equityLedger->id,
                    'debit' => 0.00,
                    'credit' => $totalDebits,
                    'notes' => 'Opening Balance Equity offset',
                ]);
            }

            if (!$journal->isBalanced()) {
                throw new \RuntimeException("Opening balance journal is unbalanced: total debits do not match credits.");
            }

            return $journal;
        });
    }
}
