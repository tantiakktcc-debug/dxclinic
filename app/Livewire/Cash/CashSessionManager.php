<?php

namespace App\Livewire\Cash;

use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundTransfer;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\BankLedgerService;
use App\Services\CashSessionService;
use App\Services\ClinicScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CashSessionManager extends Component
{
    public string $currentDate = '';
    public ?CashSession $activeSession = null;
    public ?Clinic $clinic = null;

    // Sub-navigation tabs (Rule 29: Current Session, Sessions History, Expenses, Bank Deposit)
    public string $currentTab = 'session'; 

    // Session opening form
    public string $openSessionType = 'MORNING';
    public float $openingPhysicalCash = 0.00;
    public string $sessionNotes = '';

    // Session closing form
    public ?float $physicalClosingCash = null;
    public string $varianceReason = '';

    // Cash to Bank Deposit modal/tab
    public bool $showDepositModal = false;
    public ?int $depositBankAccountId = null;
    public float $depositAmount = 0.00;
    public string $depositReference = '';
    public string $depositNotes = '';

    // Expense Entry modal/tab
    public bool $showExpenseModal = false;
    public ?int $expenseCategoryId = null;
    public float $expenseAmount = 0.00;
    public string $expensePaymentMethod = 'CASH'; // CASH or BANK
    public ?int $expenseBankAccountId = null;
    public string $expensePayee = '';
    public string $expenseNotes = '';

    public function mount(CashSessionService $cashService, ClinicScheduleService $scheduleService): void
    {
        $this->currentDate = Carbon::today()->toDateString();
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');

        $this->loadActiveSession($cashService, $scheduleService);

        $defaultBank = BankAccount::where('clinic_id', $this->clinic->id)->where('is_active', true)->first();
        if ($defaultBank) {
            $this->depositBankAccountId = $defaultBank->id;
            $this->expenseBankAccountId = $defaultBank->id;
        }

        $defaultCategory = ExpenseCategory::where('is_active', true)->orderBy('display_order')->first();
        if ($defaultCategory) {
            $this->expenseCategoryId = $defaultCategory->id;
        }
    }

    public function loadActiveSession(CashSessionService $cashService, ClinicScheduleService $scheduleService): void
    {
        if (!$this->clinic) return;

        // Find open session for today, or latest session
        $this->activeSession = CashSession::where('clinic_id', $this->clinic->id)
            ->whereDate('session_date', $this->currentDate)
            ->where('status', 'OPEN')
            ->first();

        if (!$this->activeSession) {
            // Suggest session type (Morning vs Evening vs Sunday Single)
            $availableSessions = $scheduleService->getSessionsForDate($this->clinic, $this->currentDate);
            $this->openSessionType = array_key_first($availableSessions) ?? 'MORNING';

            // Check if morning already closed today, then default to evening
            $morningSession = CashSession::where('clinic_id', $this->clinic->id)
                ->whereDate('session_date', $this->currentDate)
                ->where('session_type', 'MORNING')
                ->where('status', 'CLOSED')
                ->first();

            if ($morningSession && isset($availableSessions['EVENING'])) {
                $this->openSessionType = 'EVENING';
            }

            // Suggest opening physical cash (Section 20, 27)
            $this->openingPhysicalCash = $cashService->getSuggestedOpeningCash($this->clinic, $this->currentDate, $this->openSessionType);
        }
    }

    public function openSession(CashSessionService $cashService): void
    {
        if (!$this->clinic) return;

        try {
            $this->activeSession = $cashService->openSession(
                $this->clinic,
                $this->currentDate,
                $this->openSessionType,
                $this->openingPhysicalCash,
                auth()->user()
            );

            session()->flash('success', "{$this->openSessionType} cash session opened with ₹" . number_format($this->openingPhysicalCash, 2) . " opening cash.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeSession(CashSessionService $cashService): void
    {
        if (!$this->activeSession) return;

        if ($this->physicalClosingCash === null || $this->physicalClosingCash < 0) {
            session()->flash('error', 'Please enter a valid physical closing cash count.');
            return;
        }

        try {
            $this->activeSession = $cashService->closeSession(
                $this->activeSession,
                (float) $this->physicalClosingCash,
                $this->varianceReason,
                auth()->user()
            );

            session()->flash('success', "Session closed successfully. Variance: ₹" . number_format($this->activeSession->cash_variance, 2));
            $this->reset(['physicalClosingCash', 'varianceReason']);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function recordDeposit(CashSessionService $cashService): void
    {
        if (!$this->activeSession) {
            session()->flash('error', 'An active cash session is required to deposit cash.');
            return;
        }

        $this->validate([
            'depositBankAccountId' => 'required|exists:bank_accounts,id',
            'depositAmount' => 'required|numeric|min:1',
        ]);

        try {
            $transfer = $cashService->recordCashToBankDeposit(
                $this->activeSession,
                $this->depositBankAccountId,
                $this->depositAmount,
                $this->depositReference,
                $this->depositNotes
            );

            $this->showDepositModal = false;
            $this->reset(['depositAmount', 'depositReference', 'depositNotes']);
            $this->activeSession = $this->activeSession->fresh();

            session()->flash('success', "Bank cash deposit of ₹" . number_format($transfer->amount, 2) . " recorded successfully (Dr Bank, Cr Cash)!");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function recordExpense(): void
    {
        if (!$this->activeSession) {
            session()->flash('error', 'An active cash session is required to record expenses.');
            return;
        }

        $this->validate([
            'expenseCategoryId' => 'required|exists:expense_categories,id',
            'expenseAmount' => 'required|numeric|min:1',
            'expensePaymentMethod' => 'required|in:CASH,BANK',
            'expensePayee' => 'required|string|min:2|max:150',
        ]);

        try {
            DB::transaction(function () {
                $category = ExpenseCategory::with('ledgerAccount')->findOrFail($this->expenseCategoryId);

                // Create Expense record (Section 14)
                $expense = Expense::create([
                    'clinic_id' => $this->clinic->id,
                    'cash_session_id' => $this->activeSession->id,
                    'expense_category_id' => $category->id,
                    'amount' => $this->expenseAmount,
                    'payment_method' => $this->expensePaymentMethod,
                    'bank_account_id' => $this->expensePaymentMethod === 'BANK' ? $this->expenseBankAccountId : null,
                    'payee' => $this->expensePayee,
                    'notes' => $this->expenseNotes,
                    'expense_date' => $this->activeSession->session_date,
                ]);

                // Determine credit ledger (Cash 1001 or Bank ledger)
                if ($this->expensePaymentMethod === 'CASH') {
                    $creditLedger = LedgerAccount::where('code', '1001')->firstOrFail();
                    $desc = "Cash expense: {$this->expensePayee} ({$category->name})";
                } else {
                    $bankAccount = BankAccount::with('ledgerAccount')->findOrFail($this->expenseBankAccountId);
                    $creditLedger = $bankAccount->ledgerAccount;
                    $desc = "Bank payment expense: {$this->expensePayee} ({$category->name})";
                }

                // Balanced journal: Dr Expense, Cr Cash/Bank
                $journalNumber = 'EXP-' . date('Ymd') . '-' . rand(100, 999);
                $journal = Journal::create([
                    'journal_number' => $journalNumber,
                    'date' => $this->activeSession->session_date,
                    'description' => $desc,
                    'reference_type' => Expense::class,
                    'reference_id' => $expense->id,
                ]);

                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'ledger_account_id' => $category->ledger_account_id,
                    'debit' => $this->expenseAmount,
                    'credit' => 0.00,
                    'notes' => "Dr Expense for {$this->expensePayee}",
                ]);

                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'ledger_account_id' => $creditLedger->id,
                    'debit' => 0.00,
                    'credit' => $this->expenseAmount,
                    'notes' => "Cr {$creditLedger->name} on expense payment",
                ]);

                if (!$journal->isBalanced()) {
                    throw new \RuntimeException("Expense journal is unbalanced.");
                }
            });

            $this->showExpenseModal = false;
            $this->reset(['expenseAmount', 'expensePayee', 'expenseNotes']);
            $this->activeSession = $this->activeSession->fresh();

            session()->flash('success', "Expense recorded successfully!");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(BankLedgerService $bankService)
    {
        $bankAccounts = $this->clinic ? $bankService->getAllBankBalances($this->clinic) : [];
        $totalBookBankBalance = array_sum(array_column($bankAccounts, 'book_balance'));
        $expenseCategories = ExpenseCategory::where('is_active', true)->orderBy('display_order')->get();

        $allTodaySessions = $this->clinic
            ? CashSession::where('clinic_id', $this->clinic->id)
                ->whereDate('session_date', $this->currentDate)
                ->orderBy('id')
                ->get()
            : collect();

        // Derived collections and expenses for today (Rule 30, 39)
        $todayExpenses = $this->clinic
            ? Expense::with('expenseCategory')
                ->where('clinic_id', $this->clinic->id)
                ->whereDate('expense_date', $this->currentDate)
                ->orderByDesc('id')
                ->get()
            : collect();

        $todayDeposits = $this->clinic
            ? FundTransfer::with(['fromLedgerAccount', 'toLedgerAccount'])
                ->where('clinic_id', $this->clinic->id)
                ->whereDate('transfer_date', $this->currentDate)
                ->orderByDesc('id')
                ->get()
            : collect();

        // Today's Cash Collection (Strictly settled CASH payments - Revision 24 Section 15 & 16)
        $todayCashCollection = $this->clinic
            ? (float) DB::table('payments')
                ->where('clinic_id', $this->clinic->id)
                ->where('payment_method', 'CASH')
                ->where('status', 'SETTLED')
                ->whereDate('settled_at', $this->currentDate)
                ->sum('amount')
            : 0.00;

        $todayUpiCollection = $this->clinic
            ? (float) DB::table('payments')
                ->where('clinic_id', $this->clinic->id)
                ->where('payment_method', 'UPI')
                ->where('status', 'SETTLED')
                ->whereDate('settled_at', $this->currentDate)
                ->sum('amount')
            : 0.00;

        $todayExpensesTotal = (float) $todayExpenses->sum('amount');
        $todayCashExpensesTotal = (float) $todayExpenses->where('payment_method', 'CASH')->sum('amount');
        $todayBankExpensesTotal = (float) $todayExpenses->where('payment_method', 'BANK')->sum('amount');
        $todayBankCashDepositsTotal = (float) $todayDeposits->sum('amount');

        // Session-specific breakdowns (Morning, Evening, Sunday)
        $morningSession = $allTodaySessions->firstWhere('session_type', 'MORNING');
        $eveningSession = $allTodaySessions->firstWhere('session_type', 'EVENING');
        $sundaySession = $allTodaySessions->firstWhere('session_type', 'SUNDAY_SINGLE');

        // Opening cash from first session of the day
        $dayOpeningCash = $allTodaySessions->first() ? (float) $allTodaySessions->first()->opening_cash : (float) $this->openingPhysicalCash;
        $dayExpectedClosingCash = $dayOpeningCash + $todayCashCollection - $todayCashExpensesTotal - $todayBankCashDepositsTotal;
        $dayPhysicalCash = $this->activeSession 
            ? ((float) $this->physicalClosingCash) 
            : ($allTodaySessions->where('status', 'CLOSED')->last() ? (float) $allTodaySessions->where('status', 'CLOSED')->last()->closing_cash : 0.00);
        $dayVariance = round($dayPhysicalCash - $dayExpectedClosingCash, 2);

        return view('livewire.cash.cash-session-manager', [
            'bankAccounts' => $bankAccounts,
            'totalBookBankBalance' => $totalBookBankBalance,
            'expenseCategories' => $expenseCategories,
            'allTodaySessions' => $allTodaySessions,
            'todayExpenses' => $todayExpenses,
            'todayDeposits' => $todayDeposits,
            'todayCashCollection' => $todayCashCollection,
            'todayUpiCollection' => $todayUpiCollection,
            'todayExpensesTotal' => $todayExpensesTotal,
            'todayCashExpensesTotal' => $todayCashExpensesTotal,
            'todayBankExpensesTotal' => $todayBankExpensesTotal,
            'todayBankCashDepositsTotal' => $todayBankCashDepositsTotal,
            'morningSession' => $morningSession,
            'eveningSession' => $eveningSession,
            'sundaySession' => $sundaySession,
            'dayOpeningCash' => $dayOpeningCash,
            'dayExpectedClosingCash' => $dayExpectedClosingCash,
            'dayPhysicalCash' => $dayPhysicalCash,
            'dayVariance' => $dayVariance,
        ])->layout('components.layouts.app', ['title' => 'Daily Cash & Session Control']);
    }
}
