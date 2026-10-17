<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PrescriptionPadTemplate;
use App\Models\PrinterProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PrescriptionPdfRenderer
{
    /**
     * Ensure TrueType Unicode fonts (Noto Sans Devanagari) are registered in DomPDF's font cache.
     */
    public static function ensureUnicodeFontRegistered(): void
    {
        $fontDir = storage_path('fonts');
        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }

        $installedFile = $fontDir . '/installed-fonts.json';
        $installed = file_exists($installedFile) ? json_decode(file_get_contents($installedFile), true) : [];

        if (!isset($installed['noto sans devanagari'])) {
            $options = new \Dompdf\Options();
            $options->set('fontDir', $fontDir);
            $options->set('fontCache', $fontDir);
            $options->set('isRemoteEnabled', true);
            $options->set('isFontSubsettingEnabled', true);
            $options->setChroot([base_path()]);

            $dompdf = new \Dompdf\Dompdf($options);
            $fontMetrics = $dompdf->getFontMetrics();

            $regularPath = resource_path('fonts/NotoSansDevanagari-Regular.ttf');
            $boldPath = resource_path('fonts/NotoSansDevanagari-Bold.ttf');

            if (file_exists($regularPath)) {
                $regUrl = 'file://' . str_replace('\\', '/', realpath($regularPath));
                $fontMetrics->registerFont([
                    'family' => 'Noto Sans Devanagari',
                    'weight' => 'normal',
                    'style' => 'normal',
                ], $regUrl);
            }

            if (file_exists($boldPath)) {
                $boldUrl = 'file://' . str_replace('\\', '/', realpath($boldPath));
                $fontMetrics->registerFont([
                    'family' => 'Noto Sans Devanagari',
                    'weight' => 'bold',
                    'style' => 'normal',
                ], $boldUrl);
            }

            $fontMetrics->saveFontFamilies();
        }
    }

    /**
     * Render PDF for an encounter or sample test data.
     *
     * @param PrescriptionPadTemplate $template
     * @param array $data Mapped key-value data from PrescriptionDataMapper
     * @param string $mode 'production' or 'proof'
     * @param PrinterProfile|null $printerProfile Optional calibration profile
     * @param bool $download
     * @return Response
     */
    public function renderPdf(
        PrescriptionPadTemplate $template,
        array $data,
        string $mode = 'production',
        ?PrinterProfile $printerProfile = null,
        bool $download = false,
        string $filename = 'prescription-pad.pdf'
    ): Response {
        static::ensureUnicodeFontRegistered();

        $viewData = $this->prepareViewData($template, $data, $mode, $printerProfile);

        // Convert mm to points for Dompdf paper size: 1 mm = 72 / 25.4 pt = 2.83464567 pt
        $mmToPt = 72 / 25.4;
        $widthPt = round($template->paper_width_mm * $mmToPt, 2);
        $heightPt = round($template->paper_height_mm * $mmToPt, 2);

        $pdf = Pdf::loadView('print.prescription-pad-template', $viewData)
            ->setPaper([0, 0, $widthPt, $heightPt], $template->orientation ?? 'portrait');

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Generate raw binary PDF string (useful for testing and file persistence).
     */
    public function outputPdf(
        PrescriptionPadTemplate $template,
        array $data,
        string $mode = 'production',
        ?PrinterProfile $printerProfile = null
    ): string {
        static::ensureUnicodeFontRegistered();

        $viewData = $this->prepareViewData($template, $data, $mode, $printerProfile);

        $mmToPt = 72 / 25.4;
        $widthPt = round($template->paper_width_mm * $mmToPt, 2);
        $heightPt = round($template->paper_height_mm * $mmToPt, 2);

        $pdf = Pdf::loadView('print.prescription-pad-template', $viewData)
            ->setPaper([0, 0, $widthPt, $heightPt], $template->orientation ?? 'portrait');

        return $pdf->output();
    }

    /**
     * Prepare data array for the blade view.
     */
    public function prepareViewData(
        PrescriptionPadTemplate $template,
        array $data,
        string $mode = 'production',
        ?PrinterProfile $printerProfile = null
    ): array {
        $offsetX = $printerProfile ? (float)$printerProfile->offset_x_mm : 0.0;
        $offsetY = $printerProfile ? (float)$printerProfile->offset_y_mm : 0.0;
        $scaleX = $printerProfile ? (float)$printerProfile->scale_x : 1.0;
        $scaleY = $printerProfile ? (float)$printerProfile->scale_y : 1.0;

        $backgroundDataUri = null;
        if ($mode === 'proof' && !empty($template->background_path)) {
            $fullPath = public_path($template->background_path);
            if (file_exists($fullPath)) {
                $type = pathinfo($fullPath, PATHINFO_EXTENSION);
                $imgData = file_get_contents($fullPath);
                $backgroundDataUri = 'data:image/' . $type . ';base64,' . base64_encode($imgData);
            }
        }

        $renderedElements = [];
        $elements = $template->elements()->where('is_visible', true)->orderBy('sort_order')->get();

        foreach ($elements as $elem) {
            $finalX = round(($elem->x_mm + $offsetX) * $scaleX, 2);
            $finalY = round(($elem->y_mm + $offsetY) * $scaleY, 2);
            $finalW = round($elem->width_mm * $scaleX, 2);
            $finalH = round($elem->height_mm * $scaleY, 2);

            // Determine displayed value
            $value = '';
            if ($elem->element_type === 'STATIC_TEXT') {
                $value = $elem->config_json['static_text'] ?? $elem->field_key;
            } elseif ($elem->element_type === 'LINE' || $elem->element_type === 'RECTANGLE' || $elem->element_type === 'IMAGE') {
                $value = '';
            } else {
                $value = $data[$elem->field_key] ?? '';
            }

            $cfg = $elem->config_json ?? [];

            // Requirement A: Apply field label prefix when show_label is enabled
            if ($elem->element_type === 'FIELD' || !in_array($elem->element_type, ['STATIC_TEXT', 'LINE', 'RECTANGLE', 'IMAGE'], true)) {
                $showLabel = (bool)($cfg['show_label'] ?? false);
                if ($showLabel && $value !== '' && $value !== null) {
                    $labelText = !empty($cfg['label_text'])
                        ? $cfg['label_text']
                        : PrescriptionDataMapper::getDefaultLabelForField($elem->field_key);
                    $value = $labelText . ': ' . $value;
                }
            }

            $imageDataUri = null;
            if ($elem->element_type === 'IMAGE' && !empty($cfg['image_path'])) {
                $imgDiskPath = public_path($cfg['image_path']);
                if (!file_exists($imgDiskPath)) {
                    $rawPath = str_replace('storage/', '', $cfg['image_path']);
                    $imgDiskPath = storage_path('app/public/' . $rawPath);
                }
                if (file_exists($imgDiskPath)) {
                    $type = strtolower(pathinfo($imgDiskPath, PATHINFO_EXTENSION));
                    if ($type === 'jpg') $type = 'jpeg';
                    $imgBinary = @file_get_contents($imgDiskPath);
                    if ($imgBinary) {
                        $imageDataUri = 'data:image/' . $type . ';base64,' . base64_encode($imgBinary);
                    }
                }
            }

            $renderedElements[] = [
                'field_key' => $elem->field_key,
                'element_type' => $elem->element_type,
                'final_x_mm' => $finalX,
                'final_y_mm' => $finalY,
                'final_w_mm' => $finalW,
                'final_h_mm' => $finalH,
                'font_family' => $elem->font_family,
                'font_size' => (float)$elem->font_size,
                'font_weight' => $elem->font_weight,
                'font_style' => $elem->font_style,
                'text_align' => $elem->text_align,
                'line_height' => (float)$elem->line_height,
                'color' => $elem->color,
                'rotation' => $elem->rotation,
                'is_visible' => $elem->is_visible,
                'rendered_value' => $value,
                'border_style' => $cfg['border_style'] ?? 'solid',
                'border_width_mm' => (float)($cfg['border_width_mm'] ?? 0.3),
                'fill_color' => $cfg['fill_color'] ?? 'transparent',
                'border_radius_mm' => (float)($cfg['border_radius_mm'] ?? 0.0),
                'line_style' => $cfg['line_style'] ?? 'solid',
                'line_width_mm' => (float)($cfg['line_width_mm'] ?? 0.5),
                'image_data_uri' => $imageDataUri,
                'config_json' => $cfg,
            ];
        }

        return [
            'template' => $template,
            'paperWidthMm' => $template->paper_width_mm,
            'paperHeightMm' => $template->paper_height_mm,
            'mode' => $mode,
            'backgroundDataUri' => $backgroundDataUri,
            'renderedElements' => $renderedElements,
        ];
    }
}
