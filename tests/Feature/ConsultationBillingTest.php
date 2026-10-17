<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\Journal;
use App\Models\LedgerAccount;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\CashSessionService;
use App\Services\ConsultationBillingService;
use App\Services\PaymentService;
use App\Services\VisitService;
use Carbon\Carbon;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ConsultationBillingTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Doctor $doctor;
    private BankAccount $bankAccount;
    private CashSession $cashSession;
    private AppointmentService $appointmentService;
    private VisitService $visitService;
    private ConsultationBillingService $billingService;
    private PaymentService $paymentService;

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

        // Fee master ₹600
        FeeMaster::create([
            'clinic_id' => $this->clinic->id,
            'code' => 'CONSULTATION_REGULAR',
            'name' => 'Consultation Fee',
            'amount' => 600.00,
            'is_active' => true,
        ]);

        // Bank Account
        $bankLedger = LedgerAccount::create([
            'code' => '1002_7788',
            'name' => 'HDFC Current A/c',
            'type' => 'ASSET',
            'subtype' => 'BANK_ACCOUNT',
            'is_system' => true,
        ]);

        $this->bankAccount = BankAccount::create([
            'clinic_id' => $this->clinic->id,
            'bank_name' => 'HDFC Bank',
            'account_holder' => 'Arogya Clinic',
            'account_number' => '50200012347788',
            'ifsc' => 'HDFC0001234',
            'branch' => 'Assi Ghat',
            'ledger_account_id' => $bankLedger->id,
            'is_active' => true,
        ]);

        $this->appointmentService = app(AppointmentService::class);
        $this->visitService = app(VisitService::class);
        $this->billingService = app(ConsultationBillingService::class);
        $this->paymentService = app(PaymentService::class);

        // Open Cash Session
        $this->cashSession = app(CashSessionService::class)->openSession($this->clinic, '2026-09-28', 'MORNING', 2000.00);
    }

    public function test_creates_consultation_bill_with_correct_invariants(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-101',
            'name' => 'Anita Gupta',
            'mobile' => '9811122233',
            'age' => 28,
            'sex' => 'FEMALE',
            'address' => 'Mahmoorganj',
            'city' => 'Varanasi',
        ]);

        $app = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $visit = $this->visitService->markArrival($app);

        // Create standard bill (₹600 gross, ₹100 discount, ₹500 net)
        $bill = $this->billingService->createOrGetBill($visit, 100.00, 0.00);

        $this->assertNotNull($bill);
        $this->assertEquals(600.00, (float)$bill->gross_amount);
        $this->assertEquals(100.00, (float)$bill->discount_amount);
        $this->assertEquals(0.00, (float)$bill->waiver_amount);
        $this->assertEquals(500.00, (float)$bill->net_amount);
        $this->assertEquals('PENDING', $bill->status);

        // Verify Invariant: net == gross - discount - waiver
        $this->assertEquals(
            round($bill->gross_amount - $bill->discount_amount - $bill->waiver_amount, 2),
            round($bill->net_amount, 2)
        );
    }

    public function test_settles_cash_consultation_payment_and_posts_double_entry(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-102',
            'name' => 'Vikas Mishra',
            'mobile' => '9811122234',
            'age' => 38,
            'sex' => 'MALE',
            'address' => 'Lanka',
            'city' => 'Varanasi',
        ]);

        $app = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);

        $visit = $this->visitService->markArrival($app);
        $bill = $this->billingService->createOrGetBill($visit);

        // Settle payment in CASH
        $payment = $this->paymentService->settleConsultationPayment($bill, [
            'payment_method' => 'CASH',
            'amount' => 600.00,
            'cash_session_id' => $this->cashSession->id,
        ]);

        $this->assertNotNull($payment);
        $this->assertEquals('SETTLED', $payment->status);
        $this->assertEquals(600.00, (float)$payment->amount);
        $this->assertEquals('PAID', $bill->fresh()->status);

        // Verify Receipt generated
        $this->assertNotNull($payment->receipt);
        $this->assertEquals($patient->id, $payment->receipt->patient_id);

        // Verify Double-Entry Journal: Dr Cash (1001), Cr Consultation Income (4001)
        $journal = Journal::where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals(600.00, (float)$journal->total_debit);
        $this->assertEquals(600.00, (float)$journal->total_credit);

        // Cash Session cash collection incremented
        $this->cashSession->refresh();
        $this->assertEquals(600.00, $this->cashSession->actual_cash_receipts);
    }

    public function test_manual_upi_requires_soundbox_confirmation_and_bank_account(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-103',
            'name' => 'Pooja Tiwari',
            'mobile' => '9811122235',
            'age' => 31,
            'sex' => 'FEMALE',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $app = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);

        $visit = $this->visitService->markArrival($app);
        $bill = $this->billingService->createOrGetBill($visit);

        // 1. Without Bank Account selection -> Throws exception
        try {
            $this->paymentService->settleConsultationPayment($bill, [
                'payment_method' => 'UPI',
                'amount' => 600.00,
                'soundbox_confirmed' => true,
            ]);
            $this->fail('Expected exception when bank account is not selected');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Bank account selection is required', $e->getMessage());
        }

        // 2. Without Soundbox confirmation -> Throws exception (Section 58)
        try {
            $this->paymentService->settleConsultationPayment($bill, [
                'payment_method' => 'UPI',
                'amount' => 600.00,
                'bank_account_id' => $this->bankAccount->id,
                'soundbox_confirmed' => false,
            ]);
            $this->fail('Expected exception when soundbox confirmation is missing');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Physical Soundbox confirmation is required', $e->getMessage());
        }

        // 3. With Soundbox confirmed -> Settles cleanly and does NOT alter physical cash
        $payment = $this->paymentService->settleConsultationPayment($bill, [
            'payment_method' => 'UPI',
            'amount' => 600.00,
            'bank_account_id' => $this->bankAccount->id,
            'soundbox_confirmed' => true,
            'reference_number' => 'UPI998877665544',
            'cash_session_id' => $this->cashSession->id,
        ]);

        $this->assertNotNull($payment);
        $this->assertEquals('SETTLED', $payment->status);
        $this->assertTrue((bool)$payment->soundbox_confirmed);

        // Double-entry: Dr Bank Account, Cr Consultation Income
        $journal = Journal::where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());

        $drBank = $journal->entries()->where('ledger_account_id', $this->bankAccount->ledger_account_id)->first();
        $this->assertEquals(600.00, (float)$drBank->debit);

        // Physical cash remains untouched; UPI collection is incremented
        $this->cashSession->refresh();
        $this->assertEquals(600.00, $this->cashSession->actual_upi_collection);
    }

    public function test_telephone_grace_rebooking_grants_fee_free_consultation(): void
    {
        // Patient booked via TELEPHONE 2 days ago and was marked ABSENT (Section 86)
        $pastDate = Carbon::parse('2026-09-28')->subDays(2)->toDateString();
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-104',
            'name' => 'Manoj Singh',
            'mobile' => '9811122236',
            'age' => 45,
            'sex' => 'MALE',
            'address' => 'Shivpur',
            'city' => 'Varanasi',
        ]);

        $absentApp = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $pastDate,
            'session_type' => 'MORNING',
            'booking_source' => 'TELEPHONIC',
            'token_number' => 12,
            'formatted_token' => '012',
            'status' => 'ABSENT',
            'secure_token' => 'test-token-grace-12345',
        ]);

        // Verify eligibility for Telephone Grace
        $eligible = $this->appointmentService->getEligibleTelephoneGraceAppointment($patient, '2026-09-28');
        $this->assertNotNull($eligible);
        $this->assertEquals($absentApp->id, $eligible->id);

        // Rebook with Telephone Grace
        $rebookedApp = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'TELEPHONIC',
            'grace_parent_appointment_id' => $absentApp->id,
        ]);

        $this->assertEquals($absentApp->id, $rebookedApp->grace_parent_appointment_id);

        // Patient arrives
        $visit = $this->visitService->markArrival($rebookedApp);

        // Generate bill: Gross ₹600, Waiver ₹600, Net ₹0, Status 'WAIVED' (Section 86)
        $bill = $this->billingService->createOrGetBill($visit);

        $this->assertEquals(600.00, (float)$bill->gross_amount);
        $this->assertEquals(600.00, (float)$bill->waiver_amount);
        $this->assertEquals(0.00, (float)$bill->net_amount);
        $this->assertEquals('WAIVED', $bill->status);
    }
}
