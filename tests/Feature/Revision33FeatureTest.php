<?php

namespace Tests\Feature;

use App\Livewire\Operator\ArrivalQueue;
use App\Livewire\Operator\ConsultationBilling;
use App\Livewire\Operator\OperatorBooking;
use App\Livewire\Pwa\PatientBooking;
use App\Livewire\Settings\BookingQrManager;
use App\Livewire\Settings\FeeMasterManager;
use App\Livewire\Settings\PrescriptionPadDesigner;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\Doctor;
use App\Models\ExpenseCategory;
use App\Models\FeeMaster;
use App\Models\Patient;
use App\Models\PrescriptionPadElement;
use App\Models\PrescriptionPadTemplate;
use App\Models\User;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\ClinicClock;
use App\Services\ClinicScheduleService;
use App\Services\ConsultationBillingService;
use App\Services\PatientIdentityService;
use App\Services\PrescriptionPdfRenderer;
use Carbon\Carbon;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\FeeMasterSeeder;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Revision33FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctorA;
    protected Doctor $doctorB;
    protected User $adminUser;
    protected User $operatorUser;
    protected User $doctorUserA;
    protected ClinicScheduleService $scheduleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        // Monday 2026-09-28 07:30 IST
        Carbon::setTestNow(Carbon::parse('2026-09-28 07:30:00', 'Asia/Kolkata'));

        $this->clinic = Clinic::create([
            'name' => 'DxClinic Test Facility',
            'phone' => '9876543210',
            'address' => 'Station Road',
            'city' => 'Varanasi',
            'is_onboarded' => true,
            'booking_qr_prod_url' => 'https://dxclinic.example.local/book',
            'schedule_config' => [
                'monday' => ['morning' => ['08:00', '09:00'], 'evening' => ['17:00', '19:00']],
                'tuesday' => ['morning' => ['08:00', '09:00'], 'evening' => ['17:00', '19:00']],
                'wednesday' => ['morning' => ['08:00', '09:00'], 'evening' => ['17:00', '19:00']],
                'thursday' => ['morning' => ['08:00', '09:00'], 'evening' => ['17:00', '19:00']],
                'friday' => ['morning' => ['08:00', '09:00'], 'evening' => ['17:00', '19:00']],
                'saturday' => ['morning' => ['08:00', '09:00'], 'evening' => ['17:00', '19:00']],
                'sunday' => ['single' => ['08:00', '10:00']],
            ],
        ]);

        $this->seed(FeeMasterSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);

        $this->doctorA = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Amit Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-11111',
            'specialization' => 'Cardiologist',
            'contact_number' => '+91 9876543001',
            'is_active' => true,
            'schedule_config' => [
                'monday' => ['morning' => ['10:00', '12:00'], 'evening' => ['18:00', '21:00']],
                'tuesday' => ['morning' => ['10:00', '12:00'], 'evening' => ['18:00', '21:00']],
                'wednesday' => ['morning' => ['10:00', '12:00'], 'evening' => ['18:00', '21:00']],
                'thursday' => ['morning' => ['10:00', '12:00'], 'evening' => ['18:00', '21:00']],
                'friday' => ['morning' => ['10:00', '12:00'], 'evening' => ['18:00', '21:00']],
                'saturday' => ['morning' => ['10:00', '12:00'], 'evening' => ['18:00', '21:00']],
                'sunday' => ['single' => ['11:00', '13:00']],
            ],
        ]);

        $this->doctorB = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Priya Patel',
            'qualification' => 'MBBS, MS',
            'registration_number' => 'MCI-22222',
            'specialization' => 'General Surgery',
            'contact_number' => '+91 9876543002',
            'is_active' => true,
            'schedule_config' => null, // Inherits clinic default
        ]);

        $this->clinic->update(['default_doctor_id' => $this->doctorA->id]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@dxclinic.test',
            'password' => bcrypt('password123'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->operatorUser = User::create([
            'name' => 'Operator User',
            'email' => 'operator@dxclinic.test',
            'password' => bcrypt('password123'),
            'role' => 'OPERATOR',
            'is_active' => true,
        ]);

        $this->doctorUserA = User::create([
            'name' => 'Dr. Amit User',
            'email' => 'dr.amit@dxclinic.test',
            'password' => bcrypt('password123'),
            'role' => 'DOCTOR',
            'doctor_id' => $this->doctorA->id,
            'is_active' => true,
        ]);

        $this->scheduleService = app(ClinicScheduleService::class);
    }

    /**
     * Phase 1: Admin Print Template Route & Dashboard Quick Action Target
     */
    public function test_admin_print_template_route_requires_auth_and_dashboard_tile_is_correct(): void
    {
        // 1. Unauthenticated request to settings route redirects to login
        $response = $this->get('/settings/prescription-pad-templates');
        $response->assertRedirect('/login');

        // 2. Authenticated Admin gets 200 OK
        $response = $this->actingAs($this->adminUser)->get('/settings/prescription-pad-templates');
        $response->assertStatus(200);

        // 3. Verify Dashboard view contains the correct href for Print Templates tile
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Dashboard::class)
            ->assertSee(route('settings.prescription-pad-templates'));
    }

    /**
     * Phase 2: Consultation Label Normalization
     */
    public function test_consultation_label_normalization_across_ui_and_preservation_of_enums(): void
    {
        // 1. Fee Master Seeder / DB record should be "Clinic Consultancy" (Revision 38)
        $fee = FeeMaster::where('code', 'CONSULTATION_PHYSICAL')->first();
        $this->assertNotNull($fee);
        $this->assertEquals('Clinic Consultancy', $fee->name);

        // 2. Operator Booking renders canonical "Clinic Consultancy"
        Livewire::actingAs($this->operatorUser)
            ->test(OperatorBooking::class)
            ->assertSee('Clinic Consultancy')
            ->assertDontSee('Clinic Consultation')
            ->assertDontSee('Clinic Physical Consultation');

        // 3. Patient PWA renders canonical "Clinic Consultancy"
        Livewire::test(PatientBooking::class, ['public_slug' => $this->clinic->public_slug])
            ->assertSee('Clinic Consultancy')
            ->assertDontSee('Clinic Consultation')
            ->assertDontSee('Clinic Physical Consultation');

        // 4. Verify internal enum CLINIC_PHYSICAL is still accepted
        $patientService = app(PatientIdentityService::class);
        $appointmentService = app(AppointmentService::class);

        $patient = $patientService->registerPatient([
            'clinic_id' => $this->clinic->id,
            'name' => 'Kailash Nath',
            'age' => 45,
            'sex' => 'MALE',
            'mobile' => '+91 9876543100',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
        ]);

        $app = $appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        $this->assertEquals('CLINIC_PHYSICAL', $app->consultation_mode);
    }

    /**
     * Phases 3-10: Prescription Pad Designer Typography, Static Text, Rectangle & Line Isolation
     */
    public function test_prescription_pad_designer_typography_and_elements_persistence(): void
    {
        $template = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'name' => 'Test Prescription Pad',
            'page_size' => 'A4',
            'orientation' => 'PORTRAIT',
            'width_mm' => 210,
            'height_mm' => 297,
            'is_active' => true,
        ]);

        // Element 1: Header Static Text with 12pt font
        $elem1 = PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'static_header',
            'element_type' => 'STATIC_TEXT',
            'label' => 'Clinic Header Notice',
            'pos_x_mm' => 20,
            'pos_y_mm' => 15,
            'width_mm' => 80,
            'height_mm' => 10,
            'font_size' => 12,
            'font_weight' => 'bold',
            'text_align' => 'left',
            'color' => '#1e293b',
            'config_json' => ['static_text' => 'Authorized Consultation Pad'],
            'is_visible' => true,
        ]);

        // Element 2: Small Footer Static Text with 4pt font
        $elem2 = PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'static_footer',
            'element_type' => 'STATIC_TEXT',
            'label' => 'Fine Print Notice',
            'pos_x_mm' => 20,
            'pos_y_mm' => 280,
            'width_mm' => 150,
            'height_mm' => 6,
            'font_size' => 4,
            'font_weight' => 'normal',
            'text_align' => 'center',
            'color' => '#64748b',
            'config_json' => ['static_text' => 'Not valid for medicolegal purposes without physical doctor signature.'],
            'is_visible' => true,
        ]);

        // Element 3: Styled Rectangle
        $elem3 = PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'rect_rx_box',
            'element_type' => 'RECTANGLE',
            'label' => 'Rx Box',
            'pos_x_mm' => 15,
            'pos_y_mm' => 50,
            'width_mm' => 180,
            'height_mm' => 200,
            'font_size' => 10,
            'color' => '#3b82f6',
            'config_json' => [
                'border_style' => 'dashed',
                'border_width_mm' => 1.5,
                'fill_color' => '#f8fafc',
                'border_radius_mm' => 4,
            ],
            'is_visible' => true,
        ]);

        // Element 4: Styled Line
        $elem4 = PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'line_divider',
            'element_type' => 'LINE',
            'label' => 'Divider Line',
            'pos_x_mm' => 15,
            'pos_y_mm' => 45,
            'width_mm' => 180,
            'height_mm' => 2,
            'font_size' => 10,
            'color' => '#e2e8f0',
            'config_json' => [
                'line_style' => 'dotted',
                'line_width_mm' => 2.0,
            ],
            'is_visible' => true,
        ]);

        // Test Livewire component loads elements with isolated font sizes
        $test = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $template->id]);

        $loadedElements = $test->get('elements');
        $this->assertCount(4, $loadedElements);

        $e1 = collect($loadedElements)->firstWhere('id', $elem1->id);
        $e2 = collect($loadedElements)->firstWhere('id', $elem2->id);
        $e3 = collect($loadedElements)->firstWhere('id', $elem3->id);
        $e4 = collect($loadedElements)->firstWhere('id', $elem4->id);

        $this->assertEquals(12, $e1['font_size']);
        $this->assertEquals('Authorized Consultation Pad', $e1['config_json']['static_text']);

        $this->assertEquals(4, $e2['font_size']);
        $this->assertEquals('Not valid for medicolegal purposes without physical doctor signature.', $e2['config_json']['static_text']);

        $this->assertEquals('dashed', $e3['config_json']['border_style']);
        $this->assertEquals(1.5, $e3['config_json']['border_width_mm']);
        $this->assertEquals('#f8fafc', $e3['config_json']['fill_color']);
        $this->assertEquals(4, $e3['config_json']['border_radius_mm']);

        $this->assertEquals('dotted', $e4['config_json']['line_style']);
        $this->assertEquals(2.0, $e4['config_json']['line_width_mm']);

        // Verify PDF renderer correctly receives all attributes
        $pdfRenderer = app(PrescriptionPdfRenderer::class);
        $viewData = $pdfRenderer->prepareViewData($template, [], 'proof');
        $viewElements = $viewData['renderedElements'];

        $ve1 = collect($viewElements)->firstWhere('field_key', $elem1->field_key);
        $ve2 = collect($viewElements)->firstWhere('field_key', $elem2->field_key);
        $ve3 = collect($viewElements)->firstWhere('field_key', $elem3->field_key);
        $ve4 = collect($viewElements)->firstWhere('field_key', $elem4->field_key);

        $this->assertEquals('Authorized Consultation Pad', $ve1['rendered_value']);
        $this->assertEquals(12, $ve1['font_size']);

        $this->assertStringContainsString('medicolegal', $ve2['rendered_value']);
        $this->assertEquals(4, $ve2['font_size']);

        $this->assertEquals('dashed', $ve3['border_style']);
        $this->assertEquals(1.5, $ve3['border_width_mm']);
        $this->assertEquals('#f8fafc', $ve3['fill_color']);
        $this->assertEquals(4, $ve3['border_radius_mm']);

        $this->assertEquals('dotted', $ve4['line_style']);
        $this->assertEquals(2.0, $ve4['line_width_mm']);
    }

    /**
     * Phases 11 & 12: Doctor Fee Master Hierarchy
     */
    public function test_doctor_fee_master_hierarchy_and_consultation_billing(): void
    {
        // 1. Without doctor-specific fee, doctorA resolves to clinic fee (₹600)
        $defaultPhysicalFee = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->clinic->id, $this->doctorA->id);
        $this->assertEquals(600.00, $defaultPhysicalFee);

        // 2. Add doctorA-specific fee override at ₹850
        FeeMaster::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'code' => 'CONSULTATION_PHYSICAL',
            'name' => 'Cardiology Consultation (Dr. Amit)',
            'amount' => 850.00,
            'is_active' => true,
        ]);

        // 3. Now doctorA resolves to ₹850, while doctorB resolves to clinic default ₹600
        $doctorAFee = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->clinic->id, $this->doctorA->id);
        $doctorBFee = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->clinic->id, $this->doctorB->id);
        $this->assertEquals(850.00, $doctorAFee);
        $this->assertEquals(600.00, $doctorBFee);

        // 4. Test ConsultationBillingService creates bill matching doctor-specific rate
        $patientService = app(PatientIdentityService::class);
        $patient = $patientService->registerPatient([
            'clinic_id' => $this->clinic->id,
            'name' => 'Mahesh Verma',
            'age' => 52,
            'sex' => 'MALE',
            'mobile' => '+91 9876543200',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $appA = app(AppointmentService::class)->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        $visitA = Visit::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_id' => $appA->id,
            'visit_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'visit_type' => 'FIRST_VISIT',
            'status' => 'ARRIVED',
            'arrived_at' => now(),
        ]);

        $billingService = app(ConsultationBillingService::class);
        $billA = $billingService->createOrGetBill($visitA);

        $this->assertEquals(850.00, $billA->gross_amount);
        $this->assertEquals(850.00, $billA->net_amount);
    }

    /**
     * Phases 13, 14 & 15: Doctor-Specific Schedule Hierarchy & Booking Integration
     */
    public function test_doctor_schedule_hierarchy_and_dynamic_session_updates(): void
    {
        // 1. Doctor A has custom schedule: Morning 10:00-12:00
        $sessionsA = $this->scheduleService->getSessionsForDate($this->clinic, '2026-09-28', $this->doctorA);
        $this->assertEquals('10:00', $sessionsA['MORNING']['start']);
        $this->assertEquals('12:00', $sessionsA['MORNING']['end']);

        // 2. Doctor B inherits clinic default: Morning 08:00-09:00
        $sessionsB = $this->scheduleService->getSessionsForDate($this->clinic, '2026-09-28', $this->doctorB);
        $this->assertEquals('08:00', $sessionsB['MORNING']['start']);
        $this->assertEquals('09:00', $sessionsB['MORNING']['end']);

        // 3. Test OperatorBooking dynamically updates available sessions when doctor changes
        $component = Livewire::actingAs($this->operatorUser)
            ->test(OperatorBooking::class)
            ->set('selected_doctor_id', $this->doctorA->id);

        $availableSessions = $component->get('available_sessions');
        $this->assertEquals('10:00', $availableSessions['MORNING']['start']);
        $this->assertEquals('12:00', $availableSessions['MORNING']['end']);

        // Switch to Doctor B
        $component->set('selected_doctor_id', $this->doctorB->id);
        $availableSessions = $component->get('available_sessions');
        $this->assertEquals('08:00', $availableSessions['MORNING']['start']);
        $this->assertEquals('09:00', $availableSessions['MORNING']['end']);

        // 4. Test PatientBooking PWA dynamically updates available sessions when doctor changes
        $pwaComponent = Livewire::test(PatientBooking::class, ['public_slug' => $this->clinic->public_slug])
            ->set('selected_doctor_id', $this->doctorA->id);

        $pwaSessions = $pwaComponent->get('available_sessions');
        $this->assertEquals('10:00', $pwaSessions['MORNING']['start']);
        $this->assertEquals('12:00', $pwaSessions['MORNING']['end']);

        $pwaComponent->set('selected_doctor_id', $this->doctorB->id);
        $pwaSessions = $pwaComponent->get('available_sessions');
        $this->assertEquals('08:00', $pwaSessions['MORNING']['start']);
        $this->assertEquals('09:00', $pwaSessions['MORNING']['end']);
    }

    /**
     * Phase 15: Doctor Login Context Binding in ArrivalQueue
     */
    public function test_doctor_login_binds_queue_context(): void
    {
        $patientService = app(PatientIdentityService::class);
        $appointmentService = app(AppointmentService::class);

        $patient1 = $patientService->registerPatient([
            'clinic_id' => $this->clinic->id,
            'name' => 'Patient for Doc A',
            'age' => 30,
            'sex' => 'FEMALE',
            'mobile' => '+91 9876543301',
            'address' => 'Lanka',
            'city' => 'Varanasi',
        ]);

        $patient2 = $patientService->registerPatient([
            'clinic_id' => $this->clinic->id,
            'name' => 'Patient for Doc B',
            'age' => 40,
            'sex' => 'MALE',
            'mobile' => '+91 9876543302',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
        ]);

        $appA = $appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient1->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        $appB = $appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient2->id,
            'doctor_id' => $this->doctorB->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        // When Doctor A logs in, ArrivalQueue only sees Appointment A
        Livewire::actingAs($this->doctorUserA)
            ->test(ArrivalQueue::class)
            ->assertSee('Patient for Doc A')
            ->assertDontSee('Patient for Doc B');

        // When Operator logs in, ArrivalQueue sees both appointments
        Livewire::actingAs($this->operatorUser)
            ->test(ArrivalQueue::class)
            ->assertSee('Patient for Doc A')
            ->assertSee('Patient for Doc B');
    }

    /**
     * Phase 16: Tea/Coffee Expense Category
     */
    public function test_tea_coffee_expense_category_exists(): void
    {
        $cat = ExpenseCategory::where('code', 'EXP-TEA')->first();
        $this->assertNotNull($cat);
        $this->assertEquals('Tea/Coffee', $cat->name);
        $this->assertTrue($cat->is_active);
        $this->assertNotNull($cat->ledger_account_id);
    }

    /**
     * Phase 17: Dedicated Public PWA Layout
     */
    public function test_public_pwa_layout_isolation_without_clinic_navigation(): void
    {
        $response = $this->get('/book/' . $this->clinic->public_slug);
        $response->assertStatus(200);

        // Assert public header content is present
        $response->assertSee('OPD Token Booking');
        $response->assertSee('Booking Open');

        // Assert authenticated navigation, operator buttons, or admin sidebar do NOT exist in public page
        $response->assertDontSee('btn_open_mobile_sidebar');
        $response->assertDontSee('Clinical Management');
        $response->assertDontSee('Operator Desk');
        $response->assertDontSee('Settings');
    }

    /**
     * Phases 18 & 19: Public Booking URL Configuration and Booking QR
     */
    public function test_public_booking_url_and_qr_configuration(): void
    {
        $expectedUrl = 'https://dxclinic.example.local/book';

        $this->assertEquals($expectedUrl, config('app.public_booking_url'));

        Livewire::actingAs($this->adminUser)
            ->test(BookingQrManager::class)
            ->assertSet('prodUrl', $expectedUrl);
    }

    /**
     * Phase 23: PDF Verification (Production & Proof rendering)
     */
    public function test_prescription_pdf_renderer_generates_valid_production_and_proof_pdf(): void
    {
        $template = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'name' => 'Feature Test Pad Template',
            'paper_size' => 'A4',
            'paper_width_mm' => 210,
            'paper_height_mm' => 297,
            'orientation' => 'portrait',
            'is_active' => true,
        ]);

        PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'static_header',
            'element_type' => 'STATIC_TEXT',
            'x_mm' => 20,
            'y_mm' => 15,
            'width_mm' => 170,
            'height_mm' => 12,
            'font_size' => 16,
            'color' => '#1e3a8a',
            'config_json' => ['static_text' => 'METRO CLINICAL RESEARCH HOSPITAL'],
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'line_sep',
            'element_type' => 'LINE',
            'x_mm' => 20,
            'y_mm' => 30,
            'width_mm' => 170,
            'height_mm' => 1,
            'color' => '#2563eb',
            'config_json' => ['line_style' => 'solid', 'line_width_mm' => 0.8],
            'is_visible' => true,
            'sort_order' => 2,
        ]);

        PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'patient_box',
            'element_type' => 'RECTANGLE',
            'x_mm' => 20,
            'y_mm' => 35,
            'width_mm' => 170,
            'height_mm' => 25,
            'color' => '#93c5fd',
            'config_json' => [
                'border_style' => 'solid',
                'border_width_mm' => 0.5,
                'fill_color' => '#f0f9ff',
                'border_radius_mm' => 3.0,
            ],
            'is_visible' => true,
            'sort_order' => 3,
        ]);

        $renderer = new PrescriptionPdfRenderer();
        $sampleData = [
            'patient.name' => 'Rahul Sharma',
            'appointment.token' => 'Token #001',
        ];

        $prodPdf = $renderer->outputPdf($template, $sampleData, 'production');
        $this->assertNotEmpty($prodPdf);
        $this->assertStringStartsWith('%PDF-', $prodPdf);
        $this->assertGreaterThan(1000, strlen($prodPdf));

        $proofPdf = $renderer->outputPdf($template, $sampleData, 'proof');
        $this->assertNotEmpty($proofPdf);
        $this->assertStringStartsWith('%PDF-', $proofPdf);
        $this->assertGreaterThan(1000, strlen($proofPdf));
    }

    /**
     * Phase 24: Security & Settings Auth Middleware Protection
     */
    public function test_settings_routes_require_authentication(): void
    {
        $protectedUrls = [
            '/settings/prescription-pad-templates',
            '/settings/fee-master',
            '/settings/users',
            '/settings/clinic-doctor',
            '/settings/expense-categories',
            '/settings/bank-accounts',
            '/settings/booking-qr',
        ];

        foreach ($protectedUrls as $url) {
            $response = $this->get($url);
            $response->assertRedirect('/login');
        }
    }

    /**
     * Phase 24: Security & Doctor Context Isolation in Billing and Arrival
     */
    public function test_doctor_context_isolation_in_billing_and_arrival(): void
    {
        $appointmentService = app(AppointmentService::class);
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-99001',
            'name' => 'Confidential Patient',
            'mobile' => '9988776655',
            'age' => 45,
            'sex' => 'FEMALE',
            'address' => 'Line 1 Test Nagar',
            'city' => 'Varanasi',
        ]);

        // Appointment booked for Doctor B
        $appB = $appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorB->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'EVENING',
            'booking_source' => 'CLINIC',
        ]);

        // Doctor A tries to access Doctor B's consultation billing -> expects 403
        Livewire::actingAs($this->doctorUserA)
            ->test(ConsultationBilling::class, ['appointmentId' => $appB->id])
            ->assertStatus(403);

        // Doctor A searches for Doctor B appointment in ArrivalQueue check-in search -> returns null
        Livewire::actingAs($this->doctorUserA)
            ->test(ArrivalQueue::class)
            ->set('filterDate', '2026-09-28')
            ->set('arrivalSearchQuery', $appB->formatted_token)
            ->call('searchPatientForArrival')
            ->assertSet('searchedAppointment', null);
    }
}
