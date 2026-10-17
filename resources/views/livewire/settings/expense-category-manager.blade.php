<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Expense Category Master</h1>
            <p class="text-xs text-slate-500 mt-0.5">Define and manage operational expense categories mapped to authoritative double-entry general ledger accounts (Revision 24 Section 12-13)</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="openCreateModal" id="btn_add_expense_category" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Expense Category</span>
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Search & Filters -->
    <div class="flex flex-col sm:flex-row items-center gap-3 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
        <div class="relative flex-1 w-full">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by category name, code, or description..." class="w-full pl-9 pr-3 py-2 text-xs border border-slate-300 rounded-xl">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <div class="w-full sm:w-48">
            <select wire:model.live="statusFilter" class="w-full py-2 px-3 text-xs border border-slate-300 rounded-xl bg-white font-medium">
                <option value="ALL">All Categories</option>
                <option value="ACTIVE">Active Only</option>
                <option value="INACTIVE">Inactive Only</option>
            </select>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider text-[10px] font-bold">
                    <th class="py-3 px-4">Order</th>
                    <th class="py-3 px-4">Category Name & Code</th>
                    <th class="py-3 px-4">Ledger Account (GL)</th>
                    <th class="py-3 px-4">Description</th>
                    <th class="py-3 px-4">Expenses Count</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @forelse($categories as $category)
                    <tr class="hover:bg-slate-50/70 transition" wire:key="category-{{ $category->id }}">
                        <td class="py-3 px-4 font-mono font-bold text-slate-500">
                            #{{ $category->display_order }}
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-bold text-slate-900 text-sm">{{ $category->name }}</div>
                            <div class="text-[10px] font-mono text-slate-500 uppercase tracking-wider">{{ $category->code }}</div>
                        </td>
                        <td class="py-3 px-4 font-mono text-[11px]">
                            @if($category->ledgerAccount)
                                <span class="bg-slate-100 text-slate-800 px-2 py-0.5 rounded font-bold">
                                    {{ $category->ledgerAccount->code }} &bull; {{ $category->ledgerAccount->name }}
                                </span>
                            @else
                                <span class="text-rose-600 font-bold">Unassigned</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-slate-500 text-[11px] max-w-xs truncate">
                            {{ $category->description ?: '—' }}
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $category->expenses_count > 0 ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-500' }}">
                                {{ $category->expenses_count }} {{ Str::plural('expense', $category->expenses_count) }}
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <button type="button" wire:click="toggleStatus({{ $category->id }})" class="cursor-pointer">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase transition {{ $category->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                            <button type="button" wire:click="openEditModal({{ $category->id }})" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-[11px]">
                                Edit
                            </button>
                            @if($category->expenses_count === 0)
                                <button type="button" wire:click="deleteCategory({{ $category->id }})" onclick="return confirm('Delete unused expense category \'{{ addslashes($category->name) }}\'?') || event.stopImmediatePropagation()" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-lg text-[11px]">
                                    Delete
                                </button>
                            @else
                                <button type="button" disabled title="Cannot delete category with historical expenses. Deactivate it instead." class="px-2.5 py-1 bg-slate-50 text-slate-300 font-medium rounded-lg text-[11px] cursor-not-allowed">
                                    Locked
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                            No expense categories found. Click "Add Expense Category" to configure one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Create Category Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Add Expense Category</h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="createCategory" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Category Name *</label>
                        <input type="text" wire:model="newName" placeholder="e.g., Clinic Supplies, Rent, Internet" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                        @error('newName') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Category Code * (Unique)</label>
                        <input type="text" wire:model="newCode" placeholder="e.g., EXP_SUPPLIES, EXP_RENT" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono uppercase">
                        @error('newCode') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Double-Entry Ledger Account (GL) *</label>
                        <select wire:model="newLedgerAccountId" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                            <option value="">Select Expense Ledger Account</option>
                            @foreach($expenseLedgers as $ledger)
                                <option value="{{ $ledger->id }}">{{ $ledger->code }} - {{ $ledger->name }}</option>
                            @endforeach
                        </select>
                        @error('newLedgerAccountId') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Display Order</label>
                            <input type="number" wire:model="newDisplayOrder" min="0" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                            @error('newDisplayOrder') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Status</label>
                            <label class="flex items-center gap-2 mt-2 cursor-pointer font-bold text-slate-700">
                                <input type="checkbox" wire:model="newIsActive" class="rounded border-slate-300 text-blue-600">
                                <span>Active Category</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Description / Notes</label>
                        <textarea wire:model="newDescription" rows="2" placeholder="Optional notes about what expenses qualify for this category" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium"></textarea>
                        @error('newDescription') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Create Category</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Category Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Edit Expense Category</h3>
                    <button type="button" wire:click="$set('showEditModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="updateCategory" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Category Name *</label>
                        <input type="text" wire:model="editName" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                        @error('editName') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Category Code * (Unique)</label>
                        <input type="text" wire:model="editCode" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono uppercase">
                        @error('editCode') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Double-Entry Ledger Account (GL) *</label>
                        <select wire:model="editLedgerAccountId" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                            <option value="">Select Expense Ledger Account</option>
                            @foreach($expenseLedgers as $ledger)
                                <option value="{{ $ledger->id }}">{{ $ledger->code }} - {{ $ledger->name }}</option>
                            @endforeach
                        </select>
                        @error('editLedgerAccountId') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Display Order</label>
                            <input type="number" wire:model="editDisplayOrder" min="0" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                            @error('editDisplayOrder') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Status</label>
                            <label class="flex items-center gap-2 mt-2 cursor-pointer font-bold text-slate-700">
                                <input type="checkbox" wire:model="editIsActive" class="rounded border-slate-300 text-blue-600">
                                <span>Active Category</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Description / Notes</label>
                        <textarea wire:model="editDescription" rows="2" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium"></textarea>
                        @error('editDescription') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2 border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
