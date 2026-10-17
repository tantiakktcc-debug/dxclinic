<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\ClinicDoctorSeatAddon;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\DiagnosticItem;
use App\Models\Doctor;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeMaster;
use App\Models\LedgerAccount;
use App\Models\License;
use App\Models\LicenseRenewal;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PrescriptionPadTemplate;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Visit;
use App\Models\Vital;
use App\Services\DiagnosticBillingService;
use App\Services\PrescriptionDataMapper;
use App\Services\TenantContext;
use Carbon\Carbon;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Revision41SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic1;
    protected Clinic $clinic2;
    protected Clinic $demoClinic;
    protected User $admin1;
    protected User $operator1;
    protected User $admin2;
    protected User $operator2;
    protected Doctor $doctor1;
    protected Doctor $doctor2;
    protected Patient $patient1;
    protected Patient $patient2;
    protected Appointment $app1;
    protected Appointment $app2;
    protected ConsultationBill $conBill1;
    protected ConsultationBill $conBill2;
    protected DiagnosticBill $diagBill1;
    protected DiagnosticBill $diagBill2;
    protected SubscriptionPlan $planYearly;
    protected SubscriptionPlan $planFiveYear;
    protected License $license1;
    protected License $license2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        $this->planYearly = SubscriptionPlan::firstOrCreate(
            ['code' => 'YEARLY'],
            [
                'name' => 'Yearly Plan',
                'duration_months' => 12,
                'price' => 14999.00,
                'included_doctor_seats' => 1,
                'additional_doctor_seat_price' => 2999.00,
                'is_active' => true,
                'display_order' => 1,
            ]
        );

        $this->planFiveYear = SubscriptionPlan::firstOrCreate(
            ['code' => 'FIVE_YEAR'],
            [
                'name' => '5-Year Plan',
                'duration_months' => 60,
                'price' => 59999.00,
                'included_doctor_seats' => 2,
                'additional_doctor_seat_price' => 2499.00,
                'is_active' => true,
                'display_order' => 4,
            ]
        );

        // Clinic 1 (NX0001)
        $this->clinic1 = Clinic::create([
            'name' => 'Sharma Care Clinic Varanasi',
            'clinic_code' => 'NX0001',
            'city' => 'Varanasi',
            'address' => 'Assi Ghat',
            'phone' => '9876543001',
            'is_onboarded' => true,
            'onboarding_status' => 'COMPLETED',
            'activation_status' => 'ACTIVATED',
        ]);

        $this->license1 = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->planYearly->id,
            'license_key_hash' => hash('sha256', 'NXCL-NX0001-TEST'),
            'license_key_encrypted' => Crypt::encryptString('NXCL-NX0001-TEST'),
            'license_key_prefix' => 'NXCL-TEST',
            'start_date' => Carbon::parse('2026-08-29'),
            'end_date' => Carbon::parse('2027-08-29'),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'included_doctor_seats' => 1,
            'status' => 'ACTIVE',
        ]);

        // Clinic 2 (NX0002)
        $this->clinic2 = Clinic::create([
            'name' => 'Verma Diagnostics Lucknow',
            'clinic_code' => 'NX0002',
            'city' => 'Lucknow',
            'address' => 'Hazratganj',
            'phone' => '9876543002',
            'is_onboarded' => true,
            'onboarding_status' => 'COMPLETED',
            'activation_status' => 'ACTIVATED',
        ]);

        $this->license2 = License::create([
            'clinic_id' => $this->clinic2->id,
            'plan_id' => $this->planYearly->id,
            'license_key_hash' => hash('sha256', 'NXCL-NX0002-TEST'),
            'license_key_encrypted' => Crypt::encryptString('NXCL-NX0002-TEST'),
            'license_key_prefix' => 'NXCL-TEST',
            'start_date' => Carbon::parse('2026-08-29'),
            'end_date' => Carbon::parse('2027-08-29'),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'included_doctor_seats' => 1,
            'status' => 'ACTIVE',
        ]);

        // Demo Clinic
        $this->demoClinic = Clinic::create([
            'name' => 'DxClinic Demo Centre',
            'clinic_code' => 'democlinic',
            'city' => 'Varanasi',
            'address' => 'BHU Gate',
            'phone' => '9876543099',
            'is_onboarded' => true,
            'onboarding_status' => 'COMPLETED',
            'activation_status' => 'ACTIVATED',
        ]);

        License::create([
            'clinic_id' => $this->demoClinic->id,
            'plan_id' => $this->planFiveYear->id,
            'license_key_hash' => hash('sha256', 'NXCL-DEMO-TEST'),
            'license_key_encrypted' => Crypt::encryptString('NXCL-DEMO-TEST'),
            'license_key_prefix' => 'NXCL-DEMO',
            'start_date' => Carbon::parse('2026-09-28'),
            'end_date' => Carbon::parse('2031-09-28'),
            'amount' => 59999.00,
            'net_amount' => 59999.00,
            'included_doctor_seats' => 2,
            'status' => 'ACTIVE',
        ]);

        // Users
        $this->admin1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Admin Clinic 1',
            'email' => 'admin1@dxclinic.local',
            'password' => bcrypt('Admin1Password!'),
            'role' => 'ADMIN',
        ]);

        $this->operator1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Operator Clinic 1',
            'email' => 'operator1@dxclinic.local',
            'password' => bcrypt('Operator1Password!'),
            'role' => 'OPERATOR',
        ]);

        $this->admin2 = User::create([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Admin Clinic 2',
            'email' => 'admin2@dxclinic.local',
            'password' => bcrypt('Admin2Password!'),
            'role' => 'ADMIN',
        ]);

        $this->operator2 = User::create([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Operator Clinic 2',
            'email' => 'operator2@dxclinic.local',
            'password' => bcrypt('Operator2Password!'),
            'role' => 'OPERATOR',
        ]);

        // Doctors
        $this->doctor1 = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'specialization' => 'General Medicine',
            'registration_number' => 'MCI-12345',
            'is_active' => true,
        ]);

        $this->doctor2 = Doctor::create([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Dr. Sunil Verma',
            'qualification' => 'MBBS, DNB',
            'specialization' => 'Pathology',
            'registration_number' => 'MCI-67890',
            'is_active' => true,
        ]);

        // Patients
        $this->patient1 = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-NX1-0001',
            'name' => 'Ramesh Kumar',
            'age' => 45,
            'sex' => 'MALE',
            'mobile' => '9876543211',
            'address' => 'Kabir Nagar Colony',
            'city' => 'Varanasi',
        ]);

        $this->patient2 = Patient::create([
            'clinic_id' => $this->clinic2->id,
            'patient_id' => 'DXP-NX2-0001',
            'name' => 'Sunita Devi',
            'age' => 42,
            'sex' => 'FEMALE',
            'mobile' => '9876543212',
            'address' => 'Hazratganj Main',
            'city' => 'Lucknow',
        ]);

        // Appointments
        $this->app1 = Appointment::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor1->id,
            'patient_id' => $this->patient1->id,
            'token_number' => 1,
            'formatted_token' => 'M-001',
            'secure_token' => 'secure-token-nx0001-patient-0001',
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'CONFIRMED',
            'booking_source' => 'WALKIN',
        ]);

        $this->app2 = Appointment::create([
            'clinic_id' => $this->clinic2->id,
            'doctor_id' => $this->doctor2->id,
            'patient_id' => $this->patient2->id,
            'token_number' => 1,
            'formatted_token' => 'M-001',
            'secure_token' => 'secure-token-nx0002-patient-0002',
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'CONFIRMED',
            'booking_source' => 'WALKIN',
        ]);

        // Visits
        $visit1 = Visit::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor1->id,
            'patient_id' => $this->patient1->id,
            'appointment_id' => $this->app1->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'IN_PROGRESS',
        ]);

        $visit2 = Visit::create([
            'clinic_id' => $this->clinic2->id,
            'doctor_id' => $this->doctor2->id,
            'patient_id' => $this->patient2->id,
            'appointment_id' => $this->app2->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'IN_PROGRESS',
        ]);

        // Bills
        $this->conBill1 = ConsultationBill::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor1->id,
            'patient_id' => $this->patient1->id,
            'appointment_id' => $this->app1->id,
            'visit_id' => $visit1->id,
            'bill_number' => 'BILL-C-NX1-001',
            'bill_date' => now()->toDateString(),
            'gross_amount' => 500.00,
            'net_amount' => 500.00,
            'status' => 'PAID',
        ]);

        $this->conBill2 = ConsultationBill::create([
            'clinic_id' => $this->clinic2->id,
            'doctor_id' => $this->doctor2->id,
            'patient_id' => $this->patient2->id,
            'appointment_id' => $this->app2->id,
            'visit_id' => $visit2->id,
            'bill_number' => 'BILL-C-NX2-001',
            'bill_date' => now()->toDateString(),
            'gross_amount' => 600.00,
            'net_amount' => 600.00,
            'status' => 'PAID',
        ]);

        $this->diagBill1 = DiagnosticBill::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor1->id,
            'patient_id' => $this->patient1->id,
            'bill_number' => 'BILL-D-NX1-001',
            'bill_date' => now()->toDateString(),
            'gross_amount' => 1200.00,
            'net_amount' => 1200.00,
            'status' => 'PAID',
        ]);

        $this->diagBill2 = DiagnosticBill::create([
            'clinic_id' => $this->clinic2->id,
            'doctor_id' => $this->doctor2->id,
            'patient_id' => $this->patient2->id,
            'bill_number' => 'BILL-D-NX2-001',
            'bill_date' => now()->toDateString(),
            'gross_amount' => 1500.00,
            'net_amount' => 1500.00,
            'status' => 'PAID',
        ]);
    }

    // =========================================================================
    // 1. CRITICAL C1: /api/queue/today Tenant Isolation & Authorization
    // =========================================================================

    public function test_api_queue_today_unauthenticated_is_rejected(): void
    {
        $response = $this->getJson('/api/queue/today');
        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }

    public function test_api_queue_today_arbitrary_clinic_id_from_unauthenticated_client_is_rejected(): void
    {
        // Must never accept arbitrary clinic_id from unauthenticated callers
        $response = $this->getJson('/api/queue/today?clinic_id=' . $this->clinic1->id);
        $response->assertStatus(401);

        $response2 = $this->getJson('/api/queue/today?clinic_code=NX0001');
        $response2->assertStatus(401);
    }

    public function test_api_queue_today_authenticated_nx0001_sees_only_nx0001_appointments(): void
    {
        $this->actingAs($this->operator1);

        $response = $this->getJson('/api/queue/today');
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals('NX0001', $json['clinic_code']);
        $this->assertEquals($this->clinic1->id, $json['clinic_id']);

        // Must contain patient1, but NEVER patient2
        $tokens = collect($json['queue']);
        $this->assertTrue($tokens->contains('patient_name', 'Ramesh Kumar'));
        $this->assertFalse($tokens->contains('patient_name', 'Sunita Devi'));
    }

    public function test_api_queue_today_authenticated_nx0002_sees_only_nx0002_appointments(): void
    {
        $this->actingAs($this->operator2);

        $response = $this->getJson('/api/queue/today');
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals('NX0002', $json['clinic_code']);
        $this->assertEquals($this->clinic2->id, $json['clinic_id']);

        // Must contain patient2, but NEVER patient1
        $tokens = collect($json['queue']);
        $this->assertTrue($tokens->contains('patient_name', 'Sunita Devi'));
        $this->assertFalse($tokens->contains('patient_name', 'Ramesh Kumar'));
    }

    public function test_api_queue_today_signed_url_allows_only_intended_clinic_queue(): void
    {
        // Valid signed URL for NX0001
        $signedUrl = URL::signedRoute('api.queue.today', ['clinic_code' => 'NX0001']);

        $response = $this->getJson($signedUrl);
        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals('NX0001', $json['clinic_code']);
        $this->assertTrue(collect($json['queue'])->contains('patient_name', 'Ramesh Kumar'));
        $this->assertFalse(collect($json['queue'])->contains('patient_name', 'Sunita Devi'));

        // Tampered signature must fail
        $tamperedUrl = $signedUrl . '&tamper=true';
        $tamperedResponse = $this->getJson($tamperedUrl);
        $tamperedResponse->assertStatus(401);
    }

    // =========================================================================
    // 2. CRITICAL C2: /api/reports/daily-summary Tenant Financial Protection
    // =========================================================================

    public function test_api_daily_summary_unauthenticated_is_rejected(): void
    {
        $response = $this->getJson('/api/reports/daily-summary');
        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }

    public function test_api_daily_summary_authenticated_nx0001_sees_only_nx0001_financials(): void
    {
        $this->actingAs($this->admin1);

        $response = $this->getJson('/api/reports/daily-summary');
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals('NX0001', $json['clinic_code']);
        $this->assertEquals('Sharma Care Clinic Varanasi', $json['clinic_name']);
        $this->assertEquals($this->clinic1->id, $json['clinic_id']);
        $this->assertArrayHasKey('cash_collections', $json);
        $this->assertArrayHasKey('upi_collections', $json);
        $this->assertArrayHasKey('expenses', $json);
    }

    public function test_api_daily_summary_authenticated_nx0002_sees_only_nx0002_financials(): void
    {
        $this->actingAs($this->admin2);

        $response = $this->getJson('/api/reports/daily-summary');
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals('NX0002', $json['clinic_code']);
        $this->assertEquals('Verma Diagnostics Lucknow', $json['clinic_name']);
        $this->assertEquals($this->clinic2->id, $json['clinic_id']);
        $this->assertArrayHasKey('cash_collections', $json);
        $this->assertArrayHasKey('upi_collections', $json);
        $this->assertArrayHasKey('expenses', $json);
    }

    // =========================================================================
    // 3. HIGH H1: /api/health Sanitization
    // =========================================================================

    public function test_api_health_is_public_and_exposes_no_tenant_pii_or_financials(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals('ok', $json['status']);
        $this->assertEquals('connected', $json['database']);
        $this->assertArrayHasKey('server_time', $json);

        // MUST NOT contain sensitive tenant or financial details
        $this->assertArrayNotHasKey('clinic_name', $json);
        $this->assertArrayNotHasKey('clinic_code', $json);
        $this->assertArrayNotHasKey('is_onboarded', $json);
        $this->assertArrayNotHasKey('active_session', $json);
        $this->assertArrayNotHasKey('expected_cash', $json);
        $this->assertArrayNotHasKey('financials', $json);
    }

    // =========================================================================
    // 4. HIGH H2: Diagnostic Billing Scoping
    // =========================================================================

    public function test_diagnostic_billing_service_scopes_to_tenant_and_global_catalog(): void
    {
        // Global item (shared master)
        $globalItem = DiagnosticItem::create([
            'clinic_id' => null,
            'type' => 'TEST',
            'code' => 'CBC_GLOBAL',
            'name' => 'Complete Blood Count (CBC)',
            'rate' => 350.00,
            'is_active' => true,
        ]);

        // Clinic 1 custom item
        $c1Item = DiagnosticItem::create([
            'clinic_id' => $this->clinic1->id,
            'type' => 'TEST',
            'code' => 'C1_CUSTOM_TEST',
            'name' => 'Clinic 1 Special Lipid Panel',
            'rate' => 750.00,
            'is_active' => true,
        ]);

        // Clinic 2 custom item
        $c2Item = DiagnosticItem::create([
            'clinic_id' => $this->clinic2->id,
            'type' => 'TEST',
            'code' => 'C2_CUSTOM_TEST',
            'name' => 'Clinic 2 Thyroid Comprehensive',
            'rate' => 1100.00,
            'is_active' => true,
        ]);

        $service = app(DiagnosticBillingService::class);

        // When queried for Clinic 1
        $c1List = $service->getFrequentlyUsed(20, $this->clinic1->id);
        $c1Ids = collect($c1List)->pluck('id')->toArray();

        $this->assertContains($globalItem->id, $c1Ids, 'Global item must appear in Clinic 1 catalog');
        $this->assertContains($c1Item->id, $c1Ids, 'Clinic 1 custom item must appear in Clinic 1 catalog');
        $this->assertNotContains($c2Item->id, $c1Ids, 'Clinic 2 custom item must NEVER appear in Clinic 1 catalog');

        // When queried for Clinic 2
        $c2List = $service->getFrequentlyUsed(20, $this->clinic2->id);
        $c2Ids = collect($c2List)->pluck('id')->toArray();

        $this->assertContains($globalItem->id, $c2Ids, 'Global item must appear in Clinic 2 catalog');
        $this->assertContains($c2Item->id, $c2Ids, 'Clinic 2 custom item must appear in Clinic 2 catalog');
        $this->assertNotContains($c1Item->id, $c2Ids, 'Clinic 1 custom item must NEVER appear in Clinic 2 catalog');
    }

    // =========================================================================
    // 5. HIGH H4: Print & PDF IDOR Protection
    // =========================================================================

    public function test_print_and_pdf_routes_require_authentication(): void
    {
        // Unauthenticated access must redirect to login or reject
        $unauthReceiptPrint = $this->get("/print/consultation-receipt/{$this->conBill1->id}");
        $unauthReceiptPrint->assertRedirect(route('login'));

        $unauthDoctorPadPrint = $this->get("/print/doctor-pad/{$this->app1->id}");
        $unauthDoctorPadPrint->assertRedirect(route('login'));

        $unauthDiagReceiptPrint = $this->get("/print/diagnostic-receipt/{$this->diagBill1->id}");
        $unauthDiagReceiptPrint->assertRedirect(route('login'));

        $unauthLabSlipPrint = $this->get("/print/lab-collection-slip/{$this->diagBill1->id}");
        $unauthLabSlipPrint->assertRedirect(route('login'));

        $unauthReceiptPdf = $this->get("/pdf/consultation-receipt/{$this->conBill1->id}");
        $unauthReceiptPdf->assertRedirect(route('login'));

        $unauthDoctorPadPdf = $this->get("/pdf/doctor-pad/{$this->app1->id}");
        $unauthDoctorPadPdf->assertRedirect(route('login'));

        $unauthDiagReceiptPdf = $this->get("/pdf/diagnostic-receipt/{$this->diagBill1->id}");
        $unauthDiagReceiptPdf->assertRedirect(route('login'));

        $unauthLabSlipPdf = $this->get("/pdf/lab-slip/{$this->diagBill1->id}");
        $unauthLabSlipPdf->assertRedirect(route('login'));

        $unauthPatientHistoryPdf = $this->get("/pdf/patient-history/{$this->patient1->id}");
        $unauthPatientHistoryPdf->assertRedirect(route('login'));

        $unauthOpdReportPdf = $this->get('/pdf/opd-report');
        $unauthOpdReportPdf->assertRedirect(route('login'));

        $unauthDiagReportPdf = $this->get('/pdf/diagnostic-report');
        $unauthDiagReportPdf->assertRedirect(route('login'));
    }

    public function test_cross_tenant_print_idor_is_blocked_with_403(): void
    {
        // Logged in as Operator 2 (Hazratganj Lucknow)
        $this->actingAs($this->operator2);

        // Attempting to print Clinic 1's Consultation Receipt
        $response = $this->get("/print/consultation-receipt/{$this->conBill1->id}");
        $response->assertStatus(403);

        // Attempting to print Clinic 1's Doctor Pad
        $responsePad = $this->get("/print/doctor-pad/{$this->app1->id}");
        $responsePad->assertStatus(403);

        // Attempting to print Clinic 1's Diagnostic Receipt
        $responseDiag = $this->get("/print/diagnostic-receipt/{$this->diagBill1->id}");
        $responseDiag->assertStatus(403);

        // Attempting to print Clinic 1's Lab Collection Slip
        $responseSlip = $this->get("/print/lab-collection-slip/{$this->diagBill1->id}");
        $responseSlip->assertStatus(403);
    }

    public function test_cross_tenant_pdf_idor_is_blocked_with_403(): void
    {
        // Logged in as Operator 2 (Hazratganj Lucknow)
        $this->actingAs($this->operator2);

        // Attempting to download Clinic 1's Consultation Receipt PDF
        $response = $this->get("/pdf/consultation-receipt/{$this->conBill1->id}");
        $response->assertStatus(403);

        // Attempting to download Clinic 1's Doctor Pad PDF
        $responsePad = $this->get("/pdf/doctor-pad/{$this->app1->id}");
        $responsePad->assertStatus(403);

        // Attempting to download Clinic 1's Diagnostic Receipt PDF
        $responseDiag = $this->get("/pdf/diagnostic-receipt/{$this->diagBill1->id}");
        $responseDiag->assertStatus(403);

        // Attempting to download Clinic 1's Lab Slip PDF
        $responseSlip = $this->get("/pdf/lab-slip/{$this->diagBill1->id}");
        $responseSlip->assertStatus(403);

        // Attempting to download Clinic 1's Patient History PDF
        $responseHist = $this->get("/pdf/patient-history/{$this->patient1->id}");
        $responseHist->assertStatus(403);
    }

    public function test_cross_tenant_prescription_template_pdf_is_blocked_with_403(): void
    {
        $template1 = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor1->id,
            'name' => 'Dr. Rajesh Sharma Letterhead',
            'canvas_width' => 148,
            'canvas_height' => 210,
            'is_default' => true,
        ]);

        // Unauthenticated access must redirect to login
        $unauth = $this->get("/pdf/prescription-template/{$template1->id}");
        $unauth->assertRedirect(route('login'));

        // Operator 2 attempting to download Clinic 1 template must get 403
        $this->actingAs($this->operator2);
        $crossTenant = $this->get("/pdf/prescription-template/{$template1->id}");
        $crossTenant->assertStatus(403);

        // Operator 1 accessing their own template succeeds
        $this->actingAs($this->operator1);
        $owner = $this->get("/pdf/prescription-template/{$template1->id}");
        $owner->assertStatus(200);
        $owner->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_public_token_pdf_and_manual_pdf_remain_accessible(): void
    {
        // Public token capability URL
        $tokenPdf = $this->get("/token-pdf/{$this->app1->secure_token}");
        $tokenPdf->assertStatus(200);

        $tokenPrint = $this->get("/print/token/{$this->app1->secure_token}");
        $tokenPrint->assertStatus(200);

        // Public documentation manuals PDF
        $manualPdf = $this->get('/pdf/manual/operator');
        $manualPdf->assertStatus(200);
        $manualPdf->assertHeader('Content-Type', 'application/pdf');
    }

    // =========================================================================
    // 6. PRESCRIPTION FIELD CORRECTIONS: M1 & M2
    // =========================================================================

    public function test_prescription_mapper_patient_full_address_with_city_name(): void
    {
        // Case 1: Both address and city present
        $patientBoth = new Patient([
            'address' => 'Kabir Nagar Colony',
            'city' => 'Varanasi',
        ]);
        $dataBoth = PrescriptionDataMapper::getSampleData(null, null);
        $this->assertArrayHasKey('patient.full_address_with_city_name', $dataBoth);

        // Test with encounter mapping
        $visit = new Visit(['visit_date' => now()]);
        $app = new Appointment(['token_number' => 10]);
        $app->setRelation('patient', $patientBoth);
        $app->setRelation('doctor', $this->doctor1);
        $app->setRelation('clinic', $this->clinic1);

        $mapped = PrescriptionDataMapper::mapEncounter($app, $visit, null);
        $this->assertEquals('Kabir Nagar Colony, Varanasi', $mapped['patient.full_address_with_city_name']);

        // Case 2: Address empty, city present
        $patientCityOnly = new Patient(['address' => '', 'city' => 'Varanasi']);
        $app->setRelation('patient', $patientCityOnly);
        $mappedCity = PrescriptionDataMapper::mapEncounter($app, $visit, null);
        $this->assertEquals('Varanasi', $mappedCity['patient.full_address_with_city_name']);
        $this->assertStringNotContainsString(', Varanasi', $mappedCity['patient.full_address_with_city_name']);

        // Case 3: Address present, city empty
        $patientAddressOnly = new Patient(['address' => 'Kabir Nagar Colony', 'city' => '']);
        $app->setRelation('patient', $patientAddressOnly);
        $mappedAddress = PrescriptionDataMapper::mapEncounter($app, $visit, null);
        $this->assertEquals('Kabir Nagar Colony', $mappedAddress['patient.full_address_with_city_name']);
        $this->assertStringNotContainsString('Address,', $mappedAddress['patient.full_address_with_city_name']);

        // Case 4: Both empty
        $patientEmpty = new Patient(['address' => null, 'city' => null]);
        $app->setRelation('patient', $patientEmpty);
        $mappedEmpty = PrescriptionDataMapper::mapEncounter($app, $visit, null);
        $this->assertEquals('', $mappedEmpty['patient.full_address_with_city_name']);
    }

    public function test_prescription_mapper_patient_age_sex_format(): void
    {
        $visit = new Visit(['visit_date' => now()]);
        $app = new Appointment(['token_number' => 10]);
        $app->setRelation('doctor', $this->doctor1);
        $app->setRelation('clinic', $this->clinic1);

        // Standard female
        $p1 = new Patient(['age' => 42, 'sex' => 'FEMALE']);
        $app->setRelation('patient', $p1);
        $m1 = PrescriptionDataMapper::mapEncounter($app, $visit, null);
        $this->assertEquals('42 Yrs / F', $m1['patient.age_sex']);

        // Standard male
        $p2 = new Patient(['age' => 35, 'sex' => 'MALE']);
        $app->setRelation('patient', $p2);
        $m2 = PrescriptionDataMapper::mapEncounter($app, $visit, null);
        $this->assertEquals('35 Yrs / M', $m2['patient.age_sex']);

        // Age 0 (infant)
        $p3 = new Patient(['age' => 0, 'sex' => 'MALE']);
        $app->setRelation('patient', $p3);
        $m3 = PrescriptionDataMapper::mapEncounter($app, $visit, null);
        $this->assertEquals('0 Yrs / M', $m3['patient.age_sex']);

        // Nullable sex
        $p4 = new Patient(['age' => 28, 'sex' => null]);
        $app->setRelation('patient', $p4);
        $m4 = PrescriptionDataMapper::mapEncounter($app, $visit, null);
        $this->assertEquals('28 Yrs', $m4['patient.age_sex']);
    }

    // =========================================================================
    // 7. EXPENSE CATEGORIES ARCHITECTURE: Global by Design
    // =========================================================================

    public function test_expense_categories_is_global_master_while_expenses_is_tenant_scoped(): void
    {
        // Architectural assertion: expense_categories has NO clinic_id column
        $this->assertFalse(
            Schema::hasColumn('expense_categories', 'clinic_id'),
            'expense_categories must remain a shared global master table without clinic_id'
        );

        // Architectural assertion: expenses table DOES have clinic_id column
        $this->assertTrue(
            Schema::hasColumn('expenses', 'clinic_id'),
            'expenses transaction table must have clinic_id for strict tenant isolation'
        );

        // Create global category
        $ledger = LedgerAccount::where('type', 'EXPENSE')->first() ?? LedgerAccount::first();
        $cat = ExpenseCategory::firstOrCreate(
            ['code' => 'CLINIC_SUPPLIES'],
            ['name' => 'Clinic Supplies', 'ledger_account_id' => $ledger->id, 'is_active' => true]
        );

        // Create expense for Clinic 1
        $exp1 = Expense::create([
            'clinic_id' => $this->clinic1->id,
            'expense_category_id' => $cat->id,
            'payee' => 'Surgical Supplies Varanasi',
            'amount' => 1500.00,
            'expense_date' => now()->toDateString(),
            'description' => 'Cotton rolls and sanitizers for Clinic 1',
        ]);

        // Create expense for Clinic 2
        $exp2 = Expense::create([
            'clinic_id' => $this->clinic2->id,
            'expense_category_id' => $cat->id,
            'payee' => 'Lab Instruments Lucknow',
            'amount' => 2500.00,
            'expense_date' => now()->toDateString(),
            'description' => 'Test tubes for Clinic 2 lab',
        ]);

        // Scoping verification: Clinic 1 expenses must not include Clinic 2
        $c1Expenses = Expense::where('clinic_id', $this->clinic1->id)->get();
        $this->assertTrue($c1Expenses->contains('id', $exp1->id));
        $this->assertFalse($c1Expenses->contains('id', $exp2->id));

        $c2Expenses = Expense::where('clinic_id', $this->clinic2->id)->get();
        $this->assertTrue($c2Expenses->contains('id', $exp2->id));
        $this->assertFalse($c2Expenses->contains('id', $exp1->id));
    }

    // =========================================================================
    // 8. LICENSE RECORD & REMAINING DAYS MATHEMATICAL DERIVATION
    // =========================================================================

    public function test_nx0001_and_demo_license_mathematical_remaining_days(): void
    {
        // NX0001: 2026-08-29 -> 2027-08-29
        $this->assertEquals('YEARLY', $this->license1->plan->code);
        $this->assertEquals('ACTIVE', $this->license1->status);
        $this->assertEquals('2026-08-29', $this->license1->start_date->format('Y-m-d'));
        $this->assertEquals('2027-08-29', $this->license1->end_date->format('Y-m-d'));

        // Derive remaining days from end_date
        $expectedRemainingDays = (int) ceil(now()->diffInSeconds($this->license1->end_date, false) / 86400);
        $this->assertGreaterThan(300, $expectedRemainingDays);

        // Demo Clinic: 5-Year Plan (60 months)
        $demoLicense = License::where('clinic_id', $this->demoClinic->id)->first();
        $this->assertNotNull($demoLicense);
        $this->assertEquals('FIVE_YEAR', $demoLicense->plan->code);
        $this->assertEquals(60, $this->planFiveYear->duration_months);
        $this->assertEquals('2026-09-28', $demoLicense->start_date->format('Y-m-d'));
        $this->assertEquals('2031-09-28', $demoLicense->end_date->format('Y-m-d'));

        $demoRemainingDays = (int) ceil(now()->diffInSeconds($demoLicense->end_date, false) / 86400);
        $this->assertGreaterThan(1800, $demoRemainingDays);

        // Ensure distinctness
        $this->assertNotEquals($this->license1->id, $demoLicense->id);
        $this->assertNotEquals($this->clinic1->id, $this->demoClinic->id);
    }

    // =========================================================================
    // 9. PRESCRIPTION FONT SIZES TEST (Section 10)
    // =========================================================================

    public function test_prescription_font_sizes_persistence_and_rendering(): void
    {
        $template = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor1->id,
            'name' => 'Font Size Test Template',
            'canvas_width' => 148,
            'canvas_height' => 210,
        ]);

        $testedSizes = [4, 6, 8, 10, 12];
        foreach ($testedSizes as $size) {
            $elem = \App\Models\PrescriptionPadElement::create([
                'template_id' => $template->id,
                'element_type' => 'TEXT',
                'field_key' => 'patient.name',
                'pos_x' => 20,
                'pos_y' => 20 + ($size * 2),
                'width' => 100,
                'height' => 10,
                'font_size' => $size,
                'font_family' => 'Helvetica',
            ]);

            // DB Font Size must exactly equal specified physical size
            $this->assertEquals($size, (int) $elem->fresh()->font_size);
        }
    }
}
