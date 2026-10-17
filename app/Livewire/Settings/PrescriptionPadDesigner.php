<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\PrescriptionPadTemplate;
use App\Models\PrinterProfile;
use App\Services\PrescriptionDataMapper;
use App\Services\PrescriptionPadTemplateService;
use App\Services\PrescriptionPdfRenderer;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class PrescriptionPadDesigner extends Component
{
    use WithFileUploads;

    public int $templateId;
    public PrescriptionPadTemplate $template;
    public array $elements = [];
    public ?int $selectedIndex = null;
    public array $selectedIndices = [];
    public bool $isDirty = false;
    public array $clipboard = [];
    public array $undoStack = [];
    public array $redoStack = [];

    // Viewport & Tool controls
    public float $zoom = 1.0;
    public float $panX = 0.0;
    public float $panY = 0.0;
    public string $activeTool = 'select'; // 'select' or 'hand'
    public bool $showGrid = true;
    public bool $snapToGrid = false;
    public float $gridSizeMm = 5.0;
    public string $previewMode = 'proof'; // 'proof' or 'production'

    // Background scan & Image uploads
    public $scanFile = null;
    public $imageUploadFile = null;
    public ?float $pendingImageX = null;
    public ?float $pendingImageY = null;
    public ?string $flashMessage = null;

    // Catalogs
    public array $fieldCatalog = [];
    public array $sampleData = [];

    // Printer Calibration
    public float $printerOffsetX = 0.0;
    public float $printerOffsetY = 0.0;
    public float $printerScaleX = 1.0;
    public float $printerScaleY = 1.0;

    public string $activeLeftTab = 'toolbox'; // 'toolbox' or 'layers'
    public bool $isFullScreen = false;

    protected $listeners = [
        'elementMoved' => 'handleElementMoved',
        'elementsMoved' => 'handleElementsMoved',
        'elementResized' => 'handleElementResized',
        'elementSelected' => 'selectElement',
        'elementsSelected' => 'selectElements',
        'setLeftTab' => 'setLeftTab',
        'setFullScreen' => 'setFullScreen',
        'setGridSize' => 'setGridSize',
        'updateElementProperty' => 'updateElementProperty',
        'updateElementConfig' => 'updateElementConfig',
        'updateMultiProperty' => 'updateMultiProperty',
        'groupSelected' => 'groupSelected',
        'ungroupSelected' => 'ungroupSelected',
        'duplicateElement' => 'duplicateElement',
        'deleteElement' => 'deleteElement',
        'alignElements' => 'alignElements',
        'distributeElements' => 'distributeElements',
        'setElementRotation' => 'setElementRotation',
        'undo' => 'undo',
        'redo' => 'redo',
        'copySelected' => 'copySelected',
        'paste' => 'paste',
        'lockSelected' => 'lockSelected',
        'deleteSelected' => 'deleteSelected',
        'duplicateSelected' => 'duplicateSelected',
    ];

    public function mount(int $templateId): void
    {
        abort_unless(auth()->user()?->role === 'ADMIN', 403, 'Administrator access required to design prescription pad templates.');
        $this->templateId = $templateId;
        $this->loadTemplate();
    }

    public function loadTemplate(): void
    {
        $this->template = PrescriptionPadTemplate::with(['doctor', 'clinic', 'elements' => function ($q) {
            $q->orderBy('sort_order');
        }])->findOrFail($this->templateId);

        $this->fieldCatalog = PrescriptionDataMapper::getFieldCatalog($this->template->clinic);
        $this->sampleData = PrescriptionDataMapper::getSampleData($this->template->doctor, $this->template->clinic);

        $printer = PrinterProfile::where('clinic_id', $this->template->clinic_id)->where('is_default', true)->first();
        if ($printer) {
            $this->printerOffsetX = (float)$printer->offset_x_mm;
            $this->printerOffsetY = (float)$printer->offset_y_mm;
            $this->printerScaleX = (float)$printer->scale_x;
            $this->printerScaleY = (float)$printer->scale_y;
        }

        $this->elements = [];
        foreach ($this->template->elements as $idx => $elem) {
            $cfg = $elem->config_json ?? [];
            if ($elem->element_type === 'STATIC_TEXT') {
                $cfg['static_text'] = $cfg['static_text'] ?? 'Rx / Notes';
            } elseif ($elem->element_type === 'RECTANGLE') {
                $cfg['border_style'] = $cfg['border_style'] ?? 'solid';
                $cfg['border_width_mm'] = isset($cfg['border_width_mm']) ? (float)$cfg['border_width_mm'] : 0.3;
                $cfg['fill_color'] = $cfg['fill_color'] ?? 'transparent';
                $cfg['border_radius_mm'] = isset($cfg['border_radius_mm']) ? (float)$cfg['border_radius_mm'] : 0.0;
            } elseif ($elem->element_type === 'LINE') {
                $cfg['line_style'] = $cfg['line_style'] ?? 'solid';
                $cfg['line_width_mm'] = isset($cfg['line_width_mm']) ? (float)$cfg['line_width_mm'] : 0.5;
            } elseif ($elem->element_type === 'IMAGE') {
                $cfg['image_path'] = $cfg['image_path'] ?? '';
                $cfg['lock_aspect_ratio'] = isset($cfg['lock_aspect_ratio']) ? (bool)$cfg['lock_aspect_ratio'] : true;
                $cfg['aspect_ratio'] = isset($cfg['aspect_ratio']) ? (float)$cfg['aspect_ratio'] : 1.0;
                $cfg['opacity'] = isset($cfg['opacity']) ? (float)$cfg['opacity'] : 1.0;
            }

            if ($elem->element_type === 'FIELD' || !in_array($elem->element_type, ['STATIC_TEXT', 'LINE', 'RECTANGLE', 'IMAGE'], true)) {
                $cfg['show_label'] = isset($cfg['show_label']) ? (bool)$cfg['show_label'] : false;
                $cfg['label_text'] = !empty($cfg['label_text']) ? (string)$cfg['label_text'] : PrescriptionDataMapper::getDefaultLabelForField($elem->field_key);
            }

            $this->elements[] = [
                'id' => $elem->id,
                'uid' => 'elem_' . ($elem->id ?? $idx) . '_' . substr(md5((string)$elem->field_key), 0, 4),
                'field_key' => $elem->field_key,
                'element_type' => $elem->element_type,
                'x_mm' => (float)$elem->x_mm,
                'y_mm' => (float)$elem->y_mm,
                'width_mm' => (float)$elem->width_mm,
                'height_mm' => (float)$elem->height_mm,
                'font_family' => $elem->font_family ?? 'Helvetica',
                'font_size' => (float)$elem->font_size,
                'font_weight' => $elem->font_weight ?? 'normal',
                'font_style' => $elem->font_style ?? 'normal',
                'text_align' => $elem->text_align ?? 'left',
                'line_height' => (float)($elem->line_height ?: 1.2),
                'color' => $elem->color ?? '#000000',
                'rotation' => (int)$elem->rotation,
                'sort_order' => (int)$elem->sort_order,
                'is_locked' => (bool)$elem->is_locked,
                'is_visible' => (bool)$elem->is_visible,
                'config_json' => $cfg,
            ];
        }

        if (count($this->elements) > 0 && is_null($this->selectedIndex)) {
            $this->selectedIndex = 0;
            $this->selectedIndices = [0];
        } else {
            $this->selectedIndices = !is_null($this->selectedIndex) ? [$this->selectedIndex] : [];
        }
        $this->isDirty = false;
    }

    public function selectElement(int $index, bool $multi = false): void
    {
        if (!isset($this->elements[$index])) return;

        if ($multi) {
            $pos = array_search($index, $this->selectedIndices, true);
            if ($pos !== false) {
                unset($this->selectedIndices[$pos]);
                $this->selectedIndices = array_values($this->selectedIndices);
            } else {
                $this->selectedIndices[] = $index;
            }
            $this->selectedIndex = !empty($this->selectedIndices) ? end($this->selectedIndices) : null;
        } else {
            $this->selectedIndex = $index;
            $this->selectedIndices = [$index];
        }

        if ($this->selectedIndex !== null) {
            $this->dispatch('element-selected', index: $this->selectedIndex);
        }
    }

    public function selectElements(array $indices): void
    {
        $valid = [];
        foreach ($indices as $i) {
            if (isset($this->elements[$i])) {
                $valid[] = (int)$i;
            }
        }
        $this->selectedIndices = array_values(array_unique($valid));
        $this->selectedIndex = !empty($this->selectedIndices) ? end($this->selectedIndices) : null;
    }

    public function deselectElement(): void
    {
        $this->selectedIndex = null;
        $this->selectedIndices = [];
    }

    public function setLeftTab(string $tab): void
    {
        $this->activeLeftTab = in_array($tab, ['toolbox', 'layers'], true) ? $tab : 'toolbox';
    }

    public function selectAll(): void
    {
        $this->selectedIndices = array_keys($this->elements);
        $this->selectedIndex = count($this->elements) > 0 ? 0 : null;
    }

    public function pushHistorySnapshot(): void
    {
        if (count($this->undoStack) >= 50) {
            array_shift($this->undoStack);
        }
        $this->undoStack[] = [
            'elements' => $this->elements,
            'selectedIndex' => $this->selectedIndex,
            'selectedIndices' => $this->selectedIndices,
        ];
        $this->redoStack = [];
        $this->isDirty = true;
    }

    public function undo(): void
    {
        if (empty($this->undoStack)) return;

        $this->redoStack[] = [
            'elements' => $this->elements,
            'selectedIndex' => $this->selectedIndex,
            'selectedIndices' => $this->selectedIndices,
        ];

        $prev = array_pop($this->undoStack);
        $this->elements = $prev['elements'];
        $this->selectedIndex = $prev['selectedIndex'];
        $this->selectedIndices = $prev['selectedIndices'];
        $this->isDirty = true;
    }

    public function redo(): void
    {
        if (empty($this->redoStack)) return;

        $this->undoStack[] = [
            'elements' => $this->elements,
            'selectedIndex' => $this->selectedIndex,
            'selectedIndices' => $this->selectedIndices,
        ];

        $next = array_pop($this->redoStack);
        $this->elements = $next['elements'];
        $this->selectedIndex = $next['selectedIndex'];
        $this->selectedIndices = $next['selectedIndices'];
        $this->isDirty = true;
    }

    /**
     * Test whether two rectangles with a clearance margin intersect.
     */
    public function rectanglesOverlap(
        float $x1, float $y1, float $w1, float $h1,
        float $x2, float $y2, float $w2, float $h2,
        float $clearance = 2.0
    ): bool {
        $r2Left = $x2 - $clearance;
        $r2Top = $y2 - $clearance;
        $r2Right = $x2 + $w2 + $clearance;
        $r2Bottom = $y2 + $h2 + $clearance;

        $r1Right = $x1 + $w1;
        $r1Bottom = $y1 + $h1;

        if ($r1Right <= $r2Left || $x1 >= $r2Right) return false;
        if ($r1Bottom <= $r2Top || $y1 >= $r2Bottom) return false;

        return true;
    }

    /**
     * Get conservative axis-aligned bounding box (AABB) for an existing element,
     * accounting for rotation.
     */
    public function getElementAABB(array $elem): array
    {
        $x = (float)($elem['x_mm'] ?? 0);
        $y = (float)($elem['y_mm'] ?? 0);
        $w = (float)($elem['width_mm'] ?? 0);
        $h = (float)($elem['height_mm'] ?? 0);
        $rot = (float)($elem['rotation'] ?? 0);

        if ($rot == 0 || $rot == 360) {
            return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
        }

        $rad = deg2rad($rot);
        $cos = cos($rad);
        $sin = sin($rad);
        $xs = [0, $w * $cos, $w * $cos - $h * $sin, -$h * $sin];
        $ys = [0, $w * $sin, $w * $sin + $h * $cos, $h * $cos];

        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);

        return [
            'x' => $x + $minX,
            'y' => $y + $minY,
            'w' => $maxX - $minX,
            'h' => $maxY - $minY,
        ];
    }

    /**
     * Find nearest collision-free position starting from desired center.
     */
    public function findNearestAvailablePosition(
        float $desiredX,
        float $desiredY,
        float $widthMm,
        float $heightMm,
        float $clearance = 2.0
    ): array {
        $paperW = (float)$this->template->paper_width_mm;
        $paperH = (float)$this->template->paper_height_mm;

        $margin = 2.0;
        $minX = $margin;
        $minY = $margin;
        $maxX = max($minX, $paperW - $widthMm - $margin);
        $maxY = max($minY, $paperH - $heightMm - $margin);

        $startX = max($minX, min($maxX, $desiredX));
        $startY = max($minY, min($maxY, $desiredY));

        $existingBoxes = [];
        foreach ($this->elements as $elem) {
            $existingBoxes[] = $this->getElementAABB($elem);
        }

        $hasCollision = function(float $cx, float $cy) use ($existingBoxes, $widthMm, $heightMm, $clearance): bool {
            foreach ($existingBoxes as $box) {
                if ($this->rectanglesOverlap($cx, $cy, $widthMm, $heightMm, $box['x'], $box['y'], $box['w'], $box['h'], $clearance)) {
                    return true;
                }
            }
            return false;
        };

        if (!$hasCollision($startX, $startY)) {
            return ['x' => round($startX, 2), 'y' => round($startY, 2)];
        }

        $step = max(2.0, (float)$this->gridSizeMm);
        $directions = [
            [1, 0],   // right
            [-1, 0],  // left
            [0, 1],   // down
            [0, -1],  // up
            [1, 1],   // right/down
            [-1, 1],  // left/down
            [1, -1],  // right/up
            [-1, -1], // left/up
        ];

        $maxRadius = max($paperW, $paperH);
        $bestCandidate = null;
        $bestDist = PHP_FLOAT_MAX;

        for ($radius = $step; $radius <= $maxRadius; $radius += $step) {
            foreach ($directions as [$dx, $dy]) {
                $candX = $startX + ($dx * $radius);
                $candY = $startY + ($dy * $radius);

                if ($candX < $minX || $candX > $maxX || $candY < $minY || $candY > $maxY) {
                    continue;
                }

                if (!$hasCollision($candX, $candY)) {
                    $dist = hypot($candX - $startX, $candY - $startY);
                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $bestCandidate = ['x' => round($candX, 2), 'y' => round($candY, 2)];
                    }
                }
            }

            if ($bestCandidate !== null) {
                return $bestCandidate;
            }
        }

        // If no completely free position exists, find candidate with minimum overlap area
        $minOverlapArea = PHP_FLOAT_MAX;
        $leastOverlapCandidate = ['x' => round($startX, 2), 'y' => round($startY, 2)];

        for ($radius = 0; $radius <= $maxRadius; $radius += ($step * 2)) {
            foreach ($directions as [$dx, $dy]) {
                $candX = max($minX, min($maxX, $startX + ($dx * $radius)));
                $candY = max($minY, min($maxY, $startY + ($dy * $radius)));

                $overlapArea = 0.0;
                foreach ($existingBoxes as $box) {
                    $xOverlap = max(0, min($candX + $widthMm, $box['x'] + $box['w']) - max($candX, $box['x']));
                    $yOverlap = max(0, min($candY + $heightMm, $box['y'] + $box['h']) - max($candY, $box['y']));
                    $overlapArea += ($xOverlap * $yOverlap);
                }

                if ($overlapArea < $minOverlapArea) {
                    $minOverlapArea = $overlapArea;
                    $leastOverlapCandidate = ['x' => round($candX, 2), 'y' => round($candY, 2)];
                }
            }
        }

        session()->flash('warning', 'No free position available on the current page.');
        return $leastOverlapCandidate;
    }

    public function addField(string $fieldKey, ?float $xMm = null, ?float $yMm = null): void
    {
        if ($this->template->status !== 'DRAFT') {
            session()->flash('error', "Template is {$this->template->status}. Please duplicate to create a new draft to add fields.");
            return;
        }

        // Find metadata in catalog
        $meta = null;
        foreach ($this->fieldCatalog as $group => $fields) {
            if (isset($fields[$fieldKey])) {
                $meta = $fields[$fieldKey];
                break;
            }
        }

        $defaultW = (float)($meta['default_w'] ?? 40.0);
        $defaultH = (float)($meta['default_h'] ?? 8.0);
        $fontWeight = $meta['font_weight'] ?? 'normal';
        $fontSize = (float)($meta['font_size'] ?? 10.0);

        $desiredX = $xMm ?? (($this->template->paper_width_mm - $defaultW) / 2);
        $desiredY = $yMm ?? (($this->template->paper_height_mm - $defaultH) / 2);
        $pos = $this->findNearestAvailablePosition($desiredX, $desiredY, $defaultW, $defaultH, 2.0);
        $nextX = $pos['x'];
        $nextY = $pos['y'];

        $uid = 'elem_fld_' . time() . '_' . rand(100, 999);

        $this->pushHistorySnapshot();

        $this->elements[] = [
            'id' => null,
            'uid' => $uid,
            'field_key' => $fieldKey,
            'element_type' => 'FIELD',
            'x_mm' => round($nextX, 2),
            'y_mm' => round($nextY, 2),
            'width_mm' => $defaultW,
            'height_mm' => $defaultH,
            'font_family' => 'Helvetica',
            'font_size' => $fontSize,
            'font_weight' => $fontWeight,
            'font_style' => 'normal',
            'text_align' => 'left',
            'line_height' => 1.2,
            'color' => '#000000',
            'rotation' => 0,
            'sort_order' => count($this->elements) + 1,
            'is_locked' => false,
            'is_visible' => true,
            'config_json' => [
                'show_label' => false,
                'label_text' => PrescriptionDataMapper::getDefaultLabelForField($fieldKey),
            ],
        ];

        $newIdx = count($this->elements) - 1;
        $this->selectedIndex = $newIdx;
        $this->selectedIndices = [$newIdx];
        $this->dispatch('element-selected', index: $newIdx);
    }

    public function addStaticText(?float $xMm = null, ?float $yMm = null): void
    {
        if ($this->template->status !== 'DRAFT') {
            session()->flash('error', "Template is {$this->template->status}. Duplicate to create a draft.");
            return;
        }

        $this->pushHistorySnapshot();

        $existingStaticCount = 0;
        foreach ($this->elements as $e) {
            if (($e['element_type'] ?? '') === 'STATIC_TEXT') {
                $existingStaticCount++;
            }
        }

        $newNum = $existingStaticCount + 1;
        $uniqueSuffix = substr(md5(uniqid((string)mt_rand(), true)), 0, 4);
        $fieldKey = "static_text_{$newNum}_{$uniqueSuffix}";

        $widthMm = 50.0;
        $heightMm = 8.0;

        $desiredX = $xMm ?? (($this->template->paper_width_mm - $widthMm) / 2);
        $desiredY = $yMm ?? (($this->template->paper_height_mm - $heightMm) / 2);
        $pos = $this->findNearestAvailablePosition($desiredX, $desiredY, $widthMm, $heightMm, 2.0);
        $nextX = $pos['x'];
        $nextY = $pos['y'];

        $this->elements[] = [
            'id' => null,
            'uid' => 'elem_txt_' . time() . '_' . $newNum,
            'field_key' => $fieldKey,
            'element_type' => 'STATIC_TEXT',
            'x_mm' => round($nextX, 2),
            'y_mm' => round($nextY, 2),
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'font_family' => 'Helvetica',
            'font_size' => 10.0,
            'font_weight' => 'normal',
            'font_style' => 'normal',
            'text_align' => 'left',
            'line_height' => 1.2,
            'color' => '#000000',
            'rotation' => 0,
            'sort_order' => count($this->elements) + 1,
            'is_locked' => false,
            'is_visible' => true,
            'config_json' => [
                'static_text' => "Static Text #{$newNum}",
            ],
        ];

        $newIdx = count($this->elements) - 1;
        $this->selectedIndex = $newIdx;
        $this->selectedIndices = [$newIdx];
        $this->dispatch('element-selected', index: $newIdx);
    }

    public function addLine(?float $xMm = null, ?float $yMm = null): void
    {
        if ($this->template->status !== 'DRAFT') {
            session()->flash('error', "Template is {$this->template->status}. Duplicate to create a draft.");
            return;
        }

        $this->pushHistorySnapshot();

        $existingCount = 0;
        foreach ($this->elements as $e) {
            if (($e['element_type'] ?? '') === 'LINE') {
                $existingCount++;
            }
        }
        $newNum = $existingCount + 1;
        $uniqueSuffix = substr(md5(uniqid((string)mt_rand(), true)), 0, 4);
        $fieldKey = "line_{$newNum}_{$uniqueSuffix}";

        $widthMm = 170.0;
        $heightMm = 1.0;

        $desiredX = $xMm ?? (($this->template->paper_width_mm - $widthMm) / 2);
        $desiredY = $yMm ?? (($this->template->paper_height_mm - $heightMm) / 2);
        $pos = $this->findNearestAvailablePosition($desiredX, $desiredY, $widthMm, $heightMm, 2.0);
        $nextX = $pos['x'];
        $nextY = $pos['y'];

        $this->elements[] = [
            'id' => null,
            'uid' => 'elem_line_' . time() . '_' . $newNum,
            'field_key' => $fieldKey,
            'element_type' => 'LINE',
            'x_mm' => round($nextX, 2),
            'y_mm' => round($nextY, 2),
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'font_family' => 'Helvetica',
            'font_size' => 10.0,
            'font_weight' => 'normal',
            'font_style' => 'normal',
            'text_align' => 'left',
            'line_height' => 1.0,
            'color' => '#000000',
            'rotation' => 0,
            'sort_order' => count($this->elements) + 1,
            'is_locked' => false,
            'is_visible' => true,
            'config_json' => [
                'line_style' => 'solid',
                'line_width_mm' => 0.5,
            ],
        ];

        $newIdx = count($this->elements) - 1;
        $this->selectedIndex = $newIdx;
        $this->selectedIndices = [$newIdx];
        $this->dispatch('element-selected', index: $newIdx);
    }

    public function addRectangle(?float $xMm = null, ?float $yMm = null): void
    {
        if ($this->template->status !== 'DRAFT') {
            session()->flash('error', "Template is {$this->template->status}. Duplicate to create a draft.");
            return;
        }

        $this->pushHistorySnapshot();

        $existingCount = 0;
        foreach ($this->elements as $e) {
            if (($e['element_type'] ?? '') === 'RECTANGLE') {
                $existingCount++;
            }
        }
        $newNum = $existingCount + 1;
        $uniqueSuffix = substr(md5(uniqid((string)mt_rand(), true)), 0, 4);
        $fieldKey = "rectangle_{$newNum}_{$uniqueSuffix}";

        $widthMm = 50.0;
        $heightMm = 25.0;

        $desiredX = $xMm ?? (($this->template->paper_width_mm - $widthMm) / 2);
        $desiredY = $yMm ?? (($this->template->paper_height_mm - $heightMm) / 2);
        $pos = $this->findNearestAvailablePosition($desiredX, $desiredY, $widthMm, $heightMm, 2.0);
        $nextX = $pos['x'];
        $nextY = $pos['y'];

        $this->elements[] = [
            'id' => null,
            'uid' => 'elem_rect_' . time() . '_' . $newNum,
            'field_key' => $fieldKey,
            'element_type' => 'RECTANGLE',
            'x_mm' => round($nextX, 2),
            'y_mm' => round($nextY, 2),
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'font_family' => 'Helvetica',
            'font_size' => 10.0,
            'font_weight' => 'normal',
            'font_style' => 'normal',
            'text_align' => 'left',
            'line_height' => 1.0,
            'color' => '#000000',
            'rotation' => 0,
            'sort_order' => count($this->elements) + 1,
            'is_locked' => false,
            'is_visible' => true,
            'config_json' => [
                'border_style' => 'solid',
                'border_width_mm' => 0.3,
                'fill_color' => 'transparent',
                'border_radius_mm' => 0.0,
            ],
        ];

        $newIdx = count($this->elements) - 1;
        $this->selectedIndex = $newIdx;
        $this->selectedIndices = [$newIdx];
        $this->dispatch('element-selected', index: $newIdx);
    }

    public function updatedImageUploadFile(): void
    {
        $this->uploadImageElement();
    }

    public function uploadImageElement(): void
    {
        if ($this->template->status !== 'DRAFT') {
            session()->flash('error', "Template is {$this->template->status}. Duplicate to create a draft.");
            $this->reset('imageUploadFile');
            return;
        }

        if (!$this->imageUploadFile) {
            return;
        }

        $this->validate([
            'imageUploadFile' => 'required|image|mimes:png,jpg,jpeg|max:5120',
        ]);

        $realPath = $this->imageUploadFile->getRealPath();
        if (!$realPath || !file_exists($realPath) || !is_readable($realPath)) {
            session()->flash('error', 'Uploaded image file cannot be read.');
            $this->reset('imageUploadFile');
            return;
        }

        $imageInfo = @getimagesize($realPath);
        if (!$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG])) {
            session()->flash('error', 'Invalid image file. Only authentic PNG and JPEG images are supported.');
            $this->reset('imageUploadFile');
            return;
        }

        $widthPx = (int)$imageInfo[0];
        $heightPx = (int)$imageInfo[1];
        $aspectRatio = ($heightPx > 0) ? round($widthPx / $heightPx, 4) : 1.0;

        $clinicId = (int)($this->template->clinic_id ?? 1);
        $ext = strtolower($this->imageUploadFile->getClientOriginalExtension());
        if ($ext === 'jpeg') $ext = 'jpg';
        $safeName = 'img_elem_' . $this->template->id . '_' . uniqid() . '_' . time() . '.' . $ext;

        $path = $this->imageUploadFile->storeAs('prescription_images/' . $clinicId, $safeName, 'public');

        $existingImgCount = 0;
        foreach ($this->elements as $e) {
            if (($e['element_type'] ?? '') === 'IMAGE') {
                $existingImgCount++;
            }
        }

        $newNum = $existingImgCount + 1;
        $uniqueSuffix = substr(md5(uniqid((string)mt_rand(), true)), 0, 4);
        $fieldKey = "image_{$newNum}_{$uniqueSuffix}";

        // Initial sensible size in mm (e.g. 40mm width, height based on aspect ratio)
        $initialW = 40.0;
        $initialH = round($initialW / ($aspectRatio ?: 1.0), 2);
        if ($initialH > 40.0) {
            $initialH = 40.0;
            $initialW = round($initialH * $aspectRatio, 2);
        }
        if ($initialH < 5.0) $initialH = 5.0;
        if ($initialW < 10.0) $initialW = 10.0;

        $desiredX = $this->pendingImageX ?? (($this->template->paper_width_mm - $initialW) / 2);
        $desiredY = $this->pendingImageY ?? (($this->template->paper_height_mm - $initialH) / 2);
        $this->pendingImageX = null;
        $this->pendingImageY = null;

        $pos = $this->findNearestAvailablePosition($desiredX, $desiredY, $initialW, $initialH, 2.0);
        $nextX = $pos['x'];
        $nextY = $pos['y'];

        $this->pushHistorySnapshot();

        $this->elements[] = [
            'id' => null,
            'uid' => 'elem_img_' . time() . '_' . $newNum,
            'field_key' => $fieldKey,
            'element_type' => 'IMAGE',
            'x_mm' => round($nextX, 2),
            'y_mm' => round($nextY, 2),
            'width_mm' => round($initialW, 2),
            'height_mm' => round($initialH, 2),
            'font_family' => 'Helvetica',
            'font_size' => 10.0,
            'font_weight' => 'normal',
            'font_style' => 'normal',
            'text_align' => 'left',
            'line_height' => 1.0,
            'color' => '#000000',
            'rotation' => 0,
            'sort_order' => count($this->elements) + 1,
            'is_locked' => false,
            'is_visible' => true,
            'config_json' => [
                'image_path' => 'storage/' . $path,
                'original_width_px' => $widthPx,
                'original_height_px' => $heightPx,
                'aspect_ratio' => $aspectRatio,
                'lock_aspect_ratio' => true,
                'opacity' => 1.0,
            ],
        ];

        $newIdx = count($this->elements) - 1;
        $this->selectedIndex = $newIdx;
        $this->selectedIndices = [$newIdx];
        $this->dispatch('element-selected', index: $newIdx);
        $this->reset('imageUploadFile');
        session()->flash('success', 'Image element placed on prescription pad canvas.');
    }

    public function handleElementMoved(int $index, float $newXMm, float $newYMm): void
    {
        if (!isset($this->elements[$index]) || $this->elements[$index]['is_locked']) {
            return;
        }

        $this->pushHistorySnapshot();

        if ($this->snapToGrid && $this->gridSizeMm > 0) {
            $newXMm = round($newXMm / $this->gridSizeMm) * $this->gridSizeMm;
            $newYMm = round($newYMm / $this->gridSizeMm) * $this->gridSizeMm;
        }

        // Clamp to paper boundary
        $newXMm = max(0.0, min($this->template->paper_width_mm - 2, $newXMm));
        $newYMm = max(0.0, min($this->template->paper_height_mm - 2, $newYMm));

        $this->elements[$index]['x_mm'] = round($newXMm, 2);
        $this->elements[$index]['y_mm'] = round($newYMm, 2);
        $this->selectedIndex = $index;
        $this->selectedIndices = [$index];
    }

    public function handleElementsMoved(array $moved): void
    {
        if (empty($moved)) return;
        $this->pushHistorySnapshot();

        foreach ($moved as $item) {
            $index = (int)($item['index'] ?? -1);
            if (!isset($this->elements[$index]) || $this->elements[$index]['is_locked']) {
                continue;
            }

            $newX = (float)($item['x_mm'] ?? $this->elements[$index]['x_mm']);
            $newY = (float)($item['y_mm'] ?? $this->elements[$index]['y_mm']);

            if ($this->snapToGrid && $this->gridSizeMm > 0) {
                $newX = round($newX / $this->gridSizeMm) * $this->gridSizeMm;
                $newY = round($newY / $this->gridSizeMm) * $this->gridSizeMm;
            }

            $newX = max(0.0, min($this->template->paper_width_mm - 2, $newX));
            $newY = max(0.0, min($this->template->paper_height_mm - 2, $newY));

            $this->elements[$index]['x_mm'] = round($newX, 2);
            $this->elements[$index]['y_mm'] = round($newY, 2);
        }
    }

    public function handleElementResized(int $index, float $newX, float $newY, float $newW, float $newH): void
    {
        if (!isset($this->elements[$index]) || $this->elements[$index]['is_locked']) {
            return;
        }

        $this->pushHistorySnapshot();

        $this->elements[$index]['x_mm'] = max(0.0, min($this->template->paper_width_mm - 2, round($newX, 2)));
        $this->elements[$index]['y_mm'] = max(0.0, min($this->template->paper_height_mm - 2, round($newY, 2)));
        $this->elements[$index]['width_mm'] = max(2.0, min($this->template->paper_width_mm, round($newW, 2)));
        $this->elements[$index]['height_mm'] = max(0.5, min($this->template->paper_height_mm, round($newH, 2)));
        $this->selectedIndex = $index;
        $this->selectedIndices = [$index];
    }

    public function updateElementProperty(int $index, string $prop, $value): void
    {
        if (!isset($this->elements[$index]) || $this->elements[$index]['is_locked']) {
            return;
        }

        $this->pushHistorySnapshot();
        $this->selectedIndex = $index;
        $this->selectedIndices = [$index];

        if ($prop === 'static_text') {
            $this->elements[$index]['config_json']['static_text'] = (string)$value;
        } elseif (in_array($prop, ['x_mm', 'y_mm', 'width_mm', 'height_mm', 'font_size', 'line_height'])) {
            $this->elements[$index][$prop] = (float)$value;
        } elseif ($prop === 'rotation' || $prop === 'sort_order') {
            $this->elements[$index][$prop] = (int)$value;
        } elseif ($prop === 'is_locked' || $prop === 'is_visible') {
            $this->elements[$index][$prop] = (bool)$value;
        } else {
            $this->elements[$index][$prop] = $value;
        }
    }

    public function updateElementConfig(int $index, string $configKey, $value): void
    {
        if (!isset($this->elements[$index]) || $this->elements[$index]['is_locked']) {
            return;
        }

        $this->pushHistorySnapshot();
        $this->selectedIndex = $index;
        $this->selectedIndices = [$index];
        $this->elements[$index]['config_json'][$configKey] = $value;
    }

    public function setElementRotation(int $index, int $degrees): void
    {
        if (!isset($this->elements[$index]) || $this->elements[$index]['is_locked']) {
            return;
        }

        $this->pushHistorySnapshot();
        $norm = (($degrees % 360) + 360) % 360;
        $this->elements[$index]['rotation'] = $norm;
    }

    public function setPendingImagePlacement(?float $xMm, ?float $yMm): void
    {
        $this->pendingImageX = $xMm;
        $this->pendingImageY = $yMm;
    }

    public function setFullScreen(bool $isFull): void
    {
        $this->isFullScreen = (bool)$isFull;
    }

    public function setGridSize(float $size): void
    {
        if ($size >= 0.5 && $size <= 50.0) {
            $this->gridSizeMm = round($size, 1);
        }
    }

    public function updateMultiProperty(string $property, mixed $value): void
    {
        $indices = !empty($this->selectedIndices) ? $this->selectedIndices : (!is_null($this->selectedIndex) ? [$this->selectedIndex] : []);
        $unlocked = array_values(array_filter($indices, fn($i) => isset($this->elements[$i]) && !$this->elements[$i]['is_locked']));
        if (empty($unlocked)) return;

        $this->pushHistorySnapshot();

        $textProps = ['font_family', 'font_size', 'font_weight', 'font_style', 'text_align', 'color', 'line_height'];

        foreach ($unlocked as $idx) {
            $type = $this->elements[$idx]['element_type'] ?? '';
            $isTextCapable = in_array($type, ['FIELD', 'STATIC_TEXT'], true);
            $isRect = ($type === 'RECTANGLE');
            $isLine = ($type === 'LINE');

            if (in_array($property, $textProps, true)) {
                if (!$isTextCapable) continue;

                if ($property === 'font_size') {
                    // Precision 0.1 pt
                    $this->elements[$idx]['font_size'] = round((float)$value, 1);
                } else {
                    $this->elements[$idx][$property] = $value;
                }
            } elseif ($property === 'is_visible') {
                $this->elements[$idx]['is_visible'] = (bool)$value;
            } elseif ($property === 'is_locked') {
                $this->elements[$idx]['is_locked'] = (bool)$value;
            } elseif ($property === 'fill_color' && $isRect) {
                $this->elements[$idx]['config_json']['fill_color'] = $value;
            } elseif ($property === 'border_color' && ($isRect || $isLine)) {
                $this->elements[$idx]['color'] = $value;
            } elseif ($property === 'border_width_mm' && ($isRect || $isLine)) {
                $this->elements[$idx]['config_json']['border_width_mm'] = max(0.1, round((float)$value, 2));
            } elseif ($property === 'border_style' && ($isRect || $isLine)) {
                $this->elements[$idx]['config_json']['border_style'] = $value;
            }
        }
    }

    public function groupSelected(): void
    {
        $indices = !empty($this->selectedIndices) ? $this->selectedIndices : (!is_null($this->selectedIndex) ? [$this->selectedIndex] : []);
        if (count($indices) < 2) return;

        $this->pushHistorySnapshot();
        $groupId = 'grp_' . time() . '_' . substr(md5(uniqid()), 0, 6);

        foreach ($indices as $idx) {
            if (isset($this->elements[$idx])) {
                $this->elements[$idx]['config_json']['group_id'] = $groupId;
            }
        }
    }

    public function ungroupSelected(): void
    {
        $indices = !empty($this->selectedIndices) ? $this->selectedIndices : (!is_null($this->selectedIndex) ? [$this->selectedIndex] : []);
        if (empty($indices)) return;

        $this->pushHistorySnapshot();

        foreach ($indices as $idx) {
            if (isset($this->elements[$idx])) {
                unset($this->elements[$idx]['config_json']['group_id']);
            }
        }
    }

    public function alignElements(string $direction): void
    {
        $indices = !empty($this->selectedIndices) ? $this->selectedIndices : (!is_null($this->selectedIndex) ? [$this->selectedIndex] : []);
        $unlocked = array_values(array_filter($indices, fn($i) => isset($this->elements[$i]) && !$this->elements[$i]['is_locked']));
        if (empty($unlocked)) return;

        $this->pushHistorySnapshot();

        if (count($unlocked) === 1) {
            // Align single element relative to page
            $idx = $unlocked[0];
            $w = (float)$this->elements[$idx]['width_mm'];
            $h = (float)$this->elements[$idx]['height_mm'];
            $paperW = (float)$this->template->paper_width_mm;
            $paperH = (float)$this->template->paper_height_mm;

            switch ($direction) {
                case 'left':
                    $this->elements[$idx]['x_mm'] = 0.0;
                    break;
                case 'center':
                    $this->elements[$idx]['x_mm'] = round(($paperW - $w) / 2, 2);
                    break;
                case 'right':
                    $this->elements[$idx]['x_mm'] = round($paperW - $w, 2);
                    break;
                case 'top':
                    $this->elements[$idx]['y_mm'] = 0.0;
                    break;
                case 'middle':
                    $this->elements[$idx]['y_mm'] = round(($paperH - $h) / 2, 2);
                    break;
                case 'bottom':
                    $this->elements[$idx]['y_mm'] = round($paperH - $h, 2);
                    break;
            }
        } else {
            // Align multiple elements relative to their selection bounding box
            $minX = PHP_FLOAT_MAX;
            $maxX = -PHP_FLOAT_MAX;
            $minY = PHP_FLOAT_MAX;
            $maxY = -PHP_FLOAT_MAX;

            foreach ($unlocked as $idx) {
                $x = (float)$this->elements[$idx]['x_mm'];
                $y = (float)$this->elements[$idx]['y_mm'];
                $w = (float)$this->elements[$idx]['width_mm'];
                $h = (float)$this->elements[$idx]['height_mm'];
                if ($x < $minX) $minX = $x;
                if ($x + $w > $maxX) $maxX = $x + $w;
                if ($y < $minY) $minY = $y;
                if ($y + $h > $maxY) $maxY = $y + $h;
            }

            $boxCenterX = ($minX + $maxX) / 2;
            $boxCenterY = ($minY + $maxY) / 2;

            foreach ($unlocked as $idx) {
                $w = (float)$this->elements[$idx]['width_mm'];
                $h = (float)$this->elements[$idx]['height_mm'];

                switch ($direction) {
                    case 'left':
                        $this->elements[$idx]['x_mm'] = round($minX, 2);
                        break;
                    case 'center':
                        $this->elements[$idx]['x_mm'] = round($boxCenterX - ($w / 2), 2);
                        break;
                    case 'right':
                        $this->elements[$idx]['x_mm'] = round($maxX - $w, 2);
                        break;
                    case 'top':
                        $this->elements[$idx]['y_mm'] = round($minY, 2);
                        break;
                    case 'middle':
                        $this->elements[$idx]['y_mm'] = round($boxCenterY - ($h / 2), 2);
                        break;
                    case 'bottom':
                        $this->elements[$idx]['y_mm'] = round($maxY - $h, 2);
                        break;
                }
            }
        }
    }

    public function distributeElements(string $direction): void
    {
        $indices = !empty($this->selectedIndices) ? $this->selectedIndices : [];
        $unlocked = array_values(array_filter($indices, fn($i) => isset($this->elements[$i]) && !$this->elements[$i]['is_locked']));
        if (count($unlocked) < 3) return;

        $this->pushHistorySnapshot();

        if ($direction === 'horizontal') {
            usort($unlocked, fn($a, $b) => $this->elements[$a]['x_mm'] <=> $this->elements[$b]['x_mm']);

            $firstIdx = $unlocked[0];
            $lastIdx = end($unlocked);

            $startX = (float)$this->elements[$firstIdx]['x_mm'];
            $lastX = (float)$this->elements[$lastIdx]['x_mm'];
            $lastW = (float)$this->elements[$lastIdx]['width_mm'];
            $endX = $lastX + $lastW;

            $totalElemW = 0.0;
            foreach ($unlocked as $idx) {
                $totalElemW += (float)$this->elements[$idx]['width_mm'];
            }

            $totalGap = ($endX - $startX) - $totalElemW;
            $gap = $totalGap / (count($unlocked) - 1);

            $currentX = $startX;
            for ($k = 0; $k < count($unlocked); $k++) {
                $idx = $unlocked[$k];
                $w = (float)$this->elements[$idx]['width_mm'];
                if ($k > 0 && $k < count($unlocked) - 1) {
                    $this->elements[$idx]['x_mm'] = round($currentX, 2);
                }
                $currentX += $w + $gap;
            }
        } elseif ($direction === 'vertical') {
            usort($unlocked, fn($a, $b) => $this->elements[$a]['y_mm'] <=> $this->elements[$b]['y_mm']);

            $firstIdx = $unlocked[0];
            $lastIdx = end($unlocked);

            $startY = (float)$this->elements[$firstIdx]['y_mm'];
            $lastY = (float)$this->elements[$lastIdx]['y_mm'];
            $lastH = (float)$this->elements[$lastIdx]['height_mm'];
            $endY = $lastY + $lastH;

            $totalElemH = 0.0;
            foreach ($unlocked as $idx) {
                $totalElemH += (float)$this->elements[$idx]['height_mm'];
            }

            $totalGap = ($endY - $startY) - $totalElemH;
            $gap = $totalGap / (count($unlocked) - 1);

            $currentY = $startY;
            for ($k = 0; $k < count($unlocked); $k++) {
                $idx = $unlocked[$k];
                $h = (float)$this->elements[$idx]['height_mm'];
                if ($k > 0 && $k < count($unlocked) - 1) {
                    $this->elements[$idx]['y_mm'] = round($currentY, 2);
                }
                $currentY += $h + $gap;
            }
        }
    }

    public function duplicateElement(int $index): void
    {
        if (!isset($this->elements[$index])) return;

        $this->pushHistorySnapshot();

        $elem = $this->elements[$index];
        $elem['id'] = null;
        $uniqueSuffix = substr(md5(uniqid((string)mt_rand(), true)), 0, 4);
        $elem['field_key'] = $elem['field_key'] . '_copy_' . $uniqueSuffix;
        $elem['uid'] = 'elem_copy_' . time() . '_' . rand(100, 999);
        $elem['x_mm'] = min($this->template->paper_width_mm - 10, round($elem['x_mm'] + 5.0, 2));
        $elem['y_mm'] = min($this->template->paper_height_mm - 10, round($elem['y_mm'] + 5.0, 2));
        $elem['sort_order'] = count($this->elements) + 1;

        if ($elem['element_type'] === 'STATIC_TEXT' && isset($elem['config_json']['static_text'])) {
            $elem['config_json']['static_text'] = $elem['config_json']['static_text'] . ' (Copy)';
        }

        $this->elements[] = $elem;
        $newIdx = count($this->elements) - 1;
        $this->selectedIndex = $newIdx;
        $this->selectedIndices = [$newIdx];
        $this->dispatch('element-selected', index: $newIdx);
    }

    public function duplicateSelected(): void
    {
        $indices = !empty($this->selectedIndices) ? $this->selectedIndices : (!is_null($this->selectedIndex) ? [$this->selectedIndex] : []);
        if (empty($indices)) return;

        $this->pushHistorySnapshot();

        $newIndices = [];
        foreach ($indices as $i) {
            if (!isset($this->elements[$i])) continue;

            $elem = $this->elements[$i];
            $elem['id'] = null;
            $uniqueSuffix = substr(md5(uniqid((string)mt_rand(), true)), 0, 4);
            $elem['field_key'] = $elem['field_key'] . '_copy_' . $uniqueSuffix;
            $elem['uid'] = 'elem_copy_' . time() . '_' . rand(100, 999);
            $elem['x_mm'] = min($this->template->paper_width_mm - 10, round($elem['x_mm'] + 5.0, 2));
            $elem['y_mm'] = min($this->template->paper_height_mm - 10, round($elem['y_mm'] + 5.0, 2));
            $elem['sort_order'] = count($this->elements) + 1;

            if ($elem['element_type'] === 'STATIC_TEXT' && isset($elem['config_json']['static_text'])) {
                $elem['config_json']['static_text'] = $elem['config_json']['static_text'] . ' (Copy)';
            }

            $this->elements[] = $elem;
            $newIndices[] = count($this->elements) - 1;
        }

        $this->resequenceSortOrder();
        $this->selectedIndices = $newIndices;
        $this->selectedIndex = !empty($newIndices) ? $newIndices[0] : null;
        if ($this->selectedIndex !== null) {
            $this->dispatch('element-selected', index: $this->selectedIndex);
        }
    }

    public function deleteSelected(): void
    {
        $indices = !empty($this->selectedIndices) ? $this->selectedIndices : (!is_null($this->selectedIndex) ? [$this->selectedIndex] : []);
        if (empty($indices)) return;

        $this->pushHistorySnapshot();

        // Sort descending so splicing from back does not invalidate preceding indices
        rsort($indices);
        foreach ($indices as $i) {
            if (isset($this->elements[$i]) && !$this->elements[$i]['is_locked']) {
                array_splice($this->elements, $i, 1);
            }
        }

        $this->resequenceSortOrder();
        $this->selectedIndex = count($this->elements) > 0 ? 0 : null;
        $this->selectedIndices = count($this->elements) > 0 ? [0] : [];
    }

    public function lockSelected(bool $lock = true): void
    {
        $indices = !empty($this->selectedIndices) ? $this->selectedIndices : (!is_null($this->selectedIndex) ? [$this->selectedIndex] : []);
        if (empty($indices)) return;

        $this->pushHistorySnapshot();

        foreach ($indices as $i) {
            if (isset($this->elements[$i])) {
                $this->elements[$i]['is_locked'] = $lock;
            }
        }
    }

    public function copySelected(): void
    {
        $indices = !empty($this->selectedIndices) ? $this->selectedIndices : (!is_null($this->selectedIndex) ? [$this->selectedIndex] : []);
        if (empty($indices)) return;

        $this->clipboard = [];
        foreach ($indices as $i) {
            if (isset($this->elements[$i])) {
                $item = $this->elements[$i];
                $item['id'] = null; // strip DB ID
                $this->clipboard[] = $item;
            }
        }
    }

    public function cutSelected(): void
    {
        $this->copySelected();
        $this->deleteSelected();
    }

    public function paste(): void
    {
        if (empty($this->clipboard)) return;

        $this->pushHistorySnapshot();

        $newIndices = [];
        foreach ($this->clipboard as $item) {
            $elem = $item;
            $uniqueSuffix = substr(md5(uniqid((string)mt_rand(), true)), 0, 4);
            $elem['field_key'] = $elem['field_key'] . '_paste_' . $uniqueSuffix;
            $elem['uid'] = 'elem_paste_' . time() . '_' . rand(100, 999);
            $elem['x_mm'] = min($this->template->paper_width_mm - 10, round($elem['x_mm'] + 5.0, 2));
            $elem['y_mm'] = min($this->template->paper_height_mm - 10, round($elem['y_mm'] + 5.0, 2));
            $elem['sort_order'] = count($this->elements) + 1;

            $this->elements[] = $elem;
            $newIndices[] = count($this->elements) - 1;
        }

        $this->resequenceSortOrder();
        $this->selectedIndices = $newIndices;
        $this->selectedIndex = !empty($newIndices) ? $newIndices[0] : null;
        if ($this->selectedIndex !== null) {
            $this->dispatch('element-selected', index: $this->selectedIndex);
        }
    }

    public function bringForward(int $index): void
    {
        if ($index < count($this->elements) - 1) {
            $this->pushHistorySnapshot();
            $temp = $this->elements[$index];
            $this->elements[$index] = $this->elements[$index + 1];
            $this->elements[$index + 1] = $temp;
            $this->selectedIndex = $index + 1;
            $this->selectedIndices = [$index + 1];
            $this->resequenceSortOrder();
        }
    }

    public function sendBackward(int $index): void
    {
        if ($index > 0) {
            $this->pushHistorySnapshot();
            $temp = $this->elements[$index];
            $this->elements[$index] = $this->elements[$index - 1];
            $this->elements[$index - 1] = $temp;
            $this->selectedIndex = $index - 1;
            $this->selectedIndices = [$index - 1];
            $this->resequenceSortOrder();
        }
    }

    public function bringToFront(int $index): void
    {
        if ($index < count($this->elements) - 1) {
            $this->pushHistorySnapshot();
            $elem = array_splice($this->elements, $index, 1)[0];
            $this->elements[] = $elem;
            $newIdx = count($this->elements) - 1;
            $this->selectedIndex = $newIdx;
            $this->selectedIndices = [$newIdx];
            $this->resequenceSortOrder();
        }
    }

    public function sendToBack(int $index): void
    {
        if ($index > 0) {
            $this->pushHistorySnapshot();
            $elem = array_splice($this->elements, $index, 1)[0];
            array_unshift($this->elements, $elem);
            $this->selectedIndex = 0;
            $this->selectedIndices = [0];
            $this->resequenceSortOrder();
        }
    }

    private function resequenceSortOrder(): void
    {
        foreach ($this->elements as $i => &$elem) {
            $elem['sort_order'] = $i + 1;
        }
    }

    public function nudge(int $index, float $dxMm, float $dyMm): void
    {
        $indices = in_array($index, $this->selectedIndices, true) && count($this->selectedIndices) > 1
            ? $this->selectedIndices
            : [$index];

        $this->pushHistorySnapshot();

        foreach ($indices as $i) {
            if (!isset($this->elements[$i]) || $this->elements[$i]['is_locked']) {
                continue;
            }
            $x = $this->elements[$i]['x_mm'] + $dxMm;
            $y = $this->elements[$i]['y_mm'] + $dyMm;

            $this->elements[$i]['x_mm'] = max(0.0, min($this->template->paper_width_mm - 2, round($x, 2)));
            $this->elements[$i]['y_mm'] = max(0.0, min($this->template->paper_height_mm - 2, round($y, 2)));
        }
    }

    public function deleteElement(int $index): void
    {
        if (isset($this->elements[$index]) && !$this->elements[$index]['is_locked']) {
            $this->pushHistorySnapshot();
            array_splice($this->elements, $index, 1);
            $this->selectedIndex = count($this->elements) > 0 ? max(0, $index - 1) : null;
            $this->selectedIndices = !is_null($this->selectedIndex) ? [$this->selectedIndex] : [];
            $this->resequenceSortOrder();
        }
    }

    public function toggleVisibility(int $index): void
    {
        if (isset($this->elements[$index])) {
            $this->pushHistorySnapshot();
            $this->elements[$index]['is_visible'] = !$this->elements[$index]['is_visible'];
        }
    }

    public function toggleLock(int $index): void
    {
        if (isset($this->elements[$index])) {
            $this->pushHistorySnapshot();
            $this->elements[$index]['is_locked'] = !$this->elements[$index]['is_locked'];
        }
    }

    public function uploadScan(): void
    {
        $this->validate([
            'scanFile' => 'required|image|mimes:png,jpg,jpeg|max:10240', // up to 10MB
        ]);

        $filename = 'pad_scan_' . $this->template->id . '_' . time() . '.' . $this->scanFile->getClientOriginalExtension();
        $path = $this->scanFile->storeAs('scans', $filename, 'public');

        $fullPath = storage_path('app/public/' . $path);
        $widthPx = null;
        $heightPx = null;
        if (file_exists($fullPath)) {
            $info = @getimagesize($fullPath);
            if ($info) {
                $widthPx = $info[0];
                $heightPx = $info[1];
            }
        }

        $this->template->update([
            'background_path' => 'storage/' . $path,
            'background_width_px' => $widthPx,
            'background_height_px' => $heightPx,
        ]);

        $this->reset('scanFile');
        $this->loadTemplate();

        session()->flash('success', 'Pad reference scan uploaded successfully. Visible in Proof Mode.');
    }

    public function removeScan(): void
    {
        if ($this->template->background_path) {
            $rawPath = str_replace('storage/', '', $this->template->background_path);
            if (Storage::disk('public')->exists($rawPath)) {
                Storage::disk('public')->delete($rawPath);
            }
            $this->template->update([
                'background_path' => null,
                'background_width_px' => null,
                'background_height_px' => null,
            ]);
            $this->loadTemplate();
            session()->flash('info', 'Reference scan removed.');
        }
    }

    public function saveDraft(PrescriptionPadTemplateService $service): void
    {
        if ($this->template->status !== 'DRAFT') {
            session()->flash('error', "Cannot save directly to {$this->template->status} template. Please duplicate to create a new draft.");
            return;
        }

        $userId = auth()->id() ?? 1;

        $service->saveElements($this->template, $this->elements, $userId);

        AuditLog::log('PRESCRIPTION_TEMPLATE_ELEMENTS_SAVED', 'PRESCRIPTION_TEMPLATE', $this->template->id, [
            'elements_count' => count($this->elements),
            'version' => $this->template->version,
        ]);

        $this->loadTemplate();
        $this->isDirty = false;
        $this->undoStack = [];
        $this->redoStack = [];
        $this->flashMessage = 'Template draft saved successfully.';
        session()->flash('success', 'Template draft saved successfully.');
    }

    public function activate(PrescriptionPadTemplateService $service): void
    {
        $userId = auth()->id() ?? 1;

        if ($this->template->status === 'DRAFT') {
            $service->saveElements($this->template, $this->elements, $userId);
        }

        try {
            $service->activateTemplate($this->template, $userId, true);

            AuditLog::log('PRESCRIPTION_TEMPLATE_ACTIVATED', 'PRESCRIPTION_TEMPLATE', $this->template->id, [
                'doctor_id' => $this->template->doctor_id,
                'version' => $this->template->version,
            ]);

            $this->loadTemplate();
            session()->flash('success', "Template '{$this->template->name}' (v{$this->template->version}) is now ACTIVE!");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function duplicate(PrescriptionPadTemplateService $service): void
    {
        $userId = auth()->id() ?? 1;
        $newDraft = $service->duplicateAsDraft($this->template, $userId);

        AuditLog::log('PRESCRIPTION_TEMPLATE_DUPLICATED', 'PRESCRIPTION_TEMPLATE', $newDraft->id, [
            'source_id' => $this->template->id,
            'new_version' => $newDraft->version,
        ]);

        session()->flash('success', "Created new Draft Version {$newDraft->version}.");
        $this->redirect(route('settings.prescription-pad-designer', $newDraft->id), navigate: true);
    }

    public function downloadTestPdf(string $mode = 'production')
    {
        $renderer = app(PrescriptionPdfRenderer::class);
        $printer = PrinterProfile::where('clinic_id', $this->template->clinic_id)->where('is_default', true)->first();

        // Render with current canvas elements for instant feedback
        $tempTemplate = clone $this->template;
        $sampleData = $this->sampleData;

        return response()->streamDownload(function () use ($renderer, $tempTemplate, $sampleData, $mode, $printer) {
            echo $renderer->outputPdf(
                $tempTemplate,
                $sampleData,
                $mode,
                $printer
            );
        }, "prescription-test-{$this->template->id}-{$mode}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function render()
    {
        return view('livewire.settings.prescription-pad-designer')
            ->layout('components.layouts.app');
    }
}
