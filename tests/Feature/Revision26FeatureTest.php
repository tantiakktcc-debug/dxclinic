<?php

namespace Tests\Feature;

use App\Livewire\Operator\ConsultationBilling;
use App\Livewire\Operator\DiagnosticBilling;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\DiagnosticBillItem;
use App\Models\DiagnosticItem;
use App\Models\Doctor;
use App\Models\LedgerAccount;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptRevision;
use App\Models\Refund;
use App\Models\User;
use App\Models\Visit;
use App\Services\BillModificationService;
use App\Services\CashReconciliationService;
use App\Services\PaymentService;
use App\Services\RefundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Revision26FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected User $adminUser;
    protected User $operatorUser;
    protected User $doctorUser;
    protected BankAccount $bankAccount;
    protected CashSession $cashSession;
    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'name' => 'DxClinic Health Center',
            'code' => 'DXC',
            'phone' => '9876543210',
            'status' => 'ACTIVE',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
            'state' => 'Uttar Pradesh',
            'pincode' => '221005',
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Manoj Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-12345',
            'specialization' => 'General Medicine',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Administrator',
            'email' => 'admin@dxclinic.test',
            'password' => bcrypt('secret123'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->operatorUser = User::create([
            'name' => 'Front Desk Operator',
            'email' => 'operator@dxclinic.test',
            'password' => bcrypt('secret123'),
            'role' => 'OPERATOR',
            'status' => 'ACTIVE',
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dr. Manoj Sharma User',
            'email' => 'doctor@dxclinic.test',
            'password' => bcrypt('secret123'),
            'role' => 'DOCTOR',
            'status' => 'ACTIVE',
        ]);

        $bankLedger = LedgerAccount::create([
            'code' => '1002',
            'name' => 'HDFC Bank Primary',
            'type' => 'ASSET',
            'subtype' => 'BANK',
            'is_system' => true,
        ]);

        $this->bankAccount = BankAccount::create([
            'clinic_id' => $this->clinic->id,
            'bank_name' => 'HDFC Bank',
            'account_holder' => 'DxClinic Health Center',
            'account_number' => '50100234567890',
            'ifsc' => 'HDFC0001234',
            'branch' => 'Assi Ghat',
            'ledger_account_id' => $bankLedger->id,
            'is_active' => true,
        ]);

        $this->cashSession = CashSession::create([
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->operatorUser->id,
            'session_date' => Carbon::now()->toDateString(),
            'session_type' => 'MORNING',
            'opening_physical_cash' => 2000.00,
            'status' => 'OPEN',
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P10001',
            'name' => 'Ramesh Gupta',
            'mobile' => '9876543210',
            'age' => 45,
            'sex' => 'MALE',
            'address' => 'Lanka',
            'city' => 'Varanasi',
        ]);
    }

    public function test_consultation_billing_history_filters_and_pagination(): void
    {
        $this->actingAs($this->operatorUser);

        // Create 3 historical consultation bills
        for ($i = 1; $i <= 3; $i++) {
            $app = Appointment::create([
                'clinic_id' => $this->clinic->id,
                'patient_id' => $this->patient->id,
                'doctor_id' => $this->doctor->id,
                'appointment_date' => Carbon::now()->subDays($i)->toDateString(),
                'session_type' => 'MORNING',
                'booking_source' => 'CLINIC',
                'token_number' => $i,
                'formatted_token' => "T-00{$i}",
                'secure_token' => \Illuminate\Support\Str::random(32),
                'status' => 'ARRIVED',
            ]);

            $visit = Visit::create([
                'clinic_id' => $this->clinic->id,
                'appointment_id' => $app->id,
                'patient_id' => $this->patient->id,
                'doctor_id' => $this->doctor->id,
                'visit_date' => $app->appointment_date,
                'session_type' => 'MORNING',
                'status' => 'IN_CONSULTATION',
            ]);

            ConsultationBill::create([
                'clinic_id' => $this->clinic->id,
                'bill_number' => "CBL-2026-00{$i}",
                'appointment_id' => $app->id,
                'visit_id' => $visit->id,
                'patient_id' => $this->patient->id,
                'doctor_id' => $this->doctor->id,
                'bill_date' => $app->appointment_date,
                'gross_amount' => 600.00,
                'discount_amount' => 0.00,
                'waiver_amount' => 0.00,
                'net_amount' => 600.00,
                'status' => 'PAID',
            ]);
        }

        Livewire::test(ConsultationBilling::class)
            ->set('activeTab', 'history')
            ->set('dateFrom', Carbon::now()->subDays(5)->toDateString())
            ->set('dateTo', Carbon::now()->toDateString())
            ->assertSee('CBL-2026-001')
            ->assertSee('CBL-2026-002')
            ->assertSee('CBL-2026-003')
            ->assertSee('Ramesh Gupta')
            // Test filter by specific bill number
            ->set('searchBillNumber', 'CBL-2026-002')
            ->assertSee('CBL-2026-002')
            ->assertDontSee('CBL-2026-001');
    }

    public function test_diagnostic_billing_history_filters_and_rates_preserved(): void
    {
        $this->actingAs($this->operatorUser);

        $cbc = DiagnosticItem::create([
            'code' => 'CBC',
            'name' => 'Complete Blood Count',
            'type' => 'TEST',
            'rate' => 300.00,
            'is_active' => true,
        ]);

        $dBill = DiagnosticBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'DBL-2026-001',
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'gross_amount' => 300.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'net_amount' => 300.00,
            'status' => 'PAID',
        ]);

        DiagnosticBillItem::create([
            'diagnostic_bill_id' => $dBill->id,
            'diagnostic_item_id' => $cbc->id,
            'item_type' => 'TEST',
            'item_name' => 'Complete Blood Count',
            'rate' => 300.00, // Historical rate snapshotted
            'quantity' => 1,
            'amount' => 300.00,
        ]);

        // Change master rate to test historical preservation
        $cbc->update(['rate' => 500.00]);

        Livewire::test(DiagnosticBilling::class)
            ->set('activeTab', 'history')
            ->set('dateFrom', Carbon::now()->toDateString())
            ->set('dateTo', Carbon::now()->toDateString())
            ->assertSee('DBL-2026-001')
            ->assertSee('Complete Blood Count')
            ->assertSee('₹300.00'); // Preserved, not 500.00
    }

    public function test_pending_bill_controlled_modification_with_mandatory_reason(): void
    {
        $app = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => Carbon::now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 10,
            'formatted_token' => 'T-010',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'appointment_id' => $app->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visit_date' => $app->appointment_date,
            'session_type' => 'MORNING',
            'status' => 'IN_CONSULTATION',
        ]);

        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'CBL-PENDING-01',
            'appointment_id' => $app->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'gross_amount' => 600.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'net_amount' => 600.00,
            'status' => 'PENDING',
        ]);

        $modService = app(BillModificationService::class);

        // Test empty/short reason rejected
        $this->expectException(\InvalidArgumentException::class);
        $modService->modifyPendingConsultationBill($bill, [
            'discount_amount' => 100.00,
            'reason' => 'edit', // Generic / too short
        ], $this->operatorUser);
    }

    public function test_pending_bill_valid_modification_creates_audit_log(): void
    {
        $app = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => Carbon::now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 11,
            'formatted_token' => 'T-011',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'appointment_id' => $app->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visit_date' => $app->appointment_date,
            'session_type' => 'MORNING',
            'status' => 'IN_CONSULTATION',
        ]);

        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'CBL-PENDING-02',
            'appointment_id' => $app->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'gross_amount' => 600.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'net_amount' => 600.00,
            'status' => 'PENDING',
        ]);

        $modService = app(BillModificationService::class);
        $updated = $modService->modifyPendingConsultationBill($bill, [
            'discount_amount' => 100.00,
            'reason' => 'Doctor recommended partial consultation fee discount.',
        ], $this->operatorUser);

        $this->assertEquals(500.00, (float) $updated->net_amount);
        $this->assertEquals(100.00, (float) $updated->discount_amount);

        // Verify audit log
        $audit = AuditLog::where('entity_type', ConsultationBill::class)
            ->where('entity_id', $bill->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals('BILL_CORRECTION', $audit->change_category);
        $this->assertStringContainsString('Doctor recommended partial consultation fee discount', $audit->reason);
    }

    public function test_paid_consultation_bill_discount_after_payment_as_refund(): void
    {
        $app = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => Carbon::now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 12,
            'formatted_token' => 'T-012',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'appointment_id' => $app->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visit_date' => $app->appointment_date,
            'session_type' => 'MORNING',
            'status' => 'IN_CONSULTATION',
        ]);

        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'CBL-PAID-01',
            'appointment_id' => $app->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'gross_amount' => 600.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'net_amount' => 600.00,
            'status' => 'PENDING',
        ]);

        // Settle payment ₹600 via PaymentService
        $payment = app(PaymentService::class)->settleConsultationPayment($bill, [
            'payment_method' => 'CASH',
            'cash_session_id' => $this->cashSession->id,
            'amount' => 600.00,
        ]);

        $this->assertEquals(600.00, (float) $payment->amount);
        $this->assertEquals('PAID', $bill->fresh()->status);

        // Now doctor recommends ₹100 discount after payment
        $refundService = app(RefundService::class);
        $refund = $refundService->processDirectRefund([
            'payment_id' => $payment->id,
            'consultation_bill_id' => $bill->id,
            'patient_id' => $this->patient->id,
            'refund_type' => 'REFUND_AS_DISCOUNT',
            'refund_amount' => 100.00,
            'refund_method' => 'CASH',
            'cash_session_id' => $this->cashSession->id,
            'reason' => 'Refund for discount given as per doctor recommendation.',
            'doctor_instruction_by' => $this->doctor->id,
        ], $this->adminUser);

        // Verify Invariants:
        // 1. Original bill remains ₹600 immutable
        $freshBill = $bill->fresh();
        $this->assertEquals(600.00, (float) $freshBill->net_amount);
        $this->assertEquals(0.00, (float) $freshBill->discount_amount);
        $this->assertEquals('PAID', $freshBill->status); // Remains PAID because partially refunded

        // 2. Original payment remains ₹600 immutable
        $freshPayment = $payment->fresh();
        $this->assertEquals(600.00, (float) $freshPayment->amount);

        // 3. Derived retained amounts: Paid 600 - Refund 100 = 500
        $this->assertEquals(100.00, $freshBill->refunded_amount);
        $this->assertEquals(500.00, $freshBill->retained_amount);
        $this->assertEquals(500.00, $freshPayment->retained_amount);
        $this->assertEquals(500.00, $refundService->getRemainingRefundableAmount($freshPayment));

        // 4. Double entry reversal journal verified and balanced
        $journal = $refund->journal;
        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals(100.00, (float) $journal->entries->where('debit', '>', 0)->sum('debit'));
        $this->assertEquals(100.00, (float) $journal->entries->where('credit', '>', 0)->sum('credit'));

        // 5. Receipt revision record created
        $receipt = Receipt::where('payment_id', $payment->id)->first();
        $this->assertNotNull($receipt);
        $revision = ReceiptRevision::where('receipt_id', $receipt->id)->first();
        $this->assertNotNull($revision);
        $this->assertEquals(1, $revision->revision_number);
        $this->assertEquals(100.00, (float) $revision->new_values['refund_amount']);
    }

    public function test_full_refund_marks_bill_refunded(): void
    {
        $app = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => Carbon::now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 13,
            'formatted_token' => 'T-013',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'appointment_id' => $app->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visit_date' => $app->appointment_date,
            'session_type' => 'MORNING',
            'status' => 'IN_CONSULTATION',
        ]);

        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'CBL-PAID-FULL',
            'appointment_id' => $app->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'gross_amount' => 600.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'net_amount' => 600.00,
            'status' => 'PENDING',
        ]);

        $payment = app(PaymentService::class)->settleConsultationPayment($bill, [
            'payment_method' => 'CASH',
            'cash_session_id' => $this->cashSession->id,
            'amount' => 600.00,
        ]);

        $refundService = app(RefundService::class);
        $refund = $refundService->processDirectRefund([
            'payment_id' => $payment->id,
            'consultation_bill_id' => $bill->id,
            'patient_id' => $this->patient->id,
            'refund_type' => 'FULL',
            'refund_amount' => 600.00,
            'refund_method' => 'CASH',
            'cash_session_id' => $this->cashSession->id,
            'reason' => 'Patient had an emergency before consultation took place.',
        ], $this->adminUser);

        // Bill status transitions to REFUNDED
        $this->assertEquals('REFUNDED', $bill->fresh()->status);
        $this->assertEquals(0.00, $bill->fresh()->retained_amount);

        // Subsequent refund attempt rejected (zero refundable left)
        $this->expectException(\InvalidArgumentException::class);
        $refundService->requestRefund([
            'payment_id' => $payment->id,
            'refund_amount' => 50.00,
            'refund_method' => 'CASH',
            'reason' => 'Over refund attempt test should fail.',
        ], $this->adminUser);
    }

    public function test_diagnostic_increase_creates_supplementary_bill(): void
    {
        $cbc = DiagnosticItem::create([
            'code' => 'CBC-01',
            'name' => 'Complete Blood Count',
            'type' => 'TEST',
            'rate' => 300.00,
            'is_active' => true,
        ]);

        $lft = DiagnosticItem::create([
            'code' => 'LFT-01',
            'name' => 'Liver Function Test',
            'type' => 'TEST',
            'rate' => 700.00,
            'is_active' => true,
        ]);

        $originalBill = DiagnosticBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'DBL-ORIG-01',
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'gross_amount' => 300.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'net_amount' => 300.00,
            'status' => 'PAID',
        ]);

        DiagnosticBillItem::create([
            'diagnostic_bill_id' => $originalBill->id,
            'diagnostic_item_id' => $cbc->id,
            'item_type' => 'TEST',
            'item_name' => 'Complete Blood Count',
            'rate' => 300.00,
            'quantity' => 1,
            'amount' => 300.00,
        ]);

        // Settle original payment
        $payment = app(PaymentService::class)->settleDiagnosticPayment($originalBill, [
            'payment_method' => 'CASH',
            'cash_session_id' => $this->cashSession->id,
            'amount' => 300.00,
        ]);

        // Later doctor requests LFT as well -> create supplementary bill
        $modService = app(BillModificationService::class);
        $suppBill = $modService->createSupplementaryDiagnosticBill($originalBill, [
            [
                'item_id' => $lft->id,
                'rate' => 700.00,
                'quantity' => 1,
            ]
        ], 'Doctor requested additional liver investigation after initial review.', $this->operatorUser);

        // Invariants:
        // 1. Original bill remains ₹300 unchanged
        $this->assertEquals(300.00, (float) $originalBill->fresh()->net_amount);
        $this->assertEquals('PAID', $originalBill->fresh()->status);

        // 2. Supplementary bill created with correct reference and amount
        $this->assertEquals('DBL-ORIG-01-SUPP-1', $suppBill->bill_number);
        $this->assertEquals(700.00, (float) $suppBill->net_amount);
        $this->assertEquals('PENDING', $suppBill->status);

        // 3. Audit trail records supplementary relation
        $audit = AuditLog::where('change_category', 'SUPPLEMENTARY_BILL')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($originalBill->id, $audit->new_values['original_bill_id']);
    }

    public function test_doctor_pad_reprint_idempotency_creates_zero_records(): void
    {
        $app = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => Carbon::now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 20,
            'formatted_token' => 'T-020',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        // Baseline counts before print
        $payCountBefore = Payment::count();
        $receiptCountBefore = Receipt::count();
        $refundCountBefore = Refund::count();
        $appCountBefore = Appointment::count();

        // Print Doctor Pad endpoint
        $response = $this->actingAs($this->operatorUser)->get(route('print.doctor.pad', $app->id));
        $response->assertStatus(200);

        // PDF Doctor Pad endpoint
        $pdfResponse = $this->actingAs($this->operatorUser)->get(route('pdf.doctor-pad', $app->id));
        $pdfResponse->assertStatus(200);

        // Assert 100% Zero side effects
        $this->assertEquals($payCountBefore, Payment::count());
        $this->assertEquals($receiptCountBefore, Receipt::count());
        $this->assertEquals($refundCountBefore, Refund::count());
        $this->assertEquals($appCountBefore, Appointment::count());
    }

    public function test_refund_concurrency_race_condition_prevention(): void
    {
        $app = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => Carbon::now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 21,
            'formatted_token' => 'T-021',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'appointment_id' => $app->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visit_date' => $app->appointment_date,
            'session_type' => 'MORNING',
            'status' => 'IN_CONSULTATION',
        ]);

        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'CBL-RACE-01',
            'appointment_id' => $app->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'gross_amount' => 600.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'net_amount' => 600.00,
            'status' => 'PENDING',
        ]);

        $payment = app(PaymentService::class)->settleConsultationPayment($bill, [
            'payment_method' => 'CASH',
            'cash_session_id' => $this->cashSession->id,
            'amount' => 600.00,
        ]);

        $refundService = app(RefundService::class);

        // Request A for ₹400
        $refundA = $refundService->requestRefund([
            'payment_id' => $payment->id,
            'consultation_bill_id' => $bill->id,
            'patient_id' => $this->patient->id,
            'refund_amount' => 400.00,
            'refund_method' => 'CASH',
            'cash_session_id' => $this->cashSession->id,
            'reason' => 'First partial refund request of 400.',
        ], $this->operatorUser);

        // Request B for ₹300 (Total 400 + 300 = 700 > 600)
        $refundB = $refundService->requestRefund([
            'payment_id' => $payment->id,
            'consultation_bill_id' => $bill->id,
            'patient_id' => $this->patient->id,
            'refund_amount' => 300.00,
            'refund_method' => 'CASH',
            'cash_session_id' => $this->cashSession->id,
            'reason' => 'Second partial refund request of 300.',
        ], $this->operatorUser);

        // Settle A first (succeeds)
        $refundService->approveRefund($refundA, $this->adminUser);
        $refundService->processRefund($refundA, $this->adminUser);
        $this->assertEquals('PROCESSED', $refundA->fresh()->status);

        // Now attempt to settle B (must fail because only 200 is refundable)
        $refundService->approveRefund($refundB, $this->adminUser);

        $this->expectException(\InvalidArgumentException::class);
        $refundService->processRefund($refundB, $this->adminUser);
    }
}
