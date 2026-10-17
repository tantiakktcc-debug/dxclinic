<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PrescriptionPadElement;
use App\Models\PrescriptionPadTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PrescriptionPadTemplateService
{
    /**
     * Standard paper sizes in millimeters.
     */
    public const PAPER_SIZES = [
        'A4' => ['name' => 'A4 (210 × 297 mm)', 'width_mm' => 210.0, 'height_mm' => 297.0],
        'A5' => ['name' => 'A5 (148 × 210 mm)', 'width_mm' => 148.0, 'height_mm' => 210.0],
        'Letter' => ['name' => 'Letter (215.9 × 279.4 mm)', 'width_mm' => 215.9, 'height_mm' => 279.4],
        'Half Letter' => ['name' => 'Half Letter (139.7 × 215.9 mm)', 'width_mm' => 139.7, 'height_mm' => 215.9],
        'Custom' => ['name' => 'Custom Size', 'width_mm' => 210.0, 'height_mm' => 297.0],
    ];

    /**
     * Create a new draft template.
     */
    public function createDraft(array $data, int $userId): PrescriptionPadTemplate
    {
        return DB::transaction(function () use ($data, $userId) {
            $clinicId = $data['clinic_id'];
            $doctorId = $data['doctor_id'];

            // Next version for this doctor in this clinic
            $latestVersion = PrescriptionPadTemplate::where('clinic_id', $clinicId)
                ->where('doctor_id', $doctorId)
                ->max('version') ?? 0;

            $template = PrescriptionPadTemplate::create([
                'clinic_id' => $clinicId,
                'doctor_id' => $doctorId,
                'name' => $data['name'] ?? 'Doctor Pad Template V' . ($latestVersion + 1),
                'description' => $data['description'] ?? null,
                'paper_width_mm' => $data['paper_width_mm'] ?? 210.00,
                'paper_height_mm' => $data['paper_height_mm'] ?? 297.00,
                'orientation' => $data['orientation'] ?? 'portrait',
                'background_path' => $data['background_path'] ?? null,
                'background_width_px' => $data['background_width_px'] ?? null,
                'background_height_px' => $data['background_height_px'] ?? null,
                'template_type' => $data['template_type'] ?? 'CUSTOM',
                'version' => $latestVersion + 1,
                'status' => 'DRAFT',
                'is_active' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return $template;
        });
    }

    /**
     * Create a new DRAFT template from the legacy Doctor Pad configuration.
     * Legacy settings remain untouched.
     */
    public function createFromLegacySettings(int $doctorId, int $clinicId, int $userId): PrescriptionPadTemplate
    {
        return DB::transaction(function () use ($doctorId, $clinicId, $userId) {
            $doctor = Doctor::findOrFail($doctorId);

            $latestVersion = PrescriptionPadTemplate::where('clinic_id', $clinicId)
                ->where('doctor_id', $doctorId)
                ->max('version') ?? 0;

            $template = PrescriptionPadTemplate::create([
                'clinic_id' => $clinicId,
                'doctor_id' => $doctorId,
                'name' => 'Imported Legacy Pad - ' . $doctor->display_name . ' V' . ($latestVersion + 1),
                'description' => 'Imported from legacy Doctor Pad settings. Stored as draft; legacy remains active.',
                'paper_width_mm' => 209.55, // 8.25 in
                'paper_height_mm' => 296.44, // 11.671 in
                'orientation' => 'portrait',
                'background_path' => file_exists(public_path('images/doctor-pad-reference.jpg')) ? 'images/doctor-pad-reference.jpg' : null,
                'template_type' => 'LEGACY_IMPORT',
                'version' => $latestVersion + 1,
                'status' => 'DRAFT',
                'is_active' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Legacy elements mapped from inches to mm (1 in = 25.4 mm)
            $legacyElements = [
                [
                    'field_key' => 'patient.name',
                    'element_type' => 'FIELD',
                    'x_mm' => round(3.28 * 25.4, 2), // 83.31 mm
                    'y_mm' => round(2.06 * 25.4, 2), // 52.32 mm
                    'width_mm' => round(3.5 * 25.4, 2), // 88.90 mm
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 11.0,
                    'font_weight' => 'bold',
                    'sort_order' => 1,
                ],
                [
                    'field_key' => 'patient.age_sex',
                    'element_type' => 'FIELD',
                    'x_mm' => round(6.94 * 25.4, 2), // 176.28 mm
                    'y_mm' => round(2.06 * 25.4, 2), // 52.32 mm
                    'width_mm' => 30.00,
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 10.0,
                    'font_weight' => 'normal',
                    'sort_order' => 2,
                ],
                [
                    'field_key' => 'patient.address',
                    'element_type' => 'FIELD',
                    'x_mm' => round(2.85 * 25.4, 2), // 72.39 mm
                    'y_mm' => round(2.34 * 25.4, 2), // 59.44 mm
                    'width_mm' => round(3.7 * 25.4, 2), // 93.98 mm
                    'height_mm' => 8.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 9.5,
                    'font_weight' => 'normal',
                    'sort_order' => 3,
                ],
                [
                    'field_key' => 'visit.date',
                    'element_type' => 'FIELD',
                    'x_mm' => round(6.72 * 25.4, 2), // 170.69 mm
                    'y_mm' => round(2.34 * 25.4, 2), // 59.44 mm
                    'width_mm' => 30.00,
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 10.0,
                    'font_weight' => 'normal',
                    'sort_order' => 4,
                ],
                [
                    'field_key' => 'patient.patient_id',
                    'element_type' => 'FIELD',
                    'x_mm' => round(4.28 * 25.4, 2), // 108.71 mm
                    'y_mm' => round(2.76 * 25.4, 2), // 70.10 mm
                    'width_mm' => round(2.0 * 25.4, 2), // 50.80 mm
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 10.0,
                    'font_weight' => 'bold',
                    'sort_order' => 5,
                ],
                [
                    'field_key' => 'visit.token_no',
                    'element_type' => 'FIELD',
                    'x_mm' => round(6.38 * 25.4, 2), // 162.05 mm
                    'y_mm' => round(2.76 * 25.4, 2), // 70.10 mm
                    'width_mm' => round(1.5 * 25.4, 2), // 38.10 mm
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 10.0,
                    'font_weight' => 'bold',
                    'sort_order' => 6,
                ],
                [
                    'field_key' => 'vitals.weight',
                    'element_type' => 'FIELD',
                    'x_mm' => round(1.01 * 25.4, 2), // 25.65 mm
                    'y_mm' => round(1.96 * 25.4, 2), // 49.78 mm
                    'width_mm' => 30.00,
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 10.0,
                    'font_weight' => 'normal',
                    'sort_order' => 7,
                ],
                [
                    'field_key' => 'vitals.bp',
                    'element_type' => 'FIELD',
                    'x_mm' => round(1.01 * 25.4, 2), // 25.65 mm
                    'y_mm' => round(2.29 * 25.4, 2), // 58.17 mm
                    'width_mm' => 35.00,
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 10.0,
                    'font_weight' => 'normal',
                    'sort_order' => 8,
                ],
                [
                    'field_key' => 'vitals.pulse',
                    'element_type' => 'FIELD',
                    'x_mm' => round(1.01 * 25.4, 2), // 25.65 mm
                    'y_mm' => round(2.62 * 25.4, 2), // 66.55 mm
                    'width_mm' => 25.00,
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 10.0,
                    'font_weight' => 'normal',
                    'sort_order' => 9,
                ],
                [
                    'field_key' => 'vitals.spo2',
                    'element_type' => 'FIELD',
                    'x_mm' => round(1.01 * 25.4, 2), // 25.65 mm
                    'y_mm' => round(2.94 * 25.4, 2), // 74.68 mm
                    'width_mm' => 25.00,
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 10.0,
                    'font_weight' => 'normal',
                    'sort_order' => 10,
                ],
                [
                    'field_key' => 'vitals.temperature',
                    'element_type' => 'FIELD',
                    'x_mm' => round(1.01 * 25.4, 2), // 25.65 mm
                    'y_mm' => round(3.26 * 25.4, 2), // 82.80 mm
                    'width_mm' => 25.00,
                    'height_mm' => 6.00,
                    'font_family' => 'Helvetica',
                    'font_size' => 10.0,
                    'font_weight' => 'normal',
                    'sort_order' => 11,
                ],
            ];

            foreach ($legacyElements as $elem) {
                PrescriptionPadElement::create(array_merge($elem, ['template_id' => $template->id]));
            }

            return $template;
        });
    }

    /**
     * Duplicate a template as a new DRAFT version.
     * The original remains untouched.
     */
    public function duplicateAsDraft(PrescriptionPadTemplate $source, int $userId): PrescriptionPadTemplate
    {
        return DB::transaction(function () use ($source, $userId) {
            $latestVersion = PrescriptionPadTemplate::where('clinic_id', $source->clinic_id)
                ->where('doctor_id', $source->doctor_id)
                ->max('version') ?? 0;

            $newVersion = $latestVersion + 1;

            $copy = PrescriptionPadTemplate::create([
                'clinic_id' => $source->clinic_id,
                'doctor_id' => $source->doctor_id,
                'name' => $source->name . " (v{$newVersion})",
                'description' => $source->description,
                'paper_width_mm' => $source->paper_width_mm,
                'paper_height_mm' => $source->paper_height_mm,
                'orientation' => $source->orientation,
                'background_path' => $source->background_path,
                'background_width_px' => $source->background_width_px,
                'background_height_px' => $source->background_height_px,
                'template_type' => $source->template_type,
                'version' => $newVersion,
                'status' => 'DRAFT',
                'is_active' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($source->elements as $element) {
                $copy->elements()->create($element->replicate()->toArray());
            }

            return $copy;
        });
    }

    /**
     * Validate a template before activation according to strict production criteria:
     * - Paper size valid (>0)
     * - Attending doctor selected and valid
     * - Template name valid
     * - At least one element configured
     * - All coordinates valid (x >= 0, y >= 0, w > 0, h > 0)
     * - No unsafe field keys
     * - All field keys resolvable by generic renderer
     */
    public function validateTemplateForActivation(PrescriptionPadTemplate $template): void
    {
        if (empty(trim((string)$template->name))) {
            throw new InvalidArgumentException("Template name cannot be empty.");
        }

        if ((float)$template->paper_width_mm <= 0 || (float)$template->paper_height_mm <= 0) {
            throw new InvalidArgumentException("Template paper dimensions must be positive numbers (found {$template->paper_width_mm} × {$template->paper_height_mm} mm).");
        }

        if (!$template->doctor_id || !$template->doctor()->exists()) {
            throw new InvalidArgumentException("A valid attending doctor must be assigned to activate the prescription pad template.");
        }

        $elements = $template->elements()->get();
        if ($elements->isEmpty()) {
            throw new InvalidArgumentException("Template '{$template->name}' must contain at least one printable element before activation.");
        }

        foreach ($elements as $elem) {
            if ((float)$elem->x_mm < 0 || (float)$elem->y_mm < 0) {
                throw new InvalidArgumentException("Element '{$elem->field_key}' has negative coordinates (X: {$elem->x_mm} mm, Y: {$elem->y_mm} mm). Coordinates must be non-negative.");
            }

            if ((float)$elem->width_mm <= 0 || (float)$elem->height_mm <= 0) {
                throw new InvalidArgumentException("Element '{$elem->field_key}' has invalid dimensions (W: {$elem->width_mm} mm, H: {$elem->height_mm} mm). Dimensions must be positive.");
            }

            if (!PrescriptionDataMapper::isFieldKeySafeAndResolvable((string)$elem->field_key, (string)$elem->element_type)) {
                throw new InvalidArgumentException("Element '{$elem->field_key}' is not safe or cannot be resolved by the prescription pad renderer.");
            }
        }
    }

    /**
     * Activate a template.
     * Any previously active template for the same doctor is safely ARCHIVED.
     */
    public function activateTemplate(PrescriptionPadTemplate $template, int $userId, bool $validate = false): void
    {
        if ($validate) {
            $this->validateTemplateForActivation($template);
        }

        DB::transaction(function () use ($template, $userId) {
            // Archive currently active template for this doctor
            PrescriptionPadTemplate::where('clinic_id', $template->clinic_id)
                ->where('doctor_id', $template->doctor_id)
                ->where('is_active', true)
                ->where('id', '!=', $template->id)
                ->update([
                    'is_active' => false,
                    'status' => 'ARCHIVED',
                    'updated_by' => $userId,
                ]);

            $template->update([
                'is_active' => true,
                'status' => 'ACTIVE',
                'activated_at' => now(),
                'updated_by' => $userId,
            ]);
        });
    }

    /**
     * Archive a template.
     */
    public function archiveTemplate(PrescriptionPadTemplate $template, int $userId): void
    {
        $template->update([
            'is_active' => false,
            'status' => 'ARCHIVED',
            'updated_by' => $userId,
        ]);
    }

    /**
     * Delete a draft or unused template.
     * Prohibits deletion if historically referenced by an appointment.
     */
    public function deleteTemplate(PrescriptionPadTemplate $template, int $userId): void
    {
        if ($template->appointments()->exists()) {
            throw new InvalidArgumentException("Template '{$template->name}' (v{$template->version}) cannot be deleted because it is historically referenced by clinical appointments. Please archive it instead.");
        }

        DB::transaction(function () use ($template) {
            $template->elements()->delete();
            $template->delete();
        });
    }

    /**
     * Save elements for a draft template.
     * Enforces that active templates cannot be directly mutated.
     */
    public function saveElements(PrescriptionPadTemplate $template, array $elements, int $userId): void
    {
        if ($template->status !== 'DRAFT') {
            throw new InvalidArgumentException("Cannot directly edit a non-DRAFT template (Version {$template->version} is {$template->status}). Please duplicate to create a new draft.");
        }

        DB::transaction(function () use ($template, $elements, $userId) {
            // Delete existing elements and recreate with new configuration
            $template->elements()->delete();

            $sortOrder = 0;
            foreach ($elements as $elem) {
                $sortOrder++;
                $template->elements()->create([
                    'field_key' => $elem['field_key'],
                    'element_type' => $elem['element_type'] ?? 'FIELD',
                    'x_mm' => round((float)($elem['x_mm'] ?? 0), 2),
                    'y_mm' => round((float)($elem['y_mm'] ?? 0), 2),
                    'width_mm' => round((float)($elem['width_mm'] ?? 50), 2),
                    'height_mm' => round((float)($elem['height_mm'] ?? 10), 2),
                    'font_family' => $elem['font_family'] ?? 'Helvetica',
                    'font_size' => round((float)($elem['font_size'] ?? 10), 2),
                    'font_weight' => $elem['font_weight'] ?? 'normal',
                    'font_style' => $elem['font_style'] ?? 'normal',
                    'text_align' => $elem['text_align'] ?? 'left',
                    'line_height' => round((float)($elem['line_height'] ?? 1.2), 2),
                    'color' => $elem['color'] ?? '#000000',
                    'rotation' => (int)($elem['rotation'] ?? 0),
                    'sort_order' => $sortOrder,
                    'is_locked' => (bool)($elem['is_locked'] ?? false),
                    'is_visible' => (bool)($elem['is_visible'] ?? true),
                    'config_json' => $elem['config_json'] ?? null,
                ]);
            }

            $template->update(['updated_by' => $userId]);
        });
    }
}
