<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 space-y-6">
    <!-- Header with Tabs and Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded bg-indigo-100 text-indigo-800 text-xs font-black uppercase tracking-wider">Reports</span>
                <span class="text-xs text-slate-400 font-semibold">&bull;</span>
                <span class="text-xs text-slate-500 font-medium">Diagnostic Lab Analytics</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-1">
                @if($activeTab === 'session') Session-wise Diagnostic Report
                @else Day-wise Diagnostic Report
                @endif
            </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 shadow-sm transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print Report
            </button>
            <a href="{{ route('pdf.diagnostic-report', ['period' => $period, 'start_date' => $startDate, 'end_date' => $endDate, 'doctor_id' => $doctorId, 'session_type' => $sessionType, 'payment_status' => $paymentStatus]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Download PDF
            </a>
            <button wire:click="exportCsv" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV
            </button>
        </div>
    </div>

    <!-- Navigation Tabs (Section 60, 61) -->
    <div class="flex items-center gap-2 border-b border-slate-200">
        <button wire:click="setTab('session')" class="px-4 py-2.5 text-xs font-bold border-b-2 transition {{ $activeTab === 'session' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            Session-wise Diagnostic Report
        </button>
        <button wire:click="setTab('day')" class="px-4 py-2.5 text-xs font-bold border-b-2 transition {{ $activeTab === 'day' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            Day-wise Diagnostic Report
        </button>
    </div>

    <!-- Filter Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Period</label>
                <select wire:model.live="period" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="half_yearly">Half-Yearly</option>
                    <option value="financial_year">Financial Year (Apr-Mar)</option>
                    <option value="custom">Custom Date Range</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">From Date</label>
                <input type="date" wire:model.live="startDate" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">To Date</label>
                <input type="date" wire:model.live="endDate" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Doctor Scope</label>
                <select wire:model.live="doctorId" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="ALL">All Doctors (Combined)</option>
                    @foreach($doctors as $d)
                        <option value="{{ $d['id'] }}">{{ \App\Services\DoctorNamePresenter::format($d['name']) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Session</label>
                <select wire:model.live="sessionType" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="ALL">All Sessions</option>
                    <option value="MORNING">Morning</option>
                    <option value="EVENING">Evening</option>
                    <option value="SUNDAY">Sunday</option>
                </select>
            </div>
        </div>

        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between">
            <div class="w-64">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Payment Status</label>
                <select wire:model.live="paymentStatus" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="ALL">All Statuses</option>
                    <option value="PAID">Settled (Paid)</option>
                    <option value="PENDING">Pending (Unpaid)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Summary KPI Cards (Section 62) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Diagnostic Bills</span>
            <div class="text-2xl font-black text-slate-800 mt-1">{{ $report['total_items'] }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <span class="text-[10px] uppercase font-bold text-indigo-600 block tracking-wider">Total Billed Amount</span>
            <div class="text-2xl font-black text-indigo-700 mt-1">₹{{ number_format($report['total_billed'], 2) }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <span class="text-[10px] uppercase font-bold text-emerald-600 block tracking-wider">Settled Diagnostic Collection</span>
            <div class="text-2xl font-black text-emerald-700 mt-1">₹{{ number_format($report['total_collection'], 2) }}</div>
        </div>
    </div>

    <!-- Diagnostic Report Table (Section 125 Format) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Diagnostic Register (Date Range: {{ $report['start_date'] }} to {{ $report['end_date'] }})</span>
            <span class="text-xs text-slate-500 font-semibold">{{ count($report['rows']) }} Records Found</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs divide-y divide-slate-200">
                <thead class="bg-slate-50 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Patient ID</th>
                        <th class="py-3 px-3">Patient Name</th>
                        <th class="py-3 px-4">Diagnostic Test / Package</th>
                        <th class="py-3 px-3 text-right">Amount</th>
                        <th class="py-3 px-3">Doctor</th>
                        <th class="py-3 px-2">Session</th>
                        <th class="py-3 px-2">Payment Status</th>
                        <th class="py-3 px-3">Payment Mode</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($report['rows'] as $r)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-2.5 px-3 font-mono text-slate-500 whitespace-nowrap">{{ $r['date_formatted'] }}</td>
                            <td class="py-2.5 px-3 font-mono font-bold text-indigo-800">{{ $r['patient_id'] }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-900">{{ $r['patient_name'] }}</td>
                            <td class="py-2.5 px-4 font-medium text-slate-700 max-w-xs truncate" title="{{ $r['diagnostic_item'] }}">{{ $r['diagnostic_item'] }}</td>
                            <td class="py-2.5 px-3 text-right font-black text-slate-900">₹{{ number_format($r['amount'], 2) }}</td>
                            <td class="py-2.5 px-3 font-medium text-slate-700 whitespace-nowrap">{{ $r['doctor'] }}</td>
                            <td class="py-2.5 px-2">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                    {{ $r['session'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $r['payment_status'] === 'PAID' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $r['payment_status'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-[10px] text-slate-600 whitespace-nowrap">
                                {{ $r['payment_mode'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400 text-xs">
                                No diagnostic records found matching the selected filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <!-- Diagnostic Total Collection Footer (Section 60, 61, 62, 125) -->
                <tfoot class="bg-gradient-to-r from-indigo-50 to-teal-50 border-t-2 border-indigo-200">
                    <tr>
                        <td colspan="4" class="py-4 px-4 text-sm font-black text-slate-900 uppercase tracking-wider text-right">
                            TOTAL COLLECTION:
                        </td>
                        <td class="py-4 px-3 text-right text-base font-black text-indigo-800 whitespace-nowrap">
                            ₹{{ number_format($report['total_collection'], 2) }}
                        </td>
                        <td colspan="4" class="py-4 px-3 text-xs text-slate-500 font-semibold">
                            (Billed: ₹{{ number_format($report['total_billed'], 2) }} &bull; Settled Collection: ₹{{ number_format($report['total_collection'], 2) }})
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
