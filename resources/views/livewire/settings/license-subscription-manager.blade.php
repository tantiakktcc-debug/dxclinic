<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">License & Subscription</h1>
                @if ($license)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider
                        {{ $license->status === 'ACTIVE' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : '' }}
                        {{ $license->status === 'TRIAL' ? 'bg-sky-100 text-sky-800 border border-sky-300' : '' }}
                        {{ $license->status === 'EXPIRED' ? 'bg-rose-100 text-rose-800 border border-rose-300' : '' }}
                        {{ $license->status === 'SUSPENDED' ? 'bg-amber-100 text-amber-800 border border-amber-300' : '' }}
                        {{ $license->status === 'REVOKED' ? 'bg-slate-100 text-slate-800 border border-slate-400' : '' }}">
                        {{ $license->status }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Authorized Tenant: <span class="font-mono font-bold text-slate-800">{{ $clinic?->clinic_code }}</span> • {{ $clinic?->name }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Asia/Kolkata Clock</span>
            </span>
        </div>
    </div>

    <!-- Status & Entitlement Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Plan Card -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Current Plan</div>
            <div class="mt-2">
                <div class="text-xl font-extrabold text-slate-900">{{ $license?->plan?->name ?? 'Standard License' }}</div>
                <div class="text-xs text-slate-500 mt-0.5">
                    Duration: {{ $license?->plan?->duration_months ?? 12 }} Months
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                <span>License Key</span>
                <span class="font-mono font-semibold">{{ $license?->masked_key ?? '-' }}</span>
            </div>
        </div>

        <!-- Validity Period Card -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Validity Period</div>
            <div class="mt-2">
                <div class="text-sm font-semibold text-slate-600">Valid From</div>
                <div class="text-base font-bold text-slate-900 font-mono">
                    {{ $license?->start_date ? $license->start_date->format('d-m-Y') : '-' }}
                </div>
                <div class="text-sm font-semibold text-slate-600 mt-2">Valid Until</div>
                <div class="text-base font-black text-slate-900 font-mono">
                    {{ $license?->end_date ? $license->end_date->format('d-m-Y') : '-' }}
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                <span>Coverage</span>
                <span>Annual / Term</span>
            </div>
        </div>

        <!-- Remaining Days Card -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Remaining Time</div>
            <div class="mt-2">
                <div class="text-2xl font-black {{ $remainingDays > 30 ? 'text-emerald-600' : ($remainingDays > 0 ? 'text-amber-600' : 'text-rose-600') }} font-mono">
                    {{ $remainingFormatted }}
                </div>
                <div class="text-xs text-slate-500 mt-1">
                    {{ $remainingDays > 0 ? 'Continuous active service' : 'Subscription period elapsed' }}
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                <span>Authoritative Zone</span>
                <span class="font-mono">IST (UTC+5:30)</span>
            </div>
        </div>

        <!-- Doctor Seats Capacity Card -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Doctor Seat Capacity</div>
            <div class="mt-2">
                <div class="text-2xl font-black text-indigo-600 font-mono">
                    {{ $clinic?->activeDoctorCount() }} / {{ $clinic?->totalDoctorCapacity() }} <span class="text-xs font-bold text-slate-500">Seats</span>
                </div>
                <div class="text-xs text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                    <span>Inc: <strong>{{ $license?->included_doctor_seats ?? 1 }}</strong></span>
                    <span>•</span>
                    <span>Active Addons: <strong>{{ $activeAddonsCount }}</strong></span>
                    @if($pendingRequestedSeats > 0)
                        <span>•</span>
                        <span class="text-amber-600 font-bold">Pending: <strong>+{{ $pendingRequestedSeats }}</strong></span>
                    @endif
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold {{ $clinic?->availableDoctorSeats() > 0 ? 'text-emerald-600' : 'text-amber-600' }}">
                <span>Available Seats</span>
                <span>{{ $clinic?->availableDoctorSeats() }} free</span>
            </div>
        </div>
    </div>

    <!-- Feedback Banners -->
    @if($purchaseSuccessMessage)
        <div id="seat_purchase_success_banner" class="p-5 bg-emerald-50 border border-emerald-300 rounded-2xl text-emerald-900 text-sm shadow-xs space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="font-bold text-base">{{ $purchaseSuccessMessage }}</span>
                </div>
                <button type="button" wire:click="$set('purchaseSuccessMessage', null)" class="text-emerald-700 hover:text-emerald-900 font-bold text-lg">&times;</button>
            </div>
            <div class="p-3 bg-white/80 rounded-xl border border-emerald-200 text-xs text-slate-700 space-y-1">
                <div class="font-bold text-emerald-800">Application Developer Contact for Payment & Activation:</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                    <div><strong>Developer:</strong> Kundan Kumar (Nexora Solutions)</div>
                    <div><strong>Location:</strong> Varanasi, Uttar Pradesh, India</div>
                    <div><strong>Phone / WhatsApp:</strong> <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('dxclinic.developer_phone') ?: '+919876543210') }}" class="font-mono text-emerald-700 font-bold underline">{{ config('dxclinic.developer_phone') ?: '+91 98765 43210' }}</a></div>
                    <div><strong>Email:</strong> <a href="mailto:{{ config('dxclinic.developer_email', 'contact@nexorasolutionsind.co.in') }}" class="font-mono text-emerald-700 font-bold underline">{{ config('dxclinic.developer_email', 'contact@nexorasolutionsind.co.in') }}</a></div>
                    <div class="sm:col-span-2"><strong>Website:</strong> <a href="https://nexorasolutionsind.co.in" target="_blank" class="text-emerald-700 underline">https://nexorasolutionsind.co.in</a></div>
                </div>
            </div>
        </div>
    @endif

    @if($pendingSeatRequest)
        <div id="seat_pending_request_banner" class="p-4 bg-amber-50 border border-amber-300 rounded-2xl text-amber-900 text-sm shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-200 text-amber-800 flex items-center justify-center shrink-0 font-bold">
                    ⏳
                </div>
                <div>
                    <div class="font-bold">Pending Doctor Seat Request: #{{ $pendingSeatRequest->request_number }} (+{{ $pendingRequestedSeats }} seat(s))</div>
                    <div class="text-xs text-amber-700 mt-0.5">Please contact the application developer to complete payment and activate your additional doctor seat.</div>
                </div>
            </div>
            <div class="text-right text-xs shrink-0">
                <span class="px-2.5 py-1 rounded-full font-bold bg-amber-100 text-amber-800 border border-amber-300 uppercase">
                    {{ $pendingSeatRequest->status }}
                </span>
            </div>
        </div>
    @endif

    @if($purchaseErrorMessage)
        <div id="seat_purchase_error_banner" class="p-4 bg-rose-50 border border-rose-300 rounded-2xl text-rose-900 text-sm flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                <span class="font-bold">{{ $purchaseErrorMessage }}</span>
            </div>
            <button type="button" wire:click="$set('purchaseErrorMessage', null)" class="text-rose-700 hover:text-rose-900 font-bold">&times;</button>
        </div>
    @endif

    <!-- Doctor Seat Expansion / Request Workflow (Part 5) -->
    <div id="doctor_seat_addon_section" class="bg-gradient-to-br from-indigo-50/50 via-white to-sky-50/50 p-6 rounded-2xl border border-indigo-200/80 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-indigo-100">
            <div>
                <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Request Additional Doctor Seats</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Scale your clinic by requesting additional consultant doctor seats. Entitlement activates upon fulfillment by the developer.</p>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-500 block">Unit Seat Price</span>
                <span class="text-sm font-bold text-indigo-700">₹{{ number_format($pricePerSeat, 2) }} / year</span>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div class="bg-white p-3 rounded-xl border border-slate-200">
                <span class="text-slate-400 block font-semibold">Current Plan</span>
                <span class="font-bold text-slate-800 text-sm mt-0.5 block" id="current_plan_name">{{ $license?->plan?->name ?? 'Standard License' }}</span>
                <span class="text-[11px] text-slate-500">Included: {{ $license?->included_doctor_seats ?? 1 }} seat(s)</span>
            </div>
            <div class="bg-white p-3 rounded-xl border border-slate-200">
                <span class="text-slate-400 block font-semibold">Current Capacity</span>
                <span class="font-bold text-slate-800 text-sm mt-0.5 block" id="current_total_capacity">{{ $currentCapacity }} Seats</span>
                <span class="text-[11px] text-slate-500">Used: {{ $clinic?->activeDoctorCount() }} | Avail: {{ $clinic?->availableDoctorSeats() }}</span>
            </div>
            <div class="bg-white p-3 rounded-xl border border-slate-200">
                <span class="text-slate-400 block font-semibold">Available Seats</span>
                <span class="font-bold {{ $clinic?->availableDoctorSeats() > 0 ? 'text-emerald-700' : 'text-amber-700' }} text-sm mt-0.5 block" id="available_seats_count">
                    {{ $clinic?->availableDoctorSeats() }} Available
                </span>
                <span class="text-[11px] text-slate-500">Active Addons: {{ $activeAddonsCount }}</span>
            </div>
            <div class="bg-white p-3 rounded-xl border border-slate-200">
                <span class="text-slate-400 block font-semibold">Requested Quantity</span>
                <span class="font-bold text-indigo-700 text-sm mt-0.5 block" id="resulting_total_capacity">
                    +{{ $addonSeatsCount }} Seat(s)
                </span>
                <span class="text-[11px] text-slate-500">New Capacity if approved: {{ $resultingCapacity }}</span>
            </div>
        </div>

        <!-- Interactive Request Bar -->
        <div class="bg-white p-4 rounded-xl border border-indigo-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <label for="input_addon_seats_count" class="text-xs font-bold text-slate-700">Seats to Request:</label>
                <div class="flex items-center">
                    <button type="button" wire:click="$set('addonSeatsCount', {{ max(1, $addonSeatsCount - 1) }})" class="w-8 h-8 rounded-l-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold border border-slate-300 flex items-center justify-center cursor-pointer">-</button>
                    <input type="number" min="1" max="10" id="input_addon_seats_count" wire:model.live="addonSeatsCount" class="w-14 h-8 text-center text-xs font-bold border-y border-slate-300 focus:outline-none focus:ring-0">
                    <button type="button" wire:click="$set('addonSeatsCount', {{ min(10, $addonSeatsCount + 1) }})" class="w-8 h-8 rounded-r-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold border border-slate-300 flex items-center justify-center cursor-pointer">+</button>
                </div>
                <div class="text-xs text-slate-600">
                    Estimated Amount: <strong class="text-slate-900 text-sm font-mono" id="total_payable_amount">₹{{ number_format($totalPayable, 2) }}</strong>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($canPurchaseSeats)
                    <button type="button"
                            wire:click="requestDoctorSeatAddon"
                            id="btn_purchase_doctor_seat"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>Request Additional Doctor Seat</span>
                    </button>
                @else
                    <span class="text-xs text-slate-400 italic">Administrator authorization required to request seats</span>
                @endif
            </div>
        </div>

        <!-- Contact Developer Note -->
        <div class="p-3 bg-indigo-50/60 rounded-xl border border-indigo-100 text-xs text-indigo-900 flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Doctor-seat expansion requires authorization and payment arrangement with the application developer.</span>
            </div>
            <div class="font-bold">
                Kundan Kumar (Nexora Solutions) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('dxclinic.developer_phone') ?: '+919876543210') }}" class="underline">{{ config('dxclinic.developer_phone') ?: '+91 98765 43210' }}</a> · <a href="mailto:{{ config('dxclinic.developer_email', 'contact@nexorasolutionsind.co.in') }}" class="underline">{{ config('dxclinic.developer_email', 'contact@nexorasolutionsind.co.in') }}</a>
            </div>
        </div>
    </div>

    <!-- Active Doctor Seats Allocation -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Licensed Doctor Seats Allocation</h2>
                <p class="text-xs text-slate-500 mt-0.5">Currently registered active doctors bound to your licensed seats.</p>
            </div>
            <span class="text-xs font-bold px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200">
                {{ $doctors->count() }} of {{ $clinic?->totalDoctorCapacity() }} Seats Utilized
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs font-bold uppercase tracking-wider border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Seat #</th>
                        <th class="px-6 py-3">Doctor Name</th>
                        <th class="px-6 py-3">Specialization</th>
                        <th class="px-6 py-3">Mobile</th>
                        <th class="px-6 py-3">Seat Type</th>
                        <th class="px-6 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($doctors as $idx => $doc)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-3.5 font-mono font-bold text-slate-600">
                                #{{ $idx + 1 }}
                            </td>
                            <td class="px-6 py-3.5 font-bold text-slate-900">
                                {{ $doc->name }}
                            </td>
                            <td class="px-6 py-3.5 text-slate-600">
                                {{ $doc->specialization ?? 'General Physician' }}
                            </td>
                            <td class="px-6 py-3.5 font-mono text-slate-600">
                                {{ $doc->phone ?? '-' }}
                            </td>
                            <td class="px-6 py-3.5">
                                @if ($idx < ($license?->included_doctor_seats ?? 1))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                        Included (Seat 1)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                        Paid Add-on (₹2,999/yr)
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                    ACTIVE
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                No active doctors registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Renewal & Billing Operational Notice -->
    <div class="bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-2xl p-6 shadow-sm border border-slate-800">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center shrink-0 border border-white/20">
                <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-white">Direct Renewal & Doctor Seat Expansion Policy</h3>
                <p class="text-sm text-slate-300 mt-1 leading-relaxed">
                    DxClinic subscription renewals and doctor seat expansions are paid outside the application directly to the software provider.
                    Once your payment is confirmed, the Superadmin updates your clinic's subscription atomically.
                </p>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="bg-white/5 border border-white/10 rounded-xl p-3">
                        <div class="font-bold text-sky-400">✓ No License Keys Needed</div>
                        <div class="text-slate-400 mt-0.5">Renewals never require entering license keys or codes.</div>
                    </div>
                    <div class="bg-white/5 border border-white/10 rounded-xl p-3">
                        <div class="font-bold text-emerald-400">✓ Zero User Disruption</div>
                        <div class="text-slate-400 mt-0.5">Existing credentials and settings continue working seamlessly.</div>
                    </div>
                    <div class="bg-white/5 border border-white/10 rounded-xl p-3">
                        <div class="font-bold text-amber-400">✓ Additional Doctor Seats</div>
                        <div class="text-slate-400 mt-0.5">Standard ₹2,999/doctor/year with pro-rata remaining time billing.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
