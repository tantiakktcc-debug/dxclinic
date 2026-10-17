<div class="h-full flex flex-col overflow-hidden bg-slate-50"
     x-data="{
         init() {
             window.addEventListener('keydown', (e) => {
                 if (e.key === 'Escape') {
                     $wire.closeCollectModal();
                     $wire.closeRejectModal();
                     $wire.closeRecollectModal();
                     $wire.closeResultEntryModal();
                     $wire.closeReleaseModal();
                     $wire.closeCorrectionModal();
                     $wire.closeAuditModal();
                 }
             });
         }
     }">

    <!-- Flash Notifications -->
    @if (session()->has('success'))
        <div class="bg-emerald-600 text-white px-4 py-2.5 text-xs font-bold flex items-center justify-between shadow-xs z-50">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-white hover:text-emerald-200 font-bold">&times;</button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-rose-600 text-white px-4 py-2.5 text-xs font-bold flex items-center justify-between shadow-xs z-50">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-white hover:text-rose-200 font-bold">&times;</button>
        </div>
    @endif

    <!-- Top Action / Header Bar -->
    <div class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3.5 shrink-0 flex flex-wrap items-center justify-between gap-3 shadow-2xs">
        <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-base shadow-sm">
                🔬
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-base font-black text-slate-900 tracking-tight">Diagnostic Laboratory Workflow</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200">
                        Revision 36
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium">Sample Tracking • Structured Result Entry • Clinical Verification • Final Reports</p>
            </div>
        </div>

        <!-- Metric KPI Pills -->
        <div class="flex items-center gap-2 text-xs">
            <span class="bg-slate-100 text-slate-700 font-bold px-2.5 py-1.5 rounded-lg border border-slate-200">
                Orders: <strong class="text-slate-900">{{ $counts['total'] }}</strong>
            </span>
            <span class="bg-amber-50 text-amber-800 font-bold px-2.5 py-1.5 rounded-lg border border-amber-200">
                Sample Pending: <strong class="text-amber-900">{{ $counts['pending_sample'] }}</strong>
            </span>
            <span class="bg-teal-50 text-teal-800 font-bold px-2.5 py-1.5 rounded-lg border border-teal-200">
                Collected: <strong class="text-teal-900">{{ $counts['collected'] }}</strong>
            </span>
            <span class="bg-blue-50 text-blue-800 font-bold px-2.5 py-1.5 rounded-lg border border-blue-200">
                Entered: <strong class="text-blue-900">{{ $counts['result_entered'] }}</strong>
            </span>
            <span class="bg-purple-50 text-purple-800 font-bold px-2.5 py-1.5 rounded-lg border border-purple-200">
                Verified: <strong class="text-purple-900">{{ $counts['verified'] }}</strong>
            </span>
            <span class="bg-emerald-50 text-emerald-800 font-bold px-2.5 py-1.5 rounded-lg border border-emerald-200">
                Released: <strong class="text-emerald-900">{{ $counts['released'] }}</strong>
            </span>
        </div>
    </div>

    <!-- Navigation Tabs & Search/Filter Toolbar -->
    <div class="bg-slate-100/80 border-b border-slate-200 px-4 sm:px-6 py-2.5 shrink-0 flex flex-wrap items-center justify-between gap-3">
        <!-- Tabs -->
        <div class="flex items-center gap-1.5 bg-white p-1 rounded-xl border border-slate-200 shadow-2xs">
            <button type="button" wire:click="setTab('worklist')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 {{ $activeTab === 'worklist' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
                <span>📋 Investigation Worklist</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $activeTab === 'worklist' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $counts['pending_sample'] + $counts['collected'] + $counts['result_entered'] + $counts['verified'] }}</span>
            </button>
            <button type="button" wire:click="setTab('samples')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 {{ $activeTab === 'samples' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
                <span>🧪 Sample Tracking</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $activeTab === 'samples' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $counts['collected'] }}</span>
            </button>
            <button type="button" wire:click="setTab('reports')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 {{ $activeTab === 'reports' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
                <span>📑 Released Reports</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $activeTab === 'reports' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $counts['released'] }}</span>
            </button>
        </div>

        <!-- Search & Filter Controls -->
        <div class="flex items-center gap-2 flex-wrap">
            <div class="relative w-64">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search patient, order, sample, ID..."
                       class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>

            <select wire:model.live="filterStatus" class="bg-white border border-slate-300 rounded-lg text-xs font-medium px-2.5 py-1.5 text-slate-700 focus:ring-indigo-500">
                <option value="ALL">All Statuses</option>
                <option value="SAMPLE_PENDING">Sample Pending</option>
                <option value="COLLECTED">Collected</option>
                <option value="RESULT_ENTERED">Results Entered</option>
                <option value="VERIFIED">Verified</option>
                <option value="RELEASED">Released</option>
            </select>

            <input type="date" wire:model.live="dateFrom" class="bg-white border border-slate-300 rounded-lg text-xs font-medium px-2 py-1.5 text-slate-700">
            <span class="text-xs text-slate-400">to</span>
            <input type="date" wire:model.live="dateTo" class="bg-white border border-slate-300 rounded-lg text-xs font-medium px-2 py-1.5 text-slate-700">

            <button type="button" wire:click="resetFilters" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-200" title="Reset Filters">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </div>
    </div>

    <!-- Main Workspace Content Table -->
    <div class="flex-1 overflow-y-auto p-4 sm:p-6">

        @if ($activeTab === 'worklist')
            <!-- ============================================================== -->
            <!-- TAB 1: INVESTIGATION WORKLIST                                  -->
            <!-- ============================================================== -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Order Number</th>
                                <th class="px-4 py-3">Patient</th>
                                <th class="px-4 py-3">Investigations Ordered</th>
                                <th class="px-4 py-3">Attending Doctor</th>
                                <th class="px-4 py-3 text-center">Billing & Payment</th>
                                <th class="px-4 py-3 text-center">Specimen ID</th>
                                <th class="px-4 py-3 text-center">Order Status</th>
                                <th class="px-4 py-3 text-right">Clinical Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @forelse ($orders as $order)
                                @php
                                    $specimen = $order->specimens->whereNotIn('status', ['REJECTED', 'CANCELLED'])->last();
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <!-- Order No & Date -->
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">{{ $order->order_number }}</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">{{ $order->order_date ? $order->order_date->format('d M Y H:i') : '—' }}</div>
                                        @if ($order->priority === 'STAT')
                                            <span class="inline-block mt-1 px-1.5 py-0.2 rounded text-[9px] font-black uppercase tracking-wider bg-rose-100 text-rose-700">STAT URGENT</span>
                                        @elseif ($order->priority === 'URGENT')
                                            <span class="inline-block mt-1 px-1.5 py-0.2 rounded text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-700">URGENT</span>
                                        @endif
                                    </td>

                                    <!-- Patient Info -->
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">{{ $order->patient->name }}</div>
                                        <div class="text-[11px] text-indigo-700 font-mono font-bold">{{ $order->patient->patient_id }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $order->patient->age }} Y / {{ $order->patient->sex }}</div>
                                    </td>

                                    <!-- Investigations -->
                                    <td class="px-4 py-3 max-w-xs">
                                        <div class="space-y-1">
                                            @foreach ($order->items as $item)
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $item->status === 'RELEASED' ? 'bg-emerald-500' : ($item->status === 'VERIFIED' ? 'bg-purple-500' : ($item->status === 'COLLECTED' ? 'bg-teal-500' : 'bg-slate-300')) }}"></span>
                                                    <span class="text-xs font-semibold text-slate-800">{{ $item->test_name }}</span>
                                                    <span class="text-[10px] text-slate-400">({{ $item->specimen_type ?? 'Blood' }})</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>

                                    <!-- Doctor -->
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $order->doctor ? 'Dr. ' . $order->doctor->name : 'Attending Staff' }}
                                    </td>

                                    <!-- Financial Settlement (Read-Only) -->
                                    <td class="px-4 py-3 text-center">
                                        @if ($order->diagnosticBill)
                                            <div class="text-xs font-bold text-slate-800">₹{{ number_format($order->diagnosticBill->net_amount, 2) }}</div>
                                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $order->diagnosticBill->status === 'PAID' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                {{ $order->diagnosticBill->status }}
                                            </span>
                                            <div class="text-[9px] text-slate-400 font-mono mt-0.5">{{ $order->diagnosticBill->bill_number }}</div>
                                        @else
                                            <span class="text-slate-400 text-[11px]">—</span>
                                        @endif
                                    </td>

                                    <!-- Specimen ID -->
                                    <td class="px-4 py-3 text-center">
                                        @if ($specimen)
                                            <div class="font-mono font-bold text-teal-800 text-[11px] bg-teal-50 border border-teal-200 px-2 py-1 rounded-md inline-block">
                                                {{ $specimen->specimen_identifier }}
                                            </div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">{{ $specimen->collected_at ? $specimen->collected_at->format('H:i') : '' }}</div>
                                        @else
                                            <span class="text-slate-400 text-[11px]">Not Collected</span>
                                        @endif
                                    </td>

                                    <!-- Order Status Badge -->
                                    <td class="px-4 py-3 text-center">
                                        @if ($order->status === 'RELEASED')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                                ✓ RELEASED
                                            </span>
                                        @elseif ($order->status === 'VERIFIED')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                                ⏳ VERIFIED
                                            </span>
                                        @elseif ($order->status === 'RESULT_ENTERED')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                                📝 RESULTS ENTERED
                                            </span>
                                        @elseif ($order->status === 'COLLECTED')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-teal-100 text-teal-800">
                                                🧪 SAMPLE COLLECTED
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                                ⏳ SAMPLE PENDING
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Clinical Workflow Actions -->
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- Step 1: Collect Specimen -->
                                            @if (in_array($order->status, ['ORDERED', 'SAMPLE_PENDING']))
                                                <button type="button" wire:click="openCollectModal({{ $order->id }})"
                                                        class="px-2.5 py-1.2 rounded-lg text-xs font-bold bg-teal-600 hover:bg-teal-700 text-white transition shadow-2xs">
                                                    Collect Sample
                                                </button>
                                            @endif

                                            <!-- Step 2: Result Entry (Once Collected or Entered) -->
                                            @if (in_array($order->status, ['COLLECTED', 'PROCESSING', 'RESULT_ENTERED']))
                                                <button type="button" wire:click="openResultEntry({{ $order->id }})"
                                                        class="px-2.5 py-1.2 rounded-lg text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white transition shadow-2xs">
                                                    {{ $order->status === 'RESULT_ENTERED' ? 'Edit Results' : 'Enter Results' }}
                                                </button>
                                            @endif

                                            <!-- Step 3: Verify (Doctor or Admin only) -->
                                            @if ($order->status === 'RESULT_ENTERED' && ($userRole === 'ADMIN' || $userRole === 'DOCTOR'))
                                                <button type="button" wire:click="verifyResults({{ $order->id }})"
                                                        class="px-2.5 py-1.2 rounded-lg text-xs font-bold bg-purple-600 hover:bg-purple-700 text-white transition shadow-2xs">
                                                    Verify
                                                </button>
                                            @endif

                                            <!-- Step 4: Release Report (Doctor or Admin only) -->
                                            @if ($order->status === 'VERIFIED' && ($userRole === 'ADMIN' || $userRole === 'DOCTOR'))
                                                <button type="button" wire:click="openReleaseModal({{ $order->id }})"
                                                        class="px-2.5 py-1.2 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-2xs">
                                                    Release Report
                                                </button>
                                            @endif

                                            <!-- View / Reprint Released Report -->
                                            @if ($order->status === 'RELEASED' && $order->report)
                                                <a href="{{ route('pdf.diagnostic-result-report', ['reportId' => $order->report->id]) }}" target="_blank"
                                                   class="px-2.5 py-1.2 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 transition flex items-center gap-1">
                                                    <span>PDF</span>
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                </a>
                                            @endif

                                            <!-- Audit History -->
                                            <button type="button" wire:click="viewAuditHistory({{ $order->id }})" title="View Clinical Audit Trail"
                                                    class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-12 text-slate-400 font-medium">
                                        No diagnostic orders found for the selected filter criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($orders->hasPages())
                    <div class="px-4 py-3 border-t border-slate-200">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>

        @elseif ($activeTab === 'samples')
            <!-- ============================================================== -->
            <!-- TAB 2: SPECIMEN / SAMPLE TRACKING                             -->
            <!-- ============================================================== -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Sample Identifier</th>
                                <th class="px-4 py-3">Patient & Order</th>
                                <th class="px-4 py-3">Specimen Type</th>
                                <th class="px-4 py-3">Collection Details</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3">Notes / Rejection Reason</th>
                                <th class="px-4 py-3 text-right">Sample Controls</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @forelse ($specimens as $sample)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-bold text-slate-900 text-xs">{{ $sample->specimen_identifier }}</div>
                                        @if ($sample->is_recollection)
                                            <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-800">Recollection</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-800">{{ $sample->order->patient->name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">Order: {{ $sample->order->order_number }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-800">
                                            {{ $sample->specimen_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $sample->collected_at ? $sample->collected_at->format('d M Y H:i') : '—' }}</div>
                                        <div class="text-[10px] text-slate-400">By: {{ $sample->collectedBy?->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($sample->status === 'REJECTED')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-100 text-rose-800">REJECTED</span>
                                        @elseif ($sample->status === 'RELEASED')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">RELEASED</span>
                                        @elseif ($sample->status === 'VERIFIED')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-100 text-purple-800">VERIFIED</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-teal-100 text-teal-800">{{ $sample->status }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate text-slate-500">
                                        {{ $sample->rejection_reason ?: ($sample->notes ?: '—') }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if (!in_array($sample->status, ['REJECTED', 'RELEASED', 'CANCELLED']))
                                                <button type="button" wire:click="openRejectModal({{ $sample->id }})"
                                                        class="px-2 py-1 rounded text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition">
                                                    Reject
                                                </button>
                                            @endif
                                            @if ($sample->status === 'REJECTED')
                                                <button type="button" wire:click="openRecollectModal({{ $sample->id }})"
                                                        class="px-2 py-1 rounded text-xs font-semibold bg-teal-600 hover:bg-teal-700 text-white transition shadow-2xs">
                                                    Recollect
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-12 text-slate-400 font-medium">
                                        No specimens registered matching query.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($specimens->hasPages())
                    <div class="px-4 py-3 border-t border-slate-200">
                        {{ $specimens->links() }}
                    </div>
                @endif
            </div>

        @elseif ($activeTab === 'reports')
            <!-- ============================================================== -->
            <!-- TAB 3: RELEASED DIAGNOSTIC REPORTS                            -->
            <!-- ============================================================== -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Report Number</th>
                                <th class="px-4 py-3">Patient</th>
                                <th class="px-4 py-3">Investigations</th>
                                <th class="px-4 py-3">Released Date & By</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Reprint & Correction</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @forelse ($reports as $rep)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900 font-mono">{{ $rep->report_number }}</div>
                                        <div class="text-[10px] text-slate-400">Order: {{ $rep->order->order_number }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-800">{{ $rep->patient->name }}</div>
                                        <div class="text-[11px] font-mono text-indigo-700">{{ $rep->patient->patient_id }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $rep->order->items->pluck('test_name')->implode(', ') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $rep->released_at ? $rep->released_at->format('d M Y H:i') : '—' }}</div>
                                        <div class="text-[10px] text-slate-400">By: {{ $rep->releasedBy?->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">
                                            ✓ FINAL
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- Print / Download PDF -->
                                            <a href="{{ route('pdf.diagnostic-result-report', ['reportId' => $rep->id]) }}" target="_blank"
                                               class="px-2.5 py-1.2 rounded-lg text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition flex items-center gap-1">
                                                <span>Print / PDF</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                            </a>

                                            <!-- Audit -->
                                            <button type="button" wire:click="viewAuditHistory({{ $rep->diagnostic_order_id }})" title="View Audit Trail"
                                                    class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-12 text-slate-400 font-medium">
                                        No released reports found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($reports->hasPages())
                    <div class="px-4 py-3 border-t border-slate-200">
                        {{ $reports->links() }}
                    </div>
                @endif
            </div>
        @endif

    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 1: SAMPLE COLLECTION MODAL                                      -->
    <!-- ===================================================================== -->
    @if ($showCollectModal && $selectedOrder)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden" @click.outside="$wire.closeCollectModal()">
                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Record Specimen Collection</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Order: <strong class="text-slate-800">{{ $selectedOrder->order_number }}</strong> • Patient: {{ $selectedOrder->patient->name }}</p>
                    </div>
                    <button type="button" wire:click="closeCollectModal" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <!-- Investigations requiring specimen -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Investigations Ordered:</label>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-200 space-y-1">
                            @foreach ($selectedOrder->items as $it)
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-slate-800">{{ $it->test_name }}</span>
                                    <span class="text-[11px] text-slate-500 font-mono">{{ $it->specimen_type ?? 'Blood' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Specimen Type Selection -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Specimen Container / Type:</label>
                        <select wire:model="specimenType" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                            <option value="Blood">Blood (Whole / EDTA)</option>
                            <option value="Serum">Serum (Plain / Gel)</option>
                            <option value="Plasma">Plasma (Fluoride / Heparin)</option>
                            <option value="Urine">Urine (Clean Catch)</option>
                            <option value="Stool">Stool Container</option>
                            <option value="Swab">Swab / Culture Media</option>
                            <option value="Other">Other Biological Specimen</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Collection Notes (Optional):</label>
                        <textarea wire:model="collectionNotes" rows="2" placeholder="e.g. Fasting sample collected at 09:30 AM" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-xs text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="bg-teal-50 border border-teal-200 rounded-lg p-3 text-[11px] text-teal-800">
                        ℹ Confirming collection will generate a unique, non-reusable specimen identifier and advance the order into the lab processing queue.
                    </div>
                </div>

                <div class="bg-slate-50 border-t border-slate-200 px-6 py-3.5 flex items-center justify-end gap-2.5">
                    <button type="button" wire:click="closeCollectModal" class="px-4 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-200 transition">Cancel</button>
                    <button type="button" wire:click="collectSample" class="px-5 py-2 rounded-lg text-xs font-bold bg-teal-600 hover:bg-teal-700 text-white transition shadow-sm">
                        Confirm Collection & Generate Sample ID
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 2: RESULT ENTRY MODAL                                           -->
    <!-- ===================================================================== -->
    @if ($showResultEntryModal && $selectedOrder)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-3xl w-full shadow-2xl border border-slate-200 overflow-hidden" @click.outside="$wire.closeResultEntryModal()">
                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Laboratory Structured Result Entry</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Order: <strong class="text-slate-800">{{ $selectedOrder->order_number }}</strong> • Patient: {{ $selectedOrder->patient->name }} ({{ $selectedOrder->patient->patient_id }})</p>
                    </div>
                    <button type="button" wire:click="closeResultEntryModal" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-4 max-h-[65vh] overflow-y-auto">
                    <table class="w-full text-left text-xs border border-slate-200 rounded-lg overflow-hidden">
                        <thead class="bg-slate-100 text-[11px] font-bold text-slate-600 uppercase border-b border-slate-200">
                            <tr>
                                <th class="px-3.5 py-2.5">Investigation</th>
                                <th class="px-3.5 py-2.5">Result Value</th>
                                <th class="px-3.5 py-2.5">Unit</th>
                                <th class="px-3.5 py-2.5">Biological Reference Range</th>
                                <th class="px-3.5 py-2.5">Clinical Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($resultInputs as $itemId => $input)
                                <tr>
                                    <td class="px-3.5 py-3">
                                        <div class="font-bold text-slate-900">{{ $input['parameter_name'] }}</div>
                                        <div class="text-[10px] text-slate-400 uppercase font-mono">{{ $input['result_type'] }}</div>
                                    </td>
                                    <td class="px-3.5 py-3">
                                        <input type="text" wire:model="resultInputs.{{ $itemId }}.result_value"
                                               placeholder="Enter value"
                                               class="w-32 bg-white border border-slate-300 rounded px-2.5 py-1.5 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500">
                                    </td>
                                    <td class="px-3.5 py-3 text-slate-600 font-mono">
                                        {{ $input['unit'] ?: '—' }}
                                    </td>
                                    <td class="px-3.5 py-3 text-slate-500 text-[11px]">
                                        {{ $input['reference_range'] ?: '—' }}
                                    </td>
                                    <td class="px-3.5 py-3">
                                        <input type="text" wire:model="resultInputs.{{ $itemId }}.remarks"
                                               placeholder="Remarks"
                                               class="w-full bg-white border border-slate-300 rounded px-2 py-1 text-xs text-slate-700">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-[11px] text-indigo-900">
                        💡 Saving results marks them as <strong>ENTERED (Draft)</strong>. Clinician verification is required before final report release.
                    </div>
                </div>

                <div class="bg-slate-50 border-t border-slate-200 px-6 py-3.5 flex items-center justify-end gap-2.5">
                    <button type="button" wire:click="closeResultEntryModal" class="px-4 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-200 transition">Cancel</button>
                    <button type="button" wire:click="saveResults" class="px-5 py-2 rounded-lg text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white transition shadow-sm">
                        Save Results
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 3: RELEASE REPORT MODAL                                         -->
    <!-- ===================================================================== -->
    @if ($showReleaseModal && $selectedOrder)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden" @click.outside="$wire.closeReleaseModal()">
                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Authorize & Release Final Report</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Order: {{ $selectedOrder->order_number }}</p>
                    </div>
                    <button type="button" wire:click="closeReleaseModal" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <p class="text-slate-700 font-medium">
                        You are about to finalize and release the diagnostic laboratory report for <strong>{{ $selectedOrder->patient->name }}</strong>.
                        All results have been clinically verified.
                    </p>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Doctor Impression / Report Notes (Optional):</label>
                        <textarea wire:model="reportNotes" rows="3" placeholder="e.g. Clinical correlation advised. Repeat in 2 weeks." class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-xs text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-[11px] text-emerald-800">
                        ✓ Once released, results become clinically immutable and can only be amended via controlled audit corrections.
                    </div>
                </div>

                <div class="bg-slate-50 border-t border-slate-200 px-6 py-3.5 flex items-center justify-end gap-2.5">
                    <button type="button" wire:click="closeReleaseModal" class="px-4 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-200 transition">Cancel</button>
                    <button type="button" wire:click="releaseReport" class="px-5 py-2 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm">
                        Confirm & Release Report
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 4: SPECIMEN REJECTION MODAL                                     -->
    <!-- ===================================================================== -->
    @if ($showRejectModal && $selectedSpecimen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden" @click.outside="$wire.closeRejectModal()">
                <div class="bg-rose-50 border-b border-rose-200 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-rose-900">Reject Specimen</h3>
                        <p class="text-xs text-rose-700 mt-0.5">Sample ID: {{ $selectedSpecimen->specimen_identifier }}</p>
                    </div>
                    <button type="button" wire:click="closeRejectModal" class="text-rose-400 hover:text-rose-700 text-lg font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Mandatory Rejection Reason:</label>
                        <textarea wire:model="rejectionReason" rows="3" placeholder="e.g. Hemolyzed specimen, insufficient quantity for CBC" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-xs text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-rose-500"></textarea>
                        @error('rejectionReason') <span class="text-rose-600 text-[11px] font-semibold block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-[11px] text-slate-600">
                        ⚠ Rejecting this specimen will return the order to <strong>Sample Pending</strong> state and allow a documented recollection. The rejected specimen remains preserved in the audit log.
                    </div>
                </div>

                <div class="bg-slate-50 border-t border-slate-200 px-6 py-3.5 flex items-center justify-end gap-2.5">
                    <button type="button" wire:click="closeRejectModal" class="px-4 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-200 transition">Cancel</button>
                    <button type="button" wire:click="rejectSample" class="px-5 py-2 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition shadow-sm">
                        Confirm Rejection
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 5: RECOLLECTION MODAL                                           -->
    <!-- ===================================================================== -->
    @if ($showRecollectModal && $selectedSpecimen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden" @click.outside="$wire.closeRecollectModal()">
                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Initiate Sample Recollection</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Parent Rejected ID: {{ $selectedSpecimen->specimen_identifier }}</p>
                    </div>
                    <button type="button" wire:click="closeRecollectModal" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="bg-rose-50 border border-rose-200 rounded-lg p-2.5 text-[11px] text-rose-800">
                        <strong>Previous Rejection Reason:</strong> {{ $selectedSpecimen->rejection_reason }}
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Recollection Notes:</label>
                        <textarea wire:model="recollectionNotes" rows="2" placeholder="e.g. Fresh EDTA tube drawn from left arm" class="w-full bg-white border border-slate-300 rounded-lg p-2.5 text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                </div>

                <div class="bg-slate-50 border-t border-slate-200 px-6 py-3.5 flex items-center justify-end gap-2.5">
                    <button type="button" wire:click="closeRecollectModal" class="px-4 py-2 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-200 transition">Cancel</button>
                    <button type="button" wire:click="recollectSample" class="px-5 py-2 rounded-lg text-xs font-bold bg-teal-600 hover:bg-teal-700 text-white transition shadow-sm">
                        Record Recollection
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL 6: AUDIT TIMELINE MODAL                                         -->
    <!-- ===================================================================== -->
    @if ($showAuditModal && $selectedOrder)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-xl w-full shadow-2xl border border-slate-200 overflow-hidden" @click.outside="$wire.closeAuditModal()">
                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Clinical Workflow Audit Trail</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Order: {{ $selectedOrder->order_number }} • Patient: {{ $selectedOrder->patient->name }}</p>
                    </div>
                    <button type="button" wire:click="closeAuditModal" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
                </div>

                <div class="p-6 max-h-[60vh] overflow-y-auto">
                    <div class="relative border-l-2 border-indigo-200 pl-4 space-y-4">
                        @forelse ($auditEntries as $entry)
                            <div class="relative">
                                <div class="absolute -left-[21px] top-1 w-3 h-3 rounded-full bg-indigo-600 border-2 border-white"></div>
                                <div class="text-xs font-bold text-slate-900">{{ str_replace('_', ' ', $entry['action']) }}</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    {{ \Carbon\Carbon::parse($entry['created_at'])->format('d M Y H:i:s') }} • By: <strong>{{ $entry['user']['name'] ?? 'System' }}</strong>
                                </div>
                                @if (!empty($entry['reason']))
                                    <div class="text-[11px] text-slate-600 mt-1 bg-slate-50 p-2 rounded border border-slate-200">
                                        {{ $entry['reason'] }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-6 text-slate-400 text-xs font-medium">
                                No clinical audit events recorded for this order.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="bg-slate-50 border-t border-slate-200 px-6 py-3.5 flex justify-end">
                    <button type="button" wire:click="closeAuditModal" class="px-4 py-2 rounded-lg text-xs font-bold bg-slate-200 hover:bg-slate-300 text-slate-700 transition">Close</button>
                </div>
            </div>
        </div>
    @endif

</div>
