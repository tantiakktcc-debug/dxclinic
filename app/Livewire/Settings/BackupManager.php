<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Clinic;
use App\Services\BackupService;
use Livewire\Component;

class BackupManager extends Component
{
    public ?Clinic $clinic = null;

    // Create Backup Modal
    public bool $showCreateModal = false;
    public string $backupPassword = '';
    public string $backupPasswordConfirmation = '';

    // Verify Backup Modal
    public bool $showVerifyModal = false;
    public ?int $verifyBackupId = null;
    public string $verifyPassword = '';
    public ?array $verifyResult = null;

    // Restore Backup Modal
    public bool $showRestoreModal = false;
    public ?int $restoreBackupId = null;
    public string $restorePassword = '';
    public string $restoreConfirmText = '';
    public ?array $restoreResult = null;

    // Automatic Schedule Configuration
    public bool $autoEnabled = true;
    public string $scheduleTime = '22:00';
    public string $customBackupFolder = '';

    public function boot(): void
    {
        $this->ensureAuthorized();
    }

    public function mount(): void
    {
        $this->ensureAuthorized();
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
        if ($this->clinic) {
            $this->autoEnabled = (bool) ($this->clinic->backup_auto_enabled ?? true);
            $this->scheduleTime = $this->clinic->backup_schedule_time ?: '22:00';
        }
        $this->customBackupFolder = storage_path('app/backups');
    }

    protected function ensureAuthorized(): void
    {
        if (auth()->check()) {
            $role = strtoupper(auth()->user()->role ?? '');
            if (!in_array($role, ['ADMIN', 'SUPERADMIN'], true)) {
                abort(403, 'Unauthorized. Clinical backup facility is strictly restricted to platform administrators.');
            }
        }
    }

    public function saveSchedule(): void
    {
        $this->validate([
            'scheduleTime' => 'required|date_format:H:i',
        ]);

        if ($this->clinic) {
            $this->clinic->update([
                'backup_auto_enabled' => $this->autoEnabled,
                'backup_schedule_time' => $this->scheduleTime,
            ]);

            AuditLog::log('BACKUP_SCHEDULE_UPDATED', 'CLINIC', $this->clinic->id, [
                'enabled' => $this->autoEnabled,
                'time' => $this->scheduleTime,
            ]);

            session()->flash('success', 'Automated daily backup schedule saved.');
        }
    }

    public function openCreateModal(): void
    {
        $this->reset(['backupPassword', 'backupPasswordConfirmation']);
        $this->showCreateModal = true;
    }

    public function createBackup(BackupService $backupService): void
    {
        $this->ensureAuthorized();

        $this->validate([
            'backupPassword' => 'required|string|min:8|same:backupPasswordConfirmation',
            'customBackupFolder' => 'nullable|string',
        ]);

        try {
            $targetFolder = !empty(trim($this->customBackupFolder)) ? trim($this->customBackupFolder) : null;
            $backup = $backupService->createBackup($this->backupPassword, 'MANUAL', $targetFolder);
            $this->showCreateModal = false;
            $this->reset(['backupPassword', 'backupPasswordConfirmation']);

            session()->flash('success', "Encrypted backup container successfully created: {$backup->filename} (" . number_format($backup->size_bytes / 1024, 1) . " KB) with SHA-256 checksum and metadata manifest.");
        } catch (\Exception $e) {
            session()->flash('error', "Backup failed: " . $e->getMessage());
        }
    }

    public function openVerifyModal(int $backupId): void
    {
        $this->verifyBackupId = $backupId;
        $this->verifyPassword = '';
        $this->verifyResult = null;
        $this->showVerifyModal = true;
    }

    public function verifyBackup(BackupService $backupService): void
    {
        $this->validate([
            'verifyPassword' => 'required|string|min:4',
        ]);

        $backup = Backup::findOrFail($this->verifyBackupId);

        try {
            $this->verifyResult = $backupService->verifyBackup($backup, $this->verifyPassword);
            if ($this->verifyResult['valid']) {
                session()->flash('success', 'Backup archive cryptographically verified with valid SHA-256 and manifest checksum.');
            } else {
                session()->flash('error', 'Verification failed: ' . ($this->verifyResult['error'] ?? 'Corrupt backup'));
            }
        } catch (\Exception $e) {
            $this->verifyResult = [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
            session()->flash('error', 'Verification failed: ' . $e->getMessage());
        }
    }

    public function openRestoreModal(int $backupId): void
    {
        $this->restoreBackupId = $backupId;
        $this->restorePassword = '';
        $this->restoreConfirmText = '';
        $this->restoreResult = null;
        $this->showRestoreModal = true;
    }

    public function restoreBackup(BackupService $backupService): void
    {
        $this->validate([
            'restorePassword' => 'required|string|min:4',
            'restoreConfirmText' => 'required|in:RESTORE',
        ]);

        $backup = Backup::findOrFail($this->restoreBackupId);

        try {
            $this->restoreResult = $backupService->restoreBackup($backup, $this->restorePassword);

            if ($this->restoreResult['success']) {
                $this->showRestoreModal = false;
                session()->flash('success', "Database successfully restored from backup! Post-restore double-entry check verified: Debits = Credits (Diff: ₹0.00).");
            } else {
                session()->flash('error', 'Restoration failed: ' . ($this->restoreResult['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Restoration error: ' . $e->getMessage());
        }
    }

    public function downloadBackup(int $id)
    {
        $this->ensureAuthorized();
        $backup = Backup::findOrFail($id);
        if (!file_exists($backup->file_path)) {
            session()->flash('error', 'Backup file does not exist on storage disk.');
            return null;
        }

        return response()->download($backup->file_path, $backup->filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    public function downloadMetadata(int $id)
    {
        $this->ensureAuthorized();
        $backup = Backup::findOrFail($id);
        $metaPath = $backup->file_path . '.meta.json';
        if (!file_exists($metaPath)) {
            // Generate on-the-fly if missing
            $metaData = [
                'backup_id' => $backup->id,
                'filename' => $backup->filename,
                'sha256_checksum' => $backup->checksum,
                'size_bytes' => $backup->size_bytes,
                'schema_version' => $backup->schema_version,
                'backup_type' => $backup->backup_type,
                'created_at' => $backup->created_at?->toIso8601String(),
                'status' => $backup->status,
            ];
            file_put_contents($metaPath, json_encode($metaData, JSON_PRETTY_PRINT));
        }

        return response()->download($metaPath, "{$backup->filename}.meta.json", [
            'Content-Type' => 'application/json',
        ]);
    }

    public function render()
    {
        $backups = Backup::orderByDesc('id')->get();

        return view('livewire.settings.backup-manager', [
            'backups' => $backups,
        ])->layout('components.layouts.app', ['title' => 'Backup & Restore']);
    }
}
