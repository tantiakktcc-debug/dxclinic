<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PrescriptionPadTemplate;
use App\Models\PrinterProfile;
use App\Services\PrescriptionPadTemplateService;
use Livewire\Component;

class PrescriptionPadManager extends Component
{
    public ?Clinic $clinic = null;
    public ?int $selectedDoctorId = null;
    public string $search = '';

    // Create Modal State
    public bool $showCreateModal = false;
    public ?int $newDoctorId = null;
    public string $newName = '';
    public string $newPaperSize = 'A4';
    public string $newOrientation = 'portrait';
    public float $newCustomWidthMm = 210.0;
    public float $newCustomHeightMm = 297.0;

    // Printer Calibration Modal State
    public bool $showPrinterModal = false;
    public float $printerOffsetX = 0.0;
    public float $printerOffsetY = 0.0;
    public float $printerScaleX = 1.0;
    public float $printerScaleY = 1.0;

    public function mount(): void
    {
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
        abort_unless(in_array(auth()->user()?->role, ['ADMIN', 'OPERATOR']), 403, 'Unauthorized access to prescription pad templates.');
        $firstDoctor = Doctor::where('clinic_id', $this->clinic->id)->first();
        $this->selectedDoctorId = $firstDoctor?->id;
        $this->newDoctorId = $firstDoctor?->id;

        $printer = PrinterProfile::where('clinic_id', $this->clinic?->id)->where('is_default', true)->first();
        if ($printer) {
            $this->printerOffsetX = (float)$printer->offset_x_mm;
            $this->printerOffsetY = (float)$printer->offset_y_mm;
            $this->printerScaleX = (float)$printer->scale_x;
            $this->printerScaleY = (float)$printer->scale_y;
        }
    }

    public function openCreateModal(): void
    {
        $this->newName = '';
        $this->newPaperSize = 'A4';
        $this->newOrientation = 'portrait';
        $this->newCustomWidthMm = 210.0;
        $this->newCustomHeightMm = 297.0;
        if (!$this->newDoctorId) {
            $this->newDoctorId = $this->selectedDoctorId;
        }
        $this->showCreateModal = true;
    }

    public function createTemplate(PrescriptionPadTemplateService $service): void
    {
        $this->validate([
            'newDoctorId' => 'required|exists:doctors,id',
            'newName' => 'required|string|min:3|max:150',
            'newPaperSize' => 'required|in:A4,A5,Letter,Half Letter,Custom',
            'newOrientation' => 'required|in:portrait,landscape',
        ]);

        $width = 210.0;
        $height = 297.0;

        if ($this->newPaperSize === 'Custom') {
            $width = $this->newCustomWidthMm;
            $height = $this->newCustomHeightMm;
        } else {
            $size = PrescriptionPadTemplateService::PAPER_SIZES[$this->newPaperSize] ?? null;
            if ($size) {
                $width = $size['width_mm'];
                $height = $size['height_mm'];
            }
        }

        $userId = auth()->id() ?? 1;

        $template = $service->createDraft([
            'clinic_id' => $this->clinic?->id ?? 1,
            'doctor_id' => $this->newDoctorId,
            'name' => trim($this->newName),
            'paper_width_mm' => $width,
            'paper_height_mm' => $height,
            'orientation' => $this->newOrientation,
            'template_type' => 'CUSTOM',
        ], $userId);

        AuditLog::log('PRESCRIPTION_TEMPLATE_CREATED', 'PRESCRIPTION_TEMPLATE', $template->id, [
            'name' => $template->name,
            'doctor_id' => $template->doctor_id,
            'version' => $template->version,
        ]);

        $this->showCreateModal = false;
        session()->flash('success', "Template '{$template->name}' (Version {$template->version}) created as DRAFT.");

        $this->redirect(route('settings.prescription-pad-designer', $template->id), navigate: true);
    }

    public function createFromLegacy(int $doctorId, PrescriptionPadTemplateService $service): void
    {
        $userId = auth()->id() ?? 1;
        $clinicId = $this->clinic?->id ?? 1;

        $template = $service->createFromLegacySettings($doctorId, $clinicId, $userId);

        AuditLog::log('PRESCRIPTION_TEMPLATE_IMPORTED_LEGACY', 'PRESCRIPTION_TEMPLATE', $template->id, [
            'doctor_id' => $doctorId,
            'version' => $template->version,
        ]);

        session()->flash('success', "Imported legacy settings into Draft Version {$template->version}. The legacy system remains untouched.");
    }

