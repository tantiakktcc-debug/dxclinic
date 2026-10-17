<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\Expense;
use App\Models\FundTransfer;
use App\Models\Payment;
use Carbon\Carbon;

class CashReconciliationService
{
    public function __construct(
        protected BankLedgerService $bankLedgerService
    ) {}

    /**
     * Generate daily aggregated summary for a clinic and date (Section 38, 82)
     */
    public function getDailyReport(Clinic $clinic, Carbon|string $date): array
    {
        $dt = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();

        $sessions = CashSession::where('clinic_id', $clinic->id)
            ->where('session_date', $dt)
            ->orderBy('id')
            ->get();

        // 1. Consultation Analytics
        $appointments = Appointment::where('clinic_id', $clinic->id)
            ->where('appointment_date', $dt)
            ->get();

        $walkinCount = $appointments->where('booking_source', 'CLINIC')->count();
        $onlineCount = $appointments->where('booking_source', 'ONLINE')->count();
        $telephonicCount = $appointments->where('booking_source', 'TELEPHONIC')->count();
        $totalConsultations = $appointments->count();

        $consultationPayments = Payment::where('clinic_id', $clinic->id)
            ->whereNotNull('consultation_bill_id')
            ->where('status', 'SETTLED')
            ->whereDate('settled_at', $dt)
            ->get();

        $consultationCash = (float) $consultationPayments->where('payment_method', 'CASH')->sum('amount');
        $consultationUpi = (float) $consultationPayments->where('payment_method', 'UPI')->sum('amount');

        // 2. Diagnostic Analytics
        $diagnosticBills = DiagnosticBill::where('clinic_id', $clinic->id)
            ->where('bill_date', $dt)
            ->get();
        $diagnosticCount = $diagnosticBills->count();

        $diagnosticPayments = Payment::where('clinic_id', $clinic->id)
            ->whereNotNull('diagnostic_bill_id')
            ->where('status', 'SETTLED')
            ->whereDate('settled_at', $dt)
            ->get();

        $diagnosticCash = (float) $diagnosticPayments->where('payment_method', 'CASH')->sum('amount');
        $diagnosticUpi = (float) $diagnosticPayments->where('payment_method', 'UPI')->sum('amount');

        // 3. Expenses
        $expenses = Expense::where('clinic_id', $clinic->id)
            ->where('expense_date', $dt)
            ->get();
        $cashExpenses = (float) $expenses->where('payment_method', 'CASH')->sum('amount');
        $bankExpenses = (float) $expenses->where('payment_method', 'BANK')->sum('amount');
        $totalExpenses = $cashExpenses + $bankExpenses;

        // 4. Cash Deposited to Bank
        $cashDeposits = (float) FundTransfer::where('clinic_id', $clinic->id)
            ->where('transfer_date', $dt)
            ->sum('amount');

        // 5. Total Daily Collections
        $totalCashCollection = $consultationCash + $diagnosticCash;
        $totalUpiCollection = $consultationUpi + $diagnosticUpi;

        // 5.1 Processed Refunds
        $cashRefunds = (float) \App\Models\Refund::where('clinic_id', $clinic->id)
            ->where('refund_method', 'CASH')
            ->where('status', 'PROCESSED')
            ->whereDate('processed_at', $dt)
            ->sum('refund_amount');

        $upiRefunds = (float) \App\Models\Refund::where('clinic_id', $clinic->id)
            ->where('refund_method', 'UPI')
            ->where('status', 'PROCESSED')
            ->whereDate('processed_at', $dt)
            ->sum('refund_amount');

        // 6. Cash Control aggregation (Section 30: Expected Closing Cash = Opening + Collections - Expenses - Deposits - Cash Refunds)
        $firstSession = $sessions->first();
        $lastSession = $sessions->last();

        $openingCash = $firstSession ? (float) $firstSession->opening_physical_cash : 0.00;
        $expectedClosingCash = round($openingCash + $totalCashCollection - $cashExpenses - $cashDeposits - $cashRefunds, 2);

        $physicalClosingCash = ($lastSession && $lastSession->closing_physical_cash !== null)
            ? (float) $lastSession->closing_physical_cash
            : null;

        $cashVariance = $physicalClosingCash !== null
            ? round($physicalClosingCash - $expectedClosingCash, 2)
            : 0.00;

        // 7. Bank Accounts Summary
        $bankAccounts = $this->bankLedgerService->getAllBankBalances($clinic);
        $totalOpeningBankBalance = (float) array_sum(array_column($bankAccounts, 'book_balance'));

        $morningSession = $sessions->firstWhere('session_type', 'MORNING');
        $eveningSession = $sessions->firstWhere('session_type', 'EVENING');

        return [
            'date' => $dt,
            'total_opening_bank_balance' => $totalOpeningBankBalance,
            'morning_breakdown' => [
                'cash_in_hand' => $morningSession ? (float) $morningSession->opening_physical_cash : 0.00,
                'upi_collection' => $morningSession ? (float) $morningSession->actual_upi_collection : 0.00,
                'expenses' => $morningSession ? (float) $morningSession->actual_cash_expenses : 0.00,
                'bank_deposit' => $morningSession ? (float) $morningSession->actual_bank_cash_deposit : 0.00,
                'closing_cash' => $morningSession?->closing_physical_cash !== null ? (float) $morningSession->closing_physical_cash : null,
            ],
            'evening_breakdown' => [
                'cash_in_hand' => $eveningSession ? (float) $eveningSession->opening_physical_cash : null,
                'upi_collection' => $eveningSession ? (float) $eveningSession->actual_upi_collection : 0.00,
                'expenses' => $eveningSession ? (float) $eveningSession->actual_cash_expenses : 0.00,
                'bank_deposit' => $eveningSession ? (float) $eveningSession->actual_bank_cash_deposit : 0.00,
                'closing_cash' => $eveningSession?->closing_physical_cash !== null ? (float) $eveningSession->closing_physical_cash : null,
            ],
            'sessions' => $sessions->map(fn($s) => [
                'session_id' => $s->id,
                'session_type' => $s->session_type,
                'status' => $s->status,
                'opening_physical_cash' => (float) $s->opening_physical_cash,
                'expected_closing_cash' => (float) $s->expected_closing_cash,
                'closing_physical_cash' => $s->closing_physical_cash !== null ? (float) $s->closing_physical_cash : null,
                'variance' => (float) $s->cash_variance,
                'variance_reason' => $s->variance_reason,
            ])->toArray(),
            'consultation' => [
                'walkin' => $walkinCount,
                'online' => $onlineCount,
                'telephonic' => $telephonicCount,
                'total' => $totalConsultations,
                'cash_collection' => $consultationCash,
                'upi_collection' => $consultationUpi,
                'total_collection' => $consultationCash + $consultationUpi,
            ],
            'diagnostics' => [
                'count' => $diagnosticCount,
                'cash_collection' => $diagnosticCash,
                'upi_collection' => $diagnosticUpi,
                'total_collection' => $diagnosticCash + $diagnosticUpi,
            ],
            'expenses' => [
                'cash_expenses' => $cashExpenses,
                'bank_expenses' => $bankExpenses,
                'total_expenses' => $totalExpenses,
            ],
            'cash_control' => [
                'opening_cash' => $openingCash,
                'cash_collections' => $totalCashCollection,
                'cash_expenses' => $cashExpenses,
                'cash_deposited_to_bank' => $cashDeposits,
                'cash_refunds' => $cashRefunds,
                'expected_closing_cash' => $expectedClosingCash,
                'physical_closing_cash' => $physicalClosingCash,
                'variance' => $cashVariance,
            ],
            'bank_control' => [
                'upi_collected' => $totalUpiCollection,
                'bank_cash_deposits' => $cashDeposits,
                'bank_expenses' => $bankExpenses,
                'upi_refunds' => $upiRefunds,
                'accounts' => $bankAccounts,
            ],
        ];
    }
}
