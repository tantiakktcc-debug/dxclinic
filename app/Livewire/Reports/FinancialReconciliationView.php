<?php

namespace App\Livewire\Reports;

use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\Expense;
use App\Models\FundTransfer;
use App\Models\JournalEntry;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FinancialReconciliationView extends Component
{
    public ?Clinic $clinic = null;

    // Filter controls
    public string $period = 'daily'; // 'daily', 'weekly', 'monthly', 'quarterly', 'half_yearly', 'fy', 'custom'
    public string $startDate = '';
    public string $endDate = '';
    public string $sessionFilter = 'ALL'; // 'ALL', 'morning', 'evening', 'single'
    public string $activeDrilldown = 'summary'; // 'summary', 'payments', 'expenses', 'transfers', 'journals', 'sessions'

    public function mount(): void
    {
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
        $this->startDate = Carbon::today()->toDateString();
        $this->endDate = Carbon::today()->toDateString();
        $this->applyPeriod('daily');
    }

    public function updatedPeriod(string $val): void
    {
        $this->applyPeriod($val);
    }

    public function applyPeriod(string $period): void
    {
        $today = Carbon::today();
        $this->period = $period;

        switch ($period) {
            case 'daily':
                $this->startDate = $today->toDateString();
                $this->endDate = $today->toDateString();
                break;
            case 'weekly':
                $this->startDate = $today->copy()->startOfWeek()->toDateString();
                $this->endDate = $today->copy()->endOfWeek()->toDateString();
                break;
            case 'monthly':
                $this->startDate = $today->copy()->startOfMonth()->toDateString();
                $this->endDate = $today->copy()->endOfMonth()->toDateString();
                break;
            case 'quarterly':
                $this->startDate = $today->copy()->firstOfQuarter()->toDateString();
                $this->endDate = $today->copy()->lastOfQuarter()->toDateString();
                break;
            case 'half_yearly':
                if ($today->month >= 4 && $today->month <= 9) {
                    $this->startDate = Carbon::create($today->year, 4, 1)->toDateString();
                    $this->endDate = Carbon::create($today->year, 9, 30)->toDateString();
                } else {
                    $startYear = $today->month < 4 ? $today->year - 1 : $today->year;
                    $this->startDate = Carbon::create($startYear, 10, 1)->toDateString();
                    $this->endDate = Carbon::create($startYear + 1, 3, 31)->toDateString();
                }
                break;
            case 'fy':
                // Financial Year: April 1 to March 31 (Section 25)
                $fyStartYear = $today->month >= 4 ? $today->year : $today->year - 1;
                $this->startDate = Carbon::create($fyStartYear, 4, 1)->toDateString();
                $this->endDate = Carbon::create($fyStartYear + 1, 3, 31)->toDateString();
                break;
            case 'custom':
                // User enters dates manually
                break;
        }
    }

    public function setDrilldown(string $tab): void
    {
        $this->activeDrilldown = $tab;
    }

    public function render()
    {
        $clinicId = $this->clinic?->id ?? 1;

        // Base date range query
        $dateStart = $this->startDate ?: Carbon::today()->toDateString();
        $dateEnd = $this->endDate ?: Carbon::today()->toDateString();

        // 1. Payments Query
        $paymentsQuery = Payment::with(['consultationBill', 'diagnosticBill', 'cashSession', 'bankAccount'])
            ->where('clinic_id', $clinicId)
            ->where('status', 'SETTLED')
            ->whereBetween(DB::raw('DATE(COALESCE(settled_at, created_at))'), [$dateStart, $dateEnd]);

        if ($this->sessionFilter !== 'ALL') {
            $paymentsQuery->whereHas('cashSession', function ($q) {
                $q->where('session_type', $this->sessionFilter);
            });
        }
        $payments = $paymentsQuery->get();

        $cashCollection = (float) $payments->where('payment_method', 'CASH')->sum('amount');
        $upiCollection = (float) $payments->where('payment_method', 'UPI')->sum('amount');
        $totalCollection = $cashCollection + $upiCollection;

        // 2. Revenue Bills (Consultation & Diagnostic)
        $consultationBills = ConsultationBill::where('clinic_id', $clinicId)
            ->whereBetween('bill_date', [$dateStart, $dateEnd])
            ->get();
        $consultationRevenue = (float) $consultationBills->sum('net_amount');
        $consultationDiscounts = (float) $consultationBills->sum('discount_amount');
        $consultationWaivers = (float) $consultationBills->where('is_grace', true)->sum('gross_amount');

        $diagnosticBills = DiagnosticBill::where('clinic_id', $clinicId)
            ->whereBetween('bill_date', [$dateStart, $dateEnd])
            ->get();
        $diagnosticRevenue = (float) $diagnosticBills->sum('net_amount');
        $diagnosticDiscounts = (float) $diagnosticBills->sum('discount_amount');

        $totalRevenue = $consultationRevenue + $diagnosticRevenue;
        $totalDiscounts = $consultationDiscounts + $diagnosticDiscounts;
        $totalWaivers = $consultationWaivers;

        // 3. Expenses Query
        $expensesQuery = Expense::with(['expenseCategory', 'cashSession', 'bankAccount'])
            ->where('clinic_id', $clinicId)
            ->whereBetween('expense_date', [$dateStart, $dateEnd]);

        if ($this->sessionFilter !== 'ALL') {
            $expensesQuery->whereHas('cashSession', function ($q) {
                $q->where('session_type', $this->sessionFilter);
            });
        }
        $expenses = $expensesQuery->get();

        $cashExpenses = (float) $expenses->where('payment_method', 'CASH')->sum('amount');
        $bankExpenses = (float) $expenses->where('payment_method', 'BANK')->sum('amount');
        $totalExpenses = $cashExpenses + $bankExpenses;

        // 4. Fund Transfers (Cash Deposits to Bank)
        $transfersQuery = FundTransfer::with(['fromLedgerAccount', 'toLedgerAccount', 'cashSession'])
            ->where('clinic_id', $clinicId)
            ->whereBetween('transfer_date', [$dateStart, $dateEnd]);

        if ($this->sessionFilter !== 'ALL') {
            $transfersQuery->whereHas('cashSession', function ($q) {
                $q->where('session_type', $this->sessionFilter);
            });
        }
        $transfers = $transfersQuery->get();
        $totalCashDeposited = (float) $transfers->sum('amount');

        // 5. Cash Sessions within period
        $sessionsQuery = CashSession::where('clinic_id', $clinicId)
            ->whereBetween('session_date', [$dateStart, $dateEnd]);

        if ($this->sessionFilter !== 'ALL') {
            $sessionsQuery->where('session_type', $this->sessionFilter);
        }
        $sessions = $sessionsQuery->orderBy('session_date')->orderBy('id')->get();

        $openingPhysicalCash = (float) ($sessions->first()?->opening_cash ?? 0.0);
        $closingPhysicalCash = (float) ($sessions->where('status', 'CLOSED')->last()?->closing_cash ?? 0.0);

        // Expected Cash Formula (Section 22, 72, 104):
        // Expected Closing Cash = Opening Physical Cash + Cash Collection - Cash Expenses - Cash Deposits
        $expectedClosingCash = $openingPhysicalCash + $cashCollection - $cashExpenses - $totalCashDeposited;
        $cashVariance = $closingPhysicalCash > 0 ? ($closingPhysicalCash - $expectedClosingCash) : 0.0;
        $isCashBalanced = abs($cashVariance) < 0.01;

        // 6. Double-Entry General Ledger Equilibrium (Section 2, 71, 102)
        $journalTotals = DB::table('journal_entries')
            ->selectRaw('SUM(debit) as total_debits, SUM(credit) as total_credits')
            ->first();

        $totalDebits = (float) ($journalTotals->total_debits ?? 0.0);
        $totalCredits = (float) ($journalTotals->total_credits ?? 0.0);
        $ledgerDifference = abs($totalDebits - $totalCredits);
        $isLedgerBalanced = $ledgerDifference < 0.01;

        // 7. Bank Accounts Book Balances
        $bankAccounts = BankAccount::where('clinic_id', $clinicId)
            ->with('ledgerAccount')
            ->get();
        $totalBookBankBalance = (float) $bankAccounts->sum('book_balance');

        // Net Operating Surplus
        $operatingSurplus = $totalRevenue - $totalExpenses;

        return view('livewire.reports.financial-reconciliation-view', [
            'cashCollection' => $cashCollection,
            'upiCollection' => $upiCollection,
            'totalCollection' => $totalCollection,
            'consultationRevenue' => $consultationRevenue,
            'diagnosticRevenue' => $diagnosticRevenue,
            'totalRevenue' => $totalRevenue,
            'totalDiscounts' => $totalDiscounts,
            'totalWaivers' => $totalWaivers,
            'cashExpenses' => $cashExpenses,
            'bankExpenses' => $bankExpenses,
            'totalExpenses' => $totalExpenses,
            'totalCashDeposited' => $totalCashDeposited,
            'openingPhysicalCash' => $openingPhysicalCash,
            'closingPhysicalCash' => $closingPhysicalCash,
            'expectedClosingCash' => $expectedClosingCash,
            'cashVariance' => $cashVariance,
            'isCashBalanced' => $isCashBalanced,
            'totalDebits' => $totalDebits,
            'totalCredits' => $totalCredits,
            'ledgerDifference' => $ledgerDifference,
            'isLedgerBalanced' => $isLedgerBalanced,
            'bankAccounts' => $bankAccounts,
            'totalBookBankBalance' => $totalBookBankBalance,
            'operatingSurplus' => $operatingSurplus,
            'payments' => $payments,
            'expenses' => $expenses,
            'transfers' => $transfers,
            'sessions' => $sessions,
        ])->layout('components.layouts.app', ['title' => 'Financial Reconciliation']);
    }
}
