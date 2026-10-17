<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use Carbon\Carbon;

class BankLedgerService
{
    /**
     * Get Book Bank Balance derived authoritatively from the linked ledger account (Section 19, 35, 80)
     * For Asset accounts: Balance = Debits - Credits
     */
    public function getBookBalance(BankAccount $bankAccount): float
    {
        return $bankAccount->book_balance;
    }

    /**
     * Get all configured bank accounts with their current Book Bank Balances
     */
    public function getAllBankBalances(Clinic $clinic): array
    {
        $accounts = BankAccount::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->with('ledgerAccount')
            ->get();

        return $accounts->map(function (BankAccount $acc) {
            return [
                'id' => $acc->id,
                'bank_name' => $acc->bank_name,
                'account_number' => $acc->account_number,
                'upi_id' => $acc->upi_id,
                'ledger_account_id' => $acc->ledger_account_id,
                'book_balance' => $acc->book_balance,
            ];
        })->toArray();
    }

    /**
     * Get bank movements (UPI receipts, cash deposits, bank expenses) for a specific cash session
     */
    public function getBankMovementsForSession(BankAccount $bankAccount, CashSession $session): array
    {
        $upiReceived = (float) $session->payments()
            ->where('bank_account_id', $bankAccount->id)
            ->where('payment_method', 'UPI')
            ->where('status', 'SETTLED')
            ->sum('amount');

        $cashDeposits = (float) $session->fundTransfers()
            ->where('to_ledger_account_id', $bankAccount->ledger_account_id)
            ->sum('amount');

        $bankExpenses = (float) $session->expenses()
            ->where('bank_account_id', $bankAccount->id)
            ->where('payment_method', 'BANK')
            ->sum('amount');

        return [
            'upi_received' => $upiReceived,
            'cash_deposited' => $cashDeposits,
            'bank_expenses' => $bankExpenses,
            'net_movement' => round($upiReceived + $cashDeposits - $bankExpenses, 2),
        ];
    }
}
