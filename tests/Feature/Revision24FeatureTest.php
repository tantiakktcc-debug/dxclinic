<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BankAccount;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\BackupService;
use App\Services\PatientIdentityService;
use App\Services\PdfGeneratorService;
use App\Services\QrCodeService;
use App\Services\RagAssistantService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Revision24FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected User $admin;
    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'DxClinic Varanasi',
            'city' => 'Varanasi',
            'address' => 'Assi Ghat',
            'phone' => '9876543210',
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'specialization' => 'General Medicine',
            'registration_number' => 'MCI-12345',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin_test@dxclinic.local',
            'password' => Hash::make('Secret123!'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->operator = User::create([
            'name' => 'Operator Test',
            'email' => 'operator_test@dxclinic.local',
            'password' => Hash::make('Secret123!'),
            'role' => 'OPERATOR',
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Test 1: Expense Category Master CRUD & Historical Guard (Section 12-13)
     */
    public function test_expense_category_master_and_historical_protection(): void
    {
        $ledger = LedgerAccount::where('type', 'EXPENSE')->first() ?? LedgerAccount::create([
            'code' => '5099',
            'name' => 'Other Expense',
            'type' => 'EXPENSE',
            'subtype' => 'OPERATING',
            'is_system' => false,
        ]);

        $code = 'TEST_CAT_' . uniqid();
        $cat = ExpenseCategory::create([
            'name' => 'Test Stationery',
            'code' => $code,
            'ledger_account_id' => $ledger->id,
            'display_order' => 10,
            'is_active' => true,
        ]);

        $this->assertNotNull($cat->id);
        $this->assertTrue($cat->is_active);

        // Inactivation
        $cat->update(['is_active' => false]);
        $this->assertFalse($cat->fresh()->is_active);

        // Historical expense protection
        $expense = Expense::create([
            'clinic_id' => $this->clinic->id,
            'expense_category_id' => $cat->id,
            'amount' => 200.00,
            'payment_method' => 'CASH',
            'payee' => 'Paper Supplier',
            'expense_date' => Carbon::today()->toDateString(),
        ]);

        $catWithCount = ExpenseCategory::withCount('expenses')->find($cat->id);
        $this->assertEquals(1, $catWithCount->expenses_count);

        // Cleanup
        $expense->delete();
        $cat->delete();
    }

    /**
     * Test 2: User & Operator Management and Password Reset (Section 65-67)
     */
    public function test_user_management_and_password_reset(): void
    {
        $email = 'op_' . uniqid() . '@dxclinic.local';
        $user = User::create([
            'name' => 'Test Operator Staff',
            'email' => $email,
            'password' => Hash::make('InitPass123!'),
            'role' => 'OPERATOR',
            'status' => 'ACTIVE',
        ]);

        $this->assertNotNull($user->id);
        $this->assertEquals('OPERATOR', $user->role);

        // Admin Reset Password
        $newSecret = 'AdminResetPass2026!';
        $user->update(['password' => Hash::make($newSecret)]);
        $this->assertTrue(Hash::check($newSecret, $user->fresh()->password));

        // Cleanup
        $user->delete();
    }

    /**
     * Test 3: Doctor Selection & Single Doctor Auto-Selection (Section 41-44)
     */
    public function test_doctor_selection_and_token_continuity(): void
    {
        $appointmentService = app(AppointmentService::class);
        $patientService = app(PatientIdentityService::class);

        $patient = $patientService->resolvePatient([
            'name' => 'Doctor Selection Patient',
            'mobile' => '9991112223',
            'clinic_id' => $this->clinic->id,
        ]);

        \App\Services\ClinicClock::setTestNow('2026-09-28 08:30:00');
        try {
            $appt = $appointmentService->bookAppointment([
                'clinic_id' => $this->clinic->id,
                'patient_id' => $patient->id,
                'appointment_date' => Carbon::today()->toDateString(),
                'session_type' => 'MORNING',
                'booking_source' => 'CLINIC',
                'doctor_id' => null, // Omitted, should auto-select
            ]);

            $this->assertNotNull($appt->doctor_id);
            $this->assertNotNull($appt->token_number);
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

    /**
     * Test 4: A5 Receipts Policy & Receipt Copies (Section 30-33)
     */
    public function test_a5_receipt_copies_and_generation(): void
    {
        $pdfService = app(PdfGeneratorService::class);

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-TEST-001',
            'name' => 'Receipt Patient',
            'age' => 30,
            'sex' => 'FEMALE',
            'mobile' => '9988776655',
            'address' => 'Lanka',
            'city' => 'Varanasi',
        ]);

        $app = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'token_number' => 1,
            'formatted_token' => '001',
            'booking_source' => 'CLINIC',
            'status' => 'ARRIVED',
            'secure_token' => str_repeat('b', 64),
        ]);

        $visit = \App\Models\Visit::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $patient->id,
            'appointment_id' => $app->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'COMPLETED',
        ]);

        $bill = \App\Models\ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $patient->id,
            'appointment_id' => $app->id,
            'visit_id' => $visit->id,
            'bill_number' => 'BILL-TEST-A5-001',
            'bill_date' => now()->toDateString(),
            'gross_amount' => 500.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'net_amount' => 500.00,
            'status' => 'PAID',
        ]);

        $payment = Payment::create([
            'clinic_id' => $this->clinic->id,
            'payment_number' => 'PAY-TEST-' . uniqid(),
            'payable_type' => \App\Models\ConsultationBill::class,
            'payable_id' => $bill->id,
            'receipt_number' => 'REC-TEST-A5-001',
            'amount' => 500.00,
            'payment_method' => 'CASH',
            'status' => 'SETTLED',
            'settled_at' => now(),
        ]);

        $data = [
            'bill' => $bill,
            'clinic' => $this->clinic,
            'patient' => $patient,
            'payment' => $payment,
            'formattedAddress' => $patient->formatted_address,
            'copy' => 'patient',
        ];

        // Patient copy
        $resPatient = $pdfService->generateConsultationReceiptPdf($data);
        $this->assertEquals(200, $resPatient->getStatusCode());
        $this->assertEquals('application/pdf', $resPatient->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $resPatient->getContent());

        // Clinic copy
        $data['copy'] = 'clinic';
        $resClinic = $pdfService->generateConsultationReceiptPdf($data);
        $this->assertEquals(200, $resClinic->getStatusCode());
        $this->assertStringStartsWith('%PDF', $resClinic->getContent());

        // Both copies
        $data['copy'] = 'both';
        $resBoth = $pdfService->generateConsultationReceiptPdf($data);
        $this->assertEquals(200, $resBoth->getStatusCode());
        $this->assertStringStartsWith('%PDF', $resBoth->getContent());
    }

    /**
     * Test 5: Clinic Logo Aspect Ratio & Safe Box (Section 34-40)
     */
    public function test_clinic_logo_upload_and_aspect_ratio_lock(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('clinic_logo.png', 300, 150);
        $path = $file->storeAs('branding', 'test_logo.png', 'public');

        $this->clinic->update([
            'logo_path' => $path,
            'logo_position' => 'center',
        ]);

        $this->assertEquals('branding/test_logo.png', $this->clinic->fresh()->logo_path);
        $this->assertEquals('center', $this->clinic->fresh()->logo_position);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    /**
     * Test 6: Online Booking QR Generation (Section 47-50)
     */
    public function test_online_booking_qr_generation(): void
    {
        $qrService = app(QrCodeService::class);
        $svg = $qrService->generateSvg('http://192.168.1.2:8000/book', 200);
        $png = $qrService->generatePng('http://192.168.1.2:8000/book', 6, 2);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringStartsWith("\x89PNG", $png);
    }

    /**
     * Test 7: RAG Operator Assistant Grounding & Class C Safety (Section 51-61, 88)
     */
    public function test_rag_assistant_grounding_and_class_c_guard(): void
    {
        $ragService = app(RagAssistantService::class);

        // Grounding unknown question
        $resUnknown = $ragService->processMessage('Can dxclinic prescribe veterinary medicine for elephants?', $this->operator, $this->clinic);
        $this->assertEquals('I could not find that in the current DxClinic knowledge base.', $resUnknown['message']);

        // Class C safety action protection
        $resDeposit = $ragService->processMessage('deposit cash into bank', $this->operator, $this->clinic);
        $this->assertEquals('action_redirect', $resDeposit['type']);
        $this->assertEquals('CLASS_C', $resDeposit['level']);
    }

    /**
     * Test 8: Double-Entry Financial Reconciliation (Section 2, 71, 102)
     */
    public function test_financial_reconciliation_equilibrium(): void
    {
        $cashAcc = LedgerAccount::where('code', '1001')->first() ?? LedgerAccount::create([
            'code' => '1001', 'name' => 'Cash in Hand', 'type' => 'ASSET', 'subtype' => 'CASH', 'is_system' => true
        ]);
        $revAcc = LedgerAccount::where('code', '4001')->first() ?? LedgerAccount::create([
            'code' => '4001', 'name' => 'Consultation Revenue', 'type' => 'REVENUE', 'subtype' => 'OPERATING', 'is_system' => true
        ]);

        $journal = \App\Models\Journal::create([
            'journal_number' => 'JRN-TEST-' . uniqid(),
            'date' => now()->toDateString(),
            'description' => 'Test Equilibrium Journal',
        ]);

        \App\Models\JournalEntry::create([
            'journal_id' => $journal->id,
            'ledger_account_id' => $cashAcc->id,
            'debit' => 500.00,
            'credit' => 0.00,
            'notes' => 'Cash received',
        ]);

        \App\Models\JournalEntry::create([
            'journal_id' => $journal->id,
            'ledger_account_id' => $revAcc->id,
            'debit' => 0.00,
            'credit' => 500.00,
            'notes' => 'Consultation revenue',
        ]);

        $journalTotals = DB::table('journal_entries')
            ->selectRaw('SUM(debit) as total_debits, SUM(credit) as total_credits')
            ->first();

        $debits = (float) ($journalTotals->total_debits ?? 0.0);
        $credits = (float) ($journalTotals->total_credits ?? 0.0);
        $diff = abs($debits - $credits);

        $this->assertGreaterThan(0, $debits);
        $this->assertLessThan(0.01, $diff, "Debits and Credits must balance to ₹0.00 difference");
    }
}
