<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\DiagnosticBill;
use App\Models\DiagnosticItem;
use App\Models\Doctor;
use App\Models\Journal;
use App\Models\LedgerAccount;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\CashSessionService;
use App\Services\DiagnosticBillingService;
use App\Services\LabCollectionSlipService;
use App\Services\PaymentService;
use App\Services\VisitService;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticBillingTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Doctor $doctor;
    private BankAccount $bankAccount;
    private CashSession $cashSession;
    private DiagnosticBillingService $billingService;
    private PaymentService $paymentService;
    private LabCollectionSlipService $labSlipService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'Arogya Clinic',
            'phone' => '9876543210',
            'address' => 'Lanka Road',
            'city' => 'Varanasi',
            'schedule_config' => [
                'monday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '09:00', 'end' => '13:00', 'max_tokens' => 30],
                ]],
            ],
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-12345',
        ]);

        $bankLedger = LedgerAccount::create([
            'code' => '1002_9911',
            'name' => 'SBI Lab Current A/c',
            'type' => 'ASSET',
            'subtype' => 'BANK_ACCOUNT',
            'is_system' => true,
        ]);

        $this->bankAccount = BankAccount::create([
            'clinic_id' => $this->clinic->id,
            'bank_name' => 'State Bank of India',
            'account_holder' => 'Arogya Diagnostics',
            'account_number' => '3020109911',
            'ifsc' => 'SBIN0001234',
            'branch' => 'Lanka',
            'ledger_account_id' => $bankLedger->id,
            'is_active' => true,
        ]);

        $this->billingService = app(DiagnosticBillingService::class);
        $this->paymentService = app(PaymentService::class);
        $this->labSlipService = app(LabCollectionSlipService::class);

        // Open Cash Session
        $this->cashSession = app(CashSessionService::class)->openSession($this->clinic, '2026-09-28', 'MORNING', 3000.00);
    }

    public function test_creates_diagnostic_bill_with_snapshotted_rates_and_invariants(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-201',
            'name' => 'Kailash Nath',
            'mobile' => '9822233344',
            'age' => 52,
            'sex' => 'MALE',
            'address' => 'Chitaipur',
            'city' => 'Varanasi',
        ]);

        // Create diagnostic items
        $item1 = DiagnosticItem::create([
            'clinic_id' => $this->clinic->id,
            'code' => 'CBC',
            'name' => 'Complete Blood Count (CBC)',
            'type' => 'TEST',
            'rate' => 350.00,
            'is_active' => true,
        ]);

        $item2 = DiagnosticItem::create([
            'clinic_id' => $this->clinic->id,
            'code' => 'LIPID',
            'name' => 'Lipid Profile',
            'type' => 'TEST',
            'rate' => 650.00,
            'is_active' => true,
        ]);

        // Create Bill: CBC (350) + Lipid (650) = 1000 Gross, 100 Discount, 900 Net
        $bill = $this->billingService->createBill(
            $patient,
            null,
            [
                ['item_id' => $item1->id, 'quantity' => 1],
                ['item_id' => $item2->id, 'quantity' => 1],
            ],
            100.00,
            0.00
        );

        $this->assertNotNull($bill);
        $this->assertEquals(1000.00, (float)$bill->gross_amount);
        $this->assertEquals(100.00, (float)$bill->discount_amount);
        $this->assertEquals(0.00, (float)$bill->waiver_amount);
        $this->assertEquals(900.00, (float)$bill->net_amount);
        $this->assertCount(2, $bill->items);

        // Verify Invariant: net == gross - discount - waiver
        $this->assertEquals(
            round($bill->gross_amount - $bill->discount_amount - $bill->waiver_amount, 2),
            round($bill->net_amount, 2)
        );

        // Usage count incremented
        $this->assertEquals(1, $item1->fresh()->usage_count);
        $this->assertEquals(1, $item2->fresh()->usage_count);
    }

    public function test_settles_diagnostic_payment_and_creates_balanced_journal(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-202',
            'name' => 'Neelam Roy',
            'mobile' => '9822233345',
            'age' => 41,
            'sex' => 'FEMALE',
            'address' => 'Sarnath',
            'city' => 'Varanasi',
        ]);

        $item = DiagnosticItem::create([
            'clinic_id' => $this->clinic->id,
            'code' => 'USG_ABD',
            'name' => 'Ultrasound Whole Abdomen',
            'type' => 'TEST',
            'rate' => 1200.00,
            'is_active' => true,
        ]);

        $bill = $this->billingService->createBill(
            $patient,
            null,
            [['item_id' => $item->id, 'quantity' => 1]],
            0.00,
            0.00
        );

        // Settle via CASH
        $payment = $this->paymentService->settleDiagnosticPayment($bill, [
            'payment_method' => 'CASH',
            'amount' => 1200.00,
            'cash_session_id' => $this->cashSession->id,
        ]);

        $this->assertNotNull($payment);
        $this->assertEquals('SETTLED', $payment->status);
        $this->assertEquals('PAID', $bill->fresh()->status);

        // Verify Journal: Dr Cash (1001), Cr Diagnostic Income (4002)
        $journal = Journal::where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals(1200.00, (float)$journal->total_debit);
        $this->assertEquals(1200.00, (float)$journal->total_credit);

        // Cash session actual cash receipts updated
        $this->cashSession->refresh();
        $this->assertEquals(1200.00, $this->cashSession->actual_cash_receipts);
    }

    public function test_generates_lab_collection_slip_without_results_or_sample_tracking(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-203',
            'name' => 'Govind Das',
            'mobile' => '9822233346',
            'age' => 60,
            'sex' => 'MALE',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
        ]);

        $item = DiagnosticItem::create([
            'clinic_id' => $this->clinic->id,
            'code' => 'HbA1c',
            'name' => 'Glycated Hemoglobin (HbA1c)',
            'type' => 'TEST',
            'rate' => 500.00,
            'is_active' => true,
        ]);

        $bill = $this->billingService->createBill(
            $patient,
            null,
            [['item_id' => $item->id, 'quantity' => 1]]
        );

        $slipData = $this->labSlipService->getSlipData($bill);

        $this->assertNotNull($slipData);
        $this->assertArrayHasKey('slip_code', $slipData);
        $this->assertStringStartsWith('LAB-', $slipData['slip_code']);
        $this->assertEquals('Govind Das', $slipData['patient_name']);
        $this->assertEquals('Assi Ghat, Varanasi', $slipData['address']);
        $this->assertCount(1, $slipData['items']);
        $this->assertEquals('Glycated Hemoglobin (HbA1c)', $slipData['items'][0]['name']);

        // Explicit contract requirement (Section 70): No results or tracking
        $this->assertArrayNotHasKey('result', $slipData);
        $this->assertArrayNotHasKey('sample_status', $slipData);
    }
}
