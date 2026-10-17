<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class UserManager extends Component
{
    // Search & filters
    public string $search = '';
    public string $roleFilter = 'ALL';

    // Create User Form
    public bool $showCreateModal = false;
    public string $newName = '';
    public string $newLoginId = '';
    public string $newEmail = '';
    public string $newPhone = '';
    public string $newRole = 'OPERATOR';
    public ?int $newDoctorId = null;
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    // Edit User Form
    public bool $showEditModal = false;
    public ?int $editUserId = null;
    public string $editName = '';
    public string $editLoginId = '';
    public string $editEmail = '';
    public string $editPhone = '';
    public string $editRole = 'OPERATOR';
    public ?int $editDoctorId = null;

    // Reset Password Modal (Admin)
    public bool $showResetPasswordModal = false;
    public ?int $resetUserId = null;
    public string $resetUserName = '';
    public string $resetPassword = '';
    public string $resetPasswordConfirmation = '';

    // Change Own Password Modal
    public bool $showChangeOwnPasswordModal = false;
    public string $currentPassword = '';
    public string $ownNewPassword = '';
    public string $ownNewPasswordConfirmation = '';

    public function openCreateModal(): void
    {
        $this->reset(['newName', 'newLoginId', 'newEmail', 'newPhone', 'newRole', 'newDoctorId', 'newPassword', 'newPasswordConfirmation']);
        $clinicId = \App\Services\TenantContext::clinicId() ?? 1;
        $firstDoc = Doctor::where('clinic_id', $clinicId)->where('is_active', true)->first();
        $this->newDoctorId = $firstDoc?->id;
        $this->autoSuggestLoginId($this->newRole);
        $this->showCreateModal = true;
    }

    public function updatedNewRole($val): void
    {
        $this->autoSuggestLoginId($val);
    }

    public function autoSuggestLoginId(string $role): void
    {
        $clinicId = \App\Services\TenantContext::clinicId() ?? 1;
        $prefix = match (strtoupper($role)) {
            'ADMIN' => 'ADM',
            'DOCTOR' => 'DOC',
            default => 'OPR',
        };
        $count = User::where('clinic_id', $clinicId)->where('login_id', 'LIKE', "{$prefix}%")->count() + 1;
        $this->newLoginId = sprintf("%s%02d", $prefix, $count);
    }

    public function createUser(): void
    {
        $rules = [
            'newName' => 'required|string|min:3|max:100',
            'newLoginId' => 'nullable|string|max:50',
            'newEmail' => 'required|email|max:255',
            'newPhone' => 'nullable|string|max:20',
            'newRole' => 'required|in:ADMIN,OPERATOR,DOCTOR',
            'newPassword' => 'required|string|min:8|same:newPasswordConfirmation',
        ];

        if ($this->newRole === 'DOCTOR') {
            $rules['newDoctorId'] = 'required|exists:doctors,id';
        }

        $this->validate($rules);

        $clinicId = \App\Services\TenantContext::clinicId() ?? 1;
        if (empty($this->newLoginId)) {
            $this->autoSuggestLoginId($this->newRole);
        }
        $loginId = strtoupper(trim($this->newLoginId));

        if (User::where('clinic_id', $clinicId)->where('login_id', $loginId)->exists()) {
            $this->addError('newLoginId', "Login ID {$loginId} is already taken in this clinic.");
            return;
        }

        $user = User::create([
            'clinic_id' => $clinicId,
            'login_id' => $loginId,
            'name' => trim($this->newName),
            'email' => strtolower(trim($this->newEmail)),
            'phone' => trim($this->newPhone),
            'role' => $this->newRole,
            'doctor_id' => $this->newRole === 'DOCTOR' ? $this->newDoctorId : null,
            'status' => 'ACTIVE',
            'password' => Hash::make($this->newPassword),
        ]);

        AuditLog::log('USER_CREATED', 'USER', $user->id, [
            'name' => $user->name,
            'login_id' => $user->login_id,
            'email' => $user->email,
            'role' => $user->role,
            'doctor_id' => $user->doctor_id,
        ]);

        $this->showCreateModal = false;
        session()->flash('success', "User '{$user->name}' (ID: {$user->login_id}) created successfully with role {$user->role}.");
    }

    public function openEditModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editUserId = $user->id;
        $this->editName = $user->name;
        $this->editLoginId = $user->login_id ?? '';
        $this->editEmail = $user->email;
        $this->editPhone = $user->phone ?? '';
        $this->editRole = $user->role;
        $this->editDoctorId = $user->doctor_id;
        $this->showEditModal = true;
    }

    public function updateUser(): void
    {
        $rules = [
            'editName' => 'required|string|min:3|max:100',
            'editLoginId' => 'nullable|string|max:50',
            'editEmail' => 'required|email|max:255',
            'editPhone' => 'nullable|string|max:20',
            'editRole' => 'required|in:ADMIN,OPERATOR,DOCTOR',
        ];

        if ($this->editRole === 'DOCTOR') {
            $rules['editDoctorId'] = 'required|exists:doctors,id';
        }

        $this->validate($rules);

        $user = User::findOrFail($this->editUserId);
        $clinicId = $user->clinic_id ?? \App\Services\TenantContext::clinicId() ?? 1;
        $loginId = !empty($this->editLoginId) ? strtoupper(trim($this->editLoginId)) : null;

        if ($loginId && User::where('clinic_id', $clinicId)->where('login_id', $loginId)->where('id', '!=', $user->id)->exists()) {
            $this->addError('editLoginId', "Login ID {$loginId} is already taken in this clinic.");
            return;
        }

        $oldRole = $user->role;

        $user->update([
            'login_id' => $loginId,
            'name' => trim($this->editName),
            'email' => strtolower(trim($this->editEmail)),
            'phone' => trim($this->editPhone),
            'role' => $this->editRole,
            'doctor_id' => $this->editRole === 'DOCTOR' ? $this->editDoctorId : null,
        ]);

        AuditLog::log('USER_UPDATED', 'USER', $user->id, [
            'name' => $user->name,
            'old_role' => $oldRole,
            'new_role' => $user->role,
            'doctor_id' => $user->doctor_id,
        ]);

        $this->showEditModal = false;
        session()->flash('success', "User '{$user->name}' updated successfully.");
    }

    public function toggleUserStatus(int $userId): void
    {
        if ($userId === auth()->id()) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }

        $user = User::findOrFail($userId);
        $newStatus = $user->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $user->update(['status' => $newStatus]);

        AuditLog::log('USER_STATUS_TOGGLED', 'USER', $user->id, ['status' => $newStatus]);
        session()->flash('success', "Status for '{$user->name}' changed to {$newStatus}.");
    }

    public function openResetPasswordModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->resetUserId = $user->id;
        $this->resetUserName = $user->name;
        $this->resetPassword = '';
        $this->resetPasswordConfirmation = '';
        $this->showResetPasswordModal = true;
    }

    public function resetPassword(): void
    {
        $this->validate([
            'resetPassword' => 'required|string|min:8|same:resetPasswordConfirmation',
        ]);

        $user = User::findOrFail($this->resetUserId);
        $user->update([
            'password' => Hash::make($this->resetPassword),
        ]);

        AuditLog::log('USER_PASSWORD_RESET', 'USER', $user->id, ['reset_by' => auth()->id()]);

        $this->showResetPasswordModal = false;
        session()->flash('success', "Password for user '{$user->name}' has been successfully reset.");
    }

    public function openChangeOwnPasswordModal(): void
    {
        $this->reset(['currentPassword', 'ownNewPassword', 'ownNewPasswordConfirmation']);
        $this->showChangeOwnPasswordModal = true;
    }

    public function changeOwnPassword(): void
    {
        $this->validate([
            'currentPassword' => 'required',
            'ownNewPassword' => 'required|string|min:8|same:ownNewPasswordConfirmation',
        ]);

        $user = auth()->user();

        if (!Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', 'The provided current password does not match our records.');
            return;
        }

        $user->update([
            'password' => Hash::make($this->ownNewPassword),
        ]);

        AuditLog::log('USER_OWN_PASSWORD_CHANGED', 'USER', $user->id);

        $this->showChangeOwnPasswordModal = false;
        session()->flash('success', 'Your password has been changed successfully.');
    }

    public function render()
    {
        $clinicId = \App\Services\TenantContext::clinicId() ?? 1;
        $query = User::with('doctor')
            ->where(function ($q) use ($clinicId) {
                $q->where('clinic_id', $clinicId)->orWhereNull('clinic_id');
            })
            ->orderBy('name');

        if ($this->roleFilter !== 'ALL') {
            $query->where('role', $this->roleFilter);
        }

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('login_id', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        $doctors = Doctor::where('clinic_id', $clinicId)->where('is_active', true)->orderBy('name')->get();

        return view('livewire.settings.user-manager', [
            'users' => $query->get(),
            'doctors' => $doctors,
        ])->layout('components.layouts.app', ['title' => 'Users & Operators']);
    }
}
