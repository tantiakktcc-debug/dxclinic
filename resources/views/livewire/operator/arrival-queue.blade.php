<div class="py-6 px-4 sm:px-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Operator Arrival & Queue Manager</h1>
            <p class="text-xs text-slate-500 mt-0.5">Verify patient arrivals, allocate tokens for walk-ins/phone, and record pre-consultation vitals.</p>
        </div>
        <div class="flex items-center gap-2">
            @php
                $authoritativeClinic = null;
                if (auth()->check()) {
                    $authUser = auth()->user();
                    if ($authUser->role === 'SUPERADMIN') {
                        $authoritativeClinic = $clinic ?? \App\Services\TenantContext::clinic() ?? $authUser->clinic;
                    } else {
                        $authoritativeClinic = $authUser->clinic ?? ($authUser->clinic_id ? \App\Models\Clinic::find($authUser->clinic_id) : null) ?? $clinic ?? \App\Services\TenantContext::clinic();
                    }
                } else {
                    $authoritativeClinic = $clinic ?? \App\Services\TenantContext::clinic();
                }
                $liveQueueUrl = $authoritativeClinic?->public_slug 
                    ? route('queue.live', ['public_slug' => $authoritativeClinic->public_slug]) 
                    : route('queue.live.direct');
            @endphp
            <button type="button" 
                    id="btn_open_live_queue_from_arrival"
                    onclick="window.open('{{ $liveQueueUrl }}', 'DxClinicLiveQueue', 'width=1280,height=800,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes')" 
                    title="Open Live Queue Window"
                    class="px-3.5 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl shadow-xs font-bold text-xs flex items-center gap-1.5 cursor-pointer">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Open Live Queue</span>
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            </button>

            <button type="button" wire:click="$set('showNewBookingModal', true)" id="btn_new_walkin_booking" class="px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl shadow font-semibold text-xs flex items-center gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>+ New Walk-in / Phone</span>
                <kbd class="text-[10px] bg-teal-800 text-teal-200 px-1.5 py-0.5 rounded font-mono font-bold">F2</kbd>
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="my-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800 font-bold">&times;</button>
        </div>
    @endif

    <!-- Prominent Patient Arrival Search & Check-in (Rule 17) -->
    <div class="my-6 bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Patient Arrival Check-in</h2>
                <p class="text-xs text-slate-500">Scan token or search booked patient to confirm arrival and initiate visit workflow.</p>
            </div>
            @if ($searchedAppointment || $activeWorkflowAppointment)
                <button type="button" wire:click="clearArrivalSearch" class="text-xs text-slate-400 hover:text-slate-600 font-semibold">
                    &times; Clear Search
                </button>
            @endif
        </div>

        <div class="flex gap-2">
            <div class="relative flex-1">
                <input type="text" wire:model="arrivalSearchQuery" wire:keydown.enter="searchPatientForArrival" id="input_arrival_search" placeholder="Search Token / Patient ID / Mobile (e.g. 204 or 9876543210)... [F4]" class="w-full p-3 pl-10 border border-slate-300 rounded-xl text-sm font-medium focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <button type="button" wire:click="searchPatientForArrival" id="btn_search_arrival" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer">
                <span>Search</span>
                <kbd class="text-[10px] bg-blue-800 text-blue-200 px-1.5 py-0.5 rounded font-mono font-bold">↵</kbd>
            </button>
        </div>

        <!-- Result Card (Rule 17) -->
        @if ($searchedAppointment)
            <div class="mt-4 p-5 bg-gradient-to-r from-blue-50/60 to-slate-50 rounded-2xl border border-blue-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-white border border-blue-200 flex flex-col items-center justify-center text-blue-700 shadow-xs">
                            <span class="text-[9px] uppercase font-bold text-slate-400">Token</span>
                            <span class="text-2xl font-black">{{ $searchedAppointment->formatted_token }}</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-slate-900">{{ $searchedAppointment->patient->name }}</h3>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $searchedAppointment->status === 'ARRIVED' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $searchedAppointment->status }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-x-3 gap-y-1">
                                <span><strong>ID:</strong> {{ $searchedAppointment->patient->patient_id }}</span>
                                <span>&bull;</span>
                                <span><strong>Source:</strong> {{ $searchedAppointment->booking_source }}</span>
                                <span>&bull;</span>
                                <span><strong>Date:</strong> {{ $searchedAppointment->appointment_date->format('d M Y') }}</span>
                                <span>&bull;</span>
                                <span><strong>Session:</strong> {{ $searchedAppointment->session_type }}</span>
                                <span>&bull;</span>
                                <span><strong>Address:</strong> {{ $searchedAppointment->patient->formatted_address }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($searchedAppointment->status === 'BOOKED')
                            <button type="button" wire:click="markArrived({{ $searchedAppointment->id }})" id="btn_confirm_arrival" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-sm shadow flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <span>Confirm Arrival</span>
                            </button>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-800 font-bold text-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                Patient Arrived
                            </span>
                            <button type="button" wire:click="selectWorkflowAppointment({{ $searchedAppointment->id }})" class="px-4 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-xl font-bold text-xs border border-blue-200">
                                View Workflow Panel &rarr;
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @elseif ($arrivalSearchPerformed && empty($searchedAppointment))
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                No booked appointment found matching "<strong>{{ $arrivalSearchQuery }}</strong>" for selected date. You can register a new walk-in patient using the "+ New Walk-in" button above.
            </div>
        @endif
    </div>

    <!-- Patient Arrival Screen — Next Actions Workflow Panel (Rule 18) -->
    @if ($activeWorkflowAppointment)
        <div class="my-6 bg-white rounded-2xl p-6 border-2 border-blue-500 shadow-sm animate-in fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">
                        #{{ $activeWorkflowAppointment->formatted_token }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Active Workflow</span>
                            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">✓ Patient Arrived</span>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ $activeWorkflowAppointment->patient->name }} 
                            <span class="text-xs font-normal text-slate-500">({{ $activeWorkflowAppointment->patient->patient_id }} &bull; {{ $activeWorkflowAppointment->patient->formatted_address }})</span>
                        </h3>
                    </div>
                </div>
                <button type="button" wire:click="$set('activeWorkflowAppointment', null)" class="text-xs text-slate-400 hover:text-slate-600 font-semibold self-start sm:self-center">
                    Close Panel &times;
                </button>
            </div>

            <!-- Workflow Progression Steps (Rule 18) -->
            @php
                $hasVisit = (bool) $activeWorkflowAppointment->visit;
                $hasVitals = $hasVisit && (bool) $activeWorkflowAppointment->visit->vital;
                $hasBill = $hasVisit && (bool) $activeWorkflowAppointment->visit->consultationBill;
                $hasPayment = $hasBill && $activeWorkflowAppointment->visit->consultationBill->payment && $activeWorkflowAppointment->visit->consultationBill->payment->status === 'SETTLED';
            @endphp

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 my-4">
                <!-- 1. Appointment Verified -->
                <div class="p-3 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-xs">
                    <span class="font-bold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        Appointment
                    </span>
                    <span class="text-[11px] block text-emerald-700 mt-0.5">Verified</span>
                </div>

                <!-- 2. Visit Created -->
                <div class="p-3 rounded-xl border {{ $hasVisit ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-500' }} text-xs">
                    <span class="font-bold flex items-center gap-1">
                        @if($hasVisit)
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        @else
                            <span class="w-3 h-3 rounded-full border border-slate-400 inline-block"></span>
                        @endif
                        Visit
                    </span>
                    <span class="text-[11px] block mt-0.5 {{ $hasVisit ? 'text-emerald-700' : 'text-slate-400' }}">Created</span>
                </div>

                <!-- 3. Vitals -->
                <div class="p-3 rounded-xl border {{ $hasVitals ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-500' }} text-xs">
                    <span class="font-bold flex items-center gap-1">
                        @if($hasVitals)
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        @else
                            <span class="w-3 h-3 rounded-full border border-slate-400 inline-block"></span>
                        @endif
                        Vitals
                    </span>
                    <span class="text-[11px] block mt-0.5 {{ $hasVitals ? 'text-emerald-700' : 'text-slate-400' }}">
                        {{ $hasVitals ? 'BP ' . $activeWorkflowAppointment->visit->vital->bp_systolic . '/' . $activeWorkflowAppointment->visit->vital->bp_diastolic : 'Pending' }}
                    </span>
                </div>

                <!-- 4. Consultation Payment -->
                <div class="p-3 rounded-xl border {{ $hasPayment ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-500' }} text-xs">
                    <span class="font-bold flex items-center gap-1">
                        @if($hasPayment)
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        @else
                            <span class="w-3 h-3 rounded-full border border-slate-400 inline-block"></span>
                        @endif
                        Consultation
                    </span>
                    <span class="text-[11px] block mt-0.5 {{ $hasPayment ? 'text-emerald-700' : 'text-slate-400' }}">
                        {{ $hasPayment ? 'Paid (₹' . number_format($activeWorkflowAppointment->visit->consultationBill->payment->amount, 0) . ')' : 'Payment Due' }}
                    </span>
                </div>

                <!-- 5. Doctor Pad -->
                <div class="p-3 rounded-xl border bg-slate-50 border-slate-200 text-slate-700 text-xs">
                    <span class="font-bold flex items-center gap-1">
                        <span class="w-3 h-3 rounded-full border border-slate-400 inline-block"></span>
                        Doctor Pad
                    </span>
                    <span class="text-[11px] block text-slate-400 mt-0.5">Ready to Print</span>
                </div>
            </div>

            <!-- Workflow Next Actions -->
            <div class="pt-3 border-t border-slate-100 flex flex-wrap gap-2.5 items-center">
                <button type="button" wire:click="openVitalsModal({{ $activeWorkflowAppointment->id }})" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>{{ $hasVitals ? 'Edit Vitals' : 'Enter Vitals' }}</span>
                </button>

                <a href="{{ route('operator.consultation', ['appointmentId' => $activeWorkflowAppointment->id]) }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5">
                    <span>Consultation Payment & Doctor Pad &rarr;</span>
                </a>

                <a href="{{ route('operator.diagnostics', ['appointmentId' => $activeWorkflowAppointment->id]) }}" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5">
                    <span>Diagnostic Billing</span>
                </a>

                <a href="{{ route('print.doctor.pad', $activeWorkflowAppointment->id) }}" target="_blank" class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-xl text-xs font-bold flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print Pad</span>
                </a>

                <button type="button" wire:click="sendTokenSlipViaWhatsApp({{ $activeWorkflowAppointment->id }})" wire:loading.attr="disabled" id="btn_send_token_slip_wa_workflow" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                    <span>Send Token Slip on WhatsApp</span>
                </button>
            </div>
        </div>
    @endif

    <!-- Controls Bar -->
    <div class="my-6 grid grid-cols-1 sm:grid-cols-4 gap-3 bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Queue Date</label>
            <input type="date" wire:model.live="filterDate" id="filter_queue_date" class="w-full p-2 border border-slate-300 rounded-lg text-xs">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Session</label>
            <select wire:model.live="filterSession" id="filter_queue_session" class="w-full p-2 border border-slate-300 rounded-lg text-xs bg-white">
                <option value="ALL">All Sessions</option>
                @if(!empty($sessions))
                    @foreach($sessions as $key => $sData)
                        <option value="{{ $key }}">{{ $sData['label'] ?? $key }}</option>
                    @endforeach
                @else
                    <option value="MORNING">Morning Session</option>
                    <option value="EVENING">Evening Session</option>
                    <option value="SUNDAY_SINGLE">Sunday Session</option>
                @endif
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Search Patient / Token / City</label>
            <div class="relative">
                <input type="text" wire:model.live.debounce.300ms="searchQuery" id="filter_search_query" placeholder="Type Token (001), Name, Mobile, Patient ID, or City..." class="w-full p-2 pl-8 border border-slate-300 rounded-lg text-xs">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Queue Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="table_arrival_queue" class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                        <th class="py-3 px-4">Token</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Session</th>
                        <th class="py-3 px-4">Patient Identity</th>
                        <th class="py-3 px-4">Address & City</th>
                        <th class="py-3 px-4">Source</th>
                        <th class="py-3 px-4 text-center">Fee</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Vitals</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($appointments as $apt)
                        @php
                            $feeStatus = app(\App\Services\FeeStatusResolver::class)->resolve($apt);
                        @endphp
                        <tr wire:key="arrival-queue-row-{{ $apt->id }}" class="hover:bg-slate-50/70 transition-colors" id="arrival_queue_row_{{ $apt->formatted_token }}">
                            <td class="py-3.5 px-4 font-black text-sm text-teal-800">
                                {{ $apt->formatted_token }}
                            </td>
                            <td class="py-3.5 px-4 font-medium text-slate-700 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($apt->appointment_date)->format('d-m-Y') }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold {{ $apt->session_type === 'MORNING' ? 'bg-amber-50 text-amber-800 border border-amber-200' : ($apt->session_type === 'EVENING' ? 'bg-indigo-50 text-indigo-800 border border-indigo-200' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $apt->session_type }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 text-xs flex items-center gap-1.5 flex-wrap">
                                    <span>{{ $apt->patient->name }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200">
                                        {{ \App\Services\DoctorNamePresenter::format($apt->doctor?->name) }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ $apt->patient->patient_id }} • {{ $apt->patient->age }}Y / {{ $apt->patient->sex }} • 📞 {{ $apt->patient->mobile }}</div>
                            </td>
                            <td class="py-3.5 px-4 max-w-xs">
                                <!-- Address and dedicated City formatted strictly as Address, City (Section 62, 101) -->
                                <span class="font-medium text-slate-800">{{ $apt->patient->formatted_address }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold 
                                    {{ $apt->booking_source === 'ONLINE' ? 'bg-indigo-100 text-indigo-800' : ($apt->booking_source === 'TELEPHONIC' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800') }}">
                                    {{ $apt->booking_source }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center" id="arrival_queue_fee_{{ $apt->formatted_token }}">
                                @if($feeStatus === 'PAID')
                                    <span id="badge_fee_status_{{ $apt->formatted_token }}" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                        PAID
                                    </span>
                                @elseif($feeStatus === 'PARTIAL')
                                    <span id="badge_fee_status_{{ $apt->formatted_token }}" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                        PARTIAL
                                    </span>
                                @elseif($feeStatus === 'REFUNDED')
                                    <span id="badge_fee_status_{{ $apt->formatted_token }}" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                        REFUNDED
                                    </span>
                                @else
                                    <span id="badge_fee_status_{{ $apt->formatted_token }}" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                        UNPAID
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold 
                                    {{ $apt->status === 'ARRIVED' ? 'bg-emerald-100 text-emerald-800' : ($apt->status === 'BOOKED' ? 'bg-blue-100 text-blue-800' : ($apt->status === 'COMPLETED' ? 'bg-purple-100 text-purple-800' : ($apt->status === 'CANCELLED' ? 'bg-rose-100 text-rose-800' : ($apt->status === 'ABSENT' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')))) }}">
                                    {{ $apt->status }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($apt->visit && $apt->visit->vital)
                                    <span class="inline-flex items-center text-emerald-700 text-[11px] font-semibold">
                                        ✓ Recorded (BP: {{ $apt->visit->vital->bp_systolic }}/{{ $apt->visit->vital->bp_diastolic }}, Pulse: {{ $apt->visit->vital->pulse }})
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[11px]">Not recorded</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                                @if ($apt->status === 'BOOKED')
                                    <button type="button" wire:click="markArrived({{ $apt->id }})" id="btn_mark_arrived_{{ $apt->formatted_token }}" class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[11px] shadow-xs cursor-pointer">
                                        Mark Arrived
                                    </button>
                                @endif

                                <button type="button" wire:click="openVitalsModal({{ $apt->id }})" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg font-semibold text-[11px] border border-slate-200 cursor-pointer">
                                    Vitals
                                </button>

                                @if($feeStatus !== 'PAID')
                                    <a href="{{ route('operator.consultation', ['appointmentId' => $apt->id]) }}" id="btn_fee_pad_{{ $apt->formatted_token }}" class="px-2.5 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-800 rounded-lg font-semibold text-[11px] border border-teal-200 inline-block">
                                        Fee & Pad
                                    </a>
                                    <button type="button" disabled id="btn_mark_seen_{{ $apt->formatted_token }}" title="Fee must be PAID before marking patient seen" class="px-2.5 py-1.5 bg-slate-100 text-slate-400 rounded-lg font-bold text-[11px] border border-slate-200 cursor-not-allowed opacity-50 inline-flex items-center gap-1">
                                        <span>Mark Seen</span>
                                        <span>🔒</span>
                                    </button>
                                @else
                                    @if(!in_array($apt->status, ['COMPLETED', 'CANCELLED', 'ABSENT']))
                                        <button type="button" wire:click="markSeen({{ $apt->id }})" id="btn_mark_seen_{{ $apt->formatted_token }}" class="px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold text-[11px] shadow-xs cursor-pointer">
                                            Mark Seen
                                        </button>
                                    @endif
                                    <a href="{{ route('operator.consultation', ['appointmentId' => $apt->id]) }}" id="btn_fee_pad_{{ $apt->formatted_token }}" class="px-2.5 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-800 rounded-lg font-semibold text-[11px] border border-teal-200 inline-block">
                                        Fee & Pad
                                    </a>
                                @endif

                                <a href="{{ route('token.pdf', $apt->secure_token) }}" target="_blank" class="px-2 py-1.5 text-slate-400 hover:text-slate-700 text-[11px]" title="Token PDF">
                                    📄
                                </a>

                                <button type="button" wire:click="sendTokenSlipViaWhatsApp({{ $apt->id }})" wire:loading.attr="disabled" id="btn_send_token_slip_wa_{{ $apt->id }}" class="px-2 py-1.5 text-emerald-600 hover:text-emerald-800 text-[11px] font-bold cursor-pointer disabled:opacity-50" title="Send Token Slip via WhatsApp">
                                    💬
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center text-slate-400 text-xs">
                                No appointments found for selected date and session filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Record Vitals (Section 56) -->
    @if ($showVitalsModal && $selectedAppointment)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg p-6 animate-in fade-in zoom-in-95">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Record Vitals — Token #{{ $selectedAppointment->formatted_token }}</h3>
                        <p class="text-xs text-slate-500">{{ $selectedAppointment->patient->name }} ({{ $selectedAppointment->patient->age }}Y / {{ $selectedAppointment->patient->sex }})</p>
                    </div>
                    <button type="button" wire:click="$set('showVitalsModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <div class="space-y-4 text-xs">
                    <!-- Blood Pressure: [ 120 ] / [ 80 ] mmHg -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <label class="block text-slate-700 font-bold mb-1.5">Blood Pressure</label>
                        <div class="flex items-center gap-2">
                            <input type="number" wire:model="bp_systolic" placeholder="120" class="w-24 p-2 text-center border border-slate-300 rounded-lg font-bold text-slate-900 bg-white">
                            <span class="text-slate-400 font-bold text-base">/</span>
                            <input type="number" wire:model="bp_diastolic" placeholder="80" class="w-24 p-2 text-center border border-slate-300 rounded-lg font-bold text-slate-900 bg-white">
                            <span class="text-slate-500 font-semibold text-xs ml-1">mmHg</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Pulse: [ 78 ] bpm -->
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <label class="block text-slate-700 font-bold mb-1.5">Pulse</label>
                            <div class="flex items-center gap-2">
                                <input type="number" wire:model="pulse" placeholder="78" class="w-24 p-2 text-center border border-slate-300 rounded-lg font-bold text-slate-900 bg-white">
                                <span class="text-slate-500 font-semibold text-xs">bpm</span>
                            </div>
                        </div>

                        <!-- SpO2: [ 98 ] % -->
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <label class="block text-slate-700 font-bold mb-1.5">SpO₂</label>
                            <div class="flex items-center gap-2">
                                <input type="number" wire:model="spo2" placeholder="98" class="w-24 p-2 text-center border border-slate-300 rounded-lg font-bold text-slate-900 bg-white">
                                <span class="text-slate-500 font-semibold text-xs">%</span>
                            </div>
                        </div>

                        <!-- Weight: [ 68.5 ] kg -->
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <label class="block text-slate-700 font-bold mb-1.5">Weight</label>
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.1" wire:model="weight" placeholder="68.5" class="w-24 p-2 text-center border border-slate-300 rounded-lg font-bold text-slate-900 bg-white">
                                <span class="text-slate-500 font-semibold text-xs">kg</span>
                            </div>
                        </div>

                        <!-- Temperature: [ 98.4 ] °F -->
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <label class="block text-slate-700 font-bold mb-1.5">Temperature</label>
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.1" wire:model="temperature" placeholder="98.4" class="w-24 p-2 text-center border border-slate-300 rounded-lg font-bold text-slate-900 bg-white">
                                <span class="text-slate-500 font-semibold text-xs">°F</span>
                            </div>
                        </div>

                        <!-- Respiratory Rate: [ 18 ] /min -->
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <label class="block text-slate-700 font-bold mb-1.5">Respiratory Rate</label>
                            <div class="flex items-center gap-2">
                                <input type="number" wire:model="respiratory_rate" placeholder="18" class="w-24 p-2 text-center border border-slate-300 rounded-lg font-bold text-slate-900 bg-white">
                                <span class="text-slate-500 font-semibold text-xs">/min</span>
                            </div>
                        </div>

                        <!-- RBS: [ 110 ] mg/dL -->
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <label class="block text-slate-700 font-bold mb-1.5">RBS Blood Sugar</label>
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.1" wire:model="rbs" placeholder="110" class="w-24 p-2 text-center border border-slate-300 rounded-lg font-bold text-slate-900 bg-white">
                                <span class="text-slate-500 font-semibold text-xs">mg/dL</span>
                            </div>
                        </div>
                    </div>

                    <!-- Fever checkbox -->
                    <div class="pt-1 flex items-center">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="fever" class="rounded text-blue-600 focus:ring-blue-500">
                            <span class="text-xs font-semibold text-slate-700">Fever Symptoms Present</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 pt-3 border-t border-slate-100 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showVitalsModal', false)" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-xs">Cancel</button>
                    <button type="button" wire:click="saveVitals" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg text-xs shadow">Save Vitals</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Complete Missing Profile Details at Arrival (Section 54) -->
    @if ($showProfileCompleteModal && $completingAppointment)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6">
                <h3 class="text-base font-bold text-slate-900 mb-1">Complete Patient Profile for Arrival</h3>
                <p class="text-xs text-slate-500 mb-4">{{ $completingAppointment->patient->name }} • Token #{{ $completingAppointment->formatted_token }}</p>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-0.5">Gender *</label>
                        <select wire:model="editSex" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                            <option value="MALE">Male</option>
                            <option value="FEMALE">Female</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-0.5">Street Address (without City) *</label>
                        <input type="text" wire:model="editAddress" placeholder="e.g. House 25, Lanka" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-0.5">City (Dedicated Field) *</label>
                        <input type="text" wire:model="editCity" placeholder="e.g. Varanasi" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showProfileCompleteModal', false)" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-xs">Cancel</button>
                    <button type="button" wire:click="saveProfileAndArrive" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow">Save & Mark Arrived</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: New Walk-in / Telephone Booking -->
    @if ($showNewBookingModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg p-6">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">New Patient Booking & Queue Token</h3>
                    <button type="button" wire:click="$set('showNewBookingModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-0.5">Booking Source *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="p-2.5 border rounded-xl flex items-center gap-2 cursor-pointer {{ $bookingSource === 'CLINIC' ? 'bg-teal-50 border-teal-500 font-bold text-teal-900' : 'border-slate-200' }}">
                                <input type="radio" wire:model="bookingSource" value="CLINIC">
                                <span>Clinic Walk-in</span>
                            </label>
                            <label class="p-2.5 border rounded-xl flex items-center gap-2 cursor-pointer {{ $bookingSource === 'TELEPHONIC' ? 'bg-amber-50 border-amber-500 font-bold text-amber-900' : 'border-slate-200' }}">
                                <input type="radio" wire:model="bookingSource" value="TELEPHONIC">
                                <span>Telephone Booking</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-0.5">Full Patient Name *</label>
                        <input type="text" wire:model="newPatientName" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('newPatientName') <span class="text-[10px] text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-medium text-slate-700 mb-0.5">Age (Years) *</label>
                            <input type="number" wire:model="newPatientAge" class="w-full p-2 border border-slate-300 rounded-lg">
                            @error('newPatientAge') <span class="text-[10px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-0.5">Gender *</label>
                            <select wire:model="newPatientSex" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                                <option value="MALE">Male</option>
                                <option value="FEMALE">Female</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-0.5">10-Digit Mobile Number *</label>
                        <input type="tel" wire:model="newPatientMobile" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('newPatientMobile') <span class="text-[10px] text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-0.5">Street Address (without City) *</label>
                        <input type="text" wire:model="newPatientAddress" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('newPatientAddress') <span class="text-[10px] text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-0.5">City (Dedicated Field) *</label>
                        <input type="text" wire:model="newPatientCity" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('newPatientCity') <span class="text-[10px] text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-0.5">Session *</label>
                        <select wire:model="newBookingSession" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                            <option value="MORNING">Morning Session (08:00–09:00)</option>
                            <option value="EVENING">Evening Session (17:00–19:00)</option>
                            <option value="SUNDAY_SINGLE">Sunday Session (09:00–11:00)</option>
                        </select>
                    </div>

                    {{-- Doctor Selection (Section 41-44) --}}
                    @if(count($activeDoctors) === 1)
                        <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-800">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Attending Doctor (Auto-Selected)</span>
                            <span class="font-bold">{{ \App\Services\DoctorNamePresenter::format($activeDoctors->first()->name) }}</span>
                            <span class="text-slate-500">({{ $activeDoctors->first()->specialization ?? $activeDoctors->first()->qualification }})</span>
                        </div>
                    @elseif(count($activeDoctors) > 1)
                        <div>
                            <label class="block font-medium text-slate-700 mb-0.5">Attending Doctor *</label>
                            <select wire:model="newBookingDoctorId" id="select_booking_doctor" class="w-full p-2 border border-slate-300 rounded-lg bg-white font-medium">
                                @foreach($activeDoctors as $doc)
                                    <option value="{{ $doc->id }}">{{ \App\Services\DoctorNamePresenter::format($doc->name) }} ({{ $doc->specialization ?? $doc->qualification }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="mt-6 pt-3 border-t border-slate-100 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showNewBookingModal', false)" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-xs">Cancel</button>
                    <button type="button" wire:click="createWalkinOrTelephone" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg text-xs shadow">Allocate Token &rarr;</button>
                </div>
            </div>
        </div>
    @endif
</div>
