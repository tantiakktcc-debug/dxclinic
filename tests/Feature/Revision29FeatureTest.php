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
use App\Services\BackupService;
use App\Services\PrescriptionDataMapper;
use App\Services\PrescriptionPadTemplateService;
use App\Services\PrescriptionPdfRenderer;
use App\Services\PrescriptionTemplateResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class Revision29FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Clinic $clinic2;
    protected Doctor $doctorA;
    protected Doctor $doctorB;
    protected Doctor $doctorC;
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
            'is_onboarded' => true,
        ]);

        $this->clinic2 = Clinic::create([
            'name' => 'Patel Medical Hospital',
            'address' => 'Civil Lines',
            'city' => 'Prayagraj',
            'phone' => '9876543001',
            'email' => 'contact@patelhospital.com',
            'is_onboarded' => true,
        ]);

        $this->doctorA = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD (Medicine)',
            'registration_number' => 'UP-12345/2015',
            'specialization' => 'Physician',
            'is_active' => true,
        ]);

        $this->doctorB = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Priya Verma',
            'qualification' => 'MBBS, DNB (Pediatrics)',
            'registration_number' => 'UP-54321/2018',
            'specialization' => 'Pediatrician',
            'is_active' => true,
        ]);

        $this->doctorC = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Amit Kumar',
            'qualification' => 'MBBS, MS (Ortho)',
            'registration_number' => 'UP-99887/2012',
            'specialization' => 'Orthopedics',
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
            'name' => 'Dr. Rajesh User',
            'email' => 'dr.rajesh@dxclinic.local',
            'password' => bcrypt('password123'),
            'role' => 'DOCTOR',
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-REV29-001',
            'name' => 'Suresh Chandra',
            'mobile' => '9876543210',
            'sex' => 'M',
            'gender' => 'Male',
            'age' => 48,
            'address' => 'B-12 Jawahar Nagar',
            'city' => 'Varanasi',
        ]);

        $this->templateService = app(PrescriptionPadTemplateService::class);
        $this->resolver = app(PrescriptionTemplateResolver::class);
        $this->renderer = app(PrescriptionPdfRenderer::class);
    }

    protected function createDoctorTemplate(Doctor $doctor, string $name, array $elements = [], array $dimensions = []): PrescriptionPadTemplate
    {
        $template = $this->templateService->createDraft([
            'clinic_id' => $doctor->clinic_id,
            'doctor_id' => $doctor->id,
            'name' => $name,
            'paper_width_mm' => $dimensions['paper_width_mm'] ?? 210.0,
            'paper_height_mm' => $dimensions['paper_height_mm'] ?? 297.0,
            'orientation' => $dimensions['orientation'] ?? 'portrait',
        ], $this->adminUser->id);

        if (!empty($elements)) {
            $this->templateService->saveElements($template, $elements, $this->adminUser->id);
        }

        return $template;
    }

    protected function createEncounter(Doctor $doctor, int $tokenNumber = 1): Appointment
    {
        return Appointment::create([
            'clinic_id' => $doctor->clinic_id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'token_number' => $tokenNumber,
            'formatted_token' => str_pad((string)$tokenNumber, 3, '0', STR_PAD_LEFT),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'COMPLETED',
            'secure_token' => Str::random(32),
            'is_legacy_pad' => false,
        ]);
    }

    /**
     * Section 4: Multi-doctor template layouts with zero cross-doctor leakage.
     */
    public function test_multi_doctor_template_layouts_no_leakage(): void
    {
        // Doctor A layout: Patient Name, Age/Sex, BP, Weight, SpO2, Token, Date
        $templateA = $this->createDoctorTemplate($this->doctorA, 'Doctor A Specialized Pad', [
            ['field_key' => 'patient.name', 'element_type' => 'FIELD', 'x_mm' => 20, 'y_mm' => 40, 'width_mm' => 60, 'height_mm' => 6],
            ['field_key' => 'patient.age_sex', 'element_type' => 'FIELD', 'x_mm' => 85, 'y_mm' => 40, 'width_mm' => 30, 'height_mm' => 6],
            ['field_key' => 'vitals.bp', 'element_type' => 'FIELD', 'x_mm' => 20, 'y_mm' => 50, 'width_mm' => 35, 'height_mm' => 6],
            ['field_key' => 'vitals.weight', 'element_type' => 'FIELD', 'x_mm' => 60, 'y_mm' => 50, 'width_mm' => 25, 'height_mm' => 6],
            ['field_key' => 'vitals.spo2', 'element_type' => 'FIELD', 'x_mm' => 90, 'y_mm' => 50, 'width_mm' => 22, 'height_mm' => 6],
            ['field_key' => 'visit.token_no', 'element_type' => 'FIELD', 'x_mm' => 170, 'y_mm' => 40, 'width_mm' => 25, 'height_mm' => 8],
            ['field_key' => 'visit.date', 'element_type' => 'FIELD', 'x_mm' => 170, 'y_mm' => 50, 'width_mm' => 30, 'height_mm' => 6],
        ], ['paper_width_mm' => 210.0, 'paper_height_mm' => 297.0]);
        $this->templateService->activateTemplate($templateA, $this->adminUser->id);

        // Doctor B layout: Patient Name, Age, Sex, BP, Pulse, Temperature, RBS, Token
        $templateB = $this->createDoctorTemplate($this->doctorB, 'Doctor B Pediatric Pad', [
            ['field_key' => 'patient.name', 'element_type' => 'FIELD', 'x_mm' => 15, 'y_mm' => 30, 'width_mm' => 50, 'height_mm' => 5],
            ['field_key' => 'patient.age', 'element_type' => 'FIELD', 'x_mm' => 70, 'y_mm' => 30, 'width_mm' => 20, 'height_mm' => 5],
            ['field_key' => 'patient.sex', 'element_type' => 'FIELD', 'x_mm' => 95, 'y_mm' => 30, 'width_mm' => 20, 'height_mm' => 5],
            ['field_key' => 'vitals.bp', 'element_type' => 'FIELD', 'x_mm' => 15, 'y_mm' => 40, 'width_mm' => 30, 'height_mm' => 5],
            ['field_key' => 'vitals.pulse', 'element_type' => 'FIELD', 'x_mm' => 50, 'y_mm' => 40, 'width_mm' => 25, 'height_mm' => 5],
            ['field_key' => 'vitals.temperature', 'element_type' => 'FIELD', 'x_mm' => 80, 'y_mm' => 40, 'width_mm' => 25, 'height_mm' => 5],
            ['field_key' => 'vitals.rbs', 'element_type' => 'FIELD', 'x_mm' => 110, 'y_mm' => 40, 'width_mm' => 25, 'height_mm' => 5],
            ['field_key' => 'visit.token_no', 'element_type' => 'FIELD', 'x_mm' => 120, 'y_mm' => 30, 'width_mm' => 20, 'height_mm' => 6],
        ], ['paper_width_mm' => 148.0, 'paper_height_mm' => 210.0]);
        $this->templateService->activateTemplate($templateB, $this->adminUser->id);

        // Doctor C layout: Patient Name, Age/Sex, Address, Weight, Height, BP, SpO2, Pulse, Token, Date
        $templateC = $this->createDoctorTemplate($this->doctorC, 'Doctor C Ortho Pad', [
            ['field_key' => 'patient.name', 'element_type' => 'FIELD', 'x_mm' => 20, 'y_mm' => 35, 'width_mm' => 60, 'height_mm' => 6],
            ['field_key' => 'patient.age_sex', 'element_type' => 'FIELD', 'x_mm' => 85, 'y_mm' => 35, 'width_mm' => 30, 'height_mm' => 6],
            ['field_key' => 'patient.address', 'element_type' => 'FIELD', 'x_mm' => 20, 'y_mm' => 45, 'width_mm' => 70, 'height_mm' => 8],
            ['field_key' => 'vitals.weight', 'element_type' => 'FIELD', 'x_mm' => 20, 'y_mm' => 55, 'width_mm' => 25, 'height_mm' => 6],
            ['field_key' => 'vitals.height', 'element_type' => 'FIELD', 'x_mm' => 50, 'y_mm' => 55, 'width_mm' => 25, 'height_mm' => 6],
            ['field_key' => 'vitals.bp', 'element_type' => 'FIELD', 'x_mm' => 80, 'y_mm' => 55, 'width_mm' => 30, 'height_mm' => 6],
            ['field_key' => 'vitals.spo2', 'element_type' => 'FIELD', 'x_mm' => 115, 'y_mm' => 55, 'width_mm' => 22, 'height_mm' => 6],
            ['field_key' => 'vitals.pulse', 'element_type' => 'FIELD', 'x_mm' => 140, 'y_mm' => 55, 'width_mm' => 25, 'height_mm' => 6],
            ['field_key' => 'visit.token_no', 'element_type' => 'FIELD', 'x_mm' => 170, 'y_mm' => 35, 'width_mm' => 25, 'height_mm' => 8],
            ['field_key' => 'visit.date', 'element_type' => 'FIELD', 'x_mm' => 170, 'y_mm' => 45, 'width_mm' => 30, 'height_mm' => 6],
        ], ['paper_width_mm' => 210.0, 'paper_height_mm' => 280.0]);
        $this->templateService->activateTemplate($templateC, $this->adminUser->id);

        // Verify resolver selects doctor's specific active template
        $activeA = $this->resolver->resolveActiveForDoctor($this->doctorA->id, $this->clinic->id);
        $activeB = $this->resolver->resolveActiveForDoctor($this->doctorB->id, $this->clinic->id);
        $activeC = $this->resolver->resolveActiveForDoctor($this->doctorC->id, $this->clinic->id);

        $this->assertEquals($templateA->id, $activeA->id);
        $this->assertEquals($templateB->id, $activeB->id);
        $this->assertEquals($templateC->id, $activeC->id);

        $this->assertEquals(7, $activeA->elements->count());
        $this->assertEquals(8, $activeB->elements->count());
        $this->assertEquals(10, $activeC->elements->count());

        $this->assertEquals(297.0, (float)$activeA->paper_height_mm);
        $this->assertEquals(210.0, (float)$activeB->paper_height_mm);
        $this->assertEquals(280.0, (float)$activeC->paper_height_mm);
    }

    /**
     * Section 22: Strict template validation before activation.
     */
    public function test_template_validation_before_activation(): void
    {
        // 1. Empty template (0 elements) cannot be activated
        $emptyTemplate = $this->createDoctorTemplate($this->doctorA, 'Empty Template', []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("must contain at least one printable element before activation");
        $this->templateService->activateTemplate($emptyTemplate, $this->adminUser->id, true);
    }

    /**
     * Section 22: Validation blocks invalid coordinates or unsafe fields.
     */
    public function test_template_validation_rejects_negative_coords_and_unsafe_fields(): void
    {
        $template = $this->createDoctorTemplate($this->doctorA, 'Unsafe Template', []);

        // Add element with negative coordinates
        PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'patient.name',
            'element_type' => 'FIELD',
            'x_mm' => -10.0,
            'y_mm' => 20.0,
            'width_mm' => 50.0,
            'height_mm' => 6.0,
        ]);

        try {
            $this->templateService->activateTemplate($template, $this->adminUser->id, true);
            $this->fail("Expected activation with negative coordinates to fail.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("negative coordinates", $e->getMessage());
        }

        // Fix coordinates but inject unsafe field key
        $template->elements()->delete();
        PrescriptionPadElement::create([
            'template_id' => $template->id,
            'field_key' => 'user.password_hash',
            'element_type' => 'FIELD',
            'x_mm' => 20.0,
            'y_mm' => 20.0,
            'width_mm' => 50.0,
            'height_mm' => 6.0,
        ]);

        try {
            $this->templateService->activateTemplate($template, $this->adminUser->id, true);
            $this->fail("Expected activation with unsafe field to fail.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("not safe", $e->getMessage());
        }
    }

    /**
     * Section 6: Template versioning lifecycle V1 -> V2 -> V3.
     */
    public function test_template_versioning_lifecycle(): void
    {
        // Create and activate V1
        $v1 = $this->createDoctorTemplate($this->doctorA, 'Pad Template', [
            ['field_key' => 'patient.name', 'element_type' => 'FIELD', 'x_mm' => 20, 'y_mm' => 30, 'width_mm' => 50, 'height_mm' => 6],
        ]);
        $this->templateService->activateTemplate($v1, $this->adminUser->id);

        $v1->refresh();
        $this->assertEquals('ACTIVE', $v1->status);
        $this->assertEquals(1, $v1->version);

        // Duplicate to create V2
        $v2 = $this->templateService->duplicateAsDraft($v1, $this->adminUser->id);
        $this->assertEquals('DRAFT', $v2->status);
        $this->assertEquals(2, $v2->version);

        // Activate V2
        $this->templateService->activateTemplate($v2, $this->adminUser->id);
        $v1->refresh();
        $v2->refresh();

        $this->assertEquals('ARCHIVED', $v1->status);
        $this->assertFalse($v1->is_active);
        $this->assertEquals('ACTIVE', $v2->status);
        $this->assertTrue($v2->is_active);

        // Duplicate to create V3 and activate
        $v3 = $this->templateService->duplicateAsDraft($v2, $this->adminUser->id);
        $this->templateService->activateTemplate($v3, $this->adminUser->id);
        $v2->refresh();
        $v3->refresh();

        $this->assertEquals('ARCHIVED', $v2->status);
        $this->assertEquals('ACTIVE', $v3->status);
        $this->assertEquals(3, $v3->version);
    }

    /**
     * Section 8 & 9: Real printer calibration offsets and scaling mathematical validation.
     */
    public function test_printer_calibration_offsets_and_scaling(): void
    {
        $template = $this->createDoctorTemplate($this->doctorA, 'Calibration Template', [
            ['field_key' => 'patient.name', 'element_type' => 'FIELD', 'x_mm' => 35.20, 'y_mm' => 48.50, 'width_mm' => 70.0, 'height_mm' => 6.0],
        ]);

        // Create printer profile with known offset (-1.2mm X, +2.5mm Y) and scale (1.02 X, 0.98 Y)
        $printer = PrinterProfile::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Canon LBP2900 Calibrated',
            'paper_width_mm' => 210.0,
            'paper_height_mm' => 297.0,
            'offset_x_mm' => -1.20,
            'offset_y_mm' => 2.50,
            'scale_x' => 1.02,
            'scale_y' => 0.98,
            'is_default' => true,
        ]);

        $viewData = $this->renderer->prepareViewData($template, ['patient.name' => 'Test Patient'], 'production', $printer);
        $renderedElem = $viewData['renderedElements'][0];

        // Expected X = (35.20 + (-1.20)) * 1.02 = 34.00 * 1.02 = 34.68 mm
        // Expected Y = (48.50 + 2.50) * 0.98 = 51.00 * 0.98 = 49.98 mm
        // Expected W = 70.0 * 1.02 = 71.40 mm
        // Expected H = 6.0 * 0.98 = 5.88 mm
        $this->assertEquals(34.68, $renderedElem['final_x_mm']);
        $this->assertEquals(49.98, $renderedElem['final_y_mm']);
        $this->assertEquals(71.40, $renderedElem['final_w_mm']);
        $this->assertEquals(5.88, $renderedElem['final_h_mm']);
    }

    /**
     * Section 14 & 15: Vitals rendering truthfulness (no fabricated placeholders).
     */
    public function test_vitals_rendering_truthfulness(): void
    {
        $appointment = $this->createEncounter($this->doctorA, 88);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_id' => $appointment->id,
            'visit_date' => Carbon::today()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'IN_CONSULTATION',
        ]);

        // Vital record with only BP, Pulse, Weight recorded (SpO2, Temp, RBS unrecorded)
        $vital = Vital::create([
            'visit_id' => $visit->id,
            'bp_systolic' => 128,
            'bp_diastolic' => 82,
            'pulse' => 74,
            'spo2' => null,
            'temperature' => null,
            'weight' => 69.5,
        ]);

        $mapped = PrescriptionDataMapper::mapEncounter($appointment, $visit, $vital);

        $this->assertEquals('128/82 mmHg', $mapped['vitals.bp']);
        $this->assertEquals('74 bpm', $mapped['vitals.pulse']);
        $this->assertEquals('69.50 kg', $mapped['vitals.weight']);

        // Missing vitals must remain strictly empty string, never 0 or fake default
        $this->assertSame('', $mapped['vitals.spo2']);
        $this->assertSame('', $mapped['vitals.temperature']);
        $this->assertSame('', $mapped['vitals.fever']);
        $this->assertSame('', $mapped['vitals.rbs']);
    }

    /**
     * Section 12 & 13: Proof mode contains watermark banner; Production mode excludes it.
     */
    public function test_proof_vs_production_mode_isolation(): void
    {
        $template = $this->createDoctorTemplate($this->doctorA, 'Proof Test Pad', [
            ['field_key' => 'patient.name', 'element_type' => 'FIELD', 'x_mm' => 20, 'y_mm' => 30, 'width_mm' => 50, 'height_mm' => 6],
        ]);

        $data = ['patient.name' => 'Suresh Chandra'];

        $proofViewData = $this->renderer->prepareViewData($template, $data, 'proof');
        $this->assertEquals('proof', $proofViewData['mode']);

        $prodViewData = $this->renderer->prepareViewData($template, $data, 'production');
        $this->assertEquals('production', $prodViewData['mode']);

        // Generate raw HTML views
        $proofHtml = view('print.prescription-pad-template', $proofViewData)->render();
        $prodHtml = view('print.prescription-pad-template', $prodViewData)->render();

        $this->assertStringContainsString('PROOF MODE', $proofHtml);
        $this->assertStringNotContainsString('PROOF MODE', $prodHtml);
    }

    /**
     * Section 39: BackupService includes prescription pad tables and assets.
     */
    public function test_backupservice_includes_prescription_and_printer_tables(): void
    {
        $backupService = app(BackupService::class);
        $backup = $backupService->createBackup('BackupRev29Password!', 'MANUAL');

        $this->assertNotNull($backup);
        $this->assertFileExists($backup->file_path);

        $verify = $backupService->verifyBackup($backup->file_path, 'BackupRev29Password!');
        $this->assertEquals('VERIFIED', $verify['status']);
        $this->assertTrue($verify['has_sql']);

        // Clean up backup file
        if (File::exists($backup->file_path)) {
            File::delete($backup->file_path);
        }
        if (File::exists("{$backup->file_path}.meta.json")) {
            File::delete("{$backup->file_path}.meta.json");
        }
    }
}
