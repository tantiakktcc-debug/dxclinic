<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PrescriptionPadElement;
use App\Models\PrescriptionPadTemplate;
use App\Models\User;
use App\Models\Visit;
use App\Models\Vital;
use App\Services\PrescriptionPadTemplateService;
use App\Services\PrescriptionPdfRenderer;
use App\Services\PrescriptionTemplateResolver;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class Revision28FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Clinic $otherClinic;
    protected Doctor $doctor1;
    protected Doctor $doctor2;
    protected User $adminUser;
    protected User $operatorUser;
    protected Patient $patient;
    protected PrescriptionPadTemplateService $templateService;
    protected PrescriptionTemplateResolver $resolver;
    protected PrescriptionPdfRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'name' => 'DxClinic Main Center',
            'address' => 'Station Road',
            'city' => 'Varanasi',
            'phone' => '9876543210',
            'email' => 'main@dxclinic.com',
            'is_onboarded' => true,
        ]);

        $this->otherClinic = Clinic::create([
            'name' => 'DxClinic Branch Two',
            'address' => 'Mall Road',
            'city' => 'Kanpur',
            'phone' => '9876543219',
            'email' => 'branch@dxclinic.com',
            'is_onboarded' => true,
        ]);

        $this->doctor1 = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'UP-12345',
            'specialization' => 'General Medicine',
            'is_active' => true,
        ]);

        $this->doctor2 = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Priya Patel',
            'qualification' => 'MBBS, MS',
            'registration_number' => 'UP-67890',
            'specialization' => 'Cardiology',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'System Admin',
            'email' => 'admin@dxclinic.local',
            'password' => bcrypt('password123'),
            'role' => 'ADMIN',
        ]);

        $this->operatorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Desk Operator',
            'email' => 'operator@dxclinic.local',
            'password' => bcrypt('password123'),
            'role' => 'OPERATOR',
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-TEST-001',
            'name' => 'Ramesh Kumar',
            'mobile' => '9876500001',
            'sex' => 'M',
            'age' => 45,
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $this->templateService = app(PrescriptionPadTemplateService::class);
        $this->resolver = app(PrescriptionTemplateResolver::class);
        $this->renderer = app(PrescriptionPdfRenderer::class);
    }

    /**
     * Helper to create a fully-formed template for a doctor
     */
    protected function createDoctorTemplate(Doctor $doctor, string $name, array $fieldKeys = ['patient.name', 'vitals.bp']): PrescriptionPadTemplate
    {
        $template = $this->templateService->createDraft([
            'clinic_id' => $doctor->clinic_id,
            'doctor_id' => $doctor->id,
            'name' => $name,
            'paper_width_mm' => 210.0,
            'paper_height_mm' => 297.0,
            'orientation' => 'portrait',
        ], $this->adminUser->id);

        $elements = [];
        $y = 20.0;
        foreach ($fieldKeys as $fk) {
            $elements[] = [
                'field_key' => $fk,
                'element_type' => 'FIELD',
                'x_mm' => 20.0,
                'y_mm' => $y,
                'width_mm' => 50.0,
                'height_mm' => 8.0,
                'font_family' => 'Helvetica',
                'font_size' => 10.0,
                'is_visible' => true,
            ];
            $y += 10.0;
        }

        $this->templateService->saveElements($template, $elements, $this->adminUser->id);
        return $template;
    }

    /**
     * Helper to create an appointment encounter
     */
    protected function createEncounter(Doctor $doctor, int $tokenNumber = 1): Appointment
    {
        return Appointment::create([
            'clinic_id' => $doctor->clinic_id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'token_number' => $tokenNumber,
            'formatted_token' => str_pad($tokenNumber, 3, '0', STR_PAD_LEFT),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'COMPLETED',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'is_legacy_pad' => false,
        ]);
    }

    /**
     * TEST A:
     * Doctor 1 has Template V1 ACTIVE.
     * Generate/commit Doctor Pad for Encounter A.
     * Verify historical record stores V1.
     */
    public function test_a_encounter_pad_generation_commits_v1(): void
    {
        $v1 = $this->createDoctorTemplate($this->doctor1, 'Dr. Sharma Pad V1');
        $this->templateService->activateTemplate($v1, $this->adminUser->id);
        $this->assertTrue($v1->fresh()->isActive());

        $encounterA = $this->createEncounter($this->doctor1, 101);
        $this->assertNull($encounterA->prescription_pad_template_id);

        // Generate Doctor Pad via resolver
        $resolved = $this->resolver->resolveForAppointment($encounterA);

        $this->assertNotNull($resolved);
        $this->assertEquals($v1->id, $resolved->id);

        // Verify historical record stores V1
        $this->assertEquals($v1->id, $encounterA->fresh()->prescription_pad_template_id);
    }

    /**
     * TEST B:
     * Duplicate/create Template V2.
     * Activate V2.
     * Verify V1 becomes ARCHIVED.
     * Verify Encounter A still references V1.
     */
    public function test_b_activate_v2_archives_v1_and_preserves_encounter_a_reference(): void
    {
        $v1 = $this->createDoctorTemplate($this->doctor1, 'Dr. Sharma Pad V1');
        $this->templateService->activateTemplate($v1, $this->adminUser->id);

        $encounterA = $this->createEncounter($this->doctor1, 102);
        $this->resolver->resolveForAppointment($encounterA);
        $this->assertEquals($v1->id, $encounterA->fresh()->prescription_pad_template_id);

        // Create V2 and activate
        $v2 = $this->templateService->duplicateAsDraft($v1, $this->adminUser->id);
        $this->assertEquals(2, $v2->version);
        $this->templateService->activateTemplate($v2, $this->adminUser->id);

        // Verify V1 is ARCHIVED and V2 is ACTIVE
        $this->assertEquals('ARCHIVED', $v1->fresh()->status);
        $this->assertFalse($v1->fresh()->is_active);
        $this->assertEquals('ACTIVE', $v2->fresh()->status);
        $this->assertTrue($v2->fresh()->is_active);

        // Verify Encounter A still references V1
        $this->assertEquals($v1->id, $encounterA->fresh()->prescription_pad_template_id);
    }

    /**
     * TEST C:
     * Reprint Encounter A.
     * Verify renderer uses V1, NOT V2.
     */
    public function test_c_reprint_encounter_a_uses_v1_not_v2(): void
    {
        $v1 = $this->createDoctorTemplate($this->doctor1, 'Dr. Sharma Pad V1', ['patient.name', 'vitals.bp']);
        $this->templateService->activateTemplate($v1, $this->adminUser->id);

        $encounterA = $this->createEncounter($this->doctor1, 103);
        $this->resolver->resolveForAppointment($encounterA);

        // Now activate V2 with different fields
        $v2 = $this->templateService->duplicateAsDraft($v1, $this->adminUser->id);
        $this->templateService->saveElements($v2, [
            [
                'field_key' => 'patient.name',
                'element_type' => 'FIELD',
                'x_mm' => 10.0,
                'y_mm' => 10.0,
                'width_mm' => 40.0,
                'height_mm' => 6.0,
                'font_family' => 'Helvetica',
                'font_size' => 10.0,
                'is_visible' => true,
            ],
            [
                'field_key' => 'vitals.pulse',
                'element_type' => 'FIELD',
                'x_mm' => 10.0,
                'y_mm' => 20.0,
                'width_mm' => 40.0,
                'height_mm' => 6.0,
                'font_family' => 'Helvetica',
                'font_size' => 10.0,
                'is_visible' => true,
            ],
        ], $this->adminUser->id);
        $this->templateService->activateTemplate($v2, $this->adminUser->id);

        // Reprint Encounter A: MUST resolve V1, not V2!
        $reprintTemplate = $this->resolver->resolveForAppointment($encounterA->fresh());

        $this->assertNotNull($reprintTemplate);
        $this->assertEquals($v1->id, $reprintTemplate->id);
        $this->assertEquals(1, $reprintTemplate->version);
        $this->assertNotEquals($v2->id, $reprintTemplate->id);

        // Test actual HTTP route reprint returns HTTP 200 using V1
        $this->actingAs($this->adminUser)
            ->get(route('print.doctor.pad', $encounterA->id))
            ->assertOk()
            ->assertViewIs('print.prescription-pad-template');
    }

    /**
     * TEST D:
     * Generate Doctor Pad for Encounter B after V2 becomes ACTIVE.
     * Verify Encounter B references V2.
     */
    public function test_d_encounter_b_generated_under_v2_references_v2(): void
    {
        $v1 = $this->createDoctorTemplate($this->doctor1, 'Dr. Sharma Pad V1');
        $this->templateService->activateTemplate($v1, $this->adminUser->id);

        $v2 = $this->templateService->duplicateAsDraft($v1, $this->adminUser->id);
        $this->templateService->activateTemplate($v2, $this->adminUser->id);

        $encounterB = $this->createEncounter($this->doctor1, 104);
        $resolvedB = $this->resolver->resolveForAppointment($encounterB);

        $this->assertNotNull($resolvedB);
        $this->assertEquals($v2->id, $resolvedB->id);
        $this->assertEquals(2, $resolvedB->version);
        $this->assertEquals($v2->id, $encounterB->fresh()->prescription_pad_template_id);
    }

    /**
     * TEST E:
     * Reprint Encounter B.
     * Verify renderer uses V2.
     */
    public function test_e_reprint_encounter_b_uses_v2(): void
    {
        $v1 = $this->createDoctorTemplate($this->doctor1, 'Dr. Sharma Pad V1');
        $this->templateService->activateTemplate($v1, $this->adminUser->id);

        $v2 = $this->templateService->duplicateAsDraft($v1, $this->adminUser->id);
        $this->templateService->activateTemplate($v2, $this->adminUser->id);

        $encounterB = $this->createEncounter($this->doctor1, 105);
        $this->resolver->resolveForAppointment($encounterB);

        // Reprint Encounter B
        $reprintTemplateB = $this->resolver->resolveForAppointment($encounterB->fresh());
        $this->assertEquals($v2->id, $reprintTemplateB->id);
        $this->assertEquals(2, $reprintTemplateB->version);
    }

    /**
     * TEST F:
     * Historical V1 remains printable while ARCHIVED.
     */
    public function test_f_archived_v1_remains_printable(): void
    {
        $v1 = $this->createDoctorTemplate($this->doctor1, 'Dr. Sharma Pad V1');
        $this->templateService->activateTemplate($v1, $this->adminUser->id);

        $encounterA = $this->createEncounter($this->doctor1, 106);
        $this->resolver->resolveForAppointment($encounterA);

        // Archive V1 by activating V2
        $v2 = $this->templateService->duplicateAsDraft($v1, $this->adminUser->id);
        $this->templateService->activateTemplate($v2, $this->adminUser->id);
        $this->assertTrue($v1->fresh()->isArchived());

        // Verify PDF generation of archived V1 is valid and non-empty
        $resolved = $this->resolver->resolveForAppointment($encounterA->fresh());
        $pdfOutput = $this->renderer->outputPdf($resolved, ['patient_name' => 'Ramesh Kumar'], 'production');

        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF-', $pdfOutput);
    }

    /**
     * TEST G:
     * Attempt unauthorized cross-clinic or cross-doctor template access.
     * Verify rejection.
     */
    public function test_g_cross_clinic_or_doctor_access_rejected(): void
    {
        $vDoctor2 = $this->createDoctorTemplate($this->doctor2, 'Dr. Patel Pad V1');
        $this->templateService->activateTemplate($vDoctor2, $this->adminUser->id);

        // Encounter belonging to Doctor 1
        $encounterDoc1 = $this->createEncounter($this->doctor1, 107);

        // Forcibly corrupt appointment foreign key to point to Doctor 2's template
        $encounterDoc1->update(['prescription_pad_template_id' => $vDoctor2->id]);

        $this->expectException(AuthorizationException::class);
        $this->resolver->resolveForAppointment($encounterDoc1->fresh());
    }

    /**
     * TEST H:
     * Legacy historical record with NULL template reference and is_legacy_pad = true.
     * Verify existing legacy renderer remains functional.
     */
    public function test_h_legacy_historical_record_uses_legacy_renderer(): void
    {
        // Doctor 1 has an active template
        $v1 = $this->createDoctorTemplate($this->doctor1, 'Dr. Sharma Pad V1');
        $this->templateService->activateTemplate($v1, $this->adminUser->id);

        // Legacy appointment
        $legacyApt = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_date' => Carbon::yesterday()->toDateString(),
            'token_number' => 999,
            'formatted_token' => '999',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'COMPLETED',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'prescription_pad_template_id' => null,
            'is_legacy_pad' => true,
        ]);

        $resolved = $this->resolver->resolveForAppointment($legacyApt);
        $this->assertNull($resolved, 'Legacy record must resolve to null to trigger legacy Blade renderer');

        // HTTP print route renders legacy blade view
        $response = $this->actingAs($this->adminUser)
            ->get(route('print.doctor.pad', $legacyApt->id));

        $response->assertOk();
        $response->assertViewIs('print.doctor-pad');
        $response->assertSee('preprinted-mode', false);
    }

    /**
     * TEST I:
     * Future active-template change does not mutate historical references.
     */
    public function test_i_future_template_activations_do_not_mutate_historical_references(): void
    {
        $v1 = $this->createDoctorTemplate($this->doctor1, 'Dr. Sharma Pad V1');
        $this->templateService->activateTemplate($v1, $this->adminUser->id);

        $encounterA = $this->createEncounter($this->doctor1, 108);
        $this->resolver->resolveForAppointment($encounterA);

        // Activate V2
        $v2 = $this->templateService->duplicateAsDraft($v1, $this->adminUser->id);
        $this->templateService->activateTemplate($v2, $this->adminUser->id);

        // Activate V3
        $v3 = $this->templateService->duplicateAsDraft($v2, $this->adminUser->id);
        $this->templateService->activateTemplate($v3, $this->adminUser->id);

        // Verify Encounter A is STILL locked to V1
        $this->assertEquals($v1->id, $encounterA->fresh()->prescription_pad_template_id);
    }

    /**
     * TEST J:
     * Template delete behavior is safe:
     * historically referenced template cannot be destructively deleted.
     */
    public function test_j_historically_referenced_template_cannot_be_deleted(): void
    {
        $v1 = $this->createDoctorTemplate($this->doctor1, 'Dr. Sharma Pad V1');
        $this->templateService->activateTemplate($v1, $this->adminUser->id);

        $encounterA = $this->createEncounter($this->doctor1, 109);
        $this->resolver->resolveForAppointment($encounterA);

        // Verify templateService->deleteTemplate throws exception
        $this->expectException(InvalidArgumentException::class);
        $this->templateService->deleteTemplate($v1, $this->adminUser->id);
    }
}
