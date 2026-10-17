<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundTransfer;
use App\Models\Journal;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\CashSessionService;
use App\Services\OpeningBalanceService;
use Carbon\Carbon;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CashControlTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private BankAccount $bankAccount;
    private CashSessionService $cashService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        // Setup Clinic
        $this->clinic = Clinic::create([
            'name' => 'Arogya Clinic Varanasi',
            'phone' => '9876543210',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
            'schedule_config' => [
                'monday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '09:00', 'end' => '13:00', 'max_tokens' => 30],
                    ['type' => 'EVENING', 'start' => '17:00', 'end' => '21:00', 'max_tokens' => 30],
                ]],
                'tuesday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '09:00', 'end' => '13:00', 'max_tokens' => 30],
                    ['type' => 'EVENING', 'start' => '17:00', 'end' => '21:00', 'max_tokens' => 30],
                ]],
                'wednesday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '09:00', 'end' => '13:00', 'max_tokens' => 30],
                    ['type' => 'EVENING', 'start' => '17:00', 'end' => '21:00', 'max_tokens' => 30],
                ]],
                'thursday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '09:00', 'end' => '13:00', 'max_tokens' => 30],
                    ['type' => 'EVENING', 'start' => '17:00', 'end' => '21:00', 'max_tokens' => 30],
                ]],
                'friday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '09:00', 'end' => '13:00', 'max_tokens' => 30],
                    ['type' => 'EVENING', 'start' => '17:00', 'end' => '21:00', 'max_tokens' => 30],
                ]],
                'saturday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '09:00', 'end' => '13:00', 'max_tokens' => 30],
                    ['type' => 'EVENING', 'start' => '17:00', 'end' => '21:00', 'max_tokens' => 30],
                ]],
                'sunday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '10:00', 'end' => '13:00', 'max_tokens' => 20],
                ]],
            ],
            'is_onboarded' => true,
        ]);

        // Setup Bank Account & linked ledger
        $bankLedger = LedgerAccount::create([
            'code' => '1002_4321',
            'name' => 'SBI Current A/c',
            'type' => 'ASSET',
            'subtype' => 'BANK_ACCOUNT',
            'is_system' => true,
        ]);

        $this->bankAccount = BankAccount::create([
            'clinic_id' => $this->clinic->id,
            'bank_name' => 'State Bank of India',
            'account_holder' => 'Arogya Clinic',
            'account_number' => '987654321',
            'ifsc' => 'SBIN0001234',
            'branch' => 'Lanka',
            'ledger_account_id' => $bankLedger->id,
            'is_active' => true,
        ]);

        $this->cashService = app(CashSessionService::class);
    }

    public function test_can_open_cash_session_with_verified_physical_cash(): void
    {
        $date = Carbon::parse('2026-09-28'); // Monday
        $session = $this->cashService->openSession($this->clinic, $date, 'MORNING', 5000.00);

        $this->assertNotNull($session);
        $this->assertEquals('OPEN', $session->status);
        $this->assertEquals(5000.00, (float)$session->opening_physical_cash);
        $this->assertEquals(5000.00, (float)$session->expected_closing_cash);
    }

    public function test_cannot_open_duplicate_session_when_already_closed(): void
    {
        $date = Carbon::parse('2026-09-28');
        $session = $this->cashService->openSession($this->clinic, $date, 'MORNING', 5000.00);
        $this->cashService->closeSession($session, 5000.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has already been closed');
        $this->cashService->openSession($this->clinic, $date, 'MORNING', 5000.00);
    }

    public function test_expected_closing_cash_strictly_tracks_actual_transactions(): void
    {
        // 1. Open Session with ₹5,000 physical cash
        $date = Carbon::parse('2026-09-28');
        $session = $this->cashService->openSession($this->clinic, $date, 'MORNING', 5000.00);

        // 2. Patient pays ₹600 in CASH
        Payment::create([
            'clinic_id' => $this->clinic->id,
            'cash_session_id' => $session->id,
            'payment_number' => 'PAY-C-001',
            'payment_method' => 'CASH',
            'amount' => 600.00,
            'status' => 'SETTLED',
            'settled_at' => Carbon::now(),
        ]);

        // 3. Another patient pays ₹600 via UPI (must NOT inflate physical cash)
        Payment::create([
            'clinic_id' => $this->clinic->id,
            'cash_session_id' => $session->id,
            'payment_number' => 'PAY-U-001',
            'payment_method' => 'UPI',
            'amount' => 600.00,
            'reference_number' => 'UPI123456789012',
            'status' => 'SETTLED',
            'soundbox_confirmed' => true,
            'bank_account_id' => $this->bankAccount->id,
            'settled_at' => Carbon::now(),
        ]);

        // 4. Cash Expense ₹200 (Tea/Sanitizer)
        $expenseCategory = ExpenseCategory::create([
            'clinic_id' => $this->clinic->id,
            'code' => 'REFRESHMENT',
            'name' => 'Refreshments',
            'ledger_account_id' => LedgerAccount::where('code', '5001')->first()->id,
        ]);

        Expense::create([
            'clinic_id' => $this->clinic->id,
            'cash_session_id' => $session->id,
            'expense_category_id' => $expenseCategory->id,
            'amount' => 200.00,
            'payment_method' => 'CASH',
            'expense_date' => $date,
            'payee' => 'Local Tea Stall',
            'notes' => 'Staff tea & stationery',
        ]);

        // 5. Cash Deposited to Bank ₹3,000
        $this->cashService->recordCashToBankDeposit(
            $session,
            $this->bankAccount->id,
            3000.00,
            'DEP-001',
            'Cash deposit to SBI'
        );

        // 6. Verify System-Derived Totals
        // Cash receipts = ₹600
        // UPI collection = ₹600
        // Cash expenses = ₹200
        // Bank deposit = ₹3000
        $this->assertEquals(600.00, $session->actual_cash_receipts);
        $this->assertEquals(600.00, $session->actual_upi_collection);
        $this->assertEquals(200.00, $session->actual_cash_expenses);
        $this->assertEquals(3000.00, $session->actual_bank_cash_deposit);

        // 7. Verify Expected Closing Cash Formula:
        // Opening (5,000) + Cash Receipts (600) - Cash Expenses (200) - Bank Deposit (3,000) = ₹2,400.00
        $expectedCash = $session->computeExpectedClosingCash();
        $this->assertEquals(2400.00, $expectedCash);

        // 8. Close session with exact matching physical cash ₹2,400.00 (Zero Variance)
        $closedSession = $this->cashService->closeSession($session, 2400.00);
        $this->assertEquals('CLOSED', $closedSession->status);
        $this->assertEquals(0.00, (float)$closedSession->cash_variance);
        $this->assertNull($closedSession->variance_reason);
    }

    public function test_variance_reason_is_mandatory_when_closing_cash_has_variance(): void
    {
        $date = Carbon::parse('2026-09-28');
        $session = $this->cashService->openSession($this->clinic, $date, 'MORNING', 1000.00);

        // Expected is 1000. Physical is 950 (Shortage of 50).
        // Attempting to close without reason must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Variance reason is REQUIRED');
        $this->cashService->closeSession($session, 950.00, '');
    }

    public function test_closing_with_variance_and_valid_reason_records_cleanly(): void
    {
        $date = Carbon::parse('2026-09-28');
        $session = $this->cashService->openSession($this->clinic, $date, 'MORNING', 1000.00);

        // Physical is 950. Reason provided: "₹50 given to courier without petty voucher"
        $closedSession = $this->cashService->closeSession($session, 950.00, '50 given to courier without voucher');
        $this->assertEquals('CLOSED', $closedSession->status);
        $this->assertEquals(-50.00, (float)$closedSession->cash_variance);
        $this->assertEquals('50 given to courier without voucher', $closedSession->variance_reason);
    }

    public function test_cash_to_bank_deposit_creates_balanced_contra_journal(): void
    {
        $date = Carbon::parse('2026-09-28');
        $session = $this->cashService->openSession($this->clinic, $date, 'MORNING', 10000.00);

        $transfer = $this->cashService->recordCashToBankDeposit(
            $session,
            $this->bankAccount->id,
            4500.00,
            'SBI-DEP-999'
        );

        $this->assertNotNull($transfer);
        $this->assertEquals(4500.00, (float)$transfer->amount);

        // Check balanced Journal: Dr Bank Cr Cash
        $journal = Journal::where('reference_type', FundTransfer::class)
            ->where('reference_id', $transfer->id)
            ->first();

        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals(4500.00, (float)$journal->total_debit);
        $this->assertEquals(4500.00, (float)$journal->total_credit);

        // Dr Bank
        $drBank = $journal->entries()->where('ledger_account_id', $this->bankAccount->ledger_account_id)->first();
        $this->assertEquals(4500.00, (float)$drBank->debit);
        $this->assertEquals(0.00, (float)$drBank->credit);

        // Cr Cash
        $crCash = $journal->entries()->whereHas('account', fn($q) => $q->where('code', '1001'))->first();
        $this->assertEquals(0.00, (float)$crCash->debit);
        $this->assertEquals(4500.00, (float)$crCash->credit);
    }
}
