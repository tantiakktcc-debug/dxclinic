<div class="max-w-4xl mx-auto py-6 px-4 sm:px-6">
    <!-- Header with Channel Differentiation (Section 9, 10) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-200 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-teal-600 text-white shadow-md">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">Operator Appointment Booking</h1>
                    <p class="text-xs text-slate-500 font-medium">Clinic Reception Desk Token Allocation</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-teal-100 text-teal-800 border border-teal-300 shadow-sm">
                <span class="w-2.5 h-2.5 rounded-full bg-teal-600"></span>
                Booking Source: Clinic Operator
            </span>
            <a href="{{ route('operator.queue', ['public_slug' => $clinic->public_slug ?? \App\Services\TenantContext::clinic()?->public_slug ?? 'clinic']) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition">
                &larr; Arrival Queue
            </a>
        </div>
    </div>

    @if ($bookingSuccess && $bookedAppointment)
        <!-- Successful Token Booking Confirmation Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-teal-200 overflow-hidden text-center p-8 transition-all animate-fade-in max-w-xl mx-auto">
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div class="flex justify-center gap-2 mb-2">
                <span class="inline-block px-3 py-1 bg-amber-100 text-amber-900 text-xs font-bold rounded-full">
                    Payment Status: UNPAID / PENDING
                </span>
                <span class="inline-block px-3 py-1 {{ $bookedAppointment->consultation_mode === 'ONLINE' ? 'bg-purple-100 text-purple-900' : ($bookedAppointment->consultation_mode === 'VISITING_CONSULTANCY' ? 'bg-amber-100 text-amber-900' : 'bg-teal-100 text-teal-900') }} text-xs font-bold rounded-full">
                    {{ $bookedAppointment->consultation_mode === 'ONLINE' ? '🌐 Online Consultancy' : ($bookedAppointment->consultation_mode === 'VISITING_CONSULTANCY' ? '🚗 Visiting Consultancy' : '🏥 Clinic Consultancy') }}
                </span>
            </div>
            <h2 class="text-xl font-bold text-slate-900">Token Successfully Allocated!</h2>
            <p class="text-xs text-slate-500">Appointment is registered in the common daily sequence.</p>

            <div class="my-6 p-6 bg-gradient-to-br from-teal-50 to-indigo-50 border border-teal-200 rounded-2xl">
                <p class="text-xs uppercase tracking-wider text-teal-700 font-bold">Daily Queue Token</p>
                <div class="text-6xl font-black text-teal-800 tracking-tight my-2" id="operator_token_display">
                    {{ $bookedAppointment->formatted_token }}
                </div>
                <div class="flex justify-center items-center gap-3 text-xs text-slate-600 mt-2">
                    <span>📅 {{ $bookedAppointment->appointment_date->format('d M Y') }}</span>
                    <span>•</span>
                    <span>⏰ {{ $bookedAppointment->session_type }}</span>
                    <span>•</span>
                    <span class="font-bold text-indigo-700">👨‍⚕️ {{ \App\Services\DoctorNamePresenter::format($bookedAppointment->doctor->name) }}</span>
                </div>
            </div>

            <div class="bg-slate-50 rounded-xl p-4 text-left text-xs space-y-2 border border-slate-200 text-slate-700 mb-6">
                <div class="flex justify-between"><span class="text-slate-500">Patient ID:</span><span class="font-bold text-slate-900">{{ $bookedAppointment->patient->patient_id }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Patient Name:</span><span class="font-bold text-slate-900">{{ $bookedAppointment->patient->name }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Attending Doctor:</span><span class="font-bold text-indigo-800">{{ \App\Services\DoctorNamePresenter::format($bookedAppointment->doctor->name) }} ({{ $bookedAppointment->doctor->specialization ?? 'General' }})</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Consultation Type:</span><span class="font-bold text-slate-900">{{ $bookedAppointment->bookingType?->name ?? ($bookedAppointment->consultation_mode === 'ONLINE' ? 'Online Consultancy' : ($bookedAppointment->consultation_mode === 'VISITING_CONSULTANCY' ? 'Visiting Consultancy' : 'Clinic Consultancy')) }} (₹{{ number_format($bookedAppointment->fee_amount ?? 0, 0) }})</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Age / Gender:</span><span>{{ $bookedAppointment->patient->age }} Y / {{ $bookedAppointment->patient->sex }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Mobile:</span><span class="font-mono font-medium">{{ $bookedAppointment->patient->mobile }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Address, City:</span><span class="font-medium text-slate-800">{{ $bookedAppointment->patient->formatted_address }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Booking Channel:</span><span class="font-bold text-teal-700">Clinic Operator</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Fee Amount:</span><span class="font-bold text-rose-700">₹{{ number_format($bookedAppointment->fee_amount ?? 0, 0) }} (Unpaid)</span></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <a href="{{ route('token.pdf', $bookedAppointment->secure_token) }}" target="_blank" id="btn_operator_download_token" class="inline-flex items-center justify-center px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl shadow font-bold text-xs gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Print Token</span>
                    <kbd class="text-[10px] bg-teal-800 text-teal-200 px-1.5 py-0.5 rounded font-mono font-bold">F7</kbd>
                </a>
                <button type="button" wire:click="sendTokenSlipViaWhatsApp" wire:loading.attr="disabled" id="btn_operator_send_whatsapp_token" class="inline-flex items-center justify-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow font-bold text-xs gap-1.5 cursor-pointer disabled:opacity-50">
                    <span>Send on WhatsApp</span>
                </button>
                <a href="{{ route('operator.queue', ['public_slug' => $clinic->public_slug ?? \App\Services\TenantContext::clinic()?->public_slug ?? 'clinic', 'date' => $bookedAppointment->appointment_date ? $bookedAppointment->appointment_date->toDateString() : now()->toDateString()]) }}" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow font-bold text-xs gap-1.5">
                    <span>Arrival Queue &rarr;</span>
                    <kbd class="text-[10px] bg-indigo-800 text-indigo-200 px-1.5 py-0.5 rounded font-mono font-bold">Alt+Q</kbd>
                </a>
                <button type="button" wire:click="bookAnother" id="btn_operator_book_another" class="inline-flex items-center justify-center px-4 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl font-bold text-xs gap-1.5">
                    <span>+ Book Another</span>
                    <kbd class="text-[10px] bg-slate-300 text-slate-800 px-1.5 py-0.5 rounded font-mono font-bold">F2</kbd>
                </button>
            </div>
        </div>
    @else
        <!-- Booking Form Layout: 2 Columns on Desktop -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Column: Patient Selection/Entry (7 cols) -->
            <div class="lg:col-span-7 space-y-4">
                <div class="bg-white rounded-2xl shadow border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">1. Patient Identification</span>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="switchToNewPatient" id="btn_mode_new_patient" class="text-xs font-semibold px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1.5 {{ $isNewPatientMode ? 'bg-teal-600 text-white shadow-xs' : 'bg-slate-200 text-slate-700 hover:bg-slate-300' }}">
                                <span>+ New Patient</span>
                                <kbd class="text-[10px] {{ $isNewPatientMode ? 'bg-teal-800 text-teal-200' : 'bg-slate-300 text-slate-700' }} px-1.5 py-0.5 rounded font-mono font-bold">F2</kbd>
                            </button>
                            <button type="button" wire:click="switchToExistingPatient" id="btn_mode_existing_patient" class="text-xs font-semibold px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1.5 {{ !$isNewPatientMode ? 'bg-teal-600 text-white shadow-xs' : 'bg-slate-200 text-slate-700 hover:bg-slate-300' }}">
                                <span>🔍 Existing Patient</span>
                                <kbd class="text-[10px] {{ !$isNewPatientMode ? 'bg-teal-800 text-teal-200' : 'bg-slate-300 text-slate-700' }} px-1.5 py-0.5 rounded font-mono font-bold">F4</kbd>
                            </button>
                        </div>
                    </div>

                    <div class="p-5 space-y-4">
                        @if ($isNewPatientMode)
                            <!-- NEW PATIENT MODE: Search section is completely removed (Revision 32 Phase 1) -->
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Full Patient Name *</label>
                                    <input type="text" wire:model="name" id="operator_patient_name" placeholder="e.g. Kundan Kumar or राम कुमार" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                                    @error('name') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Age (Years) *</label>
                                        <input type="number" wire:model="age" id="operator_patient_age" placeholder="e.g. 35 (0 for newborn)" min="0" max="130" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                                        @error('age') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Gender / Sex *</label>
                                        <select wire:model="sex" id="operator_patient_sex" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border bg-white">
                                            <option value="MALE">Male</option>
                                            <option value="FEMALE">Female</option>
                                            <option value="OTHER">Other</option>
                                        </select>
                                        @error('sex') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Mobile Number below Age & Gender (Revision 32 Phase 2) -->
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Mobile Number *</label>
                                    <input type="tel" wire:model.live.debounce.400ms="mobile" id="operator_patient_mobile" placeholder="+91 9876543210" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border font-medium">
                                    <p class="text-[11px] text-slate-500 mt-1">Accepted: 10 digits, +91 format, or 09876543210.</p>
                                    @error('mobile') <span class="text-xs text-rose-600 block mt-1 font-medium">{{ $message }}</span> @enderror
                                </div>

                                <!-- Inline Duplicate Mobile Warning (Revision 32 Phase 2) -->
                                @if (!empty($duplicateCandidates) && !$allowExplicitNewPatientWithDuplicateMobile)
                                    <div class="p-4 bg-amber-50 border-2 border-amber-300 rounded-xl space-y-3" id="duplicate_mobile_warning">
                                        <div class="flex items-center gap-2 text-amber-900 font-bold text-xs uppercase tracking-wider">
                                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            <span>Existing Patient Found</span>
                                        </div>
                                        <div class="space-y-2">
                                            @foreach($duplicateCandidates as $cand)
                                                <div class="p-3 bg-white border border-amber-200 rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                                    <div>
                                                        <div class="font-bold text-slate-900 text-sm">{{ $cand['name'] }} <span class="text-xs font-normal text-slate-500">({{ $cand['age'] ?? '?' }}Y / {{ $cand['sex'] ?? '?' }})</span></div>
                                                        <div class="text-xs text-slate-600 mt-0.5">
                                                            <strong class="text-slate-800">Patient ID:</strong> <span class="font-mono text-teal-800 font-bold">{{ $cand['patient_id'] }}</span> &bull; 
                                                            <strong class="text-slate-800">Mobile:</strong> {{ $cand['mobile'] }} &bull; 
                                                            {{ $cand['city'] ?? '' }}
                                                        </div>
                                                    </div>
                                                    <button type="button" wire:click="useExistingPatient({{ $cand['id'] }})" id="btn_use_existing_patient_{{ $cand['id'] }}" class="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg text-xs shadow-xs whitespace-nowrap">
                                                        Use Existing Patient
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="pt-2 border-t border-amber-200 flex justify-end">
                                            <button type="button" wire:click="continueAsNewPatient" id="btn_continue_as_new_patient" class="text-xs font-bold text-amber-900 hover:text-amber-950 underline flex items-center gap-1">
                                                <span>Continue as New Patient &rarr;</span>
                                            </button>
                                        </div>
                                    </div>
                                @elseif ($allowExplicitNewPatientWithDuplicateMobile)
                                    <div class="p-2.5 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-xs flex items-center justify-between">
                                        <span>✓ Explicitly confirmed to register as a separate new patient.</span>
                                        <button type="button" wire:click="$set('allowExplicitNewPatientWithDuplicateMobile', false)" class="text-[10px] text-emerald-700 hover:underline">Reset</button>
                                    </div>
                                @endif

                                <div class="grid grid-cols-3 gap-3">
                                    <div class="col-span-2">
                                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Street Address *</label>
                                        <input type="text" wire:model="address" id="operator_patient_address" placeholder="e.g. Lanka, House 12" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                                        @error('address') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">City *</label>
                                        <input type="text" wire:model="city" id="operator_patient_city" placeholder="Varanasi" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                                        @error('city') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- EXISTING PATIENT MODE: Search & Selection -->
                            @if ($selectedPatient)
                                <!-- Selected Patient Card -->
                                <div class="p-4 bg-teal-50 border border-teal-200 rounded-xl flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 bg-teal-200 text-teal-900 text-[10px] font-bold rounded">{{ $selectedPatient->patient_id }}</span>
                                            <span class="text-sm font-bold text-slate-900">{{ $selectedPatient->name }}</span>
                                        </div>
                                        <div class="text-xs text-slate-600 mt-1">
                                            {{ $selectedPatient->age }} Y, {{ $selectedPatient->sex }} • {{ $selectedPatient->mobile }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ $selectedPatient->address }}, {{ $selectedPatient->city }}
                                        </div>
                                    </div>
                                    <button type="button" wire:click="switchToExistingPatient" class="text-xs font-semibold text-rose-600 hover:text-rose-800">
                                        Change &times;
                                    </button>
                                </div>
                            @else
                                <!-- Patient Search Box -->
                                <div class="relative space-y-2">
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Search Existing Patient (Name / Mobile / ID)</label>
                                    <input type="text" wire:model.live.debounce.300ms="searchQuery" id="operator_search_patient" placeholder="Type name, 10-digit mobile, or DXP-00001..." class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                                    
                                    @if(!empty($searchResults))
                                        <div class="absolute z-20 left-0 right-0 mt-1 bg-white rounded-xl shadow-xl border border-slate-200 max-h-56 overflow-y-auto divide-y divide-slate-100">
                                            @foreach($searchResults as $res)
                                                <div wire:click="selectPatient({{ $res['id'] }})" class="p-3 hover:bg-teal-50 cursor-pointer flex items-center justify-between transition">
                                                    <div>
                                                        <div class="text-sm font-bold text-slate-900">{{ $res['name'] }} <span class="text-xs font-normal text-slate-500">({{ $res['age'] }}Y / {{ $res['sex'] }})</span></div>
                                                        <div class="text-xs text-slate-500">{{ $res['mobile'] }} • {{ $res['city'] }}</div>
                                                    </div>
                                                    <span class="px-2 py-1 bg-teal-100 text-teal-800 text-[10px] font-bold rounded">
                                                        {{ $res['patient_id'] }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column: Consultation, Doctor & Booking (5 cols) -->
            <div class="lg:col-span-5 space-y-4">
                <div class="bg-white rounded-2xl shadow border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">2. Consultation & Slot Details</span>
                    </div>

                    <div class="p-5 space-y-4">
                        {{-- STEP 1: Attending Doctor Selection (Primary Filter) --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Attending Doctor *</label>
                            @if(count($active_doctors) === 1)
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                                    <span class="text-xs font-bold text-slate-900">{{ \App\Services\DoctorNamePresenter::format($active_doctors[0]['name']) }}</span>
                                    <span class="text-[11px] text-slate-500 block">({{ $active_doctors[0]['specialization'] ?? $active_doctors[0]['qualification'] }})</span>
                                </div>
                            @else
                                <select wire:model.live="selected_doctor_id" id="operator_select_doctor" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border bg-white font-medium">
                                    <option value="">-- Choose Doctor --</option>
                                    @foreach($active_doctors as $doc)
                                        <option value="{{ $doc['id'] }}">{{ \App\Services\DoctorNamePresenter::format($doc['name']) }} ({{ $doc['specialization'] ?? $doc['qualification'] }})</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('selected_doctor_id') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        {{-- STEP 2: Doctor-Mapped Booking Type Selection (Revision 45 Strict Mapped + Active Fee) --}}
                        @if(!empty($availableBookingTypes))
                            <div>
                                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">Booking Type *</label>
                                <div class="space-y-2">
                                    @foreach($availableBookingTypes as $bt)
                                        @php
                                            $isSel = ($selected_booking_type_id == $bt['id']);
                                            $icon = match($bt['code']) {
                                                'ONLINE_CONSULTANCY' => '🌐',
                                                'EMERGENCY_CONSULTANCY' => '🚨',
                                                'VISITING_CONSULTANCY' => '🚗',
                                                default => '🏥',
                                            };
                                            $btFee = $bt['resolved_fee'] ?? 0;
                                            $borderClass = $isSel 
                                                ? ($bt['code'] === 'ONLINE_CONSULTANCY' ? 'bg-purple-50 border-purple-500 ring-1 ring-purple-500' 
                                                    : ($bt['code'] === 'EMERGENCY_CONSULTANCY' ? 'bg-rose-50 border-rose-500 ring-1 ring-rose-500' 
                                                    : 'bg-teal-50 border-teal-500 ring-1 ring-teal-500'))
                                                : 'bg-white border-slate-200 hover:bg-slate-50';
                                            $textClass = $bt['code'] === 'ONLINE_CONSULTANCY' ? 'text-purple-700' 
                                                : ($bt['code'] === 'EMERGENCY_CONSULTANCY' ? 'text-rose-700' : 'text-teal-700');
                                        @endphp
                                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition {{ $borderClass }}" id="booking_type_option_{{ $bt['id'] }}">
                                            <input type="radio" wire:model.live="selected_booking_type_id" value="{{ $bt['id'] }}" name="selected_booking_type_id" class="text-teal-600 focus:ring-teal-500">
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between">
                                                    <div class="text-xs font-bold text-slate-900">{{ $icon }} {{ $bt['name'] }}</div>
                                                    <span class="text-[11px] {{ $textClass }} font-bold">₹{{ number_format($btFee, 0) }}</span>
                                                </div>
                                                <div class="text-[11px] text-slate-500">
                                                    Mode: <span class="font-medium text-slate-700">{{ $bt['consultation_mode'] }}</span>
                                                    @if($bt['is_system'])
                                                        <span class="ml-1 text-[10px] text-slate-400 bg-slate-100 px-1 py-0.5 rounded">System</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('selected_booking_type_id') <span class="text-xs text-rose-600 block mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <div class="p-3.5 bg-amber-50 border border-amber-300 rounded-xl text-xs text-amber-900" id="no_booking_types_warning">
                                <div class="font-bold mb-1">⚠️ No Consultancy Types Available</div>
                                <div>No booking types are mapped to this doctor with configured fees. Please configure rates in <strong>Settings &rarr; Fee Master</strong> or select another doctor.</div>
                            </div>
                        @endif

                        {{-- Date & Session (Phase 3 & 4: Today/Tomorrow only, ClinicClock expiry validation) --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Appointment Date *</label>
                                <input type="date" wire:model.live="appointment_date" min="{{ $todayDate }}" max="{{ $tomorrowDate }}" id="operator_appointment_date" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                                @error('appointment_date') <span class="text-xs text-rose-600 block mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Session *</label>
                                @if (count($available_sessions) > 0)
                                    <select wire:model="session_type" id="operator_session_type" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border bg-white font-medium">
                                        @foreach ($available_sessions as $code => $info)
                                            <option value="{{ $code }}">{{ $info['label'] }} ({{ $info['start'] }}–{{ $info['end'] }})</option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="p-2 border border-amber-300 bg-amber-50 text-amber-800 text-[11px] rounded-xl font-medium">
                                        No remaining sessions for this date.
                                    </div>
                                @endif
                                @error('session_type') <span class="text-xs text-rose-600 block mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        @if ($noSessionsRemainingToday && $appointment_date === $todayDate)
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                                ⚠️ No sessions remain for today. Please select tomorrow.
                            </div>
                        @endif

                        <div class="pt-4 border-t border-slate-100">
                            <button type="button" wire:click="bookOperatorToken" id="btn_operator_confirm_booking" class="w-full py-3.5 bg-gradient-to-r from-teal-600 to-teal-700 hover:from-teal-700 hover:to-teal-800 text-white rounded-xl shadow-lg font-bold text-sm flex items-center justify-center gap-2 transition-all cursor-pointer">
                                <span>Allocate Common Queue Token &rarr;</span>
                                <kbd class="text-[11px] bg-teal-800/80 text-teal-100 px-2 py-0.5 rounded font-mono font-bold">F8</kbd>
                            </button>
                            <p class="text-center text-[11px] text-slate-400 mt-2">Source: CLINIC. Date + Session scoped token sequence. Keyboard: <span class="font-mono text-slate-500 font-bold">[F8]</span> or <span class="font-mono text-slate-500 font-bold">[Ctrl+Enter]</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
