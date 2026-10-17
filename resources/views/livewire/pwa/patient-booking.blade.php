<div class="max-w-xl mx-auto py-6 px-4 sm:px-6">
    <!-- Clinic Brand Header -->
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-teal-600 text-white shadow-lg mb-2">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
        </div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ $clinic->name ?? 'DxClinic' }}</h1>
        <p class="text-xs text-slate-500 font-medium">{{ $clinic->city ?? 'Varanasi' }} • Public Patient Online Booking Portal</p>
        <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-teal-100 text-teal-800 border border-teal-200">
            <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
            Booking Source: Online Patient
        </div>
    </div>

    @if ($bookingSuccess && $bookedAppointment)
        <!-- Successful Token Booking Confirmation Card (Section 51) -->
        <div class="bg-white rounded-2xl shadow-xl border border-teal-100 overflow-hidden text-center p-6 transition-all animate-fade-in">
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
            <h2 class="text-lg font-bold text-slate-800">Your Appointment is Confirmed!</h2>
            <p class="text-xs text-slate-500">
                {{ $bookedAppointment->consultation_mode === 'ONLINE' ? 'Doctor will connect online as per schedule.' : 'Please arrive 15 minutes before your session begins.' }}
            </p>

            <div class="my-5 p-5 bg-gradient-to-br from-teal-50 to-indigo-50 border border-teal-200 rounded-2xl">
                <p class="text-xs uppercase tracking-wider text-teal-700 font-bold">Your Common Queue Token</p>
                <div class="text-5xl font-black text-teal-800 tracking-tight my-1" id="confirmed_token_number">
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

            <div class="bg-slate-50 rounded-xl p-4 text-left text-xs space-y-1.5 border border-slate-200 text-slate-700 mb-6">
                <div class="flex justify-between"><span class="text-slate-500">Patient ID:</span><span class="font-bold text-slate-900">{{ $bookedAppointment->patient->patient_id }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Patient Name:</span><span class="font-bold text-slate-900">{{ $bookedAppointment->patient->name }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Attending Doctor:</span><span class="font-bold text-indigo-800">{{ \App\Services\DoctorNamePresenter::format($bookedAppointment->doctor->name) }} ({{ $bookedAppointment->doctor->specialization ?? 'General' }})</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Consultation Type:</span><span class="font-bold text-slate-900">{{ $bookedAppointment->bookingType?->name ?? ($bookedAppointment->consultation_mode === 'ONLINE' ? 'Online Consultancy' : ($bookedAppointment->consultation_mode === 'VISITING_CONSULTANCY' ? 'Visiting Consultancy' : 'Clinic Consultancy')) }} (₹{{ number_format($bookedAppointment->fee_amount ?? 0, 0) }})</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Age / Gender:</span><span>{{ $bookedAppointment->patient->age }} Y / {{ $bookedAppointment->patient->sex }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Address, City:</span><span class="font-medium text-slate-800">{{ $bookedAppointment->patient->formatted_address }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Booking Channel:</span><span class="font-bold text-teal-700">Online Patient</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Fee Obligation:</span><span class="font-bold text-rose-700">₹{{ number_format($bookedAppointment->fee_amount ?? 0, 0) }} (Pending Settlement)</span></div>
            </div>

            @if (session()->has('whatsapp_success'))
                <div class="mb-3 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold text-center" id="pwa_whatsapp_success_alert">
                    ✅ {{ session('whatsapp_success') }}
                </div>
            @endif
            @if (session()->has('whatsapp_error') || $errors->has('general'))
                <div class="mb-3 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs font-semibold text-center" id="pwa_whatsapp_error_alert">
                    ⚠️ {{ session('whatsapp_error') ?? $errors->first('general') }}
                </div>
            @endif

            <div class="space-y-3">
                <a href="{{ route('token.pdf', $bookedAppointment->secure_token) }}" target="_blank" id="btn_download_token_pdf" class="w-full inline-flex items-center justify-center px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white rounded-xl shadow font-bold text-sm">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Download Token Slip
                </a>
                <button type="button" wire:click="sendTokenSlipOnWhatsApp" wire:loading.attr="disabled" id="btn_send_whatsapp_token_slip" class="w-full inline-flex items-center justify-center px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow font-bold text-sm cursor-pointer disabled:opacity-50">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.587-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.941-.708-1.793s.448-1.272.607-1.446c.159-.175.346-.219.462-.219s.231.006.332.011c.107.005.25.04.385.358.144.338.491 1.2.534 1.288.043.087.072.19.014.306-.058.115-.087.189-.173.29-.087.102-.183.227-.261.305-.087.087-.178.182-.077.355.101.174.45 1.742 2.014 2.203.203.06.362.096.486.136.205.065.391.056.538.034.164-.025.505-.207.577-.406z"/></svg>
                    <span wire:loading.remove wire:target="sendTokenSlipOnWhatsApp">Send Token Slip on WhatsApp</span>
                    <span wire:loading wire:target="sendTokenSlipOnWhatsApp">Sending via WhatsApp...</span>
                </button>
                <button type="button" wire:click="bookAnother" id="btn_book_another" class="w-full text-slate-600 hover:text-slate-900 text-xs font-semibold py-2">
                    Book Another Appointment &rarr;
                </button>
            </div>
        </div>
    @else
        <!-- Booking Form Card (Section 43) -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="bg-gradient-to-r from-teal-600 to-teal-800 px-6 py-4 text-white">
                <h2 class="text-base font-bold">Patient Registration & Slot Booking</h2>
                <p class="text-xs text-teal-100">No advance payment required. Pay at the clinic counter.</p>
            </div>

            <form wire:submit.prevent="bookToken" class="p-6 space-y-4">
                @if (session()->has('error') || $errors->has('general'))
                    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs flex items-start gap-3" id="pwa_booking_error_alert">
                        <span class="text-rose-500 text-base font-bold leading-none">⚠️</span>
                        <div>
                            <p class="font-bold uppercase tracking-wider text-rose-700 text-[10px]">Booking Notice</p>
                            <p class="mt-0.5 text-rose-900 font-medium">{{ session('error') ?? $errors->first('general') }}</p>
                        </div>
                    </div>
                @endif

                {{-- STEP 1: Doctor Selection (Section 41-44) --}}
                @if(count($active_doctors) === 1)
                    <!-- Single Doctor: Auto-selected (Section 42) -->
                    <div class="p-3 bg-teal-50 border border-teal-200 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-teal-700 tracking-wider block">Attending Doctor (Auto-Selected)</span>
                            <span class="text-sm font-bold text-slate-900">{{ \App\Services\DoctorNamePresenter::format($active_doctors[0]['name']) }}</span>
                            <span class="text-xs text-slate-500">({{ $active_doctors[0]['specialization'] ?? $active_doctors[0]['qualification'] }})</span>
                        </div>
                        <input type="hidden" wire:model="selected_doctor_id">
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                    </div>
                @elseif(count($active_doctors) > 1)
                    <!-- Multi-Doctor: Selection Required with Default Preselected (Section 43, 44) -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Select Attending Doctor *</label>
                        <select wire:model.live="selected_doctor_id" id="pwa_select_doctor" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border bg-white font-medium">
                            <option value="">-- Choose Doctor --</option>
                            @foreach($active_doctors as $doc)
                                <option value="{{ $doc['id'] }}">{{ \App\Services\DoctorNamePresenter::format($doc['name']) }} ({{ $doc['specialization'] ?? $doc['qualification'] }})</option>
                            @endforeach
                        </select>
                        @error('selected_doctor_id') <span class="text-xs text-rose-600 block mt-1 font-medium">{{ $message }}</span> @enderror
                    </div>
                @endif

                {{-- STEP 2: Doctor-Mapped Booking Type Selection (Revision 45 Strict Mapped + Active Fee) --}}
                @if(!empty($availableBookingTypes))
                    <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                        <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">Consultation Type *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
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
                                        ? ($bt['code'] === 'ONLINE_CONSULTANCY' ? 'bg-purple-50 border-purple-500 text-purple-900 shadow-sm ring-1 ring-purple-500' 
                                            : 'bg-teal-50 border-teal-500 text-teal-900 shadow-sm ring-1 ring-teal-500')
                                        : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50';
                                    $textClass = $bt['code'] === 'ONLINE_CONSULTANCY' ? 'text-purple-700' : 'text-teal-700';
                                @endphp
                                <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all {{ $borderClass }}" id="pwa_booking_type_{{ $bt['id'] }}">
                                    <input type="radio" wire:model.live="selected_booking_type_id" value="{{ $bt['id'] }}" name="selected_booking_type_id" class="text-teal-600 focus:ring-teal-500">
                                    <div>
                                        <span class="text-xs font-bold block">{{ $icon }} {{ $bt['name'] }}</span>
                                        <span class="text-[11px] {{ $textClass }} font-semibold">₹{{ number_format($btFee, 0) }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('selected_booking_type_id') <span class="text-xs text-rose-600 block mt-1 font-medium">{{ $message }}</span> @enderror
                    </div>
                @else
                    <div class="p-3.5 bg-amber-50 border border-amber-300 rounded-xl text-xs text-amber-900" id="pwa_no_booking_types_warning">
                        ⚠️ <strong>No consultancy types available.</strong> No booking types are enabled with configured fees for this doctor. Please choose another doctor.
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Full Patient Name *</label>
                    <input type="text" wire:model="name" id="pwa_patient_name" placeholder="e.g. Ramesh Kumar" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                    @error('name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Age (Years) *</label>
                        <input type="number" wire:model="age" id="pwa_patient_age" placeholder="e.g. 42 (0 for newborn)" min="0" max="130" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                        @error('age') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Gender *</label>
                        <select wire:model="sex" id="pwa_patient_sex" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border bg-white">
                            <option value="MALE">Male</option>
                            <option value="FEMALE">Female</option>
                            <option value="OTHER">Other</option>
                        </select>
                        @error('sex') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Mobile Number *</label>
                    <input type="tel" wire:model.blur="mobile" id="pwa_patient_mobile" placeholder="+91 9876543210" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border font-medium">
                    <p class="text-[11px] text-slate-500 mt-1">Accepted: 10 digits (9876543210), +91 followed by 10 digits, or 09876543210.</p>
                    @error('mobile') <span class="text-xs text-rose-600 block mt-1 font-medium">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Street Address (without City) *</label>
                    <input type="text" wire:model="address" id="pwa_patient_address" placeholder="e.g. House 25, Lanka" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                    @error('address') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">City (Dedicated Field) *</label>
                    <input type="text" wire:model="city" id="pwa_patient_city" placeholder="e.g. Varanasi" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                    @error('city') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Appointment Date *</label>
                        <input type="date" wire:model.live="appointment_date" min="{{ date('Y-m-d') }}" id="pwa_appointment_date" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border">
                        @error('appointment_date') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Session *</label>
                        <select wire:model="session_type" id="pwa_session_type" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2.5 text-sm border bg-white">
                            @if(empty($available_sessions))
                                <option value="" disabled selected>-- No sessions available --</option>
                            @else
                                @foreach ($available_sessions as $code => $info)
                                    <option value="{{ $code }}">{{ $info['label'] }} ({{ $info['start'] }}-{{ $info['end'] }})</option>
                                @endforeach
                            @endif
                        </select>
                        @if(empty($available_sessions))
                            <p class="text-[11px] text-amber-600 font-medium mt-1">⚠️ No bookable sessions remaining for this date. Please choose tomorrow or another allowed date.</p>
                        @endif
                        @error('session_type') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" id="btn_pwa_submit_booking" wire:loading.attr="disabled" class="w-full py-3.5 bg-gradient-to-r from-teal-600 to-teal-700 hover:from-teal-700 hover:to-teal-800 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl shadow-lg font-bold text-sm flex items-center justify-center gap-2 transition-all cursor-pointer">
                        <span wire:loading.remove wire:target="bookToken">Get Queue Token &rarr;</span>
                        <span wire:loading wire:target="bookToken" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Allocating Token...
                        </span>
                        <kbd class="text-[11px] bg-teal-800/80 text-teal-100 px-2 py-0.5 rounded font-mono font-bold" wire:loading.remove wire:target="bookToken">F8</kbd>
                    </button>
                    <p class="text-center text-[11px] text-slate-400 mt-2">Common date-scoped queue token allocated instantly. Keyboard: <span class="font-mono text-slate-500 font-bold">[F8]</span> or <span class="font-mono text-slate-500 font-bold">[Enter]</span></p>
                </div>
            </form>
        </div>
    @endif

    <!-- Embedded Chatbot Widget Component (Section 44) -->
    <livewire:pwa.chatbot-widget :public_slug="$public_slug" :key="'chatbot-'.$public_slug" />
</div>
