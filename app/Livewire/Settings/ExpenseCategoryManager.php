<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;
use Livewire\Component;

class ExpenseCategoryManager extends Component
{
    public string $search = '';
    public string $statusFilter = 'ALL';

    // Create Modal
    public bool $showCreateModal = false;
    public string $newName = '';
    public string $newCode = '';
    public ?int $newLedgerAccountId = null;
    public int $newDisplayOrder = 0;
    public string $newDescription = '';
    public bool $newIsActive = true;

    // Edit Modal
    public bool $showEditModal = false;
    public ?int $editCategoryId = null;
    public string $editName = '';
    public string $editCode = '';
    public ?int $editLedgerAccountId = null;
    public int $editDisplayOrder = 0;
    public string $editDescription = '';
    public bool $editIsActive = true;

    public function openCreateModal(): void
    {
        $this->reset(['newName', 'newCode', 'newLedgerAccountId', 'newDisplayOrder', 'newDescription', 'newIsActive']);
        $this->newIsActive = true;
        $defaultLedger = LedgerAccount::where('type', 'EXPENSE')->first();
        if ($defaultLedger) {
            $this->newLedgerAccountId = $defaultLedger->id;
        }
        $this->showCreateModal = true;
    }

    public function createCategory(): void
    {
        $this->validate([
            'newName' => 'required|string|min:2|max:100',
            'newCode' => 'required|string|max:50|unique:expense_categories,code',
            'newLedgerAccountId' => 'required|exists:ledger_accounts,id',
            'newDisplayOrder' => 'required|integer|min:0',
            'newDescription' => 'nullable|string|max:255',
        ]);

        $category = ExpenseCategory::create([
            'name' => trim($this->newName),
            'code' => strtoupper(trim($this->newCode)),
            'ledger_account_id' => $this->newLedgerAccountId,
            'display_order' => $this->newDisplayOrder,
            'description' => trim($this->newDescription),
            'is_active' => $this->newIsActive,
        ]);

        AuditLog::log('EXPENSE_CATEGORY_CREATED', 'EXPENSE_CATEGORY', $category->id, ['name' => $category->name, 'code' => $category->code]);

        $this->showCreateModal = false;
        session()->flash('success', "Expense Category '{$category->name}' created successfully.");
    }

    public function openEditModal(int $id): void
    {
        $category = ExpenseCategory::findOrFail($id);
        $this->editCategoryId = $category->id;
        $this->editName = $category->name;
        $this->editCode = $category->code;
        $this->editLedgerAccountId = $category->ledger_account_id;
        $this->editDisplayOrder = $category->display_order ?? 0;
        $this->editDescription = $category->description ?? '';
        $this->editIsActive = (bool) $category->is_active;
        $this->showEditModal = true;
    }

    public function updateCategory(): void
    {
        $this->validate([
            'editName' => 'required|string|min:2|max:100',
            'editCode' => "required|string|max:50|unique:expense_categories,code,{$this->editCategoryId}",
            'editLedgerAccountId' => 'required|exists:ledger_accounts,id',
            'editDisplayOrder' => 'required|integer|min:0',
            'editDescription' => 'nullable|string|max:255',
        ]);

        $category = ExpenseCategory::findOrFail($this->editCategoryId);
        $category->update([
            'name' => trim($this->editName),
            'code' => strtoupper(trim($this->editCode)),
            'ledger_account_id' => $this->editLedgerAccountId,
            'display_order' => $this->editDisplayOrder,
            'description' => trim($this->editDescription),
            'is_active' => $this->editIsActive,
        ]);

        AuditLog::log('EXPENSE_CATEGORY_UPDATED', 'EXPENSE_CATEGORY', $category->id, ['name' => $category->name]);

        $this->showEditModal = false;
        session()->flash('success', "Expense Category '{$category->name}' updated successfully.");
    }

    public function toggleStatus(int $id): void
    {
        $category = ExpenseCategory::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);

        $statusStr = $category->is_active ? 'activated' : 'deactivated';
        session()->flash('success', "Category '{$category->name}' has been {$statusStr}.");
    }

    public function deleteCategory(int $id): void
    {
        $category = ExpenseCategory::withCount('expenses')->findOrFail($id);

        if ($category->expenses_count > 0) {
            session()->flash('error', "Cannot delete category '{$category->name}' because it has {$category->expenses_count} historical expense records. Deactivate it instead to preserve financial audit reports.");
            return;
        }

        $name = $category->name;
        $category->delete();

        AuditLog::log('EXPENSE_CATEGORY_DELETED', 'EXPENSE_CATEGORY', $id, ['name' => $name]);

        session()->flash('success', "Expense category '{$name}' deleted.");
    }

    public function render()
    {
        $query = ExpenseCategory::with(['ledgerAccount'])->withCount('expenses')
            ->orderBy('display_order')
            ->orderBy('name');

        if ($this->statusFilter === 'ACTIVE') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'INACTIVE') {
            $query->where('is_active', false);
        }

        if (!empty($this->search)) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                    ->orWhere('code', 'LIKE', "%{$s}%")
                    ->orWhere('description', 'LIKE', "%{$s}%");
            });
        }

        $categories = $query->get();
        $expenseLedgers = LedgerAccount::where('type', 'EXPENSE')->orderBy('code')->get();

        return view('livewire.settings.expense-category-manager', [
            'categories' => $categories,
            'expenseLedgers' => $expenseLedgers,
        ])->layout('components.layouts.app', ['title' => 'Expense Category Master']);
    }
}
