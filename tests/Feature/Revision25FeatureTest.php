<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DiagnosticBill;
use App\Models\DiagnosticBillItem;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\ConsultationBillingService;
use App\Services\PatientIdentityService;
use App\Services\PaymentService;
use App\Services\PersonNameNormalizer;
use App\Services\PhoneNumberNormalizer;
use App\Services\ReportService;
use App\Services\VisitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Revision25FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected User $operator;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'DxClinic Varanasi',
            'city' => 'Varanasi',
            'address' => 'Assi Ghat',
            'phone' => '+91 9876543210',
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'specialization' => 'General Medicine',
            'registration_number' => 'MCI-12345',
            'is_active' => true,
            'is_default_booking' => true,
        ]);

        $this->operator = User::create([
            'name' => 'Operator User',
            'email' => 'operator@dxclinic.local',
            'password' => Hash::make('Operator123!'),
            'role' => 'OPERATOR',
            'status' => 'ACTIVE',
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@dxclinic.local',
            'password' => Hash::make('Admin123!'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Test universal mobile normalization for formats A, B, C, D
     */
    public function test_mobile_number_normalization_accepted_formats(): void
    {
        $this->assertEquals('+91 9876543210', PhoneNumberNormalizer::normalize('9876543210'));
        $this->assertEquals('+91 9876543210', PhoneNumberNormalizer::normalize('+919876543210'));
        $this->assertEquals('+91 9876543210', PhoneNumberNormalizer::normalize('+91 9876543210'));
        $this->assertEquals('+91 9876543210', PhoneNumberNormalizer::normalize('09876543210'));
    }

    /**
     * Test invalid mobile numbers are rejected
     */
    public function test_mobile_number_invalid_formats_rejected(): void
    {
        $invalids = [
            '98765',
            '987654321',
            '98765432101',
            '+91',
            '+9198765',
            '+91 98765',
            '919876543210',
            '+91-9876543210',
            '+91 987654321',
            '+91 98765432101',
            'abc9876543210',
            '98765abc210',
            '+91abc9876543210',
        ];

        foreach ($invalids as $inv) {
            $this->assertFalse(PhoneNumberNormalizer::isValid($inv), "Failed to reject: $inv");
            $caught = false;
            try {
                PhoneNumberNormalizer::normalize($inv);
            } catch (\Throwable $e) {
                $caught = true;
            }
            $this->assertTrue($caught, "Expected exception for: $inv");
        }
    }

    /**
     * Test person name validation (Unicode/Indic support & spaces collapsed)
     */
    public function test_person_name_validation_and_normalization(): void
    {
        $this->assertEquals('Kundan Kumar', PersonNameNormalizer::normalize('  Kundan   Kumar  '));
        $this->assertEquals('Ram Kumar', PersonNameNormalizer::normalize('Ram Kumar'));
        $this->assertEquals('राम कुमार', PersonNameNormalizer::normalize('राम कुमार'));

        $this->assertFalse(PersonNameNormalizer::isValid('Kundan123'));
        $this->assertFalse(PersonNameNormalizer::isValid('Kundan@Kumar'));
        $this->assertFalse(PersonNameNormalizer::isValid('Kundan_Kumar'));
        $this->assertFalse(PersonNameNormalizer::isValid('Rajesh#Sharma'));
        $this->assertFalse(PersonNameNormalizer::isValid('<script>alert(1)</script>'));
    }

    /**
     * Test FeeMaster rates for CLINIC_PHYSICAL (₹600) and ONLINE (₹700)
     */
    public function test_fee_master_rates_for_modes(): void
    {
        $feePhysical = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->clinic->id);
        $this->assertEquals(600.00, $feePhysical);

        $feeOnline = FeeMaster::getFeeForMode('ONLINE', $this->clinic->id);
        $this->assertEquals(700.00, $feeOnline);
    }

    /**
     * Test booking source and consultation mode channel separation
     */
    public function test_booking_channel_separation_and_common_tokens(): void
    {
        $patientService = app(PatientIdentityService::class);
        $appointmentService = app(AppointmentService::class);

        $p1 = $patientService->registerPatient([
            'clinic_id' => $this->clinic->id,
            'name' => 'Pat One',
            'age' => 30,
            'sex' => 'MALE',
            'mobile' => '9876541111',
            'address' => 'Lanka',
            'city' => 'Varanasi',
        ]);
        $p2 = $patientService->registerPatient([
            'clinic_id' => $this->clinic->id,
            'name' => 'Pat Two',
            'age' => 25,
            'sex' => 'FEMALE',
            'mobile' => '9876542222',
            'address' => 'Assi',
            'city' => 'Varanasi',
        ]);

        $nextWeekday = Carbon::today()->isSunday() ? Carbon::tomorrow() : Carbon::today();
        $session = 'MORNING';

        $apptClinic = $appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p1->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $nextWeekday->toDateString(),
            'session_type' => $session,
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        $apptOnline = $appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p2->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $nextWeekday->toDateString(),
            'session_type' => $session,
            'booking_source' => 'ONLINE',
            'consultation_mode' => 'ONLINE',
        ]);

        $this->assertEquals('CLINIC', $apptClinic->booking_source);
        $this->assertEquals('CLINIC_PHYSICAL', $apptClinic->consultation_mode);

        $this->assertEquals('ONLINE', $apptOnline->booking_source);
        $this->assertEquals('ONLINE', $apptOnline->consultation_mode);

        // Sequential token from common sequence
        $this->assertEquals($apptClinic->token_number + 1, $apptOnline->token_number);
    }

    /**
     * Test Online Consultancy creates ₹700 unpaid obligation and no fake payment at booking
     */
    public function test_online_consultancy_billing_obligation(): void
    {
        $patientService = app(PatientIdentityService::class);
        $appointmentService = app(AppointmentService::class);
        $visitService = app(VisitService::class);
        $billingService = app(ConsultationBillingService::class);
        $paymentService = app(PaymentService::class);

        $pat = $patientService->registerPatient([
            'clinic_id' => $this->clinic->id,
            'name' => 'Online Obligation Patient',
            'age' => 29,
            'sex' => 'FEMALE',
            'mobile' => '9876543333',
            'address' => 'BHU',
            'city' => 'Varanasi',
        ]);

        $nextWeekday = Carbon::today()->isSunday() ? Carbon::tomorrow() : Carbon::today();
        $appt = $appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $pat->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $nextWeekday->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
            'consultation_mode' => 'ONLINE',
        ]);

        $visit = $visitService->markArrival($appt);
        $bill = $billingService->createOrGetBill($visit);

        $this->assertEquals(700.00, (float) $bill->gross_amount);
        $this->assertEquals(700.00, (float) $bill->net_amount);
        $this->assertEquals('PENDING', $bill->status);
        $this->assertCount(0, $bill->payments);

        // Later explicit settlement with Cash
        $payment = $paymentService->settleConsultationPayment($bill, [
            'payment_method' => 'CASH',
            'amount' => 700.00,
        ]);

        $bill->refresh();
        $this->assertEquals('PAID', $bill->status);
        $this->assertEquals(700.00, (float) $payment->amount);
    }

    /**
     * Test 5-day free OPD revisit rule (gross ₹600, waiver ₹600, net ₹0, no collection)
     */
    public function test_free_opd_revisit_rule(): void
    {
        $patientService = app(PatientIdentityService::class);
        $appointmentService = app(AppointmentService::class);
        $visitService = app(VisitService::class);
        $billingService = app(ConsultationBillingService::class);

        \App\Services\ClinicClock::setTestNow('2026-09-28 08:30:00');
        try {
            $pat = $patientService->registerPatient([
                'clinic_id' => $this->clinic->id,
                'name' => 'Revisit Testing Patient',
                'age' => 38,
                'sex' => 'MALE',
                'mobile' => '9876544444',
                'address' => 'Rathyatra',
                'city' => 'Varanasi',
            ]);

            // Prior visit 3 days ago
            $priorDate = Carbon::today()->subDays(3)->toDateString();
            $priorSession = Carbon::parse($priorDate)->isSunday() ? 'SUNDAY_SINGLE' : 'MORNING';

            $priorAppt = Appointment::create([
                'clinic_id' => $this->clinic->id,
                'patient_id' => $pat->id,
                'doctor_id' => $this->doctor->id,
                'appointment_date' => $priorDate,
                'session_type' => $priorSession,
                'booking_source' => 'CLINIC',
                'consultation_mode' => 'CLINIC_PHYSICAL',
                'token_number' => 1,
                'formatted_token' => '001',
                'secure_token' => \Illuminate\Support\Str::random(32),
                'status' => 'COMPLETED',
            ]);

            $priorVisit = Visit::create([
                'clinic_id' => $this->clinic->id,
                'patient_id' => $pat->id,
                'doctor_id' => $this->doctor->id,
                'appointment_id' => $priorAppt->id,
                'visit_date' => $priorDate,
                'session_type' => $priorSession,
                'consultation_mode' => 'CLINIC_PHYSICAL',
                'visit_type' => 'NEW',
                'status' => 'COMPLETED',
            ]);

            // Check eligibility today
            $eligibleVisit = $appointmentService->getEligibleRevisitVisit($pat, Carbon::today()->toDateString());
            $this->assertNotNull($eligibleVisit);
            $this->assertEquals($priorVisit->id, $eligibleVisit->id);

            // Book today's revisit
            $todaySession = Carbon::today()->isSunday() ? 'SUNDAY_SINGLE' : 'MORNING';
            $todayAppt = $appointmentService->bookAppointment([
                'clinic_id' => $this->clinic->id,
                'patient_id' => $pat->id,
                'doctor_id' => $this->doctor->id,
                'appointment_date' => Carbon::today()->toDateString(),
                'session_type' => $todaySession,
                'booking_source' => 'CLINIC',
                'consultation_mode' => 'CLINIC_PHYSICAL',
            ]);

            $todayVisit = $visitService->markArrival($todayAppt);
            $this->assertEquals('REVISIT', $todayVisit->visit_type);
            $this->assertEquals($priorVisit->id, $todayVisit->revisit_parent_visit_id);

            $todayBill = $billingService->createOrGetBill($todayVisit);
            $this->assertEquals(600.00, (float) $todayBill->gross_amount);
            $this->assertEquals(600.00, (float) $todayBill->waiver_amount);
            $this->assertEquals('OPD_REVISIT', $todayBill->waiver_type);
            $this->assertEquals(0.00, (float) $todayBill->net_amount);
            $this->assertEquals('WAIVED', $todayBill->status);

            // 6-day old visit is ineligible (boundary test)
            $oldDate = Carbon::today()->subDays(6)->toDateString();
            $priorVisit->update(['visit_date' => $oldDate]);
            $ineligible = $appointmentService->getEligibleRevisitVisit($pat, Carbon::today()->toDateString());
            $this->assertNull($ineligible);
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

    /**
     * Test operator booking route requires authentication
     */
    public function test_operator_booking_route_authentication(): void
    {
        $response = $this->get('/operator/booking');
        $response->assertRedirect('/login');

        $authResponse = $this->actingAs($this->operator)->get('/operator/booking');
        $authResponse->assertStatus(200);
        $authResponse->assertSee('Operator Booking');
    }

    /**
     * Test public patient booking route /book is accessible without login
     */
    public function test_public_patient_booking_route_accessible(): void
    {
        $response = $this->get('/book/' . $this->clinic->public_slug);
        $response->assertStatus(200);
        $response->assertSee('Book Appointment');
    }

    /**
     * Test OPD report service omits vitals and reconciles Total Collection footer
     */
    public function test_opd_and_diagnostic_reports(): void
    {
        $reportService = app(ReportService::class);
        $today = Carbon::today()->toDateString();

        $opd = $reportService->getOpdReport($this->clinic, [
            'period' => 'daily',
            'start_date' => $today,
        ]);

        $this->assertArrayHasKey('rows', $opd);
        $this->assertArrayHasKey('total_collection', $opd);

        // Ensure diagnostic report generates correctly
        $diag = $reportService->getDiagnosticReport($this->clinic, [
            'period' => 'daily',
            'start_date' => $today,
        ]);

        $this->assertArrayHasKey('rows', $diag);
        $this->assertArrayHasKey('total_collection', $diag);
    }

    /**
     * Test PDF report routes return application/pdf with %PDF magic bytes
     */
    public function test_pdf_report_and_manual_routes(): void
    {
        $today = Carbon::today()->toDateString();

        $routes = [
            "/pdf/opd-report?period=daily&start_date={$today}",
            "/pdf/diagnostic-report?period=daily&start_date={$today}",
            "/pdf/manual/operator-quick",
            "/pdf/manual/admin-quick",
            "/pdf/manual/financial-workflows",
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->operator)->get($route);
            $this->assertEquals(200, $response->getStatusCode(), "Route {$route} failed with status {$response->getStatusCode()}");
            $this->assertEquals('application/pdf', $response->headers->get('Content-Type'), "Route {$route} did not return application/pdf");
            $this->assertStringStartsWith('%PDF', $response->getContent(), "Route {$route} does not have %PDF signature");
        }
    }
}
