<div class="py-6 px-4 sm:px-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between pb-6 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Diagnostic Test & Package Billing</h1>
            <p class="text-xs text-slate-500 mt-0.5">Select doctor-prescribed lab investigations, snapshot current rates, issue receipts/slips, and manage billing history.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="resetBilling" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold">
                + New Diagnostic Bill
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="mt-6 flex border-b border-slate-200 gap-2">
        <button type="button" wire:click="setTab('new')"
            class="px-5 py-2.5 text-xs font-bold rounded-t-xl transition-all border-b-2 flex items-center gap-2 {{ $activeTab === 'new' ? 'border-blue-600 text-blue-700 bg-blue-50/50' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Diagnostic Billing
        </button>
        <button type="button" wire:click="setTab('history')"
            class="px-5 py-2.5 text-xs font-bold rounded-t-xl transition-all border-b-2 flex items-center gap-2 {{ $activeTab === 'history' ? 'border-blue-600 text-blue-700 bg-blue-50/50' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Diagnostic Billing History
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

    <!-- TAB 1: NEW DIAGNOSTIC BILLING -->
    @if ($activeTab === 'new')
        <div class="my-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Search, Quick Add & Selected Items -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Patient Selector Card -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-3">
                    @if ($patient)
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Selected Patient for Diagnostics</span>
                                <h2 class="text-base font-bold text-slate-900">{{ $patient->name }}</h2>
                                <p class="text-xs text-slate-500">{{ $patient->patient_id }} • {{ $patient->age }}Y / {{ $patient->sex }} • 📞 {{ $patient->mobile }}</p>
                                <p class="text-xs text-slate-700 mt-0.5"><span class="font-medium">Address:</span> {{ $patient->formatted_address }}</p>
                            </div>
                            <button type="button" wire:click="$set('patient', null)" class="px-3 py-1.5 border border-slate-200 text-xs text-slate-600 hover:text-rose-600 font-semibold rounded-lg hover:bg-slate-50">
                                Change Patient
                            </button>
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Search Patient / Token / Visit / Mobile</label>
                            <div class="flex gap-2 mb-3">
                                <div class="relative flex-1">
                                    <input type="text" wire:model.live.debounce.250ms="patientSearchQuery" wire:keydown.enter="performPatientSearch" placeholder="Type Token, Name, Mobile or Patient ID..." class="w-full p-2.5 pl-8 border border-slate-300 rounded-xl text-xs">
                                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <button type="button" wire:click="performPatientSearch" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs">Search</button>
                            </div>

                            @if (!empty($patientSearchResults))
                                <div class="mb-3 p-2 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block px-1">Search Matches:</span>
                                    @foreach ($patientSearchResults as $pMatch)
                                        <button type="button" wire:click="selectPatient({{ $pMatch['id'] }})" class="w-full text-left p-2 rounded-lg hover:bg-blue-50 border border-transparent hover:border-blue-200 flex items-center justify-between text-xs">
                                            <div>
                                                <span class="font-bold text-slate-900">{{ $pMatch['name'] }}</span>
                                                <span class="text-[11px] text-slate-500 ml-2">({{ $pMatch['patient_id'] }} • {{ $pMatch['mobile'] }})</span>
                                            </div>
                                            <span class="text-[11px] font-semibold text-blue-600">Select &rarr;</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            <span class="text-[11px] font-semibold text-slate-500 block mb-1.5">Or select from recent registrations:</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($recentPatients as $p)
                                    <button type="button" wire:click="selectPatient({{ $p->id }})" class="px-3 py-1.5 bg-slate-50 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-xl text-xs text-slate-800 text-left">
                                        <span class="font-bold">{{ $p->name }}</span>
                                        <span class="text-[10px] text-slate-500 block">{{ $p->patient_id }} • {{ $p->city }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Test Catalog Search & Quick Add -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Search Diagnostic Test or Package</label>
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.250ms="searchTerm" placeholder="Search CBC, Lipid, Thyroid, Panel..." class="w-full p-2.5 pl-8 border border-slate-300 rounded-xl text-xs">
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>

                        <!-- Search Results Dropdown -->
                        @if (!empty($searchResults))
                            <div class="mt-2 bg-white border border-slate-200 rounded-xl shadow-lg max-h-56 overflow-y-auto divide-y divide-slate-100 z-10">
                                @foreach ($searchResults as $item)
                                    <div class="p-2.5 hover:bg-teal-50 flex items-center justify-between text-xs cursor-pointer" wire:click="addItem({{ $item->id }})">
                                        <div>
                                            <span class="font-bold text-slate-800">{{ $item->name }}</span>
                                            <span class="text-[10px] text-slate-400 block">{{ $item->code }} • {{ $item->type }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900">₹{{ number_format($item->rate, 2) }}</span>
                                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-[10px] font-bold rounded-lg">+ Add</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Frequently Prescribed Tests -->
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Frequently Prescribed Tests</span>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach ($frequentItems as $freq)
                                <button type="button" wire:click="addItem({{ $freq->id }})" class="p-2 bg-slate-50 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-xl text-left transition-all">
                                    <span class="text-xs font-bold text-slate-800 block truncate">{{ $freq->name }}</span>
                                    <span class="text-[11px] font-semibold text-blue-600 block">₹{{ number_format($freq->rate, 2) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Selected Line Items Table -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Prescribed Investigation Line Items</h3>
                        <span class="text-xs text-slate-500 font-medium">{{ count($selectedItems) }} item(s) selected</span>
                    </div>

                    <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                        @forelse ($selectedItems as $sId => $sItem)
                            <div class="p-3.5 flex items-center justify-between text-xs hover:bg-slate-50/50">
                                <div class="flex-1 pr-4">
                                    <span class="font-bold text-slate-900">{{ $sItem['name'] }}</span>
                                    <span class="text-[10px] text-slate-400 block">{{ $sItem['type'] }} • Rate: ₹{{ number_format($sItem['rate'], 2) }}</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <!-- Quantity Selector -->
                                    <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden">
                                        <button type="button" wire:click="updateQuantity({{ $sId }}, {{ $sItem['quantity'] - 1 }})" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold">-</button>
                                        <span class="px-3 py-1 font-bold text-slate-800 text-xs">{{ $sItem['quantity'] }}</span>
                                        <button type="button" wire:click="updateQuantity({{ $sId }}, {{ $sItem['quantity'] + 1 }})" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold">+</button>
                                    </div>
                                    <span class="font-bold text-slate-900 w-16 text-right">₹{{ number_format($sItem['amount'], 2) }}</span>
                                    <button type="button" wire:click="removeItem({{ $sId }})" class="text-slate-400 hover:text-rose-600 font-bold ml-1 text-sm">&times;</button>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-slate-400 text-xs">
                                No investigations selected yet. Search above or click a frequent test.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right: Bill Summary & Payment Box -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-5">
                    <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">Diagnostic Billing Summary</h3>

                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Subtotal (Gross)</span>
                            <span class="font-bold text-slate-800">₹{{ number_format($this->grossTotal, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-600">Discount (₹)</span>
                            <input type="number" step="0.01" wire:model.live="discountAmount" class="w-24 p-1.5 border border-slate-200 rounded-lg text-right text-xs">
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-600">Waiver (₹)</span>
                            <input type="number" step="0.01" wire:model.live="waiverAmount" class="w-24 p-1.5 border border-slate-200 rounded-lg text-right text-xs">
                        </div>
                        <div class="pt-3 border-t border-slate-200 flex justify-between text-sm font-black text-slate-900">
                            <span>Net Payable</span>
                            <span class="text-xl text-blue-700">₹{{ number_format($this->netTotal, 2) }}</span>
                        </div>
                    </div>

                    @if (!$bill)
                        <button type="button" wire:click="createBill" @if(empty($selectedItems) || !$patient) disabled @endif
                            class="w-full py-3 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-xs shadow-sm">
                            Save Bill & Continue to Payment
                        </button>
                    @else
                        <!-- Bill Created & Payment Settlement -->
                        <div class="p-4 bg-blue-50/60 rounded-xl border border-blue-200 space-y-4">
                            <div class="flex justify-between items-center text-xs">
                                <span class="font-bold text-blue-900">{{ $bill->bill_number }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $bill->status === 'PAID' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $bill->status }}
                                </span>
                            </div>

                            @if ($payment && $payment->status === 'SETTLED')
                                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl space-y-2">
                                    <div class="text-xs font-bold text-emerald-900 flex items-center gap-1.5">
                                        <span>✓</span> Diagnostic Payment Received ({{ $payment->payment_method }})
                                    </div>
                                    <div class="text-[10px] text-emerald-700">Receipt: {{ $payment->receipt?->receipt_number ?? '-' }}</div>
                                    <div class="pt-2 flex flex-col gap-2">
                                        <a href="{{ route('print.diagnostic.receipt', $bill->id) }}" target="_blank"
                                            class="w-full text-center py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold">
                                            Print A5 Receipt
                                        </a>
                                        <a href="{{ route('print.lab.slip', $bill->id) }}" target="_blank"
                                            class="w-full text-center py-2 bg-slate-900 hover:bg-black text-white rounded-lg text-xs font-bold">
                                            Print Lab Slip
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="space-y-3">
                                    <label class="block text-xs font-bold text-slate-800">Payment Method</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="p-2 border rounded-xl flex items-center gap-2 cursor-pointer text-xs {{ $paymentMethod === 'CASH' ? 'border-blue-500 bg-white font-bold' : 'border-slate-200' }}">
                                            <input type="radio" wire:model.live="paymentMethod" value="CASH">
                                            Cash
                                        </label>
                                        <label class="p-2 border rounded-xl flex items-center gap-2 cursor-pointer text-xs {{ $paymentMethod === 'UPI' ? 'border-blue-500 bg-white font-bold' : 'border-slate-200' }}">
                                            <input type="radio" wire:model.live="paymentMethod" value="UPI">
                                            Soundbox UPI
                                        </label>
                                    </div>

                                    @if ($paymentMethod === 'UPI')
                                        <div class="space-y-2 pt-2 border-t border-blue-100 text-xs">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-700">Bank Account</label>
                                                <select wire:model="selectedBankAccountId" class="w-full p-2 border border-slate-200 rounded-lg text-xs">
                                                    @foreach ($bankAccounts as $ba)
                                                        <option value="{{ $ba->id }}">{{ $ba->bank_name }} - {{ $ba->account_number }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-700">UPI Ref / UTR (Optional)</label>
                                                <input type="text" wire:model="referenceNumber" class="w-full p-2 border border-slate-200 rounded-lg text-xs">
                                            </div>
                                            <label class="flex items-center gap-2 text-[11px] font-semibold text-slate-800 cursor-pointer pt-1">
                                                <input type="checkbox" wire:model="soundboxConfirmed" class="rounded text-blue-600">
                                                <span>I confirm Soundbox audio confirmation</span>
                                            </label>
                                        </div>
                                    @endif

                                    <button type="button" wire:click="settlePayment"
                                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm">
                                        Confirm Diagnostic Payment
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: DIAGNOSTIC BILLING HISTORY -->
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
                        <input type="text" wire:model.live.debounce.300ms="filterPatient" placeholder="Name, ID, Mobile" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bill Number</label>
                        <input type="text" wire:model.live.debounce.300ms="searchBillNumber" placeholder="e.g. DBL-..." class="w-full border-slate-200 rounded-xl p-2 text-xs">
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
                        <label class="block font-bold text-slate-700 mb-1">Doctor</label>
                        <select wire:model.live="filterDoctorId" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                            <option value="">All Doctors</option>
                            @foreach ($doctors as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                            @endforeach
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
                    <div class="flex items-end">
                        <button type="button" wire:click="resetFilters" class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold rounded-xl text-xs w-full">
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Historical Diagnostic Bills Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Historical Diagnostic Bills</h3>
                    <span class="text-xs text-slate-400">Total: {{ $historyBills->total() }} records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="p-3">Bill No</th>
                                <th class="p-3">Date</th>
                                <th class="p-3">Patient</th>
                                <th class="p-3">Doctor</th>
                                <th class="p-3">Investigations</th>
                                <th class="p-3 text-right">Original Net</th>
                                <th class="p-3 text-right">Refunded</th>
                                <th class="p-3 text-right">Retained</th>
                                <th class="p-3 text-center">Status</th>
                                <th class="p-3 text-center">Edit</th>
                                <th class="p-3">Method</th>
                                <th class="p-3">Receipt</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse ($historyBills as $dhBill)
                                @php
                                    $refunded = $dhBill->refunded_amount;
                                    $retained = $dhBill->retained_amount;
                                    $isPartiallyRefunded = $refunded > 0 && $retained > 0;
                                    $isSupp = str_contains($dhBill->bill_number, '-SUPP-');
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-3 font-semibold text-slate-900">
                                        {{ $dhBill->bill_number }}
                                        @if ($isSupp)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-100 text-indigo-800 ml-1">SUPP</span>
                                        @endif
                                    </td>
                                    <td class="p-3">{{ $dhBill->bill_date?->format('d M Y') }}</td>
                                    <td class="p-3">
                                        <div class="font-bold text-slate-900">{{ $dhBill->patient->name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $dhBill->patient->patient_id }}</div>
                                    </td>
                                    <td class="p-3">{{ $dhBill->doctor?->name ?? 'OPD Doctor' }}</td>
                                    <td class="p-3">
                                        <span class="font-semibold text-slate-800">{{ $dhBill->items->count() }} test(s)</span>
                                        <span class="text-[10px] text-slate-400 block truncate max-w-xs">
                                            {{ $dhBill->items->pluck('item_name')->take(2)->join(', ') }}
                                            @if ($dhBill->items->count() > 2) ... @endif
                                        </span>
                                    </td>
                                    <td class="p-3 text-right font-medium">₹{{ number_format($dhBill->net_amount, 2) }}</td>
                                    <td class="p-3 text-right text-rose-600 font-medium">
                                        {{ $refunded > 0 ? '-₹' . number_format($refunded, 2) : '-' }}
                                    </td>
                                    <td class="p-3 text-right font-bold text-slate-900">₹{{ number_format($retained, 2) }}</td>
                                    <td class="p-3 text-center">
                                        @if ($dhBill->status === 'REFUNDED')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">REFUNDED</span>
                                        @elseif ($isPartiallyRefunded)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">PARTIAL REFUND</span>
                                        @elseif ($dhBill->status === 'PAID')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">PAID</span>
                                        @elseif ($dhBill->status === 'PENDING')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-800">PENDING</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">{{ $dhBill->status }}</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        @if ($dhBill->status !== 'CANCELLED' && $dhBill->status !== 'REFUNDED')
                                            <button type="button" wire:click="openModifyModal({{ $dhBill->id }})" title="Edit / Controlled Modification"
                                                class="px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-[11px] font-semibold">
                                                Edit
                                            </button>
                                        @else
                                            <span class="text-slate-300 text-[11px]">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-[11px] font-medium">{{ $dhBill->payment?->payment_method ?? '-' }}</td>
                                    <td class="p-3 text-[11px]">{{ $dhBill->payment?->receipt?->receipt_number ?? '-' }}</td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" wire:click="viewBillDetail({{ $dhBill->id }})" title="View Details"
                                                class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold">
                                                View
                                            </button>
                                            @if ($dhBill->payment)
                                                <a href="{{ route('print.diagnostic.receipt', $dhBill->id) }}" target="_blank" title="Print Receipt"
                                                    class="px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-800 rounded-lg text-[11px] font-semibold">
                                                    Receipt
                                                </a>
                                            @endif
                                            <a href="{{ route('print.lab.slip', $dhBill->id) }}" target="_blank" title="Print Lab Slip"
                                                class="px-2 py-1 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold">
                                                Lab Slip
                                            </a>
                                            <button type="button" wire:click="viewAuditHistory({{ $dhBill->id }})" title="View Audit Trail"
                                                class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-[11px] font-semibold">
                                                Audit
                                            </button>
                                            @if ($dhBill->status !== 'CANCELLED' && $dhBill->status !== 'REFUNDED')
                                                <button type="button" wire:click="openModifyModal({{ $dhBill->id }})" title="Modify or Refund"
                                                    class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg text-[11px] font-semibold">
                                                    {{ $dhBill->status === 'PAID' ? 'Refund / Adjust' : 'Modify' }}
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="p-8 text-center text-slate-400">No diagnostic bills match the selected filters.</td>
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

    <!-- DETAIL MODAL -->
    @if ($showDetailModal && $detailBill)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-2xl w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Diagnostic Bill Detail</h3>
                        <p class="text-xs text-slate-500">{{ $detailBill->bill_number }} • Date: {{ $detailBill->bill_date?->format('d M Y') }}</p>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="text-slate-400 hover:text-slate-700 text-xl font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto text-xs text-slate-700">
                    <!-- Patient Summary -->
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
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Status</span>
                            <span class="font-bold px-2 py-0.5 rounded text-[10px] {{ $detailBill->status === 'REFUNDED' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ $detailBill->status }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Receipt</span>
                            <span class="font-semibold text-slate-800">{{ $detailBill->payment?->receipt?->receipt_number ?? 'N/A' }}</span>
                        </div>
                    </div>

                    <!-- Line Items (Historical Rates Preserved) -->
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Prescribed Line Items (Historical Rates Preserved)</h4>
                        <div class="border border-slate-200 rounded-2xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] border-b border-slate-200">
                                    <tr>
                                        <th class="p-2.5">Investigation</th>
                                        <th class="p-2.5">Type</th>
                                        <th class="p-2.5 text-center">Qty</th>
                                        <th class="p-2.5 text-right">Historical Rate</th>
                                        <th class="p-2.5 text-right">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($detailBill->items as $item)
                                        <tr>
                                            <td class="p-2.5 font-bold text-slate-900">{{ $item->item_name }}</td>
                                            <td class="p-2.5 text-[10px] text-slate-500">{{ $item->item_type }}</td>
                                            <td class="p-2.5 text-center">{{ $item->quantity }}</td>
                                            <td class="p-2.5 text-right">₹{{ number_format($item->rate, 2) }}</td>
                                            <td class="p-2.5 text-right font-bold text-slate-800">₹{{ number_format($item->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <tbody class="divide-y divide-slate-100">
                                <tr class="bg-slate-50/50">
                                    <td class="p-3 text-slate-500">Original Gross</td>
                                    <td class="p-3 text-right font-medium">₹{{ number_format($detailBill->gross_amount, 2) }}</td>
                                </tr>
                                @if ($detailBill->discount_amount > 0)
                                    <tr>
                                        <td class="p-3 text-slate-500">Original Discount</td>
                                        <td class="p-3 text-right text-emerald-600">-₹{{ number_format($detailBill->discount_amount, 2) }}</td>
                                    </tr>
                                @endif
                                <tr class="bg-slate-100/70 font-semibold text-slate-900">
                                    <td class="p-3">Original Net Settled</td>
                                    <td class="p-3 text-right font-bold">₹{{ number_format($detailBill->net_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="p-3 text-slate-500">Processed Refunds</td>
                                    <td class="p-3 text-right text-rose-600 font-bold">-₹{{ number_format($detailBill->refunded_amount, 2) }}</td>
                                </tr>
                                <tr class="bg-blue-50/60 font-bold text-blue-950 text-sm">
                                    <td class="p-3">Current Net Retained</td>
                                    <td class="p-3 text-right font-black">₹{{ number_format($detailBill->retained_amount, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Refunds Section -->
                    @if ($detailBill->refunds->count() > 0)
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Refunds Processed</h4>
                            <div class="space-y-2">
                                @foreach ($detailBill->refunds as $ref)
                                    <div class="p-3 rounded-xl border border-rose-200 bg-rose-50/30 flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-slate-900">{{ $ref->refund_number }} ({{ $ref->refund_method }})</div>
                                            <div class="text-[11px] text-slate-600">Reason: {{ $ref->reason }}</div>
                                        </div>
                                        <span class="font-black text-rose-700 text-sm">₹{{ number_format($ref->refund_amount, 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="p-6 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                    <button type="button" wire:click="closeDetailModal" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-100">
                        Close
                    </button>
                    <div class="flex items-center gap-2">
                        @if ($detailBill->payment)
                            <a href="{{ route('print.diagnostic.receipt', $detailBill->id) }}" target="_blank"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold">
                                Print Receipt
                            </a>
                        @endif
                        <a href="{{ route('print.lab.slip', $detailBill->id) }}" target="_blank"
                            class="px-4 py-2 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-semibold">
                            Lab Slip
                        </a>
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
                        <h3 class="text-lg font-bold text-slate-900">Diagnostic Bill Audit Trail</h3>
                        <p class="text-xs text-slate-500">Immutable record of modifications, reason codes, and user actions.</p>
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
                                <span class="inline-block px-2 py-0.5 rounded text-[9px] font-bold bg-blue-100 text-blue-800">
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

    <!-- CONTROLLED MODIFY / REFUND / SUPPLEMENTARY MODAL -->
    @if ($showModifyModal && $modifyBill)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-xl w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-amber-50/50">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ $modifyBill->status === 'PENDING' ? 'Modify Pending Diagnostic Bill' : 'Controlled Modification / Refund / Supplement' }}
                        </h3>
                        <p class="text-xs text-slate-500">Bill #{{ $modifyBill->bill_number }} • Patient: {{ $modifyBill->patient->name }}</p>
                    </div>
                    <button type="button" wire:click="closeModifyModal" class="text-slate-400 hover:text-slate-700 text-xl font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-4 text-xs text-slate-700 max-h-[70vh] overflow-y-auto">
                    @if ($modifyBill->status === 'PENDING')
                        <div class="p-3 bg-amber-50 rounded-xl text-amber-800 text-xs">
                            This bill is <strong>PENDING</strong>. You may modify the items or cancel before settlement.
                        </div>
                    @else
                        <!-- Paid Bill -->
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

                        <div>
                            <label class="block font-bold text-slate-800 mb-1">Adjustment Action</label>
                            <select wire:model.live="modificationType" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                                <option value="PARTIAL_REFUND">Partial Refund / Test Cancelled</option>
                                <option value="FULL_REFUND">Full Refund</option>
                                <option value="SUPPLEMENTARY_CHARGE">Add Additional Tests (Supplementary Bill)</option>
                            </select>
                        </div>

                        @if ($modificationType === 'SUPPLEMENTARY_CHARGE')
                            <!-- Supplementary Tests Selector -->
                            <div class="p-4 bg-indigo-50/60 rounded-2xl border border-indigo-200 space-y-3">
                                <div>
                                    <label class="block font-bold text-indigo-950 mb-1">Search & Add Additional Investigation</label>
                                    <input type="text" wire:model.live.debounce.250ms="supplementarySearchTerm" placeholder="Type test name..." class="w-full p-2 border border-indigo-200 rounded-lg text-xs">
                                    @if (!empty($supplementarySearchResults))
                                        <div class="mt-1 bg-white border border-indigo-100 rounded-lg shadow-md max-h-36 overflow-y-auto divide-y divide-slate-100">
                                            @foreach ($supplementarySearchResults as $sItem)
                                                <div wire:click="addSupplementaryItem({{ $sItem->id }})" class="p-2 flex justify-between items-center hover:bg-indigo-50 cursor-pointer">
                                                    <span>{{ $sItem->name }}</span>
                                                    <span class="font-bold text-indigo-700">₹{{ number_format($sItem->rate, 2) }} +</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <!-- Selected Supplementary Items -->
                                @if (!empty($supplementaryItems))
                                    <div class="space-y-1.5">
                                        <span class="text-[10px] font-bold text-indigo-900 uppercase">Additional Items:</span>
                                        @foreach ($supplementaryItems as $supId => $sRow)
                                            <div class="p-2 bg-white rounded-lg border border-indigo-100 flex items-center justify-between">
                                                <span>{{ $sRow['name'] }} (Qty: {{ $sRow['quantity'] }})</span>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-indigo-800">₹{{ number_format($sRow['amount'], 2) }}</span>
                                                    <button type="button" wire:click="removeSupplementaryItem({{ $supId }})" class="text-rose-500 font-bold">&times;</button>
                                                </div>
                                            </div>
                                        @endforeach
                                        <div class="text-right font-black text-indigo-900 pt-1">
                                            Supplementary Total: ₹{{ number_format(array_sum(array_column($supplementaryItems, 'amount')), 2) }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <!-- Refund Fields -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-800 mb-1">Refund Amount (₹)</label>
                                    <input type="number" step="0.01" wire:model.live.debounce.300ms="modifyRefundAmount" id="input_diag_refund_amount" class="w-full border-slate-200 rounded-xl p-2 text-xs font-bold text-rose-700">
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-800 mb-1">Disbursement Method</label>
                                    <select wire:model.live="modifyRefundMethod" id="select_diag_refund_method" class="w-full border-slate-200 rounded-xl p-2 text-xs">
                                        <option value="CASH">Physical Cash</option>
                                        <option value="UPI">UPI / Bank Account</option>
                                    </select>
                                </div>
                            </div>

                            @if ($modifyRefundMethod === 'UPI')
                                <div class="p-3 bg-indigo-50 rounded-xl space-y-2 border border-indigo-100">
                                    <div>
                                        <label class="block font-bold text-indigo-950 mb-1">Bank Account</label>
                                        <select wire:model.live="modifyBankAccountId" id="select_diag_bank_account" class="w-full border-indigo-200 rounded-lg p-2 text-xs">
                                            @foreach ($bankAccounts as $ba)
                                                <option value="{{ $ba->id }}">{{ $ba->bank_name }} - {{ $ba->account_number }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block font-bold text-indigo-950 mb-1">UTR / Ref Number</label>
                                        <input type="text" wire:model.live.debounce.300ms="modifyReferenceNumber" id="input_diag_ref_number" class="w-full border-indigo-200 rounded-lg p-2 text-xs">
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endif

                    <!-- Mandatory Reason -->
                    <div>
                        <label class="block font-bold text-slate-900 mb-1">
                            Mandatory Reason for Change <span class="text-rose-500">*</span>
                        </label>
                        <textarea wire:model.live.debounce.300ms="modifyReason" id="input_diag_modify_reason" rows="3" placeholder="Provide a detailed, meaningful explanation (minimum 10 characters)..."
                            class="w-full border-slate-200 rounded-xl p-3 text-xs focus:ring-blue-500"></textarea>
                    </div>

                    <!-- Final Confirmation Checkbox -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <label class="flex items-start gap-2.5 cursor-pointer text-xs font-semibold text-slate-800">
                            <input type="checkbox" wire:model.live="confirmModify" id="checkbox_diag_confirm_modify" class="mt-0.5 rounded text-blue-600">
                            <span>
                                I confirm that the original settled bill will remain unchanged. A new auditable refund, supplementary bill, or revision record will be created.
                            </span>
                        </label>
                    </div>
                </div>

                <div class="p-6 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                    <button type="button" wire:click="closeModifyModal" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-100">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitModification" id="btn_submit_diagnostic_refund"
                        @if (!$this->canSubmitRefund) disabled @endif
                        class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold shadow-sm">
                        Submit Modification
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
