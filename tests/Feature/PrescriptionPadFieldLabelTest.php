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
use App\Services\PrescriptionDataMapper;
use App\Services\PrescriptionPadTemplateService;
use App\Services\PrescriptionPdfRenderer;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrescriptionPadFieldLabelTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected User $adminUser;
    protected PrescriptionPadTemplate $template;
    protected PrescriptionPdfRenderer $pdfRenderer;
    protected PrescriptionDataMapper $dataMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'City Care Clinic',
            'phone' => '9876543210',
            'address' => '123 Health Ave',
            'city' => 'Mumbai',
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Sameer Verma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-12345',
            'specialization' => 'General Medicine',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Admin Test',
            'email' => 'admin@citycare.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->template = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'name' => 'Default OPD Template',
            'paper_width_mm' => 210.0,
            'paper_height_mm' => 297.0,
            'orientation' => 'portrait',
            'version' => 1,
            'status' => 'DRAFT',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->pdfRenderer = app(PrescriptionPdfRenderer::class);
        $this->dataMapper = app(PrescriptionDataMapper::class);
    }

    private function createTestPatient(string $patientId = 'P0001'): Patient
    {
        return Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patientId,
            'name' => 'Ramesh Kumar',
            'mobile' => '9876543211',
            'sex' => 'MALE',
            'age' => 49,
            'address' => 'Flat 101, Lake View, MG Road',
            'city' => 'Mumbai',
        ]);
    }

    private function createTestAppointment(Patient $patient, string $formattedToken = '031'): Appointment
    {
        return Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'token_number' => (int)$formattedToken,
            'formatted_token' => $formattedToken,
            'secure_token' => 'sec_tok_' . md5(uniqid()),
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'COMPLETED',
        ]);
    }

    /**
     * Test 1: show_label = false renders value only (e.g. 031)
     */
    public function test_field_renders_value_only_when_show_label_is_false(): void
    {
        $patient = $this->createTestPatient('P0001');
        $appointment = $this->createTestAppointment($patient, '031');

        $element = PrescriptionPadElement::create([
            'template_id' => $this->template->id,
            'element_type' => 'FIELD',
            'field_key' => 'visit.token_no',
            'x_mm' => 20,
            'y_mm' => 30,
            'width_mm' => 50,
            'height_mm' => 10,
            'font_size' => 11,
            'config_json' => [
                'show_label' => false,
                'label_text' => 'Token No.',
            ],
            'sort_order' => 1,
        ]);

        $data = PrescriptionDataMapper::mapEncounter($appointment);
        $viewData = $this->pdfRenderer->prepareViewData($this->template, $data);
        $renderedElement = collect($viewData['renderedElements'])->firstWhere('field_key', $element->field_key);

        $this->assertNotNull($renderedElement);
        $this->assertEquals('031', $renderedElement['rendered_value']);
    }

    /**
     * Test 2: show_label = true renders default label + ": " + value (e.g. Token No.: 031)
     */
    public function test_field_renders_label_prefix_when_show_label_is_true(): void
    {
        $patient = $this->createTestPatient('P0002');
        $appointment = $this->createTestAppointment($patient, '031');

        $element = PrescriptionPadElement::create([
            'template_id' => $this->template->id,
            'element_type' => 'FIELD',
            'field_key' => 'visit.token_no',
            'x_mm' => 20,
            'y_mm' => 30,
            'width_mm' => 50,
            'height_mm' => 10,
            'font_size' => 11,
            'config_json' => [
                'show_label' => true,
                'label_text' => 'Token No.',
            ],
            'sort_order' => 1,
        ]);

        $data = PrescriptionDataMapper::mapEncounter($appointment);
        $viewData = $this->pdfRenderer->prepareViewData($this->template, $data);
        $renderedElement = collect($viewData['renderedElements'])->firstWhere('field_key', $element->field_key);

        $this->assertNotNull($renderedElement);
        $this->assertEquals('Token No.: 031', $renderedElement['rendered_value']);
    }

    /**
     * Test 3: Custom label renders custom label + ": " + value (e.g. Token Number: 031)
     */
    public function test_field_renders_custom_label_text_when_configured(): void
    {
        $patient = $this->createTestPatient('P0003');
        $appointment = $this->createTestAppointment($patient, '031');

        $element = PrescriptionPadElement::create([
            'template_id' => $this->template->id,
            'element_type' => 'FIELD',
            'field_key' => 'visit.token_no',
            'x_mm' => 20,
            'y_mm' => 30,
            'width_mm' => 50,
            'height_mm' => 10,
            'font_size' => 11,
            'config_json' => [
                'show_label' => true,
                'label_text' => 'Token Number',
            ],
            'sort_order' => 1,
        ]);

        $data = PrescriptionDataMapper::mapEncounter($appointment);
        $viewData = $this->pdfRenderer->prepareViewData($this->template, $data);
        $renderedElement = collect($viewData['renderedElements'])->firstWhere('field_key', $element->field_key);

        $this->assertNotNull($renderedElement);
        $this->assertEquals('Token Number: 031', $renderedElement['rendered_value']);
    }

    /**
     * Test 4: Separator must be strictly ": " (one colon, one space)
     */
    public function test_label_separator_strictly_uses_colon_space(): void
    {
        $patient = $this->createTestPatient('P0004');
        $appointment = $this->createTestAppointment($patient, '031');

        $element = PrescriptionPadElement::create([
            'template_id' => $this->template->id,
            'element_type' => 'FIELD',
            'field_key' => 'patient.name',
            'x_mm' => 20,
            'y_mm' => 30,
            'width_mm' => 50,
            'height_mm' => 10,
            'font_size' => 11,
            'config_json' => [
                'show_label' => true,
                'label_text' => 'Patient Name',
            ],
            'sort_order' => 1,
        ]);

        $data = PrescriptionDataMapper::mapEncounter($appointment);
        $viewData = $this->pdfRenderer->prepareViewData($this->template, $data);
        $renderedElement = collect($viewData['renderedElements'])->firstWhere('field_key', $element->field_key);

        $this->assertNotNull($renderedElement);
        $this->assertStringContainsString(': ', $renderedElement['rendered_value']);
        $this->assertStringNotContainsString(' : ', $renderedElement['rendered_value']);
        $this->assertStringNotContainsString(' - ', $renderedElement['rendered_value']);
        $this->assertStringNotContainsString(' = ', $renderedElement['rendered_value']);
        $this->assertEquals('Patient Name: Ramesh Kumar', $renderedElement['rendered_value']);
    }

    /**
     * Test 5: Backward compatibility - legacy elements without show_label default to false
     */
    public function test_backward_compatibility_when_show_label_is_missing(): void
    {
        $patient = $this->createTestPatient('P0005');
        $appointment = $this->createTestAppointment($patient, '031');

        // Legacy element with empty config_json
        $element = PrescriptionPadElement::create([
            'template_id' => $this->template->id,
            'element_type' => 'FIELD',
            'field_key' => 'visit.token_no',
            'x_mm' => 20,
            'y_mm' => 30,
            'width_mm' => 50,
            'height_mm' => 10,
            'font_size' => 11,
            'config_json' => [],
            'sort_order' => 1,
        ]);

        $this->assertFalse($element->show_label);
        $this->assertEquals('Token No.', $element->effective_label);

        $data = PrescriptionDataMapper::mapEncounter($appointment);
        $viewData = $this->pdfRenderer->prepareViewData($this->template, $data);
        $renderedElement = collect($viewData['renderedElements'])->firstWhere('field_key', $element->field_key);

        $this->assertNotNull($renderedElement);
        $this->assertEquals('031', $renderedElement['rendered_value']);
    }

    /**
     * Test 6: Null or empty value handling - does NOT print "Weight:" or "Weight: null"
     */
    public function test_empty_value_suppresses_label_prefix(): void
    {
        $patient = $this->createTestPatient('P0006');
        // Appointment without recorded vitals
        $appointment = $this->createTestAppointment($patient, '031');

        $element = PrescriptionPadElement::create([
            'template_id' => $this->template->id,
            'element_type' => 'FIELD',
            'field_key' => 'vital.weight',
            'x_mm' => 20,
            'y_mm' => 30,
            'width_mm' => 50,
            'height_mm' => 10,
            'font_size' => 11,
            'config_json' => [
                'show_label' => true,
                'label_text' => 'Weight',
            ],
            'sort_order' => 1,
        ]);

        $data = PrescriptionDataMapper::mapEncounter($appointment);
        $viewData = $this->pdfRenderer->prepareViewData($this->template, $data);
        $renderedElement = collect($viewData['renderedElements'])->firstWhere('field_key', $element->field_key);

        $this->assertNotNull($renderedElement);
        // Must be empty string, never "Weight:" or "Weight: null"
        $this->assertSame('', $renderedElement['rendered_value']);
    }

    /**
     * Test 7: PrescriptionPadDesigner Livewire component field addition & property update
     */
    public function test_designer_initializes_field_label_properties_and_supports_updating(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->template->id]);

        // Add a field
        $comp->call('addField', 'visit.token_no');

        $elements = $comp->get('elements');
        $this->assertCount(1, $elements);
        $addedField = $elements[0];

        $this->assertEquals('FIELD', $addedField['element_type']);
        $this->assertEquals('visit.token_no', $addedField['field_key']);
        $this->assertFalse($addedField['config_json']['show_label']);
        $this->assertEquals('Token No.', $addedField['config_json']['label_text']);

        // Update show_label and label_text via updateElementConfig
        $comp->call('updateElementConfig', 0, 'show_label', true);
        $comp->call('updateElementConfig', 0, 'label_text', 'Token Number');
        $comp->call('saveDraft');

        // Reload from database to verify persistence
        $this->template->refresh();
        $persisted = $this->template->elements()->first();

        $this->assertNotNull($persisted);
        $this->assertTrue($persisted->show_label);
        $this->assertEquals('Token Number', $persisted->label_text);
    }

    /**
     * Test 8: Livewire deleteElement action deletes exact targeted element
     */
    public function test_designer_delete_element_removes_exact_targeted_object(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->template->id]);

        $comp->call('addField', 'visit.token_no');    // idx 0
        $comp->call('addField', 'patient.name');      // idx 1
        $comp->call('addField', 'patient.age');       // idx 2

        $this->assertCount(3, $comp->get('elements'));

        // Delete element at index 1 (patient.name)
        $comp->call('deleteElement', 1);

        $remaining = $comp->get('elements');
        $this->assertCount(2, $remaining);
        $this->assertEquals('visit.token_no', $remaining[0]['field_key']);
        $this->assertEquals('patient.age', $remaining[1]['field_key']);

        // Persist and verify in DB
        $comp->call('saveDraft');
        $dbKeys = $this->template->elements()->orderBy('sort_order')->pluck('field_key')->all();
        $this->assertEquals(['visit.token_no', 'patient.age'], $dbKeys);
    }

    /**
     * Test 9: Field insertion immediately selects new element and dispatches element-selected event
     */
    public function test_designer_field_insertion_selects_new_element_and_dispatches_event(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->template->id]);

        // Add Field A
        $comp->call('addField', 'patient.name');
        $this->assertSame(0, $comp->get('selectedIndex'));
        $comp->assertDispatched('element-selected', index: 0);

        // Add Field B
        $comp->call('addField', 'patient.age');
        $this->assertSame(1, $comp->get('selectedIndex'));
        $comp->assertDispatched('element-selected', index: 1);

        // Show Field Name toggle on Field B without second click
        $comp->call('updateElementConfig', 1, 'show_label', true);
        $elements = $comp->get('elements');
        $this->assertTrue($elements[1]['config_json']['show_label']);
        $this->assertFalse($elements[0]['config_json']['show_label']);
    }

    /**
     * Test 10: Cut selected copies elements to clipboard and removes from canvas
     */
    public function test_designer_cut_selected_copies_to_clipboard_and_deletes_element(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->template->id]);

        $comp->call('addField', 'patient.name'); // idx 0
        $comp->call('addField', 'patient.mobile'); // idx 1

        $this->assertCount(2, $comp->get('elements'));

        // Select and cut idx 1
        $comp->call('selectElement', 1);
        $comp->call('cutSelected');

        // Elements count is now 1
        $elements = $comp->get('elements');
        $this->assertCount(1, $elements);
        $this->assertEquals('patient.name', $elements[0]['field_key']);

        // Clipboard should contain the cut element
        $clipboard = $comp->get('clipboard');
        $this->assertNotEmpty($clipboard);
        $this->assertEquals('patient.mobile', $clipboard[0]['field_key']);

        // Paste restores it
        $comp->call('paste');
        $this->assertCount(2, $comp->get('elements'));
    }
}

