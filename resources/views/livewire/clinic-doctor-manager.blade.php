<div class="space-y-8">
    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Clinic & Doctor Management</h1>
            <p class="text-sm text-slate-500 mt-1">Manage clinic profile, doctor accounts, active statuses, and default booking doctor (Revision 24)</p>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- CLINIC INFORMATION CARD --}}
    {{-- ================================================================== --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-sky-50 border-b border-blue-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Clinic Information</h2>
                    <span class="text-xs text-slate-500">Legal entity name and operational contact details</span>
                </div>
            </div>
            @if(!$clinicEditMode)
                <button wire:click="enableClinicEdit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-blue-700 bg-white border border-blue-200 rounded-lg hover:bg-blue-50 transition shadow-xs" id="btn_edit_clinic">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Clinic
                </button>
            @endif
        </div>

        <div class="p-6">
            @if($clinicEditMode)
                <form wire:submit="saveClinic" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Clinic Name *</label>
                            <input type="text" wire:model="clinicName" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="clinic_name_input">
                            @error('clinicName') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Address</label>
                            <input type="text" wire:model="clinicAddress" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="clinic_address_input">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">City</label>
                            <input type="text" wire:model="clinicCity" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="clinic_city_input">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Phone</label>
                            <input type="tel" wire:model="clinicPhone" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="clinic_phone_input">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Email</label>
                            <input type="email" wire:model="clinicEmail" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="clinic_email_input">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Default Booking Doctor</label>
                            <select wire:model="defaultDoctorId" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                                <option value="">-- No Default (Auto-select single doctor or force selection) --</option>
                                @foreach($doctors as $d)
                                    @if($d['is_active'])
                                        <option value="{{ $d['id'] }}">{{ \App\Services\DoctorNamePresenter::format($d['name']) }} ({{ $d['qualification'] }})</option>
                                    @endif
                                @endforeach
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">Preselected for patient PWA and desk walk-in booking when multiple doctors exist.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Max Advance Booking Window (Days)</label>
                            <div class="flex items-center gap-3">
                                <input type="number" min="0" max="90" wire:model="maxAdvanceBookingDays" class="w-24 px-3 py-2 border border-slate-200 rounded-lg text-sm font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="clinic_max_advance_days">
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" wire:click="$set('maxAdvanceBookingDays', 0)" class="px-2.5 py-1 text-xs font-semibold rounded border {{ $maxAdvanceBookingDays === 0 ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">Today Only (0)</button>
                                    <button type="button" wire:click="$set('maxAdvanceBookingDays', 1)" class="px-2.5 py-1 text-xs font-semibold rounded border {{ $maxAdvanceBookingDays === 1 ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">Today + Tomorrow (1)</button>
                                    <button type="button" wire:click="$set('maxAdvanceBookingDays', 6)" class="px-2.5 py-1 text-xs font-semibold rounded border {{ $maxAdvanceBookingDays === 6 ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">Today + 6 Days (6)</button>
                                    <button type="button" wire:click="$set('maxAdvanceBookingDays', 29)" class="px-2.5 py-1 text-xs font-semibold rounded border {{ $maxAdvanceBookingDays === 29 ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">Today + 29 Days (29)</button>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Defines how many calendar days in advance appointments can be booked across all channels.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 pt-3 border-t border-slate-200">
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700 transition shadow-sm" id="btn_save_clinic">
                            Save Changes
                        </button>
                        <button type="button" wire:click="cancelClinicEdit" class="px-4 py-2 text-sm font-bold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Clinic Name</div>
                        <div class="text-sm font-bold text-slate-900">{{ $clinicName ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Address</div>
                        <div class="text-sm text-slate-700">{{ $clinicAddress ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">City</div>
                        <div class="text-sm text-slate-700">{{ $clinicCity ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Phone</div>
                        <div class="text-sm text-slate-700 font-mono">{{ $clinicPhone ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Email</div>
                        <div class="text-sm text-slate-700">{{ $clinicEmail ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Default Booking Doctor</div>
                        <div class="text-sm font-bold text-indigo-700">
                            @php
                                $defDoc = collect($doctors)->firstWhere('id', $defaultDoctorId);
                            @endphp
                            {{ $defDoc ? \App\Services\DoctorNamePresenter::format($defDoc['name']) : 'None (Manual selection required)' }}
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Max Advance Booking Window</div>
                        <div class="text-sm font-bold text-emerald-700">
                            {{ $maxAdvanceBookingDays }} Day(s)
                            <span class="text-xs font-medium text-slate-500">
                                @if($maxAdvanceBookingDays === 0) (Today only)
                                @elseif($maxAdvanceBookingDays === 1) (Today + Tomorrow)
                                @else (Today through +{{ $maxAdvanceBookingDays }} calendar days)
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- DOCTOR MANAGEMENT SECTION --}}
    {{-- ================================================================== --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-emerald-50 to-teal-50 border-b border-emerald-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Doctor Profiles</h2>
                    <span class="text-xs text-slate-500">Configure medical consultants, active status, and default booking doctor</span>
                </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <div class="px-3 py-1 rounded-full text-xs font-bold {{ $canAddDoctor ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}" id="badge_doctor_seats">
                        Doctor Seats: {{ $activeDoctorCount }} / {{ $doctorCapacity }} used ({{ $availableDoctorSeats }} available)
                    </div>
                    @if(!$canAddDoctor)
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-rose-100 text-rose-800 border border-rose-200" id="badge_seat_limit_reached">Seat Limit Reached</span>
                    @endif
                </div>
                @if(!$showDoctorAddForm && !$doctorEditMode)
                    @if($canAddDoctor)
                        <button wire:click="showAddDoctor" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-emerald-700 bg-white border border-emerald-200 rounded-lg hover:bg-emerald-50 transition shadow-xs" id="btn_add_doctor">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add Doctor
                        </button>
                    @else
                        <a href="{{ route('subscription') }}" id="btn_buy_additional_doctor_seat" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-lg transition shadow-xs" title="Request additional doctor seat via subscription portal">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Request Additional Doctor Seat
                        </a>
                    @endif
                @endif
            </div>
        </div>

        <div class="p-6 space-y-4">
            @if(!$canAddDoctor)
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-xs flex items-center justify-between gap-2" id="seat_limit_banner">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>You have reached the maximum number of doctor seats included in your license. Request an additional doctor seat to register more doctors.</span>
                    </div>
                    <a href="{{ route('subscription') }}" class="font-bold text-indigo-700 hover:text-indigo-900 underline whitespace-nowrap ml-2">
                        Request Additional Seat &rarr;
                    </a>
                </div>
            @endif
            @error('doctorCapacity')
                <div class="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-xs flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror
            {{-- Doctor Add/Edit Form --}}
            @if($showDoctorAddForm || $doctorEditMode)
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-5">
                    <h3 class="text-sm font-bold text-slate-800 mb-4">{{ $doctorEditMode ? 'Edit Doctor Profile' : 'Add New Doctor' }}</h3>
                    <form wire:submit="saveDoctor" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Doctor Name *</label>
                                <input type="text" wire:model="doctorName" placeholder="e.g. S.K. Sharma" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white" id="doctor_name_input">
                                @error('doctorName') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Qualification *</label>
                                <input type="text" wire:model="doctorQualification" placeholder="e.g. MBBS, MD" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white" id="doctor_qualification_input">
                                @error('doctorQualification') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Registration Number *</label>
                                <input type="text" wire:model="doctorRegistration" placeholder="e.g. MCI-12345" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white" id="doctor_reg_input">
                                @error('doctorRegistration') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Specialization</label>
                                <input type="text" wire:model="doctorSpecialization" placeholder="e.g. General Medicine" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white" id="doctor_spec_input">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Contact Number</label>
                                <input type="tel" wire:model="doctorContact" placeholder="e.g. 9876543210" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white" id="doctor_contact_input">
                            </div>
                            <div class="flex items-center pt-6">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                    <input type="checkbox" wire:model="doctorIsActive" class="w-4 h-4 rounded text-emerald-600">
                                    <span>Active Doctor (Eligible for bookings)</span>
                                </label>
                            </div>
                        </div>

                        {{-- Consultation Types Availability (Revision 34) --}}
                        <div class="mt-4 pt-4 border-t border-slate-200">
                            <div class="mb-3">
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider block">Consultation Types Availability</span>
                                <span class="text-[11px] text-slate-500">Select which consultation types are available for patient booking and operator billing</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 bg-white p-2.5 rounded-lg border border-slate-200 hover:border-emerald-300 transition">
                                    <input type="checkbox" wire:model="consultationPhysical" class="w-4 h-4 rounded text-emerald-600">
                                    <span>Physical / Clinic OPD</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 bg-white p-2.5 rounded-lg border border-slate-200 hover:border-emerald-300 transition">
                                    <input type="checkbox" wire:model="consultationOnline" class="w-4 h-4 rounded text-emerald-600">
                                    <span>Online Video</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 bg-white p-2.5 rounded-lg border border-slate-200 hover:border-emerald-300 transition">
                                    <input type="checkbox" wire:model="consultationVisiting" class="w-4 h-4 rounded text-emerald-600">
                                    <span>Visiting Consultancy</span>
                                </label>
                            </div>
                        </div>

                        {{-- Doctor Schedule Configuration --}}
                        <div class="mt-4 pt-4 border-t border-slate-200">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider block">OPD Schedule Configuration</span>
                                    <span class="text-[11px] text-slate-500">Configure custom doctor OPD consultation timings or inherit clinic defaults</span>
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-3 py-1.5 rounded-lg">
                                    <input type="checkbox" wire:model.live="customSchedule" class="w-4 h-4 rounded text-indigo-600">
                                    <span>Custom Doctor Schedule</span>
                                </label>
                            </div>

                            @if($customSchedule)
                                <div class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Morning Session (Mon-Sat)</label>
                                            <div class="flex items-center gap-2">
                                                <input type="time" wire:model="morningStart" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono w-full">
                                                <span class="text-xs text-slate-400 font-bold">to</span>
                                                <input type="time" wire:model="morningEnd" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono w-full">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Evening Session (Mon-Sat)</label>
                                            <div class="flex items-center gap-2">
                                                <input type="time" wire:model="eveningStart" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono w-full">
                                                <span class="text-xs text-slate-400 font-bold">to</span>
                                                <input type="time" wire:model="eveningEnd" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono w-full">
                                            </div>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Sunday Single Session</label>
                                            <div class="flex items-center gap-2 max-w-sm">
                                                <input type="time" wire:model="sundayStart" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono w-full">
                                                <span class="text-xs text-slate-400 font-bold">to</span>
                                                <input type="time" wire:model="sundayEnd" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono w-full">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-500 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Inherits clinic-wide default schedule (Morning: 08:00–09:00, Evening: 17:00–19:00, Sunday: 09:00–11:00)</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-3 pt-3 border-t border-slate-200">
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 text-white text-sm font-bold rounded-lg hover:bg-emerald-700 transition shadow-sm" id="btn_save_doctor">
                                {{ $doctorEditMode ? 'Update Doctor' : 'Add Doctor' }}
                            </button>
                            <button type="button" wire:click="cancelDoctorEdit" class="px-4 py-2 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Doctor List --}}
            @if(count($doctors) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($doctors as $doc)
                        <div class="bg-white rounded-xl border {{ $doc['id'] === $defaultDoctorId ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-slate-200' }} p-4 hover:shadow-md transition" wire:key="doctor-{{ $doc['id'] }}">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl {{ $doc['is_active'] ? 'bg-gradient-to-br from-emerald-400 to-teal-600' : 'bg-slate-300' }} flex items-center justify-center text-white font-black text-lg shadow-sm">
                                        {{ strtoupper(substr($doc['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-slate-900">{{ \App\Services\DoctorNamePresenter::format($doc['name']) }}</span>
                                            @if($doc['id'] === $defaultDoctorId)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-indigo-100 text-indigo-800 border border-indigo-200">
                                                    Default Doctor
                                                </span>
                                            @endif
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase {{ $doc['is_active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">
                                                {{ $doc['is_active'] ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-blue-600 font-semibold">{{ $doc['qualification'] }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button wire:click="editDoctor({{ $doc['id'] }})" class="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded hover:bg-slate-200 transition" id="btn_edit_doctor_{{ $doc['id'] }}">
                                        Edit
                                    </button>
                                    <button wire:click="toggleDoctorStatus({{ $doc['id'] }})" class="text-xs font-bold {{ $doc['is_active'] ? 'text-amber-700 bg-amber-50 hover:bg-amber-100' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100' }} px-2 py-1 rounded transition">
                                        {{ $doc['is_active'] ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-400 font-bold uppercase">Reg. No.</span>
                                    <span class="block text-slate-700 font-semibold">{{ $doc['registration_number'] }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 font-bold uppercase">Specialization</span>
                                    <span class="block text-slate-700 font-semibold">{{ $doc['specialization'] ?? '—' }}</span>
                                </div>
                                @if($doc['contact_number'])
                                    <div>
                                        <span class="text-slate-400 font-bold uppercase">Contact</span>
                                        <span class="block text-slate-700 font-semibold font-mono">{{ $doc['contact_number'] }}</span>
                                    </div>
                                @endif
                                <div>
                                    <span class="text-slate-400 font-bold uppercase">Action</span>
                                    @if($doc['is_active'] && $doc['id'] !== $defaultDoctorId)
                                        <button wire:click="setDefaultDoctor({{ $doc['id'] }})" class="text-[11px] font-bold text-indigo-600 hover:underline">
                                            Make Default Doctor &rarr;
                                        </button>
                                    @elseif($doc['id'] === $defaultDoctorId)
                                        <span class="text-[11px] text-indigo-600 font-bold">Current Default</span>
                                    @else
                                        <span class="text-[11px] text-slate-400">Inactive</span>
                                    @endif
                                </div>
                                <div class="col-span-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-400 font-bold uppercase">Schedule</span>
                                    @if(!empty($doc['schedule_config']))
                                        <span class="font-medium text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                            Custom: Morn {{ $doc['schedule_config']['monday']['morning'][0] ?? '08:00' }}–{{ $doc['schedule_config']['monday']['morning'][1] ?? '09:00' }}, Eve {{ $doc['schedule_config']['monday']['evening'][0] ?? '17:00' }}–{{ $doc['schedule_config']['monday']['evening'][1] ?? '19:00' }}
                                        </span>
                                    @else
                                        <span class="text-slate-500 font-medium">Clinic Default</span>
                                    @endif
                                </div>
                                <div class="col-span-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-400 font-bold uppercase">Types</span>
                                    <div class="flex flex-wrap gap-1">
                                        @php
                                            $types = collect($doc['consultation_types'] ?? [])->where('is_enabled', true)->pluck('consultation_mode')->toArray();
                                            if (empty($types) && empty($doc['consultation_types'])) {
                                                $types = ['CLINIC_PHYSICAL', 'ONLINE'];
                                            }
                                        @endphp
                                        @if(in_array('CLINIC_PHYSICAL', $types))
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">OPD</span>
                                        @endif
                                        @if(in_array('ONLINE', $types))
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-sky-50 text-sky-700 border border-sky-200">Online</span>
                                        @endif
                                        @if(in_array('VISITING_CONSULTANCY', $types))
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-50 text-purple-700 border border-purple-200">Visiting</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-span-2 pt-2 border-t border-slate-100 flex flex-col gap-1 text-[11px]">
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-400 font-bold uppercase">Advance Booking Rules</span>
                                        <button type="button" wire:click="openPolicyModal({{ $doc['id'] }})" class="text-blue-600 hover:underline font-bold text-[10px]">+ Add Rule</button>
                                    </div>
                                    @if(!empty($doc['booking_policies']))
                                        <div class="space-y-1 mt-1">
                                            @foreach($doc['booking_policies'] as $pol)
                                                <div class="flex items-center justify-between bg-slate-50 px-2 py-1 rounded text-[10px] border border-slate-200">
                                                    <span>
                                                        <strong>+{{ $pol['max_advance_booking_days'] }} days</strong>: 
                                                        {{ \Carbon\Carbon::parse($pol['effective_from'])->format('d M') }} – {{ \Carbon\Carbon::parse($pol['effective_to'])->format('d M Y') }}
                                                    </span>
                                                    <button type="button" wire:click="deleteDoctorPolicy({{ $pol['id'] }})" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic">Inheriting clinic default</span>
                                    @endif
                                </div>

                                <div class="col-span-2 pt-2 border-t border-slate-100 flex flex-col gap-1 text-[11px]">
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-400 font-bold uppercase">Leaves / Unavailability</span>
                                        <button type="button" wire:click="openLeaveModal({{ $doc['id'] }})" class="text-amber-600 hover:underline font-bold text-[10px]">+ Register Leave</button>
                                    </div>
                                    @if(!empty($doc['leaves']))
                                        <div class="space-y-1 mt-1">
                                            @foreach($doc['leaves'] as $lv)
                                                <div class="flex items-center justify-between bg-amber-50 px-2 py-1 rounded text-[10px] border border-amber-200 text-amber-800">
                                                    <span>
                                                        <strong>{{ $lv['session_type'] ?? 'All Day' }}</strong>: 
                                                        {{ \Carbon\Carbon::parse($lv['start_date'])->format('d M') }} – {{ \Carbon\Carbon::parse($lv['end_date'])->format('d M Y') }}
                                                        @if(!empty($lv['reason'])) ({{ $lv['reason'] }}) @endif
                                                    </span>
                                                    <button type="button" wire:click="deleteDoctorLeave({{ $lv['id'] }})" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic">No scheduled leaves</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-sm text-slate-400">
                    No doctors registered yet. Click "Add Doctor" to get started.
                </div>
            @endif
        </div>
    </div>

    {{-- Doctor Booking Policy Modal --}}
    @if($showPolicyModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-md w-full p-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Add Doctor Booking Window Policy</h3>
                    <button type="button" wire:click="closePolicyModal" class="text-slate-400 hover:text-slate-700 font-bold">&times;</button>
                </div>
                <form wire:submit.prevent="saveDoctorPolicy" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Effective From</label>
                        <input type="date" wire:model="policyEffectiveFrom" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Effective To</label>
                        <input type="date" wire:model="policyEffectiveTo" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Max Advance Booking (Days)</label>
                        <input type="number" min="0" max="90" wire:model="policyMaxAdvanceDays" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                        <p class="text-[11px] text-slate-400 mt-1">Note: Effective booking window will be capped by Clinic maximum (MIN rule).</p>
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="closePolicyModal" class="px-4 py-2 text-sm font-bold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Save Policy</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Doctor Leave Modal --}}
    @if($showLeaveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-md w-full p-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Register Doctor Leave / Unavailability</h3>
                    <button type="button" wire:click="closeLeaveModal" class="text-slate-400 hover:text-slate-700 font-bold">&times;</button>
                </div>
                <form wire:submit.prevent="saveDoctorLeave" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Start Date</label>
                            <input type="date" wire:model="leaveStartDate" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">End Date</label>
                            <input type="date" wire:model="leaveEndDate" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Session Scope</label>
                        <select wire:model="leaveSessionType" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                            <option value="">All Day (Full Unavailability)</option>
                            <option value="MORNING">Morning Session Only</option>
                            <option value="EVENING">Evening Session Only</option>
                            <option value="SUNDAY_SINGLE">Sunday Session</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Reason / Note</label>
                        <input type="text" wire:model="leaveReason" placeholder="e.g. Medical Conference, Personal Leave" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="closeLeaveModal" class="px-4 py-2 text-sm font-bold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm font-bold text-white bg-amber-600 rounded-lg hover:bg-amber-700">Save Leave</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
