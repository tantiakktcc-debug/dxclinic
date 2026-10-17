<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrescriptionPadTemplate;
use App\Models\PrescriptionPadElement;
use App\Models\PrinterProfile;
use App\Services\PrescriptionDataMapper;
use App\Services\PrescriptionPdfRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$action = $argv[1] ?? 'status';
$templateId = (int)($argv[2] ?? 44);
$marker = $argv[3] ?? null;

header('Content-Type: application/json');

switch ($action) {
    case 'snapshot':
        $template = PrescriptionPadTemplate::with(['doctor', 'clinic', 'elements' => fn($q) => $q->orderBy('sort_order')])->find($templateId);
        if (!$template) {
            echo json_encode(['success' => false, 'error' => "Template {$templateId} not found."]);
            exit(1);
        }

        $elements = [];
        foreach ($template->elements as $e) {
            $elements[] = [
                'id' => $e->id,
                'field_key' => $e->field_key,
                'element_type' => $e->element_type,
                'x_mm' => (float)$e->x_mm,
                'y_mm' => (float)$e->y_mm,
                'width_mm' => (float)$e->width_mm,
                'height_mm' => (float)$e->height_mm,
                'rotation' => (int)$e->rotation,
                'color' => $e->color,
                'sort_order' => (int)$e->sort_order,
                'is_locked' => (bool)$e->is_locked,
                'is_visible' => (bool)$e->is_visible,
                'config_json' => $e->config_json,
            ];
        }

        echo json_encode([
            'success' => true,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'clinic_id' => $template->clinic_id,
            'doctor_id' => $template->doctor_id,
            'status' => $template->status,
            'version' => $template->version,
            'count' => count($elements),
            'elements' => $elements,
        ], JSON_PRETTY_PRINT);
        break;

    case 'cleanup':
        $template = PrescriptionPadTemplate::find($templateId);
        if (!$template) {
            echo json_encode(['success' => false, 'error' => "Template {$templateId} not found."]);
            exit(1);
        }

        $query = PrescriptionPadElement::where('template_id', $templateId);

        if ($marker) {
            $query->where(function ($q) use ($marker) {
                $q->where('field_key', 'LIKE', "%{$marker}%")
                  ->orWhere('config_json', 'LIKE', "%{$marker}%");
            });
        } else {
            // General cleanup for any rev38_e2e_test_* artifacts
            $query->where(function ($q) {
                $q->where('field_key', 'LIKE', 'rev38_e2e_%')
                  ->orWhere('field_key', 'LIKE', 'e2e_rev38_%')
                  ->orWhere('config_json', 'LIKE', '%rev38_e2e_%')
                  ->orWhere('config_json', 'LIKE', '%e2e_rev38_%');
            });
        }

        $toRemove = $query->get();
        $removedCount = $toRemove->count();
        $removedKeys = [];

        DB::transaction(function () use ($toRemove, &$removedKeys, $templateId) {
            foreach ($toRemove as $el) {
                $removedKeys[] = $el->field_key;
                
                // If it was a test image, clean up physical file from storage
                $cfg = $el->config_json ?? [];
                if (!empty($cfg['image_path'])) {
                    $cleanPath = str_replace('storage/', '', $cfg['image_path']);
                    if (Storage::disk('public')->exists($cleanPath)) {
                        Storage::disk('public')->delete($cleanPath);
                    }
                }

                $el->delete();
            }

            // Resequence sort_order of remaining elements
            $remaining = PrescriptionPadElement::where('template_id', $templateId)
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $order = 1;
            foreach ($remaining as $rem) {
                if ($rem->sort_order !== $order) {
                    $rem->update(['sort_order' => $order]);
                }
                $order++;
            }
        });

        $finalCount = PrescriptionPadElement::where('template_id', $templateId)->count();

        echo json_encode([
            'success' => true,
            'template_id' => $templateId,
            'marker' => $marker,
            'removed_count' => $removedCount,
            'removed_keys' => $removedKeys,
            'remaining_count' => $finalCount,
        ], JSON_PRETTY_PRINT);
        break;

    case 'verify':
        $snapshotFile = $argv[3] ?? null;
        if (!$snapshotFile || !file_exists($snapshotFile)) {
            echo json_encode(['success' => false, 'error' => "Snapshot file not provided or not found."]);
            exit(1);
        }

        $snapshot = json_decode(file_get_contents($snapshotFile), true);
        if (!$snapshot || !isset($snapshot['elements'])) {
            echo json_encode(['success' => false, 'error' => "Invalid snapshot content."]);
            exit(1);
        }

        $currentElements = PrescriptionPadElement::where('template_id', $templateId)
            ->orderBy('sort_order', 'asc')
            ->get();

        $baselineElements = $snapshot['elements'];
        $countBefore = count($baselineElements);
        $countAfter = $currentElements->count();

        $mismatches = [];
        if ($countBefore !== $countAfter) {
            $mismatches[] = "Element count mismatch: baseline has {$countBefore}, current has {$countAfter}.";
        }

        // Check each baseline element
        foreach ($baselineElements as $idx => $base) {
            $cur = $currentElements->get($idx);
            if (!$cur) {
                $mismatches[] = "Missing element at index {$idx} (Key: {$base['field_key']}).";
                continue;
            }

            if ($cur->field_key !== $base['field_key']) {
                $mismatches[] = "Index {$idx}: Key mismatch '{$cur->field_key}' vs baseline '{$base['field_key']}'.";
            }
            if ($cur->element_type !== $base['element_type']) {
                $mismatches[] = "Index {$idx}: Type mismatch '{$cur->element_type}' vs baseline '{$base['element_type']}'.";
            }
            if (abs((float)$cur->x_mm - (float)$base['x_mm']) > 0.01) {
                $mismatches[] = "Index {$idx}: x_mm mismatch '{$cur->x_mm}' vs baseline '{$base['x_mm']}'.";
            }
            if (abs((float)$cur->y_mm - (float)$base['y_mm']) > 0.01) {
                $mismatches[] = "Index {$idx}: y_mm mismatch '{$cur->y_mm}' vs baseline '{$base['y_mm']}'.";
            }
            if (abs((float)$cur->width_mm - (float)$base['width_mm']) > 0.01) {
                $mismatches[] = "Index {$idx}: width_mm mismatch '{$cur->width_mm}' vs baseline '{$base['width_mm']}'.";
            }
            if (abs((float)$cur->height_mm - (float)$base['height_mm']) > 0.01) {
                $mismatches[] = "Index {$idx}: height_mm mismatch '{$cur->height_mm}' vs baseline '{$base['height_mm']}'.";
            }
            if ((int)$cur->rotation !== (int)$base['rotation']) {
                $mismatches[] = "Index {$idx}: rotation mismatch '{$cur->rotation}' vs baseline '{$base['rotation']}'.";
            }
            if ((int)$cur->sort_order !== (int)$base['sort_order']) {
                $mismatches[] = "Index {$idx}: sort_order mismatch '{$cur->sort_order}' vs baseline '{$base['sort_order']}'.";
            }
        }

        echo json_encode([
            'success' => true,
            'intact' => count($mismatches) === 0,
            'count_before' => $countBefore,
            'count_after' => $countAfter,
            'difference' => $countAfter - $countBefore,
            'mismatches' => $mismatches,
        ], JSON_PRETTY_PRINT);
        break;

    case 'pdf':
        $mode = $argv[3] ?? 'production';
        $outputPath = $argv[4] ?? sys_get_temp_dir() . "/template_{$templateId}_{$mode}.pdf";

        $template = PrescriptionPadTemplate::with(['doctor', 'clinic', 'elements' => fn($q) => $q->orderBy('sort_order')])->find($templateId);
        if (!$template) {
            echo json_encode(['success' => false, 'error' => "Template {$templateId} not found."]);
            exit(1);
        }

        $sampleData = PrescriptionDataMapper::getSampleData($template->doctor, $template->clinic);
        $printer = PrinterProfile::where('clinic_id', $template->clinic_id)->where('is_default', true)->first();
        $renderer = app(PrescriptionPdfRenderer::class);

        $pdfBytes = $renderer->outputPdf($template, $sampleData, $mode, $printer);
        file_put_contents($outputPath, $pdfBytes);

        $size = filesize($outputPath);
        $header = substr($pdfBytes, 0, 5);

        echo json_encode([
            'success' => true,
            'mode' => $mode,
            'output_path' => $outputPath,
            'size' => $size,
            'valid_pdf' => ($header === '%PDF-'),
        ], JSON_PRETTY_PRINT);
        break;

    default:
        $count = PrescriptionPadElement::where('template_id', $templateId)->count();
        echo json_encode([
            'success' => true,
            'template_id' => $templateId,
            'elements_count' => $count,
        ], JSON_PRETTY_PRINT);
        break;
}
