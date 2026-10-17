<div class="py-6 px-4 sm:px-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between pb-6 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Consultation Billing & Doctor Pad</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage new patient arrivals, view historical billing records, issue receipts, and print physical doctor pads.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('operator.queue', ['public_slug' => \App\Services\TenantContext::clinic()?->public_slug ?? 'clinic']) }}" class="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold">
                &larr; Arrival Queue
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="mt-6 flex border-b border-slate-200 gap-2">
        <button type="button" wire:click="setTab('new')"
            class="px-5 py-2.5 text-xs font-bold rounded-t-xl transition-all border-b-2 flex items-center gap-2 {{ $activeTab === 'new' ? 'border-teal-600 text-teal-700 bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Consultation Billing
        </button>
        <button type="button" wire:click="setTab('history')"
            class="px-5 py-2.5 text-xs font-bold rounded-t-xl transition-all border-b-2 flex items-center gap-2 {{ $activeTab === 'history' ? 'border-teal-600 text-teal-700 bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Consultation Billing History
        </button>
        <button type="button" wire:click="setTab('doctor_pads')"
            class="px-5 py-2.5 text-xs font-bold rounded-t-xl transition-all border-b-2 flex items-center gap-2 {{ $activeTab === 'doctor_pads' ? 'border-teal-600 text-teal-700 bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Doctor Pad History
        </button>
    </div>

    <!-- Alert Notifications -->
    @if (session()->has('success'))
        <div class="my-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800 font-bold">&times;</button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="my-4 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs flex items-center justify-between">
            <span>{{ session('error') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-800 font-bold">&times;</button>
        </div>
    @endif

    <!-- TAB 1: NEW CONSULTATION BILLING -->
    @if ($activeTab === 'new')
        <div class="my-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Billing / Patient Workspace -->
            <div class="lg:col-span-2 space-y-6">
                @if ($appointment)
                    <!-- Patient & Token Identity Card -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 rounded-2xl bg-teal-50 border border-teal-200 flex flex-col items-center justify-center text-teal-800">
                                    <span class="text-[9px] uppercase font-bold text-teal-600">Token</span>
                                    <span class="text-2xl font-black">{{ $appointment->formatted_token }}</span>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-slate-900">{{ $appointment->patient->name }}</h2>
                                    <p class="text-xs text-slate-500 font-medium">
                                        {{ $appointment->patient->patient_id }} • {{ $appointment->patient->age }} Y / {{ $appointment->patient->sex }} • 📞 {{ $appointment->patient->mobile }}
                                    </p>
                                    <p class="text-xs text-slate-700 mt-1">
                                        <span class="font-semibold text-slate-900">Address:</span> {{ $appointment->patient->formatted_address }}
                                    </p>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $appointment->booking_source === 'ONLINE' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-800' }}">
                                {{ $appointment->booking_source }}
                            </span>
                        </div>

                        <!-- Vitals Preview -->
                        @if ($visit && $visit->vital)
                            <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-3 sm:grid-cols-6 gap-2 text-center text-xs">
                                <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <div class="text-[10px] text-slate-400">BP</div>
                                    <div class="font-bold text-slate-800">{{ $visit->vital->bp_systolic ?? '-' }}/{{ $visit->vital->bp_diastolic ?? '-' }}</div>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <div class="text-[10px] text-slate-400">Pulse</div>
                                    <div class="font-bold text-slate-800">{{ $visit->vital->pulse ?? '-' }} bpm</div>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <div class="text-[10px] text-slate-400">SpO2</div>
                                    <div class="font-bold text-slate-800">{{ $visit->vital->spo2 ?? '-' }}%</div>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <div class="text-[10px] text-slate-400">Temp</div>
                                    <div class="font-bold text-slate-800">{{ $visit->vital->temperature ?? '-' }}°F</div>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <div class="text-[10px] text-slate-400">Weight</div>
                                    <div class="font-bold text-slate-800">{{ $visit->vital->weight ?? '-' }} kg</div>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <div class="text-[10px] text-slate-400">RBS</div>
                                    <div class="font-bold text-slate-800">{{ $visit->vital->rbs ?? '-' }}</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Consultation Fee Payment Card -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-5">
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Consultation Fee</h3>
                                <p class="text-xs text-slate-500">Bill Number: {{ $bill->bill_number }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-slate-400 block font-medium">Net Fee Payable</span>
                                <span class="text-3xl font-black text-slate-900">₹{{ number_format($bill->net_amount, 2) }}</span>
                            </div>
                        </div>

                        <!-- Fee Breakdown -->
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-2 max-w-sm">
                            <div class="flex justify-between text-slate-600">
                                <span>Gross Fee</span>
                                <span class="font-semibold text-slate-800">₹{{ number_format($bill->gross_amount, 2) }}</span>
                            </div>
                            @if ($bill->discount_amount > 0)
                                <div class="flex justify-between text-emerald-600 font-medium">
                                    <span>Discount</span>
                                    <span>-₹{{ number_format($bill->discount_amount, 2) }}</span>
                                </div>
                            @endif
                            @if ($bill->waiver_amount > 0)
                                <div class="flex justify-between text-indigo-600 font-medium">
                                    <span>Waiver ({{ $bill->waiver_type ?? 'Staff' }})</span>
                                    <span>-₹{{ number_format($bill->waiver_amount, 2) }}</span>
                                </div>
                            @endif
                            <div class="pt-2 border-t border-slate-200 flex justify-between font-bold text-slate-900">
                                <span>Net Amount</span>
                                <span>₹{{ number_format($bill->net_amount, 2) }}</span>
                            </div>
                        </div>

                        <!-- Settlement Status / Form -->
                        @if ($payment && $payment->status === 'SETTLED')
                            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">✓</span>
                                    <div>
                                        <div class="text-xs font-bold text-emerald-900 flex items-center gap-2">
                                            <span>Payment Received ({{ $payment->payment_method }})</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-200 text-emerald-800">PAID</span>
                                        </div>
                                        <div class="text-[11px] text-emerald-700">Receipt: {{ $payment->receipt?->receipt_number ?? 'Generated' }} • Payment #{{ $payment->payment_number }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('print.consultation.receipt', $bill->id) }}" target="_blank"
                                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold">
                                        Print Receipt (A5)
                                    </a>
                                </div>
                            </div>
                        @else
                            <!-- Payment Input -->
                            <div class="space-y-4">
                                <label class="block text-xs font-bold text-slate-700">Select Payment Method</label>
                                <div class="grid grid-cols-2 gap-4 max-w-sm">
                                    <label class="p-3 border rounded-xl flex items-center gap-3 cursor-pointer transition-all {{ $paymentMethod === 'CASH' ? 'border-teal-500 bg-teal-50 text-teal-900 font-bold' : 'border-slate-200' }}">
                                        <input type="radio" wire:model.live="paymentMethod" value="CASH" class="text-teal-600">
                                        <span class="text-xs">Physical Cash</span>
                                    </label>
                                    <label class="p-3 border rounded-xl flex items-center gap-3 cursor-pointer transition-all {{ $paymentMethod === 'UPI' ? 'border-teal-500 bg-teal-50 text-teal-900 font-bold' : 'border-slate-200' }}">
                                        <input type="radio" wire:model.live="paymentMethod" value="UPI" class="text-teal-600">
                                        <span class="text-xs">Manual UPI (Soundbox)</span>
                                    </label>
                                </div>

                                @if ($paymentMethod === 'UPI')
                                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-xl space-y-3 max-w-md">
                                        <div>
                                            <label class="block text-[11px] font-bold text-indigo-900">Credited Bank Account</label>
                                            <select wire:model="selectedBankAccountId" class="mt-1 w-full text-xs border-indigo-200 rounded-lg p-2">
                                                @foreach ($bankAccounts as $ba)
                                                    <option value="{{ $ba->id }}">{{ $ba->bank_name }} - {{ $ba->account_number }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-indigo-900">UPI Ref / UTR (Optional)</label>
                                            <input type="text" wire:model="referenceNumber" placeholder="e.g. 423456789012" class="mt-1 w-full text-xs border-indigo-200 rounded-lg p-2">
                                        </div>
                                        <label class="flex items-center gap-2 pt-2 border-t border-indigo-100 text-xs font-semibold text-indigo-900 cursor-pointer">
                                            <input type="checkbox" wire:model="soundboxConfirmed" class="rounded text-indigo-600">
                                            <span>I confirm hearing Paytm/GooglePay physical Soundbox announcement</span>
                                        </label>
                                    </div>
                                @endif

                                <button type="button" wire:click="settlePayment"
                                    class="w-full sm:w-auto px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-sm">
                                    Settle Payment & Generate Receipt
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Print Prescription Pad Header -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Pre-Printed Doctor Pad Header</h3>
                            <p class="text-xs text-slate-500">Prints exact patient overlay (Name, Age/Sex, Address, Date, Token, Vitals) for physical prescription pads.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('print.doctor.pad', $appointment->id) }}" target="_blank"
                                class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-800 rounded-xl text-xs font-semibold">
                                Print Pad Header
                            </a>
                            <a href="{{ route('pdf.doctor-pad', $appointment->id) }}" target="_blank"
                                class="px-4 py-2 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-semibold">
                                Download PDF
                            </a>
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-2xl p-12 border border-slate-200 text-center text-slate-500 text-xs space-y-3">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400 font-bold">📋</div>
                        <h3 class="text-sm font-bold text-slate-800">No Patient Encounter Selected</h3>
                        <p class="max-w-xs mx-auto">Select a patient from today's arrivals on the right, or search historical billing records in the History tab.</p>
                    </div>
                @endif
            </div>

            <!-- Recent Arrivals Sidebar -->
            <div class="space-y-4">
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">Today's Arrivals (OPD Queue)</h3>
                    <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                        @forelse ($recentAppointments as $appItem)
                            <div wire:click="loadAppointment({{ $appItem->id }})"
                                class="p-3 rounded-xl border cursor-pointer transition-all {{ $appointmentId === $appItem->id ? 'border-teal-500 bg-teal-50/40 shadow-xs' : 'border-slate-100 hover:border-slate-200 bg-slate-50/50' }}">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-900">{{ $appItem->patient->name }}</span>
                                    <span class="text-[10px] font-black px-2 py-0.5 rounded bg-teal-100 text-teal-800">Token {{ $appItem->token_number }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-1 flex justify-between">
                                    <span>{{ $appItem->patient->patient_id }}</span>
                                    <span class="{{ $appItem->consultationBill?->status === 'PAID' ? 'text-emerald-600 font-bold' : 'text-amber-600 font-bold' }}">
                                        {{ $appItem->consultationBill?->status ?? 'UNBILLED' }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 py-4 text-center">No arrivals recorded today.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: CONSULTATION BILLING HISTORY -->
    @if ($activeTab === 'history')
        <div class="my-6 space-y-6">
            <!-- Filter Bar -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Date From</label>
                        <input type="date" wire:model.live="dateFrom" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Date To</label>
                        <input type="date" wire:model.live="dateTo" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Search Patient</label>
                        <input type="text" wire:model.live.debounce.300ms="searchPatient" placeholder="Name, ID, Mobile" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bill Number</label>
                        <input type="text" wire:model.live.debounce.300ms="searchBillNumber" placeholder="e.g. CBL-..." class="w-full border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Doctor</label>
                        <select wire:model.live="filterDoctorId" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            <option value="">All Doctors</option>
                            @foreach ($doctors as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Status</label>
                        <select wire:model.live="filterStatus" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            <option value="">All Statuses</option>
                            <option value="PAID">PAID</option>
                            <option value="PENDING">PENDING</option>
                            <option value="REFUNDED">REFUNDED</option>
                            <option value="CANCELLED">CANCELLED</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Payment Method</label>
                        <select wire:model.live="filterPaymentMethod" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            <option value="">All Methods</option>
                            <option value="CASH">CASH</option>
                            <option value="UPI">UPI</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Token</label>
                        <input type="text" wire:model.live.debounce.300ms="searchToken" placeholder="Token #" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div class="flex items-end">
                        <button type="button" wire:click="resetFilters" class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold rounded-xl text-xs w-full">
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Historical Bills Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Historical Consultation Bills</h3>
                    <span class="text-xs text-slate-400">Total: {{ $historyBills->total() }} records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="p-3">Bill No</th>
                                <th class="p-3">Date</th>
                                <th class="p-3">Token</th>
                                <th class="p-3">Patient</th>
                                <th class="p-3">Doctor</th>
                                <th class="p-3 text-right">Original Net</th>
                                <th class="p-3 text-right">Refunded</th>
                                <th class="p-3 text-right">Retained</th>
                                <th class="p-3 text-center">Status</th>
                                <th class="p-3">Method</th>
                                <th class="p-3">Receipt</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse ($historyBills as $hBill)
                                @php
                                    $refunded = $hBill->refunded_amount;
                                    $retained = $hBill->retained_amount;
                                    $isPartiallyRefunded = $refunded > 0 && $retained > 0;
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-3 font-semibold text-slate-900">{{ $hBill->bill_number }}</td>
                                    <td class="p-3">{{ $hBill->bill_date?->format('d M Y') }}</td>
                                    <td class="p-3 font-bold text-teal-700">#{{ $hBill->appointment?->token_number ?? '-' }}</td>
                                    <td class="p-3">
                                        <div class="font-bold text-slate-900">{{ $hBill->patient->name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $hBill->patient->patient_id }}</div>
                                    </td>
                                    <td class="p-3">{{ $hBill->doctor?->name ?? 'OPD Doctor' }}</td>
                                    <td class="p-3 text-right font-medium">₹{{ number_format($hBill->net_amount, 2) }}</td>
                                    <td class="p-3 text-right text-rose-600 font-medium">
                                        {{ $refunded > 0 ? '-₹' . number_format($refunded, 2) : '-' }}
                                    </td>
                                    <td class="p-3 text-right font-bold text-slate-900">₹{{ number_format($retained, 2) }}</td>
                                    <td class="p-3 text-center">
                                        @if ($hBill->status === 'REFUNDED')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">REFUNDED</span>
                                        @elseif ($isPartiallyRefunded)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">PARTIAL REFUND</span>
                                        @elseif ($hBill->status === 'PAID')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">PAID</span>
                                        @elseif ($hBill->status === 'PENDING')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-800">PENDING</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">{{ $hBill->status }}</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-[11px] font-medium">{{ $hBill->payment?->payment_method ?? '-' }}</td>
                                    <td class="p-3 text-[11px]">{{ $hBill->payment?->receipt?->receipt_number ?? '-' }}</td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" wire:click="viewBillDetail({{ $hBill->id }})" title="View Historical Details"
                                                class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold">
                                                View
                                            </button>
                                            @if ($hBill->status !== 'CANCELLED' && $hBill->status !== 'REFUNDED')
                                                <button type="button" wire:click="openModifyModal({{ $hBill->id }})" title="Edit / Controlled Modification"
                                                    class="px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-[11px] font-semibold">
                                                    Edit
                                                </button>
                                            @endif
                                            @if ($hBill->appointment_id)
                                                <a href="{{ route('print.doctor.pad', $hBill->appointment_id) }}" target="_blank" title="View / Reprint Doctor Pad"
                                                    class="px-2 py-1 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold">
                                                    View Pad
                                                </a>
                                            @endif
                                            <button type="button" wire:click="viewAuditHistory({{ $hBill->id }})" title="View Audit Trail / History"
                                                class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-[11px] font-semibold">
                                                View Changes
                                            </button>
                                            @if ($hBill->status !== 'CANCELLED' && $hBill->status !== 'REFUNDED')
                                                <button type="button" wire:click="openModifyModal({{ $hBill->id }})" title="Controlled Modification / Refund"
                                                    class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg text-[11px] font-semibold">
                                                    Refund
                                                </button>
                                            @endif
                                            @if ($hBill->payment)
                                                <a href="{{ route('print.consultation.receipt', $hBill->id) }}" target="_blank" title="Print A5 Receipt"
                                                    class="px-2 py-1 bg-teal-50 hover:bg-teal-100 text-teal-800 rounded-lg text-[11px] font-semibold">
                                                    Receipt
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="p-8 text-center text-slate-400">No consultation bills match the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-slate-100">
                    {{ $historyBills->links() }}
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: DOCTOR PAD HISTORY -->
    @if ($activeTab === 'doctor_pads')
        <div class="my-6 space-y-6">
            <!-- Filter Bar -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Date From</label>
                        <input type="date" wire:model.live="dpDateFrom" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Date To</label>
                        <input type="date" wire:model.live="dpDateTo" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Search Patient</label>
                        <input type="text" wire:model.live.debounce.300ms="dpSearchPatient" placeholder="Name, ID, Mobile" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Doctor</label>
                        <select wire:model.live="dpDoctorId" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            <option value="">All Doctors</option>
                            @foreach ($doctors as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Doctor Pad Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Doctor Pad Print & Reprint Register</h3>
                        <p class="text-[11px] text-slate-500">Reprinting is 100% idempotent and creates zero payments, receipts, journals, or tokens.</p>
                    </div>
                    <span class="text-xs text-slate-400">Total: {{ $doctorPadAppointments->total() }} records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="p-3">Date</th>
                                <th class="p-3">Token</th>
                                <th class="p-3">Patient</th>
                                <th class="p-3">Doctor</th>
                                <th class="p-3">Template / Version</th>
                                <th class="p-3 text-center">Vitals</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse ($doctorPadAppointments as $dpApp)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-3 font-semibold text-slate-900">{{ $dpApp->appointment_date?->format('d M Y') }}</td>
                                    <td class="p-3 font-black text-teal-700">#{{ $dpApp->token_number }}</td>
                                    <td class="p-3">
                                        <div class="font-bold text-slate-900">{{ $dpApp->patient->name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $dpApp->patient->patient_id }} • {{ $dpApp->patient->age }}Y/{{ $dpApp->patient->sex }}</div>
                                    </td>
                                    <td class="p-3 font-medium text-slate-800">{{ $dpApp->doctor?->name ?? 'OPD Doctor' }}</td>
                                    <td class="p-3">
                                        @if ($dpApp->prescriptionPadTemplate)
                                            <div class="font-semibold text-slate-900">{{ $dpApp->prescription_template_name ?? $dpApp->prescriptionPadTemplate->name }}</div>
                                            <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-bold bg-blue-100 text-blue-800">v{{ $dpApp->prescriptionPadTemplate->version }}</span>
                                        @elseif ($dpApp->is_legacy_pad)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">Legacy Pad</span>
                                        @else
                                            <span class="text-slate-400 text-xs">Default Active</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        @if ($dpApp->visit?->vital)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Recorded</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">No Vitals</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('print.doctor.pad', $dpApp->id) }}" target="_blank" title="View / Print Overlay"
                                                class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-semibold">
                                                View
                                            </a>
                                            <a href="{{ route('print.doctor.pad', ['appointmentId' => $dpApp->id, 'mode' => 'proof']) }}" target="_blank" title="Preview Proof Pad"
                                                class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg text-xs font-semibold">
                                                Preview
                                            </a>
                                            <a href="{{ route('pdf.doctor-pad', $dpApp->id) }}" target="_blank" title="Reprint Production PDF"
                                                class="px-2 py-1 bg-slate-900 hover:bg-black text-white rounded-lg text-xs font-semibold">
                                                Reprint
                                            </a>
                                            @if ($dpApp->prescription_pad_template_id)
                                                <a href="{{ route('settings.prescription-pad-designer', $dpApp->prescription_pad_template_id) }}" target="_blank" title="View Template Configuration"
                                                    class="px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold">
                                                    View Template
                                                </a>
                                            @endif
                                            @if ($dpApp->consultationBill)
                                                <button type="button" wire:click="viewAuditHistory({{ $dpApp->consultationBill->id }})" title="View Changes / Audit Trail"
                                                    class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-semibold">
                                                    View Changes
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-400">No patient encounters found for pad printing.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-slate-100">
                    {{ $doctorPadAppointments->links() }}
                </div>
            </div>
        </div>
    @endif

    <!-- DETAIL MODAL -->
    @if ($showDetailModal && $detailBill)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-2xl w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Historical Consultation Bill Detail</h3>
                        <p class="text-xs text-slate-500">{{ $detailBill->bill_number }} • Date: {{ $detailBill->bill_date?->format('d M Y') }}</p>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="text-slate-400 hover:text-slate-700 text-xl font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto text-xs text-slate-700">
                    <!-- Patient & Encounter -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Patient</span>
                            <span class="font-bold text-slate-900">{{ $detailBill->patient->name }}</span>
                            <span class="text-[10px] text-slate-500 block">{{ $detailBill->patient->patient_id }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Doctor</span>
                            <span class="font-semibold text-slate-800">{{ $detailBill->doctor?->name ?? 'OPD Doctor' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Token</span>
                            <span class="font-black text-teal-700">#{{ $detailBill->appointment?->token_number ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Current Status</span>
                            <span class="font-bold px-2 py-0.5 rounded text-[10px] {{ $detailBill->status === 'REFUNDED' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ $detailBill->status }}
                            </span>
                        </div>
                    </div>

                    <!-- Financial Ledger Summary -->
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Original Settlement vs Adjustments</h4>
                        <div class="border border-slate-200 rounded-2xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <tbody class="divide-y divide-slate-100">
                                    <tr class="bg-slate-50/50">
                                        <td class="p-3 text-slate-500">Original Gross Amount</td>
                                        <td class="p-3 text-right font-medium">₹{{ number_format($detailBill->gross_amount, 2) }}</td>
                                    </tr>
                                    @if ($detailBill->discount_amount > 0)
                                        <tr>
                                            <td class="p-3 text-slate-500">Original Discount Given</td>
                                            <td class="p-3 text-right text-emerald-600">-₹{{ number_format($detailBill->discount_amount, 2) }}</td>
                                        </tr>
                                    @endif
                                    @if ($detailBill->waiver_amount > 0)
                                        <tr>
                                            <td class="p-3 text-slate-500">Original Waiver Amount ({{ $detailBill->waiver_type }})</td>
                                            <td class="p-3 text-right text-indigo-600">-₹{{ number_format($detailBill->waiver_amount, 2) }}</td>
                                        </tr>
                                    @endif
                                    <tr class="bg-slate-100/70 font-semibold text-slate-900">
                                        <td class="p-3">Original Net Billed & Settled</td>
                                        <td class="p-3 text-right font-bold">₹{{ number_format($detailBill->net_amount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 text-slate-500">Total Processed Refunds</td>
                                        <td class="p-3 text-right text-rose-600 font-bold">
                                            -₹{{ number_format($detailBill->refunded_amount, 2) }}
                                        </td>
                                    </tr>
                                    <tr class="bg-teal-50/60 font-bold text-teal-950 text-sm">
                                        <td class="p-3">Current Net Retained Amount</td>
                                        <td class="p-3 text-right font-black">₹{{ number_format($detailBill->retained_amount, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    @if ($detailBill->payment)
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Settled Payment Facts</h4>
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-bold">Payment #</span>
                                    <span class="font-semibold text-slate-800">{{ $detailBill->payment->payment_number }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-bold">Method</span>
                                    <span class="font-semibold text-slate-800">{{ $detailBill->payment->payment_method }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-bold">Receipt #</span>
                                    <span class="font-semibold text-slate-800">{{ $detailBill->payment->receipt?->receipt_number ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Refunds Section -->
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Refunds & Adjustments History</h4>
                        @if ($detailBill->refunds->count() > 0)
                            <div class="space-y-3">
                                @foreach ($detailBill->refunds as $ref)
                                    <div class="p-4 rounded-2xl border {{ $ref->status === 'PROCESSED' ? 'bg-rose-50/40 border-rose-200' : 'bg-amber-50/40 border-amber-200' }} space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900">{{ $ref->refund_number }}</span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $ref->status === 'PROCESSED' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                                                    {{ $ref->status }}
                                                </span>
                                            </div>
                                            <span class="text-sm font-black text-rose-700">₹{{ number_format($ref->refund_amount, 2) }} ({{ $ref->refund_method }})</span>
                                        </div>
                                        <div class="text-xs text-slate-600">
                                            <span class="font-semibold text-slate-800">Reason:</span> {{ $ref->reason }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 flex flex-wrap gap-4 pt-1 border-t border-slate-200/60">
                                            <span>Requested: {{ $ref->requested_at?->format('d M Y, h:i A') }} by {{ $ref->requestedBy?->name ?? 'Staff' }}</span>
                                            @if ($ref->processed_at)
                                                <span>Settled: {{ $ref->processed_at?->format('d M Y, h:i A') }} by {{ $ref->processedBy?->name ?? 'Admin' }}</span>
                                            @endif
                                        </div>
                                        @if ($ref->status === 'REQUESTED' && auth()->user()?->isAdmin())
                                            <div class="pt-2 flex justify-end gap-2">
                                                <button type="button" wire:click="openProcessRefundModal({{ $ref->id }})"
                                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold">
                                                    Approve & Settle Refund
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-slate-400 py-3 bg-slate-50 rounded-xl text-center">No refunds or adjustments recorded for this bill.</p>
                        @endif
                    </div>
                </div>

                <div class="p-6 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                    <button type="button" wire:click="closeDetailModal" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-100">
                        Close
                    </button>
                    <div class="flex items-center gap-2">
                        @if ($detailBill->payment)
                            <a href="{{ route('print.consultation.receipt', $detailBill->id) }}" target="_blank"
                                class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-semibold">
                                Print Receipt
                            </a>
                        @endif
                        @if ($detailBill->appointment_id)
                            <a href="{{ route('print.doctor.pad', $detailBill->appointment_id) }}" target="_blank"
                                class="px-4 py-2 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-semibold">
                                Doctor Pad
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- AUDIT MODAL -->
    @if ($showAuditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-2xl w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Audit Trail & Change History</h3>
                        <p class="text-xs text-slate-500">Immutable ledger of modifications, reasons, and user actions.</p>
                    </div>
                    <button type="button" wire:click="closeAuditModal" class="text-slate-400 hover:text-slate-700 text-xl font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    @forelse ($auditEntries as $audit)
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900">{{ $audit['action'] }}</span>
                                <span class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($audit['created_at'])->format('d M Y, h:i A') }}</span>
                            </div>
                            @if (!empty($audit['change_category']))
                                <span class="inline-block px-2 py-0.5 rounded text-[9px] font-bold bg-indigo-100 text-indigo-800">
                                    {{ $audit['change_category'] }}
                                </span>
                            @endif
                            @if (!empty($audit['reason']))
                                <p class="text-slate-700"><span class="font-semibold">Reason:</span> {{ $audit['reason'] }}</p>
                            @endif
                            @if (!empty($audit['financial_impact']))
                                <p class="text-emerald-700 font-medium">{{ $audit['financial_impact'] }}</p>
                            @endif
                            <div class="text-[10px] text-slate-400 pt-1">
                                User: {{ $audit['user']['name'] ?? 'System / Operator' }} • IP: {{ $audit['ip_address'] ?? '-' }}
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-xs text-slate-400 py-6">No audit entries found for this record.</p>
                    @endforelse
                </div>

                <div class="p-4 border-t border-slate-100 bg-slate-50 text-right">
                    <button type="button" wire:click="closeAuditModal" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl text-xs font-semibold">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- CONTROLLED MODIFY / REFUND MODAL -->
    @if ($showModifyModal && $modifyBill)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-xl w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-amber-50/50">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ $modifyBill->status === 'PENDING' ? 'Modify Pending Consultation Bill' : 'Initiate Controlled Refund / Discount' }}
                        </h3>
                        <p class="text-xs text-slate-500">Bill #{{ $modifyBill->bill_number }} • Patient: {{ $modifyBill->patient->name }}</p>
                    </div>
                    <button type="button" wire:click="closeModifyModal" class="text-slate-400 hover:text-slate-700 text-xl font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-4 text-xs text-slate-700">
                    @if ($modifyBill->status === 'PENDING')
                        <!-- Pending bill modification -->
                        <div class="p-3 bg-amber-50 rounded-xl text-amber-800 text-xs">
                            This bill is currently <strong>PENDING</strong> (unpaid). You may adjust fee discounts or waivers before final settlement.
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold mb-1">Gross Fee (₹)</label>
                                <input type="number" step="0.01" wire:model.live="editGrossAmount" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Discount (₹)</label>
                                <input type="number" step="0.01" wire:model.live="editDiscountAmount" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Waiver (₹)</label>
                                <input type="number" step="0.01" wire:model.live="editWaiverAmount" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Waiver Type</label>
                                <input type="text" wire:model.live="editWaiverType" placeholder="e.g. Staff, Emergency" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            </div>
                        </div>
                    @else
                        <!-- Paid bill: Strictly Controlled Refund -->
                        @php
                            $refundable = $modifyBill->payment ? app(\App\Services\RefundService::class)->getRemainingRefundableAmount($modifyBill->payment) : 0.00;
                        @endphp
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 grid grid-cols-3 gap-2 text-center text-xs">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Original Paid</span>
                                <span class="font-bold text-slate-900">₹{{ number_format($modifyBill->payment?->amount ?? $modifyBill->net_amount, 2) }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Already Refunded</span>
                                <span class="font-bold text-rose-600">₹{{ number_format($modifyBill->refunded_amount, 2) }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Remaining Refundable</span>
                                <span class="font-black text-emerald-700">₹{{ number_format($refundable, 2) }}</span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block font-bold text-slate-800 mb-1">Adjustment Type</label>
                                <select wire:model.live="modificationType" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                                    <option value="PARTIAL_REFUND">Partial Refund</option>
                                    <option value="FULL_REFUND">Full Refund</option>
                                    <option value="DISCOUNT_AFTER_SETTLEMENT">Doctor Recommended Discount</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-800 mb-1">Refund Amount (₹)</label>
                                    <input type="number" step="0.01" wire:model.live.debounce.300ms="modifyRefundAmount" id="input_modify_refund_amount" class="w-full border-slate-200 rounded-xl p-2 text-xs font-bold text-rose-700">
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-800 mb-1">Disbursement Method</label>
                                    <select wire:model.live="modifyRefundMethod" id="select_modify_refund_method" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                                        <option value="CASH">Physical Cash</option>
                                        <option value="UPI">UPI / Bank Transfer</option>
                                    </select>
                                </div>
                            </div>

                            @if ($modifyRefundMethod === 'UPI')
                                <div class="p-3 bg-indigo-50 rounded-xl space-y-2 border border-indigo-100">
                                    <div>
                                        <label class="block font-bold text-indigo-950 mb-1">Disbursed From Bank Account</label>
                                        <select wire:model.live="modifyBankAccountId" id="select_modify_bank_account" class="w-full border-indigo-200 rounded-lg p-2 text-xs">
                                            @foreach ($bankAccounts as $ba)
                                                <option value="{{ $ba->id }}">{{ $ba->bank_name }} - {{ $ba->account_number }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block font-bold text-indigo-950 mb-1">Transaction Ref / UTR</label>
                                        <input type="text" wire:model.live.debounce.300ms="modifyReferenceNumber" id="input_modify_ref_number" placeholder="e.g. UPI12345678" class="w-full border-indigo-200 rounded-lg p-2 text-xs">
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label class="block font-bold text-slate-800 mb-1">Recommending Doctor (Optional)</label>
                                <select wire:model="modifyDoctorInstructionBy" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                                    <option value="">None (Standard Operator / Admin Refund)</option>
                                    @foreach ($doctors as $doc)
                                        <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <!-- Mandatory Reason Input -->
                    <div>
                        <label class="block font-bold text-slate-900 mb-1">
                            Mandatory Reason for Change <span class="text-rose-500">*</span>
                        </label>
                        <textarea wire:model.live.debounce.300ms="modifyReason" id="input_modify_reason" rows="3" placeholder="Provide a detailed, meaningful explanation (minimum 10 characters)..."
                            class="w-full border-slate-200 rounded-xl p-3 text-xs focus:ring-teal-500"></textarea>
                        <p class="text-[10px] text-slate-400 mt-0.5">Generic reasons like 'mistake' or 'edit' are rejected. Minimum 10 characters required.</p>
                    </div>

                    <!-- Final Invariant Confirmation -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <label class="flex items-start gap-2.5 cursor-pointer text-xs font-semibold text-slate-800">
                            <input type="checkbox" wire:model.live="confirmModify" id="checkbox_confirm_modify" class="mt-0.5 rounded text-teal-600">
                            <span>
                                I confirm that the original settled financial facts remain immutable. This action will create a new, auditable refund / revision record.
                            </span>
                        </label>
                    </div>
                </div>

                <div class="p-6 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                    <button type="button" wire:click="closeModifyModal" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-100">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitModification" id="btn_submit_refund_modification"
                        @if (!$this->canSubmitRefund) disabled @endif
                        class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold shadow-sm">
                        {{ $modifyBill->status === 'PENDING' ? 'Save Changes' : (auth()->user()?->isAdmin() ? 'Process Refund & Settle Journal' : 'Submit Refund Request') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ADMIN PROCESS REFUND MODAL -->
    @if ($showProcessRefundModal && $activeRefundToProcess)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-md w-full border border-slate-200 shadow-2xl p-6 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Administrator Refund Settlement</h3>
                    <button type="button" wire:click="closeProcessRefundModal" class="text-slate-400 hover:text-slate-700 text-xl font-bold">&times;</button>
                </div>

                <p class="text-xs text-slate-600">
                    Settling refund <strong>#{{ $activeRefundToProcess->refund_number }}</strong> for ₹{{ number_format($activeRefundToProcess->refund_amount, 2) }}.
                </p>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold mb-1">Disbursement Method</label>
                        <select wire:model.live="processRefundMethod" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            <option value="CASH">Cash in Hand</option>
                            <option value="UPI">UPI / Bank Account</option>
                        </select>
                    </div>

                    @if ($processRefundMethod === 'UPI')
                        <div>
                            <label class="block font-bold mb-1">Bank Account</label>
                            <select wire:model="processBankAccountId" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                                @foreach ($bankAccounts as $ba)
                                    <option value="{{ $ba->id }}">{{ $ba->bank_name }} - {{ $ba->account_number }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold mb-1">Transaction Ref / UTR *</label>
                            <input type="text" wire:model="processReferenceNumber" placeholder="Required for UPI settlement" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" wire:click="closeProcessRefundModal" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl text-xs font-semibold">
                        Cancel
                    </button>
                    <button type="button" wire:click="executeAdminProcessRefund" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold">
                        Confirm & Post Journal
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
