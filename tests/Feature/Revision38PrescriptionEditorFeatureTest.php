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

class Revision38PrescriptionEditorFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected User $adminUser;
    protected PrescriptionPadTemplate $draftTemplate;
    protected PrescriptionPadTemplateService $templateService;
    protected PrescriptionPdfRenderer $pdfRenderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'Metro Care Clinic',
            'phone' => '9876543210',
            'address' => '456 Ring Road',
            'city' => 'New Delhi',
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Rajesh Khanna',
            'qualification' => 'MBBS, MD (Medicine)',
            'registration_number' => 'MCI-998877',
            'specialization' => 'Physician',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Admin User',
            'email' => 'admin@metrocare.com',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->templateService = app(PrescriptionPadTemplateService::class);
        $this->pdfRenderer = app(PrescriptionPdfRenderer::class);

        $this->draftTemplate = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'name' => 'Dr. Khanna Prescription Pad V1',
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
     * 1. Multi-element selection data integrity
     */
    public function test_multi_element_selection_data_integrity(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText'); // idx 0
        $comp->call('addStaticText'); // idx 1
        $comp->call('addRectangle');  // idx 2

        $comp->call('selectElements', [0, 2]);

        $this->assertEquals([0, 2], $comp->get('selectedIndices'));
        $this->assertEquals(2, $comp->get('selectedIndex'));

        $elements = $comp->get('elements');
        $this->assertEquals('STATIC_TEXT', $elements[0]['element_type']);
        $this->assertEquals('STATIC_TEXT', $elements[1]['element_type']);
        $this->assertEquals('RECTANGLE', $elements[2]['element_type']);
    }

    /**
     * 2. Independent element identities
     */
    public function test_independent_element_identities(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText');
        $comp->call('addStaticText');
        $comp->call('addRectangle');

        $elements = $comp->get('elements');
        $this->assertCount(3, $elements);

        $keys = array_column($elements, 'field_key');
        $uids = array_column($elements, 'uid');

        $this->assertCount(3, array_unique($keys));
        $this->assertCount(3, array_unique($uids));
    }

    /**
     * 3. Layer-order persistence
     */
    public function test_layer_order_persistence(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText'); // 0
        $comp->call('addLine');       // 1
        $comp->call('addRectangle');  // 2

        // Bring element 0 forward (swaps with index 1)
        $comp->call('bringForward', 0);
        $elements = $comp->get('elements');

        $this->assertEquals('LINE', $elements[0]['element_type']);
        $this->assertEquals('STATIC_TEXT', $elements[1]['element_type']);
        $this->assertEquals(1, $elements[0]['sort_order']);
        $this->assertEquals(2, $elements[1]['sort_order']);

        // Save Draft and reload from DB
        $comp->call('saveDraft');

        $saved = PrescriptionPadElement::where('template_id', $this->draftTemplate->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $saved);
        $this->assertEquals('LINE', $saved[0]->element_type);
        $this->assertEquals('STATIC_TEXT', $saved[1]->element_type);
        $this->assertEquals('RECTANGLE', $saved[2]->element_type);
    }

    /**
     * 4. Lock state persistence & movement prevention
     */
    public function test_lock_state_persistence_and_movement_prevention(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText');
        $initialX = $comp->get('elements')[0]['x_mm'];
        $initialY = $comp->get('elements')[0]['y_mm'];

        // Lock element 0
        $comp->call('toggleLock', 0);
        $this->assertTrue($comp->get('elements')[0]['is_locked']);

        // Attempt movement while locked
        $comp->call('handleElementMoved', 0, $initialX + 30.0, $initialY + 30.0);
        $this->assertEquals($initialX, $comp->get('elements')[0]['x_mm']);
        $this->assertEquals($initialY, $comp->get('elements')[0]['y_mm']);

        // Attempt nudge while locked
        $comp->call('nudge', 0, 5.0, 5.0);
        $this->assertEquals($initialX, $comp->get('elements')[0]['x_mm']);

        // Save Draft and verify DB persistence
        $comp->call('saveDraft');
        $dbElem = PrescriptionPadElement::where('template_id', $this->draftTemplate->id)->first();
        $this->assertTrue((bool)$dbElem->is_locked);

        // Unlock and verify movement works again
        $comp->call('toggleLock', 0);
        $this->assertFalse($comp->get('elements')[0]['is_locked']);
        $comp->call('handleElementMoved', 0, $initialX + 15.0, $initialY + 15.0);
        $this->assertEquals(round($initialX + 15.0, 2), $comp->get('elements')[0]['x_mm']);
    }

    /**
     * 5. Visibility state persistence
     */
    public function test_visibility_state_persistence(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText');
        $this->assertTrue($comp->get('elements')[0]['is_visible']);

        // Toggle visibility off
        $comp->call('toggleVisibility', 0);
        $this->assertFalse($comp->get('elements')[0]['is_visible']);

        // Save Draft and check DB
        $comp->call('saveDraft');
        $dbElem = PrescriptionPadElement::where('template_id', $this->draftTemplate->id)->first();
        $this->assertFalse((bool)$dbElem->is_visible);
    }

    /**
     * 6. Duplicate identity generation
     */
    public function test_duplicate_identity_generation(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText');
        $source = $comp->get('elements')[0];

        $comp->call('duplicateElement', 0);
        $elements = $comp->get('elements');

        $this->assertCount(2, $elements);
        $duplicate = $elements[1];

        $this->assertNotEquals($source['field_key'], $duplicate['field_key']);
        $this->assertNotEquals($source['uid'], $duplicate['uid']);
        $this->assertEquals($source['x_mm'] + 5.0, $duplicate['x_mm']);
        $this->assertEquals($source['y_mm'] + 5.0, $duplicate['y_mm']);
        $this->assertStringContainsString($source['config_json']['static_text'], $duplicate['config_json']['static_text']);
    }

    /**
     * 7. Delete behavior (individual & multi-delete)
     */
    public function test_delete_behavior(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText'); // 0
        $comp->call('addLine');       // 1
        $comp->call('addRectangle');  // 2

        $this->assertCount(3, $comp->get('elements'));

        // Delete element 1 (Line)
        $comp->call('deleteElement', 1);
        $elements = $comp->get('elements');
        $this->assertCount(2, $elements);
        $this->assertEquals('STATIC_TEXT', $elements[0]['element_type']);
        $this->assertEquals('RECTANGLE', $elements[1]['element_type']);

        // Add two more elements
        $comp->call('addStaticText'); // 2
        $comp->call('addStaticText'); // 3

        // Select elements 0 and 2 and deleteSelected
        $comp->call('selectElements', [0, 2]);
        $comp->call('deleteSelected');

        $remaining = $comp->get('elements');
        $this->assertCount(2, $remaining);
        $this->assertEquals('RECTANGLE', $remaining[0]['element_type']);
    }

    /**
     * 8. Multi-element drag & move behavior
     */
    public function test_multi_element_move_behavior(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText'); // 0
        $comp->call('addRectangle');  // 1

        $elemA_x = $comp->get('elements')[0]['x_mm'];
        $elemA_y = $comp->get('elements')[0]['y_mm'];
        $elemB_x = $comp->get('elements')[1]['x_mm'];
        $elemB_y = $comp->get('elements')[1]['y_mm'];

        // Move both elements by +10mm X and +15mm Y
        $comp->call('handleElementsMoved', [
            ['index' => 0, 'x_mm' => $elemA_x + 10.0, 'y_mm' => $elemA_y + 15.0],
            ['index' => 1, 'x_mm' => $elemB_x + 10.0, 'y_mm' => $elemB_y + 15.0],
        ]);

        $elements = $comp->get('elements');
        $this->assertEquals(round($elemA_x + 10.0, 2), $elements[0]['x_mm']);
        $this->assertEquals(round($elemA_y + 15.0, 2), $elements[0]['y_mm']);
        $this->assertEquals(round($elemB_x + 10.0, 2), $elements[1]['x_mm']);
        $this->assertEquals(round($elemB_y + 15.0, 2), $elements[1]['y_mm']);
    }

    /**
     * 9. Alignment calculations (Single to page, Multi to bounds)
     */
    public function test_alignment_calculations(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText'); // width=50, height=8
        $comp->call('selectElement', 0);

        // Align single element to Page Left (0mm)
        $comp->call('alignElements', 'left');
        $this->assertEquals(0.0, $comp->get('elements')[0]['x_mm']);

        // Align single element to Page Center: (210 - 50) / 2 = 80mm
        $comp->call('alignElements', 'center');
        $this->assertEquals(80.0, $comp->get('elements')[0]['x_mm']);

        // Align single element to Page Right: 210 - 50 = 160mm
        $comp->call('alignElements', 'right');
        $this->assertEquals(160.0, $comp->get('elements')[0]['x_mm']);

        // Align single element to Page Top: 0mm
        $comp->call('alignElements', 'top');
        $this->assertEquals(0.0, $comp->get('elements')[0]['y_mm']);

        // Add second element for multi-selection alignment
        $comp->call('addRectangle'); // index 1
        $comp->call('handleElementMoved', 0, 30.0, 40.0);
        $comp->call('handleElementMoved', 1, 70.0, 90.0);

        $comp->call('selectElements', [0, 1]);

        // Multi align left: bounding minX is 30.0mm -> both get X=30.0mm
        $comp->call('alignElements', 'left');
        $elements = $comp->get('elements');
        $this->assertEquals(30.0, $elements[0]['x_mm']);
        $this->assertEquals(30.0, $elements[1]['x_mm']);

        // Multi align top: bounding minY is 40.0mm -> both get Y=40.0mm
        $comp->call('alignElements', 'top');
        $elements = $comp->get('elements');
        $this->assertEquals(40.0, $elements[0]['y_mm']);
        $this->assertEquals(40.0, $elements[1]['y_mm']);
    }

    /**
     * 10. Distribution calculations (Equal physical mm spacing for 3+ elements)
     */
    public function test_distribution_calculations(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText'); // 0 (w=50)
        $comp->call('addStaticText'); // 1 (w=50)
        $comp->call('addStaticText'); // 2 (w=50)

        // Set initial horizontal positions: A at 10, B at 20, C at 150
        $comp->call('handleElementMoved', 0, 10.0, 30.0);
        $comp->call('handleElementMoved', 1, 20.0, 40.0);
        $comp->call('handleElementMoved', 2, 150.0, 50.0);

        $comp->call('selectElements', [0, 1, 2]);
        $comp->call('distributeElements', 'horizontal');

        $elements = $comp->get('elements');
        // Total span: minX=10, maxX=(150+50)=200, total element widths=150
        // Total gap = (200 - 10 - 150) = 40mm across 2 gaps -> 20mm gap per item
        // A.x = 10, B.x = 10 + 50 + 20 = 80, C.x = 80 + 50 + 20 = 150
        $this->assertEquals(10.0, $elements[0]['x_mm']);
        $this->assertEquals(80.0, $elements[1]['x_mm']);
        $this->assertEquals(150.0, $elements[2]['x_mm']);

        // Check equal spacing between A->B and B->C
        $gap1 = $elements[1]['x_mm'] - ($elements[0]['x_mm'] + $elements[0]['width_mm']);
        $gap2 = $elements[2]['x_mm'] - ($elements[1]['x_mm'] + $elements[1]['width_mm']);
        $this->assertEquals(20.0, $gap1);
        $this->assertEquals(20.0, $gap2);
    }

    /**
     * 11. Undo / Redo state restoration
     */
    public function test_undo_redo_state_restoration(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addStaticText');
        $initX = $comp->get('elements')[0]['x_mm'];

        // Perform move
        $comp->call('handleElementMoved', 0, 65.0, 85.0);
        $this->assertEquals(65.0, $comp->get('elements')[0]['x_mm']);
        $this->assertTrue($comp->get('isDirty'));

        // Undo -> restores initial position
        $comp->call('undo');
        $this->assertEquals($initX, $comp->get('elements')[0]['x_mm']);

        // Redo -> re-applies move
        $comp->call('redo');
        $this->assertEquals(65.0, $comp->get('elements')[0]['x_mm']);
    }

    /**
     * 12. Image rotation persistence
     */
    public function test_image_rotation_persistence(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addRectangle');
        $comp->call('setElementRotation', 0, 45);

        $this->assertEquals(45, $comp->get('elements')[0]['rotation']);

        // Save Draft and reload from DB
        $comp->call('saveDraft');
        $dbElem = PrescriptionPadElement::where('template_id', $this->draftTemplate->id)->first();
        $this->assertEquals(45, $dbElem->rotation);
    }

    /**
     * 13. Historical template compatibility
     */
    public function test_historical_template_compatibility(): void
    {
        // Create an active template
        $activeTemplate = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'name' => 'Historical Template Active',
            'paper_width_mm' => 210.0,
            'paper_height_mm' => 297.0,
            'orientation' => 'portrait',
            'version' => 2,
            'status' => 'ACTIVE',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $activeTemplate->elements()->create([
            'field_key' => 'patient.name',
            'element_type' => 'FIELD',
            'x_mm' => 20.0,
            'y_mm' => 40.0,
            'width_mm' => 60.0,
            'height_mm' => 10.0,
            'font_family' => 'Helvetica',
            'font_size' => 10.0,
            'font_weight' => 'bold',
            'sort_order' => 1,
            'is_locked' => false,
            'is_visible' => true,
        ]);

        // Attempt direct modification via service should fail with InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->templateService->saveElements($activeTemplate, [], $this->adminUser->id);
    }

    /**
     * 14. PDF layer order
     */
    public function test_pdf_layer_order(): void
    {
        $this->draftTemplate->elements()->create([
            'field_key' => 'static_bg',
            'element_type' => 'RECTANGLE',
            'x_mm' => 10.0,
            'y_mm' => 10.0,
            'width_mm' => 100.0,
            'height_mm' => 50.0,
            'sort_order' => 1,
            'is_visible' => true,
            'config_json' => ['fill_color' => '#f1f5f9'],
        ]);

        $this->draftTemplate->elements()->create([
            'field_key' => 'static_fg',
            'element_type' => 'STATIC_TEXT',
            'x_mm' => 15.0,
            'y_mm' => 15.0,
            'width_mm' => 50.0,
            'height_mm' => 10.0,
            'sort_order' => 2,
            'is_visible' => true,
            'config_json' => ['static_text' => 'Overlay Text'],
        ]);

        $viewData = $this->pdfRenderer->prepareViewData($this->draftTemplate, []);
        $rendered = $viewData['renderedElements'];

        $this->assertCount(2, $rendered);
        $this->assertEquals('static_bg', $rendered[0]['field_key']);
        $this->assertEquals('static_fg', $rendered[1]['field_key']);
    }

    /**
     * 15. Image PDF transformation
     */
    public function test_image_pdf_transformation(): void
    {
        $this->draftTemplate->elements()->create([
            'field_key' => 'rotated_box',
            'element_type' => 'RECTANGLE',
            'x_mm' => 25.0,
            'y_mm' => 35.0,
            'width_mm' => 60.0,
            'height_mm' => 25.0,
            'rotation' => 30,
            'sort_order' => 1,
            'is_visible' => true,
        ]);

        $binaryPdf = $this->pdfRenderer->outputPdf($this->draftTemplate, []);
        $this->assertNotEmpty($binaryPdf);
        $this->assertStringStartsWith('%PDF', $binaryPdf);
    }

    /**
     * 16. Revision 38.1: Collision-free insertion avoids existing objects with clearance
     */
    public function test_collision_free_insertion_avoids_existing_objects(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        // Insert Object A (Field) at (50, 50)
        $comp->call('addField', 'patient_name', 50.0, 50.0);
        $elems = $comp->get('elements');
        $elemA = end($elems);
        $this->assertEquals(50.0, $elemA['x_mm']);
        $this->assertEquals(50.0, $elemA['y_mm']);

        // Insert Object B (Static Text) attempting same center (50, 50)
        $comp->call('addStaticText', 50.0, 50.0);
        $elems = $comp->get('elements');
        $elemB = end($elems);

        // B must NOT overlap A (AABB with 2mm clearance)
        $aRight = $elemA['x_mm'] + $elemA['width_mm'];
        $aBottom = $elemA['y_mm'] + $elemA['height_mm'];
        $bRight = $elemB['x_mm'] + $elemB['width_mm'];
        $bBottom = $elemB['y_mm'] + $elemB['height_mm'];

        $clearance = 2.0;
        $overlaps = !($elemB['x_mm'] >= ($aRight + $clearance) ||
                      $bRight <= ($elemA['x_mm'] - $clearance) ||
                      $elemB['y_mm'] >= ($aBottom + $clearance) ||
                      $bBottom <= ($elemA['y_mm'] - $clearance));

        $this->assertFalse($overlaps, "Newly inserted object B must not overlap existing object A");
        // Existing object A must not have been moved
        $this->assertEquals(50.0, $elems[count($elems) - 2]['x_mm']);
        $this->assertEquals(50.0, $elems[count($elems) - 2]['y_mm']);
    }

    /**
     * 17. Revision 38.1: Locked and hidden objects participate in collision detection
     */
    public function test_collision_free_insertion_accounts_for_locked_and_hidden_objects(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        // Place a locked rectangle at (40, 40)
        $comp->call('addRectangle', 40.0, 40.0);
        $elems = $comp->get('elements');
        $idxRect = count($elems) - 1;
        $comp->call('toggleLock', $idxRect);

        // Place a hidden field at (100, 40)
        $comp->call('addField', 'doctor_name', 100.0, 40.0);
        $elems = $comp->get('elements');
        $idxField = count($elems) - 1;
        $comp->call('toggleVisibility', $idxField);

        // Verify states
        $elems = $comp->get('elements');
        $this->assertTrue($elems[$idxRect]['is_locked']);
        $this->assertFalse($elems[$idxField]['is_visible']);

        // Insert new static text at (40, 40) - occupied by locked object
        $comp->call('addStaticText', 40.0, 40.0);
        $elems = $comp->get('elements');
        $newElem = end($elems);

        $rectA = $elems[$idxRect];
        $overlapsLocked = !(
            $newElem['x_mm'] >= ($rectA['x_mm'] + $rectA['width_mm']) ||
            ($newElem['x_mm'] + $newElem['width_mm']) <= $rectA['x_mm'] ||
            $newElem['y_mm'] >= ($rectA['y_mm'] + $rectA['height_mm']) ||
            ($newElem['y_mm'] + $newElem['height_mm']) <= $rectA['y_mm']
        );
        $this->assertFalse($overlapsLocked, "New object must not overlap locked object");

        // Locked object coordinates must remain untouched
        $this->assertEquals(40.0, $elems[$idxRect]['x_mm']);
        $this->assertEquals(40.0, $elems[$idxRect]['y_mm']);
    }

    /**
     * 18. Revision 38.1: Manual drag preserves free placement (intentional overlap allowed)
     */
    public function test_manual_drag_permits_intentional_overlap(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $comp->call('addField', 'patient_age', 30.0, 30.0);
        $comp->call('addField', 'patient_gender', 80.0, 30.0);

        // Manually move patient_gender directly onto patient_age at (30.0, 30.0)
        $comp->call('handleElementMoved', 1, 30.0, 30.0);

        $elems = $comp->get('elements');
        $this->assertEquals(30.0, $elems[1]['x_mm'], "Manual drag must allow free placement and intentional overlap");
        $this->assertEquals(30.0, $elems[1]['y_mm'], "Manual drag must allow free placement and intentional overlap");
    }

    /**
     * 19. Revision 38.1: Multiple consecutive insertions (A, B, C, D, E) do not overlap
     */
    public function test_multiple_consecutive_insertions_do_not_overlap(): void
    {
        $comp = Livewire::actingAs($this->adminUser)
            ->test(PrescriptionPadDesigner::class, ['templateId' => $this->draftTemplate->id]);

        $desiredX = 50.0;
        $desiredY = 50.0;

        // Insert A, B, C, D, E at the same preferred viewport center
        $comp->call('addField', 'patient_name', $desiredX, $desiredY);
        $comp->call('addField', 'patient_age', $desiredX, $desiredY);
        $comp->call('addStaticText', $desiredX, $desiredY);
        $comp->call('addRectangle', $desiredX, $desiredY);
        $comp->call('addLine', $desiredX, $desiredY);

        $elems = $comp->get('elements');
        $this->assertCount(5, $elems);

        // Assert pairwise that no two elements overlap
        for ($i = 0; $i < count($elems); $i++) {
            for ($j = $i + 1; $j < count($elems); $j++) {
                $e1 = $elems[$i];
                $e2 = $elems[$j];

                $e1R = $e1['x_mm'] + $e1['width_mm'];
                $e1B = $e1['y_mm'] + $e1['height_mm'];
                $e2R = $e2['x_mm'] + $e2['width_mm'];
                $e2B = $e2['y_mm'] + $e2['height_mm'];

                $overlaps = !($e2['x_mm'] >= $e1R ||
                              $e2R <= $e1['x_mm'] ||
                              $e2['y_mm'] >= $e1B ||
                              $e2B <= $e1['y_mm']);

                $this->assertFalse(
                    $overlaps,
                    "Element {$i} ({$e1['field_key']}) overlaps element {$j} ({$e2['field_key']})"
                );
            }
        }
    }
}
