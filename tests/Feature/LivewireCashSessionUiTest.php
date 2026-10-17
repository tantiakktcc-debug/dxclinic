<?php

namespace Tests\Feature;

use App\Livewire\Cash\CashSessionManager;
use App\Livewire\Cash\DailyReportView;
use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\ExpenseCategory;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireCashSessionUiTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        \Carbon\Carbon::setTestNow('2026-09-28 09:00:00');
        $this->seed(SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'Sharma Clinic',
            'phone' => '9876543210',
            'address' => '123, MG Road',
            'city' => 'Varanasi',
            'is_onboarded' => true,
        ]);

        $this->bankAccount = BankAccount::create([
            'clinic_id' => $this->clinic->id,
            'bank_name' => 'State Bank of India',
            'account_holder' => 'Sharma Clinic',
            'account_number' => '123456789012',
            'ifsc' => 'SBIN0001234',
            'branch' => 'Main Branch',
            'upi_id' => 'sharma@sbi',
            'is_active' => true,
            'ledger_account_id' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cash_session_lifecycle_with_expense_and_bank_deposit(): void
    {
        // 1. Open Morning Session
        $component = Livewire::test(CashSessionManager::class)
            ->assertSee('Start Morning Cash Session')
            ->set('openingPhysicalCash', 10000.00)
            ->call('openSession')
            ->assertSee('MORNING cash session opened');

        $this->assertDatabaseHas('cash_sessions', [
            'clinic_id' => $this->clinic->id,
            'session_type' => 'MORNING',
            'status' => 'OPEN',
            'opening_physical_cash' => 10000.00,
        ]);

        // 2. Record Cash Expense
        $category = ExpenseCategory::first();
        $component->set('expenseCategoryId', $category->id)
            ->set('expenseAmount', 500.00)
            ->set('expensePaymentMethod', 'CASH')
            ->set('expensePayee', 'Medicines Vendor')
            ->set('expenseNotes', 'Daily cotton & spirit')
            ->call('recordExpense')
            ->assertSee('Expense recorded successfully');

        $this->assertDatabaseHas('expenses', [
            'amount' => 500.00,
            'payee' => 'Medicines Vendor',
        ]);

        // 3. Record Cash to Bank Deposit
        $component->set('depositBankAccountId', $this->bankAccount->id)
            ->set('depositAmount', 3000.00)
            ->set('depositReference', 'DEP-SLIP-001')
            ->call('recordDeposit')
            ->assertSee('Bank cash deposit of');

        $this->assertDatabaseHas('fund_transfers', [
            'amount' => 3000.00,
            'reference_number' => 'DEP-SLIP-001',
        ]);

        // Expected cash should now be: 10,000 - 500 - 3000 = 6,500
        $session = CashSession::where('clinic_id', $this->clinic->id)->where('status', 'OPEN')->firstOrFail();
        $this->assertEquals(6500.00, $session->computeExpectedClosingCash());

        // 4. Close Session with Variance (e.g. physical count is 6,400 -> variance -100)
        // Without variance reason should fail
        $component->set('physicalClosingCash', 6400.00)
            ->set('varianceReason', '')
            ->call('closeSession')
            ->assertSee('Variance reason is REQUIRED');

        // With variance reason
        $component->set('varianceReason', 'Petty cash change rounding discrepancy')
            ->call('closeSession')
            ->assertSee('Session closed successfully');

        $session->refresh();
        $this->assertEquals('CLOSED', $session->status);
        $this->assertEquals(-100.00, $session->cash_variance);
    }

    public function test_daily_cash_report_view_renders_formula_and_metrics(): void
    {
        Livewire::test(DailyReportView::class, ['date' => now()->toDateString()])
            ->assertSee('Daily Cash Report')
            ->assertSee('Opening Bank Balance')
            ->assertSee('Total UPI Collection')
            ->assertSee('Total Expenses')
            ->assertSee('Bank Cash Deposit')
            ->assertSee('Session Details')
            ->assertSee('Verification (Closing Cash Formula)');
    }
}
