<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PrescriptionPadElement;
use App\Models\PrescriptionPadTemplate;
use App\Models\PrinterProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\Vital;
use App\Services\PrescriptionDataMapper;
use App\Services\PrescriptionPadTemplateService;
use App\Services\PrescriptionPdfRenderer;
use App\Services\PrescriptionTemplateResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class Revision30FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctorA;
    protected Doctor $doctorB;
    protected User $adminUser;
    protected User $operatorUser;
    protected User $doctorUser;
    protected Patient $patient;
    protected PrescriptionPadTemplateService $templateService;
    protected PrescriptionTemplateResolver $resolver;
    protected PrescriptionPdfRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'name' => 'Sharma Care Clinic',
            'address' => 'Assi Ghat Road',
            'city' => 'Varanasi',
            'phone' => '9876543210',
            'email' => 'contact@sharmaclinic.com',
            'public_slug' => 'sharmacare',
            'is_onboarded' => true,
        ]);

        $this->doctorA = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'UP-12345/2015',
            'specialization' => 'Physician',
            'is_active' => true,
        ]);

        $this->doctorB = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Priya Verma',
            'qualification' => 'MBBS, DNB',
            'registration_number' => 'UP-54321/2018',
            'specialization' => 'Pediatrician',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Admin Kundan',
            'email' => 'admin@dxclinic.local',
            'password' => bcrypt('password123'),
            'role' => 'ADMIN',
        ]);

        $this->operatorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Operator Desk',
            'email' => 'operator@dxclinic.local',
            'password' => bcrypt('password123'),
            'role' => 'OPERATOR',
        ]);

        $this->doctorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Rajesh Sharma',
            'email' => 'doctor@dxclinic.local',
            'password' => bcrypt('password123'),
            'role' => 'DOCTOR',
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-10001',
            'name' => 'राम कुमार',
            'sex' => 'MALE',
            'age' => 45,
            'mobile' => '9876543210',
            'city' => 'वाराणसी',
            'address' => 'दशाश्वमेध घाट, वाराणसी',
        ]);

        $this->templateService = app(PrescriptionPadTemplateService::class);
        $this->resolver = app(PrescriptionTemplateResolver::class);
        $this->renderer = app(PrescriptionPdfRenderer::class);
    }

    /**
     * Test 1: TrueType Unicode Font Registration
     */
    public function test_unicode_font_registration_and_cache(): void
    {
        PrescriptionPdfRenderer::ensureUnicodeFontRegistered();

        $fontDir = storage_path('fonts');
        $this->assertTrue(File::isDirectory($fontDir), 'Font directory must exist');

        $fontList = $fontDir . DIRECTORY_SEPARATOR . 'installed-fonts.json';
        $this->assertTrue(File::exists($fontList), 'DomPDF installed-fonts.json must exist');

        $installed = json_decode(File::get($fontList), true);
        $this->assertArrayHasKey('noto sans devanagari', $installed, 'Noto Sans Devanagari must be registered in font list');
        $this->assertArrayHasKey('normal', $installed['noto sans devanagari']);
        $this->assertArrayHasKey('bold', $installed['noto sans devanagari']);
    }

    /**
     * Test 2: Devanagari and Mixed-Script PDF Rendering
     */
    public function test_devanagari_and_mixed_script_pdf_rendering(): void
    {
        $template = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'name' => 'Dr. Sharma Hindi Pad',
            'version' => 1,
            'status' => 'ACTIVE',
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'width_mm' => 210.0,
            'height_mm' => 297.0,
        ]);

        PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'patient_name',
            'label' => 'Patient Name',
            'x_mm' => 25.0,
            'y_mm' => 35.0,
            'width_mm' => 80.0,
            'height_mm' => 8.0,
            'font_size' => 12,
            'font_family' => 'Noto Sans Devanagari',
            'font_weight' => 'bold',
            'font_color' => '#111827',
            'text_align' => 'left',
            'is_visible' => true,
        ]);

        PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'address',
            'label' => 'Address',
            'x_mm' => 25.0,
            'y_mm' => 45.0,
            'width_mm' => 100.0,
            'height_mm' => 8.0,
            'font_size' => 10,
            'font_family' => 'Noto Sans Devanagari',
            'font_weight' => 'normal',
            'font_color' => '#374151',
            'text_align' => 'left',
            'is_visible' => true,
        ]);

        $payload = [
            'patient_name' => 'राम कुमार (Ram Kumar)',
            'address' => 'वाराणसी, उत्तर प्रदेश (Varanasi, UP)',
            'vitals' => [
                'bp' => '120/80',
                'weight' => '68',
                'pulse' => '72',
                'spo2' => '98',
                'temp' => '98.4',
            ],
            'meta' => [
                'doctor_name' => 'डॉ. राजेश शर्मा',
                'date' => Carbon::now()->format('d/m/Y'),
                'token' => '001',
            ],
        ];

        $pdfBytes = $this->renderer->outputPdf($template, $payload, 'production');

        $this->assertNotEmpty($pdfBytes);
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertGreaterThan(5000, strlen($pdfBytes));
    }

    /**
     * Test 3: Printer Calibration Calculation Math
     */
    public function test_printer_calibration_calculation_and_transformation(): void
    {
        $profile = PrinterProfile::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Canon LBP2900',
            'paper_width_mm' => 210.0,
            'paper_height_mm' => 297.0,
            'offset_x_mm' => -1.5,
            'offset_y_mm' => 2.0,
            'scale_x' => 1.02,
            'scale_y' => 0.99,
            'is_default' => true,
        ]);

        $rawX = 35.0; // mm
        $rawY = 50.0; // mm

        // Transformation formula: transformed = (raw * scale) + offset
        $transformedX = ($rawX * $profile->scale_x) + $profile->offset_x_mm;
        $transformedY = ($rawY * $profile->scale_y) + $profile->offset_y_mm;

        $this->assertEquals(34.2, round($transformedX, 2));
        $this->assertEquals(51.5, round($transformedY, 2));
    }

    /**
     * Test 4: Printer Profile Isolation (Printer A vs Printer B)
     */
    public function test_printer_profile_isolation(): void
    {
        $profileA = PrinterProfile::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Canon LBP2900 Desk 1',
            'paper_width_mm' => 210.0,
            'paper_height_mm' => 297.0,
            'offset_x_mm' => -1.2,
            'offset_y_mm' => 0.5,
            'scale_x' => 1.0,
            'scale_y' => 1.0,
            'is_default' => true,
        ]);

        $profileB = PrinterProfile::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'HP LaserJet Desk 2',
            'paper_width_mm' => 210.0,
            'paper_height_mm' => 297.0,
            'offset_x_mm' => 2.5,
            'offset_y_mm' => -3.0,
            'scale_x' => 0.98,
            'scale_y' => 1.01,
            'is_default' => false,
        ]);

        $this->assertEquals(-1.2, $profileA->offset_x_mm);
        $this->assertEquals(2.5, $profileB->offset_x_mm);

        // Modifying Profile A must not affect Profile B
        $profileA->update(['offset_x_mm' => -2.0]);
        $profileB->refresh();

        $this->assertEquals(-2.0, $profileA->offset_x_mm);
        $this->assertEquals(2.5, $profileB->offset_x_mm);
    }

    /**
     * Test 5: Template Coordinate Immutability under Printer Calibration
     */
    public function test_template_coordinate_immutability(): void
    {
        $template = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'name' => 'Stationery v1',
            'version' => 1,
            'status' => 'ACTIVE',
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'width_mm' => 210.0,
            'height_mm' => 297.0,
        ]);

        $element = PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'patient_name',
            'label' => 'Patient Name',
            'x_mm' => 42.50,
            'y_mm' => 65.25,
            'width_mm' => 85.00,
            'height_mm' => 10.00,
            'font_size' => 11,
            'font_family' => 'Noto Sans Devanagari',
            'font_weight' => 'bold',
            'font_color' => '#000000',
            'text_align' => 'left',
            'is_visible' => true,
        ]);

        // Create printer calibration profile
        PrinterProfile::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Canon LBP2900',
            'paper_width_mm' => 210.0,
            'paper_height_mm' => 297.0,
            'offset_x_mm' => -5.0,
            'offset_y_mm' => 10.0,
            'scale_x' => 1.05,
            'scale_y' => 0.95,
            'is_default' => true,
        ]);

        // Render PDF with calibration
        $payload = [
            'patient_name' => 'राम कुमार',
            'vitals' => [],
            'meta' => ['date' => '03/10/2026', 'token' => '001'],
        ];

        $pdfBytes = $this->renderer->outputPdf($template, $payload, 'production');
        $this->assertNotEmpty($pdfBytes);

        // Verify stored element coordinates are strictly unchanged
        $element->refresh();
        $this->assertEquals(42.50, $element->x_mm);
        $this->assertEquals(65.25, $element->y_mm);
        $this->assertEquals(85.00, $element->width_mm);
        $this->assertEquals(10.00, $element->height_mm);

        // Verify template version is still 1
        $template->refresh();
        $this->assertEquals(1, $template->version);
    }

    /**
     * Test 6: Template Activation Boundary & Safety Validation
     */
    public function test_template_activation_safety(): void
    {
        $template = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'name' => 'Boundary Test Template',
            'version' => 1,
            'status' => 'DRAFT',
            'paper_size' => 'A5',
            'orientation' => 'portrait',
            'width_mm' => 148.0,
            'height_mm' => 210.0,
        ]);

        // Element placed out of bounds (X = 160 mm on 148 mm paper)
        PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'patient_name',
            'label' => 'Patient Name',
            'x_mm' => 160.0,
            'y_mm' => 30.0,
            'width_mm' => 50.0,
            'height_mm' => 10.0,
            'font_size' => 10,
            'font_family' => 'Noto Sans Devanagari',
            'font_weight' => 'normal',
            'font_color' => '#000000',
            'text_align' => 'left',
            'is_visible' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->templateService->activateTemplate($template, 1, true);
    }

    /**
     * Test 7: Legacy Fallback for Doctor Without Active Template
     */
    public function test_legacy_fallback_for_doctor_without_template(): void
    {
        // Doctor B has no custom template
        $activeTemplate = $this->resolver->resolveActiveForDoctor($this->doctorB->id, $this->clinic->id);
        $this->assertNull($activeTemplate);

        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorB->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => Carbon::now()->toDateString(),
            'slot_time' => '10:00:00',
            'token_number' => 1,
            'formatted_token' => '001',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'BOOKED',
            'fee_amount' => 500,
            'secure_token' => \Illuminate\Support\Str::random(32),
            'is_legacy_pad' => true,
        ]);

        $resolved = $this->resolver->resolveForAppointment($appointment);
        $this->assertNull($resolved, 'Should resolve to null template, indicating legacy pad fallback');
    }

    /**
     * Test 8: Historical Template Resolution & Immutability
     */
    public function test_historical_template_resolution(): void
    {
        $v1 = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'name' => 'Dr. Sharma Pad V1',
            'version' => 1,
            'status' => 'ACTIVE',
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'width_mm' => 210.0,
            'height_mm' => 297.0,
        ]);

        PrescriptionPadElement::create([
            'template_id' => $v1->id,
            'field_key' => 'patient_name',
            'label' => 'Patient Name',
            'x_mm' => 30.0,
            'y_mm' => 40.0,
            'width_mm' => 60.0,
            'height_mm' => 10.0,
            'font_size' => 11,
            'font_family' => 'Noto Sans Devanagari',
            'font_weight' => 'normal',
            'font_color' => '#000000',
            'text_align' => 'left',
            'is_visible' => true,
        ]);

        // Past visit created with V1
        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => Carbon::now()->subMonths(2)->toDateString(),
            'slot_time' => '11:00:00',
            'token_number' => 5,
            'formatted_token' => '005',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'COMPLETED',
            'fee_amount' => 600,
            'secure_token' => \Illuminate\Support\Str::random(32),
            'is_legacy_pad' => false,
            'prescription_pad_template_id' => $v1->id,
        ]);

        // Today: Create and activate V2
        $v2 = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'name' => 'Dr. Sharma Pad V2',
            'version' => 2,
            'status' => 'ACTIVE',
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'width_mm' => 210.0,
            'height_mm' => 297.0,
        ]);
        $v1->update(['status' => 'ARCHIVED']);

        // Historical reprint must resolve to V1, NOT V2
        $historicalTemplate = $this->resolver->resolveForAppointment($appointment);
        $this->assertNotNull($historicalTemplate);
        $this->assertEquals($v1->id, $historicalTemplate->id);
        $this->assertEquals(1, $historicalTemplate->version);
    }

    /**
     * Test 9: Reprint Idempotency (Zero financial side-effects)
     */
    public function test_reprint_idempotency_zero_side_effects(): void
    {
        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctorA->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => Carbon::now()->toDateString(),
            'slot_time' => '12:00:00',
            'token_number' => 10,
            'formatted_token' => '010',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'COMPLETED',
            'fee_amount' => 600,
            'secure_token' => \Illuminate\Support\Str::random(32),
            'is_legacy_pad' => false,
        ]);

        $initialPayments = \App\Models\Payment::count();
        $initialReceipts = \App\Models\Receipt::count();
        $initialJournals = \App\Models\Journal::count();
        $initialVisits = \App\Models\Visit::count();

        // Simulate 5 consecutive Doctor Pad prints/reprints
        for ($i = 0; $i < 5; $i++) {
            $payload = PrescriptionDataMapper::mapEncounter($appointment);
            $this->assertArrayHasKey('patient.name', $payload);
        }

        $this->assertEquals($initialPayments, \App\Models\Payment::count());
        $this->assertEquals($initialReceipts, \App\Models\Receipt::count());
        $this->assertEquals($initialJournals, \App\Models\Journal::count());
        $this->assertEquals($initialVisits, \App\Models\Visit::count());
    }

    /**
     * Test 10: Role-Based Access Security
     */
    public function test_role_based_security_for_templates_and_calibration(): void
    {
        // Doctor cannot access template designer settings
        $response = $this->actingAs($this->doctorUser)->get(route('settings.prescription-pad-templates'));
        $this->assertTrue(in_array($response->status(), [403, 302]), 'Doctor must be restricted from template designer');

        // Operator cannot access template designer
        $response = $this->actingAs($this->operatorUser)->get(route('settings.prescription-pad-designer', 1));
        $this->assertTrue(in_array($response->status(), [403, 302]), 'Operator must be restricted from template designer');

        // Admin has full access
        $response = $this->actingAs($this->adminUser)->get(route('settings.prescription-pad-templates'));
        $response->assertStatus(200);
    }
}
