<div class="py-6 px-4 sm:px-6 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Daily Cash Report</h1>
            <p class="text-xs text-slate-500 mt-0.5">Authoritative daily closing aggregated from morning and evening sessions, settled journals, and double-entry ledgers.</p>
        </div>
        <div class="flex items-center gap-3">
            <input type="date" wire:model.live="reportDate" id="report_date_input" class="p-2 border border-slate-300 rounded-xl text-xs bg-white font-medium">
            <button type="button" onclick="window.print()" id="btn_download_daily_pdf" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span>Download PDF</span>
            </button>
            <a href="{{ route('cash.sessions') }}" class="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold">
                &larr; Cash Sessions
            </a>
        </div>
    </div>

    @if (!empty($report))
        <!-- Main Card with Ribbon (Matching Mockup Panel 3: Session Summary & Daily Report) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden space-y-6">
            <!-- Blue Ribbon Header (Rule 35) -->
            <div class="bg-blue-600 px-6 py-2.5 text-white font-bold text-xs uppercase tracking-wider flex items-center justify-between">
                <span>Session Summary & Daily Report</span>
                <span class="text-xs font-normal normal-case">{{ Carbon\Carbon::parse($reportDate)->format('d F Y') }}</span>
            </div>

            <div class="p-6 sm:p-8 space-y-8">
                <!-- 4 Top Summary KPI Cards (Mockup Panel 3) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- 1. Opening Bank Balance -->
                    <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="flex items-center gap-2 text-slate-500 font-bold text-xs uppercase tracking-wider mb-2">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            <span>Opening Bank Balance</span>
                        </div>
                        <div class="text-2xl font-black text-slate-900">
                            ₹ {{ number_format($report['total_opening_bank_balance'], 2) }}
                        </div>
                        <span class="text-[10px] text-slate-400 mt-1 block">Ledger book balance</span>
                    </div>

                    <!-- 2. Total UPI Collection -->
                    <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="flex items-center gap-2 text-indigo-700 font-bold text-xs uppercase tracking-wider mb-2">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            <span>Total UPI Collection</span>
                        </div>
                        <div class="text-2xl font-black text-slate-900">
                            ₹ {{ number_format($report['bank_control']['upi_collected'], 2) }}
                        </div>
                        <span class="text-[10px] text-slate-500 mt-1 block font-medium">
                            Morning: {{ number_format($report['morning_breakdown']['upi_collection'], 0) }} | Evening: {{ number_format($report['evening_breakdown']['upi_collection'], 0) }}
                        </span>
                    </div>

                    <!-- 3. Total Expenses -->
                    <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="flex items-center gap-2 text-rose-700 font-bold text-xs uppercase tracking-wider mb-2">
                            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Total Expenses</span>
                        </div>
                        <div class="text-2xl font-black text-rose-700">
                            ₹ {{ number_format($report['expenses']['total_expenses'], 2) }}
                        </div>
                        <span class="text-[10px] text-slate-500 mt-1 block font-medium">
                            Morning: {{ number_format($report['morning_breakdown']['expenses'], 0) }} | Evening: {{ number_format($report['evening_breakdown']['expenses'], 0) }}
                        </span>
                    </div>

                    <!-- 4. Bank Cash Deposit -->
                    <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="flex items-center gap-2 text-emerald-700 font-bold text-xs uppercase tracking-wider mb-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            <span>Bank Cash Deposit</span>
                        </div>
                        <div class="text-2xl font-black text-emerald-800">
                            ₹ {{ number_format($report['cash_control']['cash_deposited_to_bank'], 2) }}
                        </div>
                        <span class="text-[10px] text-slate-400 mt-1 block">Contra transfer to bank</span>
                    </div>
                </div>

                <!-- Session Details Table (Mockup Panel 3) -->
                <div>
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-3">Session Details</h3>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                                    <th class="py-3 px-4">Session</th>
                                    <th class="py-3 px-4">Cash in Hand</th>
                                    <th class="py-3 px-4">UPI Collection</th>
                                    <th class="py-3 px-4">Expenses</th>
                                    <th class="py-3 px-4">Bank Deposit</th>
                                    <th class="py-3 px-4">Closing Cash</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-800">
                                <!-- Morning Session Row -->
                                <tr>
                                    <td class="py-3 px-4 font-bold text-slate-900">Morning</td>
                                    <td class="py-3 px-4">
                                        {{ $report['morning_breakdown']['cash_in_hand'] > 0 ? '₹ ' . number_format($report['morning_breakdown']['cash_in_hand'], 2) : '—' }}
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-indigo-700">
                                        ₹ {{ number_format($report['morning_breakdown']['upi_collection'], 2) }}
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-rose-700">
                                        ₹ {{ number_format($report['morning_breakdown']['expenses'], 2) }}
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-emerald-700">
                                        {{ $report['morning_breakdown']['bank_deposit'] > 0 ? '₹ ' . number_format($report['morning_breakdown']['bank_deposit'], 2) : '—' }}
                                    </td>
                                    <td class="py-3 px-4 font-bold">
                                        {{ $report['morning_breakdown']['closing_cash'] !== null ? '₹ ' . number_format($report['morning_breakdown']['closing_cash'], 2) : '—' }}
                                    </td>
                                </tr>

                                <!-- Evening Session Row -->
                                <tr>
                                    <td class="py-3 px-4 font-bold text-slate-900">Evening</td>
                                    <td class="py-3 px-4">
                                        {{ $report['evening_breakdown']['cash_in_hand'] !== null ? '₹ ' . number_format($report['evening_breakdown']['cash_in_hand'], 2) : '—' }}
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-indigo-700">
                                        ₹ {{ number_format($report['evening_breakdown']['upi_collection'], 2) }}
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-rose-700">
                                        ₹ {{ number_format($report['evening_breakdown']['expenses'], 2) }}
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-emerald-700">
                                        {{ $report['evening_breakdown']['bank_deposit'] > 0 ? '₹ ' . number_format($report['evening_breakdown']['bank_deposit'], 2) : '—' }}
                                    </td>
                                    <td class="py-3 px-4 font-bold">
                                        {{ $report['evening_breakdown']['closing_cash'] !== null ? '₹ ' . number_format($report['evening_breakdown']['closing_cash'], 2) : '—' }}
                                    </td>
                                </tr>

                                <!-- Total Row -->
                                <tr class="bg-slate-50/80 font-black text-slate-900 border-t-2 border-slate-200">
                                    <td class="py-3.5 px-4 uppercase tracking-wider">Total</td>
                                    <td class="py-3.5 px-4">—</td>
                                    <td class="py-3.5 px-4 text-indigo-800">₹ {{ number_format($report['bank_control']['upi_collected'], 2) }}</td>
                                    <td class="py-3.5 px-4 text-rose-800">₹ {{ number_format($report['expenses']['total_expenses'], 2) }}</td>
                                    <td class="py-3.5 px-4 text-emerald-800">₹ {{ number_format($report['cash_control']['cash_deposited_to_bank'], 2) }}</td>
                                    <td class="py-3.5 px-4 text-blue-900">
                                        {{ $report['cash_control']['physical_closing_cash'] !== null ? '₹ ' . number_format($report['cash_control']['physical_closing_cash'], 2) : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Verification (Closing Cash Formula) Box (Mockup Panel 3) -->
                @php
                    $opCash = $report['cash_control']['opening_cash'];
                    $cReceipts = $report['cash_control']['cash_collections'];
                    $cExpenses = $report['cash_control']['cash_expenses'];
                    $cDeposit = $report['cash_control']['cash_deposited_to_bank'];
                    $expClosing = $report['cash_control']['expected_closing_cash'];
                    $actClosing = $report['cash_control']['physical_closing_cash'];
                    $vVariance = $report['cash_control']['variance'];
                @endphp

                <div class="p-6 bg-emerald-50/70 border border-emerald-200 rounded-2xl space-y-2">
                    <div class="flex items-center gap-2 font-black text-emerald-950 text-sm">
                        <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs">
                            ✓
                        </div>
                        <span>Verification (Closing Cash Formula)</span>
                    </div>

                    <div class="text-xs text-emerald-900 font-medium pl-8 space-y-1">
                        <div>
                            <strong>Expected Closing Cash</strong> = Opening Cash + Total Cash Receipts - Total Expenses - Bank Deposit
                        </div>
                        <div class="font-mono text-emerald-950 font-bold">
                            = {{ number_format($opCash, 0) }} + {{ number_format($cReceipts, 0) }} - {{ number_format($cExpenses, 0) }} - {{ number_format($cDeposit, 0) }} = ₹ {{ number_format($expClosing, 2) }}
                        </div>
                        <div class="pt-2 flex items-center gap-4 text-xs font-black">
                            <span>Actual Physical Count: ₹ {{ $actClosing !== null ? number_format($actClosing, 2) : 'Not Closed' }}</span>
                            <span>&bull;</span>
                            <span class="{{ abs($vVariance) < 0.01 ? 'text-emerald-700' : 'text-rose-700' }}">
                                Variance: {{ $vVariance >= 0 ? '+' : '' }}₹ {{ number_format($vVariance, 2) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Additional Analytic Sections: Consultations & Bank Control -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-100 text-xs">
                    <!-- Consultations breakdown -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                        <span class="font-bold text-slate-800 uppercase tracking-wider block">OPD Consultations Analytics</span>
                        <div class="flex justify-between"><span>Total Appointments:</span><strong>{{ $report['consultation']['total'] }}</strong></div>
                        <div class="flex justify-between"><span>Clinic Walk-in:</span><span>{{ $report['consultation']['walkin'] }}</span></div>
                        <div class="flex justify-between"><span>Online Bookings:</span><span>{{ $report['consultation']['online'] }}</span></div>
                        <div class="flex justify-between"><span>Phone Bookings:</span><span>{{ $report['consultation']['telephonic'] }}</span></div>
                        <div class="flex justify-between pt-1 border-t border-slate-200"><span>Consultation Cash:</span><strong>₹ {{ number_format($report['consultation']['cash_collection'], 2) }}</strong></div>
                        <div class="flex justify-between"><span>Consultation UPI:</span><strong>₹ {{ number_format($report['consultation']['upi_collection'], 2) }}</strong></div>
                    </div>

                    <!-- Diagnostics breakdown -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                        <span class="font-bold text-slate-800 uppercase tracking-wider block">Diagnostic Lab Analytics</span>
                        <div class="flex justify-between"><span>Total Diagnostic Bills:</span><strong>{{ $report['diagnostics']['count'] }}</strong></div>
                        <div class="flex justify-between"><span>Diagnostic Cash:</span><strong>₹ {{ number_format($report['diagnostics']['cash_collection'], 2) }}</strong></div>
                        <div class="flex justify-between"><span>Diagnostic UPI:</span><strong>₹ {{ number_format($report['diagnostics']['upi_collection'], 2) }}</strong></div>
                        <div class="flex justify-between pt-1 border-t border-slate-200"><span>Total Diagnostic Revenue:</span><strong>₹ {{ number_format($report['diagnostics']['total_collection'], 2) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
