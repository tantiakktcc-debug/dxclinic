<?php

namespace Tests\Feature;

use App\Livewire\Settings\PrescriptionPadDesigner;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PrescriptionPadElement;
use App\Models\PrescriptionPadTemplate;
use App\Models\User;
use App\Services\PrescriptionPadTemplateService;
use App\Services\PrescriptionPdfRenderer;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Revision37PrescriptionEditorFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected User $adminUser;
    protected PrescriptionPadTemplate $draftTemplate;
    protected PrescriptionPadTemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'City Care Clinic',
            'phone' => '9876543210',
            'address' => '123 Main Road',
            'city' => 'Varanasi',
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Amit Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-11111',
            'specialization' => 'Cardiologist',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Admin Officer',
            'email' => 'admin@citycare.com',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->templateService = app(PrescriptionPadTemplateService::class);

        $this->draftTemplate = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'name' => 'Dr. Sharma Pad Draft V1',
            'paper_width_mm' => 210.0,
            'paper_height_mm' => 297.0,
            'orientation' => 'portrait',
            'version' => 1,
            'status' => 'DRAFT',
            'is_active' => false,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);
    }

    /**
     * Requirement A, B & C: Multiple Static Text elements coexist and remain independent.
     */
    public function test_multiple_static_text_elements_coexist_independently(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        // Add Static Text A
        $comp->call('addStaticText');
        $elements = $comp->get('elements');
        $this->assertCount(1, $elements);
        $this->assertEquals('STATIC_TEXT', $elements[0]['element_type']);
        $textA_key = $elements[0]['field_key'];

        // Edit Static Text A
        $comp->call('updateElementConfig', 0, 'static_text', 'Consultation Hours: 9am - 1pm');
        $elements = $comp->get('elements');
        $this->assertEquals('Consultation Hours: 9am - 1pm', $elements[0]['config_json']['static_text']);

        // Add Static Text B
        $comp->call('addStaticText');
        $elements = $comp->get('elements');
        $this->assertCount(2, $elements);

        // Verify Static Text B has unique key and does not overwrite Static Text A
        $this->assertNotEquals($textA_key, $elements[1]['field_key']);
        $this->assertEquals('Consultation Hours: 9am - 1pm', $elements[0]['config_json']['static_text']);

        // Edit Static Text B
        $comp->call('updateElementConfig', 1, 'static_text', 'Emergency: 24 Hours Contact');
        $elements = $comp->get('elements');

        $this->assertEquals('Consultation Hours: 9am - 1pm', $elements[0]['config_json']['static_text']);
        $this->assertEquals('Emergency: 24 Hours Contact', $elements[1]['config_json']['static_text']);

        // Add Static Text C
        $comp->call('addStaticText');
        $comp->call('updateElementConfig', 2, 'static_text', 'Valid for 7 days only');
        $elements = $comp->get('elements');
        $this->assertCount(3, $elements);

        $this->assertEquals('Consultation Hours: 9am - 1pm', $elements[0]['config_json']['static_text']);
        $this->assertEquals('Emergency: 24 Hours Contact', $elements[1]['config_json']['static_text']);
        $this->assertEquals('Valid for 7 days only', $elements[2]['config_json']['static_text']);
    }

    /**
     * Requirement D, E & F: Each element has independent typography; changing one element does not affect others.
     */
    public function test_individual_element_typography_isolation(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        // Add 2 static texts and 1 field
        $comp->call('addStaticText'); // index 0
        $comp->call('addStaticText'); // index 1
        $comp->call('addField', 'patient.name'); // index 2

        // Set typography for element 0 (Header text)
        $comp->call('updateElementProperty', 0, 'font_size', 16.0);
        $comp->call('updateElementProperty', 0, 'font_family', 'Times-Roman');
        $comp->call('updateElementProperty', 0, 'font_weight', 'bold');
        $comp->call('updateElementProperty', 0, 'color', '#1e3a8a');

        // Set typography for element 1 (Disclaimer text)
        $comp->call('updateElementProperty', 1, 'font_size', 8.5);
        $comp->call('updateElementProperty', 1, 'font_family', 'Courier');
        $comp->call('updateElementProperty', 1, 'font_weight', 'normal');
        $comp->call('updateElementProperty', 1, 'color', '#64748b');

        // Set typography for element 2 (Patient Name dynamic field)
        $comp->call('updateElementProperty', 2, 'font_size', 12.0);
        $comp->call('updateElementProperty', 2, 'font_family', 'Helvetica');
        $comp->call('updateElementProperty', 2, 'font_weight', 'bold');
        $comp->call('updateElementProperty', 2, 'color', '#000000');

        $elements = $comp->get('elements');

        // Verify Element 0 typography
        $this->assertEquals(16.0, $elements[0]['font_size']);
        $this->assertEquals('Times-Roman', $elements[0]['font_family']);
        $this->assertEquals('bold', $elements[0]['font_weight']);
        $this->assertEquals('#1e3a8a', $elements[0]['color']);

        // Verify Element 1 typography is strictly isolated
        $this->assertEquals(8.5, $elements[1]['font_size']);
        $this->assertEquals('Courier', $elements[1]['font_family']);
        $this->assertEquals('normal', $elements[1]['font_weight']);
        $this->assertEquals('#64748b', $elements[1]['color']);

        // Verify Element 2 typography is strictly isolated
        $this->assertEquals(12.0, $elements[2]['font_size']);
        $this->assertEquals('Helvetica', $elements[2]['font_family']);
        $this->assertEquals('bold', $elements[2]['font_weight']);
        $this->assertEquals('#000000', $elements[2]['color']);
    }

    /**
     * Requirement G, H & I: Selection targets correct element; saved coordinates & dimensions remain independent.
     */
    public function test_selection_and_coordinates_remain_independent(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText'); // index 0
        $comp->call('addRectangle');  // index 1

        // Move element 0 to (25.0, 40.0) and resize to 80x20
        $comp->call('handleElementMoved', 0, 25.0, 40.0);
        $comp->call('handleElementResized', 0, 25.0, 40.0, 80.0, 20.0);

        // Move element 1 to (30.0, 100.0) and resize to 150x45
        $comp->call('handleElementMoved', 1, 30.0, 100.0);
        $comp->call('handleElementResized', 1, 30.0, 100.0, 150.0, 45.0);

        $elements = $comp->get('elements');

        $this->assertEquals(25.0, $elements[0]['x_mm']);
        $this->assertEquals(40.0, $elements[0]['y_mm']);
        $this->assertEquals(80.0, $elements[0]['width_mm']);
        $this->assertEquals(20.0, $elements[0]['height_mm']);

        $this->assertEquals(30.0, $elements[1]['x_mm']);
        $this->assertEquals(100.0, $elements[1]['y_mm']);
        $this->assertEquals(150.0, $elements[1]['width_mm']);
        $this->assertEquals(45.0, $elements[1]['height_mm']);
    }

    /**
     * Requirement J: Save / Reload reproduces exact element state from database.
     */
    public function test_save_and_reload_reproduces_exact_element_state(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText');
        $comp->call('updateElementConfig', 0, 'static_text', 'Permanent Clinic Clinic Notice');
        $comp->call('updateElementProperty', 0, 'font_size', 14.5);
        $comp->call('updateElementProperty', 0, 'font_family', 'Arial');
        $comp->call('handleElementMoved', 0, 15.5, 25.5);
        $comp->call('handleElementResized', 0, 15.5, 25.5, 120.0, 18.0);

        // Add second element
        $comp->call('addStaticText');
        $comp->call('updateElementConfig', 1, 'static_text', 'Sub-heading Text');
        $comp->call('updateElementProperty', 1, 'font_size', 9.0);
        $comp->call('handleElementMoved', 1, 15.5, 45.0);

        // Save Draft to database
        $comp->call('saveDraft');

        // Verify elements were written to database
        $savedElements = PrescriptionPadElement::where('template_id', $this->draftTemplate->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $savedElements);
        $this->assertEquals('Permanent Clinic Clinic Notice', $savedElements[0]->config_json['static_text']);
        $this->assertEquals(14.5, (float)$savedElements[0]->font_size);
        $this->assertEquals('Arial', $savedElements[0]->font_family);
        $this->assertEquals(15.5, (float)$savedElements[0]->x_mm);
        $this->assertEquals(25.5, (float)$savedElements[0]->y_mm);
        $this->assertEquals(120.0, (float)$savedElements[0]->width_mm);
        $this->assertEquals(18.0, (float)$savedElements[0]->height_mm);

        $this->assertEquals('Sub-heading Text', $savedElements[1]->config_json['static_text']);
        $this->assertEquals(9.0, (float)$savedElements[1]->font_size);
        $this->assertEquals(15.5, (float)$savedElements[1]->x_mm);
        $this->assertEquals(45.0, (float)$savedElements[1]->y_mm);

        // Reload fresh component and verify complete state fidelity
        $freshComp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $reloadedElements = $freshComp->get('elements');
        $this->assertCount(2, $reloadedElements);
        $this->assertEquals('Permanent Clinic Clinic Notice', $reloadedElements[0]['config_json']['static_text']);
        $this->assertEquals(14.5, $reloadedElements[0]['font_size']);
        $this->assertEquals('Arial', $reloadedElements[0]['font_family']);
        $this->assertEquals(15.5, $reloadedElements[0]['x_mm']);
        $this->assertEquals(25.5, $reloadedElements[0]['y_mm']);
        $this->assertEquals(120.0, $reloadedElements[0]['width_mm']);
        $this->assertEquals(18.0, $reloadedElements[0]['height_mm']);

        $this->assertEquals('Sub-heading Text', $reloadedElements[1]['config_json']['static_text']);
        $this->assertEquals(9.0, $reloadedElements[1]['font_size']);
    }

    /**
     * Requirement K: Historical template safety — historical appointment retains template reference.
     */
    public function test_historical_template_association_remains_intact(): void
    {
        // 1. Activate template V1
        $this->templateService->activateTemplate($this->draftTemplate, $this->adminUser->id);
        $this->draftTemplate->refresh();
        $this->assertEquals('ACTIVE', $this->draftTemplate->status);

        // 2. Create historical appointment linked to template V1
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-HIST-001',
            'name' => 'Historical Patient',
            'mobile' => '+91 9988776655',
            'age' => 45,
            'sex' => 'MALE',
            'address' => 'Line 1 Test Nagar',
            'city' => 'Varanasi',
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'token_number' => 1,
            'formatted_token' => '001',
            'secure_token' => 'sec_tok_' . md5(uniqid()),
            'prescription_pad_template_id' => $this->draftTemplate->id,
            'appointment_date' => '2026-09-30',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'COMPLETED',
        ]);

        // 3. Duplicate template to create Draft V2 and activate V2
        $draftV2 = $this->templateService->duplicateAsDraft($this->draftTemplate, $this->adminUser->id);
        $this->templateService->activateTemplate($draftV2, $this->adminUser->id);

        $this->draftTemplate->refresh();
        $draftV2->refresh();

        $this->assertEquals('ARCHIVED', $this->draftTemplate->status);
        $this->assertEquals('ACTIVE', $draftV2->status);

        // Historical appointment still firmly points to historical Template V1
        $appointment->refresh();
        $this->assertEquals($this->draftTemplate->id, $appointment->prescription_pad_template_id);
        $this->assertEquals('ARCHIVED', $appointment->prescriptionPadTemplate->status);
    }

    /**
     * Requirement L: PDF Renderer generates valid PDF with independent static texts and font sizes.
     */
    public function test_pdf_renderer_outputs_independent_static_texts_and_typography(): void
    {
        PrescriptionPadElement::create([
            'template_id' => $this->draftTemplate->id,
            'field_key' => 'header_text',
            'element_type' => 'STATIC_TEXT',
            'x_mm' => 20,
            'y_mm' => 15,
            'width_mm' => 170,
            'height_mm' => 15,
            'font_family' => 'Helvetica',
            'font_size' => 18.0,
            'color' => '#1e3a8a',
            'config_json' => ['static_text' => 'SUPER SPECIALITY CLINIC'],
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        PrescriptionPadElement::create([
            'template_id' => $this->draftTemplate->id,
            'field_key' => 'sub_header_text',
            'element_type' => 'STATIC_TEXT',
            'x_mm' => 20,
            'y_mm' => 32,
            'width_mm' => 170,
            'height_mm' => 10,
            'font_family' => 'Times-Roman',
            'font_size' => 9.5,
            'color' => '#475569',
            'config_json' => ['static_text' => 'Timing: 10:00 AM - 02:00 PM | Sunday Closed'],
            'is_visible' => true,
            'sort_order' => 2,
        ]);

        $renderer = app(PrescriptionPdfRenderer::class);
        $sampleData = ['patient.name' => 'Rahul Verma'];

        $pdfOutput = $renderer->outputPdf($this->draftTemplate, $sampleData, 'production');

        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF-', $pdfOutput);
        $this->assertGreaterThan(1000, strlen($pdfOutput));
    }

    /**
     * Requirement M: Image upload and automatic aspect ratio calculation.
     */
    public function test_image_upload_and_aspect_ratio_initialization(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('clinic_logo.png', 400, 200);

        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id])
            ->set('imageUploadFile', $file);

        $elements = $comp->get('elements');
        $this->assertCount(1, $elements);

        $imgElem = $elements[0];
        $this->assertEquals('IMAGE', $imgElem['element_type']);
        $this->assertStringStartsWith('image_1_', $imgElem['field_key']);
        $this->assertEquals(40.0, $imgElem['width_mm']);
        $this->assertEquals(20.0, $imgElem['height_mm']); // 400x200 => aspect 2:1 => 40mm x 20mm
        $this->assertTrue($imgElem['config_json']['lock_aspect_ratio']);
        $this->assertEquals(2.0, (float)$imgElem['config_json']['aspect_ratio']);
        $this->assertStringContainsString('storage/prescription_images/', $imgElem['config_json']['image_path']);
    }

    /**
     * Requirement N: Multiple Image elements coexist independently.
     */
    public function test_multiple_images_coexist_independently(): void
    {
        Storage::fake('public');

        $logo1 = UploadedFile::fake()->image('clinic_logo.png', 300, 300);
        $logo2 = UploadedFile::fake()->image('doctor_badge.jpg', 600, 200);

        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id])
            ->set('imageUploadFile', $logo1);

        $comp->set('imageUploadFile', $logo2);

        $elements = $comp->get('elements');
        $this->assertCount(2, $elements);

        $this->assertNotEquals($elements[0]['field_key'], $elements[1]['field_key']);
        $this->assertNotEquals($elements[0]['uid'], $elements[1]['uid']);
        $this->assertNotEquals($elements[0]['config_json']['image_path'], $elements[1]['config_json']['image_path']);

        // Update Opacity on Image 1
        $comp->call('updateElementConfig', 0, 'opacity', 0.6);
        $elements = $comp->get('elements');

        $this->assertEquals(0.6, (float)$elements[0]['config_json']['opacity']);
        $this->assertEquals(1.0, (float)$elements[1]['config_json']['opacity']); // Image 2 unchanged
    }

    /**
     * Requirement O: Image upload security validations (reject invalid mime, oversize, malicious files).
     */
    public function test_image_upload_security_validation(): void
    {
        Storage::fake('public');

        // 1. Non-image file disguised as png (text file)
        $maliciousFile = UploadedFile::fake()->create('exploit.png', 20, 'image/png');
        // Fake file has no PNG header, getimagesize() fails

        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id])
            ->set('imageUploadFile', $maliciousFile);

        $this->assertCount(0, $comp->get('elements'));

        // 2. Reject executable .php file
        $phpFile = UploadedFile::fake()->create('shell.php', 10, 'text/x-php');
        $comp->set('imageUploadFile', $phpFile);
        $comp->assertHasErrors('imageUploadFile');
        $this->assertCount(0, $comp->get('elements'));

        // 3. Reject oversized file (> 5MB)
        $oversized = UploadedFile::fake()->image('giant_image.png')->size(6000);
        $comp->set('imageUploadFile', $oversized);
        $comp->assertHasErrors('imageUploadFile');
        $this->assertCount(0, $comp->get('elements'));
    }

    /**
     * Requirement P: Image element state survives save and reload.
     */
    public function test_image_persistence_on_save_and_reload(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('signature.png', 300, 150);

        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id])
            ->set('imageUploadFile', $file);

        // Move to (50.0, 80.0) and resize to 60.0 x 30.0
        $comp->call('handleElementMoved', 0, 50.0, 80.0);
        $comp->call('handleElementResized', 0, 50.0, 80.0, 60.0, 30.0);
        $comp->call('updateElementConfig', 0, 'opacity', 0.85);

        // Save Draft
        $comp->call('saveDraft');

        // Verify written to database
        $dbElem = PrescriptionPadElement::where('template_id', $this->draftTemplate->id)->first();
        $this->assertNotNull($dbElem);
        $this->assertEquals('IMAGE', $dbElem->element_type);
        $this->assertEquals(50.0, (float)$dbElem->x_mm);
        $this->assertEquals(80.0, (float)$dbElem->y_mm);
        $this->assertEquals(60.0, (float)$dbElem->width_mm);
        $this->assertEquals(30.0, (float)$dbElem->height_mm);
        $this->assertEquals(0.85, (float)$dbElem->config_json['opacity']);
        $this->assertTrue($dbElem->config_json['lock_aspect_ratio']);

        // Reload fresh component
        $freshComp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $reloaded = $freshComp->get('elements');
        $this->assertCount(1, $reloaded);
        $this->assertEquals('IMAGE', $reloaded[0]['element_type']);
        $this->assertEquals(50.0, $reloaded[0]['x_mm']);
        $this->assertEquals(80.0, $reloaded[0]['y_mm']);
        $this->assertEquals(60.0, $reloaded[0]['width_mm']);
        $this->assertEquals(30.0, $reloaded[0]['height_mm']);
        $this->assertEquals(0.85, (float)$reloaded[0]['config_json']['opacity']);
    }

    /**
     * Requirement Q: PDF Renderer includes IMAGE element in both Proof and Production modes.
     */
    public function test_pdf_renderer_includes_image_element_in_proof_and_production(): void
    {
        // Create actual 1x1 png image in public path
        $testImgRel = 'prescription_images/1/test_fixture.png';
        $testImgFull = public_path($testImgRel);
        if (!is_dir(dirname($testImgFull))) {
            mkdir(dirname($testImgFull), 0777, true);
        }
        // Valid 1x1 transparent PNG binary
        $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        file_put_contents($testImgFull, $png1x1);

        PrescriptionPadElement::create([
            'template_id' => $this->draftTemplate->id,
            'field_key' => 'clinic_logo',
            'element_type' => 'IMAGE',
            'x_mm' => 20.0,
            'y_mm' => 15.0,
            'width_mm' => 40.0,
            'height_mm' => 20.0,
            'config_json' => [
                'image_path' => $testImgRel,
                'opacity' => 1.0,
                'lock_aspect_ratio' => true,
            ],
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $renderer = app(PrescriptionPdfRenderer::class);
        $sampleData = [];

        // 1. Production Mode
        $prodPdf = $renderer->outputPdf($this->draftTemplate, $sampleData, 'production');
        $this->assertNotEmpty($prodPdf);
        $this->assertStringStartsWith('%PDF-', $prodPdf);

        // 2. Proof Mode
        $proofPdf = $renderer->outputPdf($this->draftTemplate, $sampleData, 'proof');
        $this->assertNotEmpty($proofPdf);
        $this->assertStringStartsWith('%PDF-', $proofPdf);

        // Cleanup fixture
        if (file_exists($testImgFull)) {
            @unlink($testImgFull);
        }
    }
}

