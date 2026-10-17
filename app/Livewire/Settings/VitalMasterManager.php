<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\Clinic;
use Livewire\Component;

class VitalMasterManager extends Component
{
    public ?Clinic $clinic = null;
    public array $vitals = [];

    public function mount(): void
    {
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
        $this->loadVitals();
    }

    public function loadVitals(): void
    {
        $defaultVitals = [
            'weight' => ['name' => 'Weight', 'unit' => 'kg', 'is_active' => true, 'min_normal' => 30.0, 'max_normal' => 120.0],
            'height' => ['name' => 'Height', 'unit' => 'cm', 'is_active' => true, 'min_normal' => 100.0, 'max_normal' => 200.0],
            'bp_systolic' => ['name' => 'Systolic Blood Pressure', 'unit' => 'mmHg', 'is_active' => true, 'min_normal' => 90, 'max_normal' => 140],
            'bp_diastolic' => ['name' => 'Diastolic Blood Pressure', 'unit' => 'mmHg', 'is_active' => true, 'min_normal' => 60, 'max_normal' => 90],
            'pulse' => ['name' => 'Pulse Rate', 'unit' => 'bpm', 'is_active' => true, 'min_normal' => 60, 'max_normal' => 100],
            'spo2' => ['name' => 'Oxygen Saturation (SpO2)', 'unit' => '%', 'is_active' => true, 'min_normal' => 95, 'max_normal' => 100],
            'temperature' => ['name' => 'Body Temperature', 'unit' => '°F', 'is_active' => true, 'min_normal' => 97.0, 'max_normal' => 99.0],
            'fever' => ['name' => 'Fever Indicator', 'unit' => 'Flag', 'is_active' => true, 'min_normal' => 0, 'max_normal' => 0],
            'respiratory_rate' => ['name' => 'Respiratory Rate', 'unit' => '/min', 'is_active' => true, 'min_normal' => 12, 'max_normal' => 20],
            'rbs' => ['name' => 'Random Blood Sugar (RBS)', 'unit' => 'mg/dL', 'is_active' => true, 'min_normal' => 70.0, 'max_normal' => 140.0],
        ];

        $savedConfig = $this->clinic?->print_branding_config['vitals'] ?? [];
        $this->vitals = array_replace_recursive($defaultVitals, $savedConfig);
    }

    public function toggleVital(string $key): void
    {
        if (isset($this->vitals[$key])) {
            $this->vitals[$key]['is_active'] = !$this->vitals[$key]['is_active'];
            $this->saveVitals();
        }
    }

    public function saveVitals(): void
    {
        if (!$this->clinic) {
            return;
        }

        $config = $this->clinic->print_branding_config ?? [];
        $config['vitals'] = $this->vitals;

        $this->clinic->update([
            'print_branding_config' => $config,
        ]);

        AuditLog::log('VITAL_MASTER_CONFIG_UPDATED', 'CLINIC', $this->clinic->id);

        session()->flash('success', 'Clinical vital parameters and normal reference ranges saved successfully.');
    }

    public function resetToDefaults(): void
    {
        if (!$this->clinic) {
            return;
        }

        $config = $this->clinic->print_branding_config ?? [];
        unset($config['vitals']);

        $this->clinic->update([
            'print_branding_config' => $config,
        ]);

        $this->loadVitals();

        AuditLog::log('VITAL_MASTER_CONFIG_RESET', 'CLINIC', $this->clinic->id);

        session()->flash('success', 'Reset vital parameters to standard medical reference defaults.');
    }

    public function render()
    {
        return view('livewire.settings.vital-master-manager', [
            'vitals' => $this->vitals,
        ])->layout('components.layouts.app', ['title' => 'Vital Master']);
    }
}
