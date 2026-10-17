<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Bank Accounts</h1>
            <p class="text-xs text-slate-500 mt-0.5">Configure clinic bank accounts, UPI identifiers, and inspect authoritative double-entry Book Bank Balances (Revision 24 Section 6, 23)</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="openCreateModal" id="btn_add_bank_account" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Bank Account</span>
            </button>
        </div>
    </div>

    <!-- Notice Banner -->
    <div class="p-3.5 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-start gap-2.5">
        <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <div>
            <span class="font-bold">Book Bank Balance Notice (Section 23):</span>
            <span class="text-amber-800">The balance displayed for each account is the internal <strong>Book Bank Balance</strong> derived authoritatively from double-entry accounting journals (Debits - Credits). It is distinct from live physical bank statement balances until manual reconciliation is performed.</span>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Search -->
    <div class="flex items-center gap-3 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
        <div class="relative flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by bank name, account number, or UPI ID..." class="w-full pl-9 pr-3 py-2 text-xs border border-slate-300 rounded-xl">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
    </div>

    <!-- Bank Accounts Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider text-[10px] font-bold">
                    <th class="py-3 px-4">Bank Details</th>
                    <th class="py-3 px-4">Account Number & IFSC</th>
                    <th class="py-3 px-4">UPI Identifier</th>
                    <th class="py-3 px-4">Mapped Ledger Account</th>
                    <th class="py-3 px-4 text-right">Book Bank Balance (₹)</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @forelse($accounts as $acc)
                    <tr class="hover:bg-slate-50/70 transition" wire:key="bank-{{ $acc->id }}">
                        <td class="py-3 px-4">
                            <div class="font-bold text-slate-900 text-sm">{{ $acc->bank_name }}</div>
                            <div class="text-[11px] text-slate-500 font-medium">{{ $acc->account_holder }} @if($acc->branch)&bull; {{ $acc->branch }}@endif</div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-mono font-bold text-slate-900 text-xs tracking-wider">{{ $acc->account_number }}</div>
                            <div class="text-[10px] font-mono text-slate-500">IFSC: {{ $acc->ifsc }}</div>
                        </td>
                        <td class="py-3 px-4 font-mono font-bold text-xs text-blue-700">
                            {{ $acc->upi_id ?: '—' }}
                        </td>
                        <td class="py-3 px-4 font-mono text-[11px]">
                            @if($acc->ledgerAccount)
                                <span class="bg-slate-100 text-slate-800 px-2 py-0.5 rounded font-bold">
                                    {{ $acc->ledgerAccount->code }} &bull; {{ $acc->ledgerAccount->name }}
                                </span>
                            @else
                                <span class="text-rose-600 font-bold">Unassigned</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right font-mono font-black text-slate-900 text-sm">
                            ₹ {{ number_format($acc->book_balance, 2) }}
                        </td>
                        <td class="py-3 px-4">
                            <button type="button" wire:click="toggleStatus({{ $acc->id }})" class="cursor-pointer">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase transition {{ $acc->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                    {{ $acc->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                            <button type="button" wire:click="openEditModal({{ $acc->id }})" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-[11px]">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                            No bank accounts found. Click "Add Bank Account" to configure one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Create Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Add Clinic Bank Account</h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="createBankAccount" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bank Name *</label>
                        <input type="text" wire:model="newBankName" placeholder="e.g., State Bank of India, HDFC Bank" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                        @error('newBankName') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Holder Name *</label>
                        <input type="text" wire:model="newAccountHolder" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                        @error('newAccountHolder') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Number * (Unique)</label>
                        <input type="text" wire:model="newAccountNumber" placeholder="Account Number" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                        @error('newAccountNumber') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">IFSC Code *</label>
                            <input type="text" wire:model="newIfsc" placeholder="e.g., SBIN0001234" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono uppercase">
                            @error('newIfsc') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Branch</label>
                            <input type="text" wire:model="newBranch" placeholder="Branch Name" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                            @error('newBranch') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">UPI ID (VPA)</label>
                        <input type="text" wire:model="newUpiId" placeholder="e.g., clinic@sbi" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                        @error('newUpiId') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-2 mt-2 cursor-pointer font-bold text-slate-700">
                            <input type="checkbox" wire:model="newIsActive" class="rounded border-slate-300 text-blue-600">
                            <span>Active Account for UPI / Settlement</span>
                        </label>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Create Bank Account</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Edit Clinic Bank Account</h3>
                    <button type="button" wire:click="$set('showEditModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="updateBankAccount" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bank Name *</label>
                        <input type="text" wire:model="editBankName" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                        @error('editBankName') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Holder Name *</label>
                        <input type="text" wire:model="editAccountHolder" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                        @error('editAccountHolder') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Number * (Unique)</label>
                        <input type="text" wire:model="editAccountNumber" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                        @error('editAccountNumber') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">IFSC Code *</label>
                            <input type="text" wire:model="editIfsc" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono uppercase">
                            @error('editIfsc') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Branch</label>
                            <input type="text" wire:model="editBranch" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                            @error('editBranch') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">UPI ID (VPA)</label>
                        <input type="text" wire:model="editUpiId" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                        @error('editUpiId') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-2 mt-2 cursor-pointer font-bold text-slate-700">
                            <input type="checkbox" wire:model="editIsActive" class="rounded border-slate-300 text-blue-600">
                            <span>Active Account</span>
                        </label>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2 border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Update Bank Account</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