    public function duplicateTemplate(int $templateId, PrescriptionPadTemplateService $service): void
    {
        $template = PrescriptionPadTemplate::findOrFail($templateId);
        $userId = auth()->id() ?? 1;

        $newDraft = $service->duplicateAsDraft($template, $userId);

        AuditLog::log('PRESCRIPTION_TEMPLATE_DUPLICATED', 'PRESCRIPTION_TEMPLATE', $newDraft->id, [
            'source_template_id' => $template->id,
            'new_version' => $newDraft->version,
        ]);

        session()->flash('success', "Duplicated to new Draft Version {$newDraft->version}.");
    }

    public function activateTemplate(int $templateId, PrescriptionPadTemplateService $service): void
    {
        $template = PrescriptionPadTemplate::findOrFail($templateId);
        $userId = auth()->id() ?? 1;

        try {
            $service->activateTemplate($template, $userId, true);

            AuditLog::log('PRESCRIPTION_TEMPLATE_ACTIVATED', 'PRESCRIPTION_TEMPLATE', $template->id, [
                'doctor_id' => $template->doctor_id,
                'version' => $template->version,
            ]);

            session()->flash('success', "Template '{$template->name}' (v{$template->version}) is now ACTIVE. Doctor Pad will use this template.");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function archiveTemplate(int $templateId, PrescriptionPadTemplateService $service): void
    {
        $template = PrescriptionPadTemplate::findOrFail($templateId);
        $userId = auth()->id() ?? 1;

        $service->archiveTemplate($template, $userId);

        AuditLog::log('PRESCRIPTION_TEMPLATE_ARCHIVED', 'PRESCRIPTION_TEMPLATE', $template->id, [
            'version' => $template->version,
        ]);

        session()->flash('info', "Template '{$template->name}' archived.");
    }

    public function openPrinterModal(): void
    {
        $printer = PrinterProfile::where('clinic_id', $this->clinic?->id)->where('is_default', true)->first();
        if ($printer) {
            $this->printerOffsetX = (float)$printer->offset_x_mm;
            $this->printerOffsetY = (float)$printer->offset_y_mm;
            $this->printerScaleX = (float)$printer->scale_x;
            $this->printerScaleY = (float)$printer->scale_y;
        } else {
            $this->printerOffsetX = 0.0;
            $this->printerOffsetY = 0.0;
            $this->printerScaleX = 1.0;
            $this->printerScaleY = 1.0;
        }
        $this->showPrinterModal = true;
    }

    public function savePrinterCalibration(): void
    {
        $this->validate([
            'printerOffsetX' => 'required|numeric|between:-50,50',
            'printerOffsetY' => 'required|numeric|between:-50,50',
            'printerScaleX' => 'required|numeric|between:0.5,2.0',
            'printerScaleY' => 'required|numeric|between:0.5,2.0',
        ]);

        $clinicId = $this->clinic?->id ?? 1;

        PrinterProfile::updateOrCreate(
            ['clinic_id' => $clinicId, 'is_default' => true],
            [
                'name' => 'Default OPD Printer',
                'paper_width_mm' => 210.0,
                'paper_height_mm' => 297.0,
                'offset_x_mm' => $this->printerOffsetX,
                'offset_y_mm' => $this->printerOffsetY,
                'scale_x' => $this->printerScaleX,
                'scale_y' => $this->printerScaleY,
            ]
        );

        AuditLog::log('PRINTER_CALIBRATION_UPDATED', 'PRINTER_PROFILE', $clinicId, [
            'offset_x' => $this->printerOffsetX,
            'offset_y' => $this->printerOffsetY,
            'scale_x' => $this->printerScaleX,
            'scale_y' => $this->printerScaleY,
        ]);

        $this->showPrinterModal = false;
        session()->flash('success', 'Printer calibration offsets saved successfully.');
    }

    public function render()
    {
        $clinicId = $this->clinic?->id ?? 1;
        $doctors = Doctor::where('clinic_id', $clinicId)->where('is_active', true)->get();

        $templatesQuery = PrescriptionPadTemplate::with(['doctor', 'creator', 'updater', 'elements'])
            ->where('clinic_id', $clinicId);

        if ($this->selectedDoctorId) {
            $templatesQuery->where('doctor_id', $this->selectedDoctorId);
        }

        if (!empty($this->search)) {
            $templatesQuery->where('name', 'like', '%' . trim($this->search) . '%');
        }

        $templates = $templatesQuery->orderBy('doctor_id')->orderByDesc('version')->get();

        return view('livewire.settings.prescription-pad-manager', [
            'doctors' => $doctors,
            'templates' => $templates,
        ])->layout('components.layouts.app');
    }
}
