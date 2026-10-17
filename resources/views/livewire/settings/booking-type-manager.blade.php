<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Booking Types</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage clinic appointment categories, doctor availability per type, and public booking toggles</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="openCreateModal" id="btn_add_booking_type"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Booking Type</span>
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="p-3.5 bg-red-50 border border-red-200 rounded-xl text-xs text-red-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Booking Types Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Display Order</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Code</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Mode</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-600">Public</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-600">Active</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-600">System</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($bookingTypes as $bt)
                    <tr class="hover:bg-slate-50 transition" wire:key="bt-{{ $bt->id }}">
                        <td class="px-4 py-3 text-slate-500 font-mono">{{ $bt->display_order }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $bt->name }}</td>
                        <td class="px-4 py-3">
                            <code class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[10px]">{{ $bt->code }}</code>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $modeLabels = [
                                    'CLINIC_PHYSICAL' => ['label' => 'Clinic', 'class' => 'bg-blue-100 text-blue-700'],
                                    'ONLINE'          => ['label' => 'Online', 'class' => 'bg-violet-100 text-violet-700'],
                                    'VISITING_CONSULTANCY' => ['label' => 'Visiting', 'class' => 'bg-amber-100 text-amber-700'],
                                ];
                                $m = $modeLabels[$bt->consultation_mode] ?? ['label' => $bt->consultation_mode, 'class' => 'bg-slate-100 text-slate-700'];
                            @endphp
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $m['class'] }}">{{ $m['label'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($bt->is_public_bookable)
                                <span class="text-emerald-600 font-bold">✓</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($bt->is_active)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Active</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($bt->is_system)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700">System</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <button wire:click="openEditModal({{ $bt->id }})"
                                        id="btn_edit_bt_{{ $bt->id }}"
                                        class="px-2.5 py-1 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                    Edit
                                </button>
                                @foreach($doctors as $doc)
                                    <button wire:click="openDoctorAvailabilityModal({{ $doc->id }})"
                                            id="btn_doctor_avail_{{ $doc->id }}_{{ $bt->id }}"
                                            class="px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition hidden">
                                    </button>
                                @endforeach
                                @if(!$bt->is_system)
                                    <button wire:click="deleteBookingType({{ $bt->id }})"
                                            wire:confirm="Delete '{{ addslashes($bt->name) }}'? This is permanent."
                                            id="btn_delete_bt_{{ $bt->id }}"
                                            class="px-2.5 py-1 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition">
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400 text-xs">No booking types found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Doctor Availability Matrix -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-700">Doctor Availability per Booking Type</h2>
            <p class="text-xs text-slate-400 mt-0.5">Click a doctor name to configure which booking types they accept.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold text-slate-600">Doctor</th>
                        @foreach($bookingTypes->where('is_active', true) as $bt)
                            <th class="px-3 py-2.5 text-center font-semibold text-slate-600 whitespace-nowrap">{{ $bt->name }}</th>
                        @endforeach
                        <th class="px-4 py-2.5 text-left font-semibold text-slate-600">Manage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($doctors as $doctor)
                        <tr class="hover:bg-slate-50 transition" wire:key="doc-avail-{{ $doctor->id }}">
                            <td class="px-4 py-2.5 font-semibold text-slate-800 whitespace-nowrap">{{ $doctor->display_name }}</td>
                            @foreach($bookingTypes->where('is_active', true) as $bt)
                                @php
                                    $avail = $bt->doctorAvailabilities->firstWhere('doctor_id', $doctor->id);
                                    $enabled = $avail?->is_enabled ?? false;
                                @endphp
                                <td class="px-3 py-2.5 text-center">
                                    @if($enabled)
                                        <span class="inline-flex items-center justify-center w-5 h-5 bg-emerald-100 rounded-full">
                                            <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center justify-center w-5 h-5 bg-slate-100 rounded-full">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-2.5">
                                <button wire:click="openDoctorAvailabilityModal({{ $doctor->id }})"
                                        id="btn_manage_doctor_avail_{{ $doctor->id }}"
                                        class="px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                    Manage
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Booking Type Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-base font-bold text-slate-800 mb-4">Add Custom Booking Type</h3>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Machine Code <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="newCode" id="input_new_bt_code"
                               placeholder="e.g. HOME_VISIT"
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl uppercase @error('newCode') border-red-400 @enderror">
                        @error('newCode') <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                        <p class="text-[10px] text-slate-400 mt-0.5">Uppercase letters, digits, underscores. Cannot match system codes.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Display Name <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="newName" id="input_new_bt_name"
                               placeholder="e.g. Home Visit"
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl @error('newName') border-red-400 @enderror">
                        @error('newName') <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Consultation Mode <span class="text-red-500">*</span></label>
                        <select wire:model="newConsultationMode" id="select_new_bt_mode"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl">
                            <option value="CLINIC_PHYSICAL">Clinic Physical</option>
                            <option value="ONLINE">Online</option>
                            <option value="VISITING_CONSULTANCY">Visiting Consultancy</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Display Order</label>
                            <input type="number" wire:model="newDisplayOrder" id="input_new_bt_order"
                                   min="1" max="999"
                                   class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl">
                        </div>
                        <div class="flex items-center gap-2 mt-4">
                            <input type="checkbox" wire:model="newIsPublicBookable" id="chk_new_bt_public" class="rounded">
                            <label for="chk_new_bt_public" class="text-xs font-semibold text-slate-600">Public Bookable (PWA)</label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showCreateModal', false)" id="btn_cancel_create_bt"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                        Cancel
                    </button>
                    <button wire:click="createBookingType" id="btn_confirm_create_bt"
                            class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition">
                        Create
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Edit Booking Type Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-base font-bold text-slate-800 mb-4">Edit Booking Type</h3>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Display Name <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="editName" id="input_edit_bt_name"
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl @error('editName') border-red-400 @enderror">
                        @error('editName') <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Consultation Mode</label>
                        <select wire:model="editConsultationMode" id="select_edit_bt_mode"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl">
                            <option value="CLINIC_PHYSICAL">Clinic Physical</option>
                            <option value="ONLINE">Online</option>
                            <option value="VISITING_CONSULTANCY">Visiting Consultancy</option>
                        </select>
                        @php $editBt = $editingId ? App\Models\BookingType::find($editingId) : null; @endphp
                        @if($editBt?->is_system)
                            <p class="text-[10px] text-amber-600 mt-0.5">⚠ System types cannot change their consultation mode.</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Display Order</label>
                            <input type="number" wire:model="editDisplayOrder" id="input_edit_bt_order"
                                   min="1" max="999"
                                   class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl">
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="editIsPublicBookable" id="chk_edit_bt_public" class="rounded">
                            <span class="text-xs font-semibold text-slate-600">Public Bookable (PWA)</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="editIsActive" id="chk_edit_bt_active" class="rounded">
                            <span class="text-xs font-semibold text-slate-600">Active</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showEditModal', false)" id="btn_cancel_edit_bt"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                        Cancel
                    </button>
                    <button wire:click="saveEdit" id="btn_confirm_edit_bt"
                            class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Doctor Availability Modal -->
    @if($showDoctorAvailabilityModal && $selectedDoctorId)
        @php $selectedDoc = $doctors->firstWhere('id', $selectedDoctorId); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
                <h3 class="text-base font-bold text-slate-800 mb-1">Doctor Availability</h3>
                <p class="text-xs text-slate-500 mb-4">{{ $selectedDoc?->display_name }}</p>

                <div class="space-y-2">
                    @foreach($bookingTypes->where('is_active', true) as $bt)
                        <label class="flex items-center gap-3 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                            <input type="checkbox"
                                   wire:model="doctorAvailabilityMap.{{ $bt->id }}"
                                   id="chk_avail_{{ $bt->id }}"
                                   class="rounded text-blue-600">
                            <div class="flex-1">
                                <span class="text-xs font-semibold text-slate-800">{{ $bt->name }}</span>
                                @if($bt->is_system)
                                    <span class="ml-1 text-[10px] text-orange-600">(system)</span>
                                @endif
                                <p class="text-[10px] text-slate-400 mt-0.5">
                                    {{ match($bt->consultation_mode) { 'ONLINE' => 'Online', 'VISITING_CONSULTANCY' => 'Visiting', default => 'Clinic Physical' } }}
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showDoctorAvailabilityModal', false)" id="btn_cancel_doctor_avail"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                        Cancel
                    </button>
                    <button wire:click="saveDoctorAvailability" id="btn_confirm_doctor_avail"
                            class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition">
                        Save Availability
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
