<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Fee Master</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage doctor-specific and clinic-wide consultation tariffs, diagnostic rates, and active states</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="openCreateModal" id="btn_add_fee" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add Tariff / Fee</span>
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

    <!-- Search & Filter Controls -->
    <div class="flex flex-col sm:flex-row items-center gap-3 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
        <div class="relative flex-1 w-full">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by fee name or code..." class="w-full pl-9 pr-3 py-2 text-xs border border-slate-300 rounded-xl">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>

        <div class="w-full sm:w-64">
            <select wire:model.live="selectedDoctorFilter" id="filter_doctor_scope" class="w-full p-2 text-xs border border-slate-300 rounded-xl bg-white text-slate-700 font-semibold">
                <option value="ALL">All Scopes (Clinic & Doctors)</option>
                <option value="GLOBAL">🏥 Clinic-wide Defaults Only</option>
                @foreach($doctors as $doc)
                    <option value="{{ $doc->id }}">👨‍⚕️ {{ $doc->display_name }} (Doctor-Specific)</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Doctor-Specific Consultancy Fee Configuration Matrix (Section 18) -->
    <div class="bg-white rounded-2xl border-2 border-indigo-200 shadow-sm p-5 space-y-4" id="doctor_fee_matrix_card">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-indigo-100 gap-2">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-1.5 bg-indigo-100 text-indigo-700 rounded-lg text-xs font-bold">Revision 45</span>
                    <h2 class="text-base font-black text-slate-900">Doctor Tariff Matrix</h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Select a doctor to configure tariffs for booking types mapped to that doctor. Unmapped types are excluded.</p>
            </div>
            <div class="w-full sm:w-80">
                <select wire:model.live="selectedDoctorFilter" id="select_tariff_doctor" class="w-full p-2.5 text-xs border border-indigo-300 rounded-xl bg-white text-slate-800 font-bold focus:ring-indigo-500 shadow-sm">
                    <option value="">-- Choose Doctor to Configure --</option>
                    @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}">👨‍⚕️ {{ $doc->display_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($selectedDoctor)
            @if(empty($doctorMatrix))
                <div class="p-6 text-center text-slate-400 text-xs bg-slate-50 rounded-xl border border-dashed border-slate-200">
                    No booking types are currently enabled for {{ $selectedDoctor->display_name }}.<br>
                    <a href="{{ route('settings.booking-types') }}" class="text-blue-600 underline font-semibold mt-1 inline-block">Manage Doctor Availability Matrix &rarr;</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-indigo-50/60 border-b border-indigo-100 text-indigo-900 uppercase tracking-wider text-[10px] font-bold">
                                <th class="py-2.5 px-3">Consultancy Type</th>
                                <th class="py-2.5 px-3">Mode</th>
                                <th class="py-2.5 px-3 w-40">Tariff Rate (₹)</th>
                                <th class="py-2.5 px-3">Configuration Status</th>
                                <th class="py-2.5 px-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-indigo-50 text-slate-700">
                            @foreach($doctorMatrix as $item)
                                <tr class="hover:bg-indigo-50/30 transition" wire:key="doc-matrix-{{ $item['booking_type_id'] }}">
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-slate-900 text-xs">{{ $item['name'] }}</div>
                                        <div class="text-[10px] font-mono text-slate-400">{{ $item['code'] }}</div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600">
                                            {{ $item['consultation_mode'] }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="relative">
                                            <span class="absolute left-2.5 top-1.5 text-xs text-slate-400 font-bold">₹</span>
                                            <input type="number" step="0.01" min="0" wire:model="doctorFeeInputs.{{ $item['booking_type_id'] }}"
                                                   id="input_doc_fee_{{ $item['booking_type_id'] }}"
                                                   placeholder="0.00"
                                                   class="w-full pl-6 pr-2 py-1 text-xs border border-slate-300 rounded-lg font-mono font-bold text-slate-900 focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        @error("doctorFeeInputs.{$item['booking_type_id']}")
                                            <span class="text-[10px] text-red-500 block mt-0.5">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td class="py-2.5 px-3">
                                        @if($item['is_fee_configured'])
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                ✓ Configured (₹{{ number_format($item['fee_amount'], 2) }})
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                                ⚠ Not Configured
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <button type="button" wire:click="saveDoctorFee({{ $item['booking_type_id'] }})"
                                                id="btn_save_doc_fee_{{ $item['booking_type_id'] }}"
                                                class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs transition">
                                            Save Fee
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            <div class="p-6 text-center text-slate-400 text-xs bg-indigo-50/30 rounded-xl border border-dashed border-indigo-200">
                Please select an attending doctor above to view and configure their doctor-specific booking type tariffs.
            </div>
        @endif
    </div>

    <!-- Fee Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider text-[10px] font-bold">
                    <th class="py-3 px-4">Fee Name & Code</th>
                    <th class="py-3 px-4">Scope / Doctor Ownership</th>
                    <th class="py-3 px-4">Standard Rate (₹)</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @forelse($fees as $fee)
                    <tr class="hover:bg-slate-50/70 transition" wire:key="fee-{{ $fee->id }}">
                        <td class="py-3 px-4">
                            <div class="font-bold text-slate-900 text-sm">{{ $fee->name }}</div>
                            <div class="text-[10px] font-mono text-slate-500 uppercase">{{ $fee->code }}</div>
                        </td>
                        <td class="py-3 px-4">
                            @if($fee->doctor)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    👨‍⚕️ {{ $fee->doctor->display_name }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                    🏥 Clinic Default
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <span class="font-mono font-black text-slate-900 text-sm">₹ {{ number_format($fee->amount, 2) }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <button type="button" wire:click="toggleStatus({{ $fee->id }})" class="cursor-pointer">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase transition {{ $fee->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                    {{ $fee->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                            <button type="button" wire:click="openEditModal({{ $fee->id }})" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-[11px]">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-slate-400 text-xs">
                            No service fees found matching the current filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Create Fee Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Add Service Tariff</h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="createFee" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Doctor Ownership / Scope *</label>
                        <select wire:model="newDoctorId" class="w-full py-2 px-3 border border-slate-300 rounded-xl bg-white text-slate-700 font-medium">
                            <option value="">🏥 Clinic-wide Default (All Doctors)</option>
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}">👨‍⚕️ {{ $doc->display_name }} (Specific Doctor Fee)</option>
                            @endforeach
                        </select>
                        <span class="text-[10px] text-slate-400 mt-0.5 block">Doctor-specific fees take precedence over clinic defaults.</span>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Fee Name *</label>
                        <input type="text" wire:model="newName" placeholder="e.g., Clinic Consultancy, Online Consultancy" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                        @error('newName') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Fee Code *</label>
                        <input type="text" wire:model="newCode" placeholder="e.g., CONSULTATION_PHYSICAL" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono uppercase">
                        @error('newCode') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Standard Rate (₹) *</label>
                        <input type="number" step="0.01" wire:model="newAmount" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono font-bold">
                        @error('newAmount') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" wire:model="newIsActive" id="newIsActive" class="rounded text-blue-600">
                        <label for="newIsActive" class="font-bold text-slate-700">Active Tariff</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl font-bold">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Save Tariff</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Fee Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Edit Service Tariff</h3>
                    <button type="button" wire:click="$set('showEditModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="updateFee" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Doctor Ownership / Scope *</label>
                        <select wire:model="editDoctorId" class="w-full py-2 px-3 border border-slate-300 rounded-xl bg-white text-slate-700 font-medium">
                            <option value="">🏥 Clinic-wide Default (All Doctors)</option>
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}">👨‍⚕️ {{ $doc->display_name }} (Specific Doctor Fee)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Fee Name *</label>
                        <input type="text" wire:model="editName" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-medium">
                        @error('editName') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Fee Code *</label>
                        <input type="text" wire:model="editCode" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono uppercase">
                        @error('editCode') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Standard Rate (₹) *</label>
                        <input type="number" step="0.01" wire:model="editAmount" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono font-bold">
                        @error('editAmount') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" wire:model="editIsActive" id="editIsActive" class="rounded text-blue-600">
                        <label for="editIsActive" class="font-bold text-slate-700">Active Tariff</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl font-bold">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Update Tariff</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
