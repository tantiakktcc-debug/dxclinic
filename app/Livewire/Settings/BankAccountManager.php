<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Clinic;
use App\Models\LedgerAccount;
use App\Services\BankLedgerService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class BankAccountManager extends Component
{
    public ?Clinic $clinic = null;
    public string $search = '';

    // Create Modal
    public bool $showCreateModal = false;
    public string $newBankName = '';
    public string $newAccountHolder = '';
    public string $newAccountNumber = '';
    public string $newIfsc = '';
    public string $newBranch = '';
    public string $newUpiId = '';
    public bool $newIsActive = true;

    // Edit Modal
    public bool $showEditModal = false;
    public ?int $editBankAccountId = null;
    public string $editBankName = '';
    public string $editAccountHolder = '';
    public string $editAccountNumber = '';
    public string $editIfsc = '';
    public string $editBranch = '';
    public string $editUpiId = '';
    public bool $editIsActive = true;

    public function mount(): void
    {
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
    }

    public function openCreateModal(): void
    {
        $this->reset(['newBankName', 'newAccountHolder', 'newAccountNumber', 'newIfsc', 'newBranch', 'newUpiId', 'newIsActive']);
        $this->newIsActive = true;
        if ($this->clinic) {
            $this->newAccountHolder = $this->clinic->name;
        }
        $this->showCreateModal = true;
    }

    public function createBankAccount(): void
    {
        $this->validate([
            'newBankName' => 'required|string|min:2|max:100',
            'newAccountHolder' => 'required|string|min:2|max:150',
            'newAccountNumber' => 'required|string|min:6|max:30|unique:bank_accounts,account_number',
            'newIfsc' => 'required|string|min:4|max:15',
            'newBranch' => 'nullable|string|max:100',
            'newUpiId' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () {
            // Find next available ledger account code in the 1002+ range
            $existingCodes = LedgerAccount::where('type', 'ASSET')
                ->where('code', '>=', '1002')
                ->pluck('code')
                ->toArray();

            $nextCodeNumber = 1002;
            while (in_array((string)$nextCodeNumber, $existingCodes, true)) {
                $nextCodeNumber++;
            }
            $ledgerCode = (string)$nextCodeNumber;

            $ledger = LedgerAccount::create([
                'code' => $ledgerCode,
                'name' => "Bank - {$this->newBankName} (" . substr(trim($this->newAccountNumber), -4) . ")",
                'type' => 'ASSET',
                'subtype' => 'BANK_ACCOUNT',
                'is_system' => true,
                'description' => "Dedicated ledger account for {$this->newBankName} A/C {$this->newAccountNumber}",
            ]);

            $bankAccount = BankAccount::create([
                'clinic_id' => $this->clinic?->id ?? 1,
                'bank_name' => trim($this->newBankName),
                'account_holder' => trim($this->newAccountHolder),
                'account_number' => trim($this->newAccountNumber),
                'ifsc' => strtoupper(trim($this->newIfsc)),
                'branch' => trim($this->newBranch),
                'upi_id' => trim($this->newUpiId),
                'ledger_account_id' => $ledger->id,
                'is_active' => $this->newIsActive,
            ]);

            AuditLog::log('BANK_ACCOUNT_CREATED', 'BANK_ACCOUNT', $bankAccount->id, [
                'bank_name' => $bankAccount->bank_name,
                'account_number' => $bankAccount->account_number,
                'ledger_id' => $ledger->id,
            ]);
        });

        $this->showCreateModal = false;
        session()->flash('success', "Bank account '{$this->newBankName}' created and mapped to General Ledger.");
    }

    public function openEditModal(int $id): void
    {
        $account = BankAccount::findOrFail($id);
        $this->editBankAccountId = $account->id;
        $this->editBankName = $account->bank_name;
        $this->editAccountHolder = $account->account_holder;
        $this->editAccountNumber = $account->account_number;
        $this->editIfsc = $account->ifsc;
        $this->editBranch = $account->branch ?? '';
        $this->editUpiId = $account->upi_id ?? '';
        $this->editIsActive = (bool) $account->is_active;
        $this->showEditModal = true;
    }

    public function updateBankAccount(): void
    {
        $this->validate([
            'editBankName' => 'required|string|min:2|max:100',
            'editAccountHolder' => 'required|string|min:2|max:150',
            'editAccountNumber' => "required|string|min:6|max:30|unique:bank_accounts,account_number,{$this->editBankAccountId}",
            'editIfsc' => 'required|string|min:4|max:15',
            'editBranch' => 'nullable|string|max:100',
            'editUpiId' => 'nullable|string|max:100',
        ]);

        $account = BankAccount::findOrFail($this->editBankAccountId);
        $account->update([
            'bank_name' => trim($this->editBankName),
            'account_holder' => trim($this->editAccountHolder),
            'account_number' => trim($this->editAccountNumber),
            'ifsc' => strtoupper(trim($this->editIfsc)),
            'branch' => trim($this->editBranch),
            'upi_id' => trim($this->editUpiId),
            'is_active' => $this->editIsActive,
        ]);

        AuditLog::log('BANK_ACCOUNT_UPDATED', 'BANK_ACCOUNT', $account->id, [
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
        ]);

        $this->showEditModal = false;
        session()->flash('success', "Bank account '{$account->bank_name}' updated successfully.");
    }

    public function toggleStatus(int $id): void
    {
        $account = BankAccount::findOrFail($id);
        $account->update(['is_active' => !$account->is_active]);

        $statusStr = $account->is_active ? 'activated' : 'deactivated';
        session()->flash('success', "Bank account '{$account->bank_name}' has been {$statusStr}.");
    }

    public function render()
    {
        $query = BankAccount::with('ledgerAccount')->orderBy('bank_name');

        if (!empty($this->search)) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('bank_name', 'LIKE', "%{$s}%")
                    ->orWhere('account_number', 'LIKE', "%{$s}%")
                    ->orWhere('upi_id', 'LIKE', "%{$s}%")
                    ->orWhere('ifsc', 'LIKE', "%{$s}%");
            });
        }

        $accounts = $query->get();

        return view('livewire.settings.bank-account-manager', [
            'accounts' => $accounts,
        ])->layout('components.layouts.app', ['title' => 'Bank Accounts']);
    }
}
