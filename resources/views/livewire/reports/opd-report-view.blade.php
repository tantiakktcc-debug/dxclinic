<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 space-y-6">
    <!-- Header with Tabs and Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded bg-teal-100 text-teal-800 text-xs font-black uppercase tracking-wider">Reports</span>
                <span class="text-xs text-slate-400 font-semibold">&bull;</span>
                <span class="text-xs text-slate-500 font-medium">OPD Consultation Analytics</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-1">
                @if($activeTab === 'session') Session-wise Patient Report
                @elseif($activeTab === 'day') Day-wise Patient Report
                @else Doctor-wise OPD Report
                @endif
            </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 shadow-sm transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print Report
            </button>
            <a href="{{ route('pdf.opd-report', ['period' => $period, 'start_date' => $startDate, 'end_date' => $endDate, 'doctor_id' => $doctorId, 'session_type' => $sessionType, 'booking_source' => $bookingSource, 'consultation_mode' => $consultationMode, 'payment_status' => $paymentStatus]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Download PDF
            </a>
            <button wire:click="exportCsv" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV
            </button>
        </div>
    </div>

    <!-- Navigation Tabs (Section 54, 56, 58) -->
    <div class="flex items-center gap-2 border-b border-slate-200">
        <button wire:click="setTab('session')" class="px-4 py-2.5 text-xs font-bold border-b-2 transition {{ $activeTab === 'session' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            Session-wise Patient Report
        </button>
        <button wire:click="setTab('day')" class="px-4 py-2.5 text-xs font-bold border-b-2 transition {{ $activeTab === 'day' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            Day-wise Patient Report
        </button>
        <button wire:click="setTab('doctor')" class="px-4 py-2.5 text-xs font-bold border-b-2 transition {{ $activeTab === 'doctor' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            Doctor-wise OPD Report
        </button>
    </div>

    <!-- Filter Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- Period Selector -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Period</label>
                <select wire:model.live="period" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-teal-500 focus:border-teal-500 bg-white">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="half_yearly">Half-Yearly</option>
                    <option value="financial_year">Financial Year (Apr-Mar)</option>
                    <option value="custom">Custom Date Range</option>
                </select>
            </div>

            <!-- Start Date -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">From Date</label>
                <input type="date" wire:model.live="startDate" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-teal-500 focus:border-teal-500">
            </div>

            <!-- End Date -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">To Date</label>
                <input type="date" wire:model.live="endDate" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-teal-500 focus:border-teal-500">
            </div>

            <!-- Doctor Filter (Section 57, 58, 59) -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Doctor Scope</label>
                <select wire:model.live="doctorId" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-teal-500 focus:border-teal-500 bg-white">
                    <option value="ALL">All Doctors (Combined)</option>
                    @foreach($doctors as $d)
                        <option value="{{ $d['id'] }}">{{ \App\Services\DoctorNamePresenter::format($d['name']) }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Session Filter (Section 55, 64) -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Session</label>
                <select wire:model.live="sessionType" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-teal-500 focus:border-teal-500 bg-white">
                    <option value="ALL">All Sessions</option>
                    <option value="MORNING">Morning</option>
                    <option value="EVENING">Evening</option>
                    <option value="SUNDAY">Sunday</option>
                </select>
            </div>

            <!-- Booking Source (Section 53) -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Booking Source</label>
                <select wire:model.live="bookingSource" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-teal-500 focus:border-teal-500 bg-white">
                    <option value="ALL">All Sources</option>
                    <option value="CLINIC">Clinic Operator</option>
                    <option value="ONLINE">Online Patient</option>
                    <option value="TELEPHONIC">Telephonic</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3 pt-3 border-t border-slate-100">
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Consultation Mode</label>
                <select wire:model.live="consultationMode" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-teal-500 focus:border-teal-500 bg-white">
                    <option value="ALL">All Modes</option>
                    <option value="CLINIC_PHYSICAL">Clinic Consultancy (₹600)</option>
                    <option value="ONLINE">Online Consultancy (₹700)</option>
                    <option value="VISITING_CONSULTANCY">Visiting Consultancy</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Payment Status</label>
                <select wire:model.live="paymentStatus" class="w-full rounded-xl border-slate-300 text-xs p-2 focus:ring-teal-500 focus:border-teal-500 bg-white">
                    <option value="ALL">All Statuses</option>
                    <option value="PAID">Settled (Paid)</option>
                    <option value="PENDING">Pending (Unpaid)</option>
                    <option value="WAIVED">Waived (₹0 Free Revisit / Grace)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Patients</span>
            <div class="text-2xl font-black text-slate-800 mt-1">{{ $report['total_patients'] }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <span class="text-[10px] uppercase font-bold text-teal-600 block tracking-wider">Settled Collection</span>
            <div class="text-2xl font-black text-teal-700 mt-1">₹{{ number_format($report['total_collection'], 2) }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <span class="text-[10px] uppercase font-bold text-indigo-600 block tracking-wider">Gross Billed</span>
            <div class="text-2xl font-black text-indigo-700 mt-1">₹{{ number_format($report['total_billed'], 2) }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <span class="text-[10px] uppercase font-bold text-amber-600 block tracking-wider">Waived (Free Revisit)</span>
            <div class="text-2xl font-black text-amber-700 mt-1">₹{{ number_format($report['total_waived'], 2) }}</div>
        </div>
    </div>

    <!-- OPD Report Table (Section 124 Format) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">OPD Patient Register (Date Range: {{ $report['start_date'] }} to {{ $report['end_date'] }})</span>
            <span class="text-xs text-slate-500 font-semibold">{{ count($report['rows']) }} Records Found</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs divide-y divide-slate-200">
                <thead class="bg-slate-50 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-3">Patient ID</th>
                        <th class="py-3 px-3">Patient Name</th>
                        <th class="py-3 px-2 text-right">Age</th>
                        <th class="py-3 px-3">Address</th>
                        <th class="py-3 px-3">City</th>
                        <th class="py-3 px-3">Mobile</th>
                        <th class="py-3 px-2 text-center">Token</th>
                        <th class="py-3 px-2">Session</th>
                        <th class="py-3 px-2">Booking Source</th>
                        <th class="py-3 px-3">Consultation Type</th>
                        <th class="py-3 px-3 text-right">Paid Fee</th>
                        <th class="py-3 px-2">Payment Mode</th>
                        <th class="py-3 px-3">Doctor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($report['rows'] as $r)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-2.5 px-3 font-mono font-bold text-teal-800">{{ $r['patient_id'] }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-900">{{ $r['patient_name'] }}</td>
                            <td class="py-2.5 px-2 text-right">{{ $r['age'] }}</td>
                            <td class="py-2.5 px-3 text-slate-600 max-w-[150px] truncate" title="{{ $r['address'] }}">{{ $r['address'] }}</td>
                            <td class="py-2.5 px-3 text-slate-600">{{ $r['city'] }}</td>
                            <td class="py-2.5 px-3 font-mono text-slate-700 whitespace-nowrap">{{ $r['mobile'] }}</td>
                            <td class="py-2.5 px-2 text-center font-black text-slate-900 bg-slate-50/50">{{ $r['token'] }}</td>
                            <td class="py-2.5 px-2">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                    {{ $r['session'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-2">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $r['booking_source'] === 'Online' ? 'bg-purple-100 text-purple-800' : ($r['booking_source'] === 'Telephonic' ? 'bg-amber-100 text-amber-800' : 'bg-teal-100 text-teal-800') }}">
                                    {{ $r['booking_source'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-semibold {{ $r['consultation_type'] === 'Online Consultancy' ? 'text-purple-700' : 'text-slate-800' }}">
                                {{ $r['consultation_type'] }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-black {{ $r['paid_fee'] > 0 ? 'text-emerald-700' : ($r['waiver_type'] ? 'text-amber-600' : 'text-slate-400') }}">
                                ₹{{ number_format($r['paid_fee'], 2) }}
                            </td>
                            <td class="py-2.5 px-2 font-mono text-[10px] text-slate-600 whitespace-nowrap">
                                {{ $r['payment_mode'] }}
                            </td>
                            <td class="py-2.5 px-3 font-medium text-slate-800 whitespace-nowrap">
                                {{ $r['doctor'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="py-8 text-center text-slate-400 text-xs">
                                No OPD consultations found matching the selected filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <!-- Report Bottom Footer (Section 20, 55, 56, 124) -->
                <tfoot class="bg-gradient-to-r from-teal-50 to-indigo-50 border-t-2 border-teal-200">
                    <tr>
                        <td colspan="10" class="py-4 px-4 text-sm font-black text-slate-900 uppercase tracking-wider text-right">
                            TOTAL COLLECTION:
                        </td>
                        <td class="py-4 px-3 text-right text-base font-black text-teal-800 whitespace-nowrap">
                            ₹{{ number_format($report['total_collection'], 2) }}
                        </td>
                        <td colspan="2" class="py-4 px-3 text-xs text-slate-500 font-semibold">
                            (Derived strictly from settled payments)
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
