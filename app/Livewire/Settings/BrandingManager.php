<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\Clinic;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class BrandingManager extends Component
{
    use WithFileUploads;

    public ?Clinic $clinic = null;
    public $logoFile;
    public string $logoPosition = 'left';
    public bool $lockAspectRatio = true;
    public int $boxWidth = 120;
    public int $boxHeight = 44;

    public function mount(): void
    {
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
        $this->logoPosition = $this->clinic->logo_position ?? 'left';
    }

    public function uploadLogo(): void
    {
        $this->validate([
            'logoFile' => 'required|image|mimes:png,jpg,jpeg|max:2048', // Max 2MB
        ]);

        if (!$this->clinic) {
            return;
        }

        // Delete old logo if exists
        if ($this->clinic->logo_path && Storage::disk('public')->exists($this->clinic->logo_path)) {
            Storage::disk('public')->delete($this->clinic->logo_path);
        }

        $extension = $this->logoFile->getClientOriginalExtension();
        $filename = 'clinic_logo_' . time() . '.' . $extension;
        $path = $this->logoFile->storeAs('branding', $filename, 'public');

        $this->clinic->update([
            'logo_path' => $path,
            'logo_position' => $this->logoPosition,
        ]);

        AuditLog::log('CLINIC_LOGO_UPLOADED', 'CLINIC', $this->clinic->id, ['logo_path' => $path]);

        $this->reset('logoFile');
        $this->clinic = $this->clinic->fresh();

        session()->flash('success', 'Clinic logo uploaded successfully and applied to print templates.');
    }

    public function updatePosition(string $position): void
    {
        if (!in_array($position, ['left', 'center', 'right'], true)) {
            return;
        }

        $this->logoPosition = $position;
        if ($this->clinic) {
            $this->clinic->update(['logo_position' => $position]);

            AuditLog::log('CLINIC_LOGO_POSITION_UPDATED', 'CLINIC', $this->clinic->id, ['position' => $position]);

            session()->flash('success', "Logo print position updated to " . ucfirst($position) . ".");
        }
    }

    public function removeLogo(): void
    {
        if ($this->clinic && $this->clinic->logo_path) {
            if (Storage::disk('public')->exists($this->clinic->logo_path)) {
                Storage::disk('public')->delete($this->clinic->logo_path);
            }

            $this->clinic->update(['logo_path' => null]);

            AuditLog::log('CLINIC_LOGO_REMOVED', 'CLINIC', $this->clinic->id);

            $this->clinic = $this->clinic->fresh();
            session()->flash('success', 'Clinic logo removed.');
        }
    }

    public function render()
    {
        return view('livewire.settings.branding-manager', [
            'clinic' => $this->clinic,
        ])->layout('components.layouts.app', ['title' => 'Print & Branding']);
    }
}
