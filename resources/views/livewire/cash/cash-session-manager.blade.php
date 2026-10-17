<div class="py-6 px-4 sm:px-6 max-w-7xl mx-auto space-y-6">
    <!-- Header & Date Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Cash Counter & Session Manager</h1>
            <p class="text-xs text-slate-500 mt-0.5">Strict double-entry cash control: Opening physical cash &rarr; Receipts &rarr; Expenses &rarr; Bank deposits &rarr; Expected &rarr; Physical count &rarr; Variance.</p>
        </div>
        <div class="flex items-center gap-3">
            <div>
                <input type="date" wire:model.live="currentDate" id="cash_current_date" class="p-2 border border-slate-300 rounded-xl text-xs bg-white font-medium">
            </div>
            <a href="{{ route('cash.daily-report', ['date' => $currentDate]) }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow font-semibold text-xs flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Daily Cash Report</span>
            </a>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- TODAY'S CASH & FINANCIAL CONTROL OVERVIEW (Section 15, 16) -->
    <!-- ================================================================== -->
    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Prominent Today's Cash Collection Card (Mandatory Section 15, 16) -->
            <div class="bg-gradient-to-br from-emerald-500 to-teal-700 text-white rounded-2xl p-5 shadow-sm border border-emerald-400">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] uppercase font-black tracking-wider text-emerald-100">Today's Cash Collection</span>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-800 text-emerald-100">Settled Cash</span>
                </div>
                <div class="text-3xl font-black mt-2 tracking-tight" id="card_today_cash_collection">
                    ₹ {{ number_format($todayCashCollection, 2) }}
                </div>
                <div class="text-[11px] text-emerald-100/90 mt-1 flex items-center justify-between">
                    <span>Physical Drawer Inflow</span>
                    <a href="{{ route('cash.daily-report', ['date' => $currentDate]) }}" class="underline font-bold hover:text-white">View Details &rarr;</a>
                </div>
            </div>

            <!-- 2. Today's UPI Collection -->
            <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500">Today's UPI Collection</span>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase bg-blue-100 text-blue-800">Direct to Bank</span>
                </div>
                <div class="text-2xl font-black text-slate-900 mt-2 tracking-tight">
                    ₹ {{ number_format($todayUpiCollection, 2) }}
                </div>
                <div class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                    <span>Soundbox Verified</span>
                    <span class="font-medium text-slate-600">Bank Credit</span>
                </div>
            </div>

            <!-- 3. Today's Expenses & Bank Deposits -->
            <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500">Cash Outflows</span>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase bg-amber-100 text-amber-800">Drawer Reductions</span>
                </div>
                <div class="text-2xl font-black text-slate-900 mt-2 tracking-tight">
                    ₹ {{ number_format($todayCashExpensesTotal + $todayBankCashDepositsTotal, 2) }}
                </div>
                <div class="text-[11px] text-slate-500 mt-1 flex items-center justify-between">
                    <span>Exp: ₹{{ number_format($todayCashExpensesTotal, 0) }}</span>
                    <span>Dep: ₹{{ number_format($todayBankCashDepositsTotal, 0) }}</span>
                </div>
            </div>

            <!-- 4. Expected Closing Cash & Variance -->
            <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] uppercase font-bold tracking-wider text-slate-500">Expected Closing Cash</span>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase {{ abs($dayVariance) < 0.01 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                        {{ abs($dayVariance) < 0.01 ? 'BALANCED' : 'VARIANCE' }}
                    </span>
                </div>
                <div class="text-2xl font-black text-slate-900 mt-2 tracking-tight">
                    ₹ {{ number_format($dayExpectedClosingCash, 2) }}
                </div>
                <div class="text-[11px] text-slate-500 mt-1 flex items-center justify-between">
                    <span>Drawer Count: ₹{{ number_format($dayPhysicalCash, 2) }}</span>
                    <span class="font-bold {{ abs($dayVariance) < 0.01 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $dayVariance >= 0 ? '+' : '' }}{{ number_format($dayVariance, 2) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Session Breakdown (Morning, Evening, Sunday) -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="font-bold text-slate-700 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                <span>Session Breakdown:</span>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-slate-600">
                <div class="flex items-center gap-1.5">
                    <span class="font-bold text-slate-800">Morning:</span>
                    @if($morningSession)
                        <span class="text-emerald-700 font-bold">₹{{ number_format($morningSession->actual_cash_collection, 2) }} Cash</span>
                        <span>(₹{{ number_format($morningSession->actual_upi_collection, 2) }} UPI)</span>
                        <span class="px-1.5 py-0.2 rounded text-[10px] {{ $morningSession->status === 'CLOSED' ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-800' }} font-bold uppercase">{{ $morningSession->status }}</span>
                    @else
                        <span class="text-slate-400">Not Started</span>
                    @endif
                </div>

                <span class="text-slate-300">|</span>

                <div class="flex items-center gap-1.5">
                    <span class="font-bold text-slate-800">Evening:</span>
                    @if($eveningSession)
                        <span class="text-emerald-700 font-bold">₹{{ number_format($eveningSession->actual_cash_collection, 2) }} Cash</span>
                        <span>(₹{{ number_format($eveningSession->actual_upi_collection, 2) }} UPI)</span>
                        <span class="px-1.5 py-0.2 rounded text-[10px] {{ $eveningSession->status === 'CLOSED' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800' }} font-bold uppercase">{{ $eveningSession->status }}</span>
                    @else
                        <span class="text-slate-400">Not Started</span>
                    @endif
                </div>

                @if($sundaySession)
                <span class="text-slate-300">|</span>
                <div class="flex items-center gap-1.5">
                    <span class="font-bold text-slate-800">Sunday:</span>
                    <span class="text-emerald-700 font-bold">₹{{ number_format($sundaySession->actual_cash_collection, 2) }} Cash</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-purple-100 text-purple-800 font-bold uppercase">{{ $sundaySession->status }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Cash Counter Navigation Sub-Tabs (Rule 29) -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto text-xs font-bold">
        <button type="button" wire:click="$set('currentTab', 'session')" class="px-4 py-2 rounded-xl transition {{ $currentTab === 'session' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
            Current Session
        </button>
        <button type="button" wire:click="$set('currentTab', 'sessions_history')" class="px-4 py-2 rounded-xl transition {{ $currentTab === 'sessions_history' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
            Sessions History
        </button>
        <button type="button" wire:click="$set('currentTab', 'expenses')" class="px-4 py-2 rounded-xl transition {{ $currentTab === 'expenses' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
            Expenses ({{ count($todayExpenses) }})
        </button>
        <button type="button" wire:click="$set('currentTab', 'deposits')" class="px-4 py-2 rounded-xl transition {{ $currentTab === 'deposits' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
            Bank Deposit ({{ count($todayDeposits) }})
        </button>
    </div>

    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800 font-bold">&times;</button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs flex items-center justify-between">
            <span>{{ session('error') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-800 font-bold">&times;</button>
        </div>
    @endif

    <!-- TAB 1: CURRENT SESSION (OPEN / CLOSE WORKFLOW) -->
    @if ($currentTab === 'session')
        @if (!$activeSession)
            <!-- START SESSION VIEW (Matching Mockup Panel 1: Start Morning Cash Session) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-4xl mx-auto animate-in fade-in">
                <!-- Ribbon Header (Rule 30, 31) -->
                <div class="bg-emerald-600 px-6 py-2 text-white font-bold text-xs uppercase tracking-wider flex items-center justify-between">
                    <span>Morning Session - At the time of Opening</span>
                    <span class="text-[11px] font-normal lowercase">clinic day opening</span>
                </div>

                <div class="p-6 sm:p-8 space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 gap-2">
                        <div>
                            <h2 class="text-xl font-black text-slate-900">Start Morning Cash Session</h2>
                            <p class="text-xs text-slate-500">Record physical starting cash. UPI collections and expenses are tracked automatically.</p>
                        </div>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 font-bold text-xs border border-emerald-200 self-start sm:self-center">
                            {{ Carbon\Carbon::parse($currentDate)->format('d M Y') }} - {{ $openSessionType }}
                        </span>
                    </div>

                    <form wire:submit.prevent="openSession" class="space-y-6 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <!-- Opening Balance of Bank Account (Rule 30, 31: Read-only ledger derived) -->
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                    Opening Balance of Bank Account *
                                </label>
                                <div class="text-2xl font-black text-slate-900 mt-2">
                                    ₹ {{ number_format($totalBookBankBalance, 2) }}
                                </div>
                                <span class="text-[11px] text-slate-400 mt-1 block">As per bank statement (Ledger-Derived, Rule 30)</span>
                            </div>

                            <!-- Morning Cash in Hand (Rule 31: Physical input) -->
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                    Morning Cash in Hand *
                                </label>
                                <div class="relative mt-2">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 font-bold text-base">₹</span>
                                    <input type="number" step="0.01" wire:model="openingPhysicalCash" id="input_opening_physical_cash" class="w-full pl-8 pr-3 py-2 bg-white border border-slate-300 rounded-xl font-black text-2xl text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                </div>
                                <span class="text-[11px] text-slate-400 mt-1 block">Physical cash available in clinic drawer</span>
                            </div>
                        </div>

                        <!-- Collapsible Accordion: Today Morning Collections & Expenses (Rule 30, Mockup Panel 1) -->
                        <div class="border border-slate-200 rounded-2xl overflow-hidden" x-data="{ open: true }">
                            <button type="button" @click="open = !open" class="w-full px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-left font-bold text-xs text-slate-700 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    <span>Today Morning Collections & Expenses</span>
                                </div>
                                <span class="text-[11px] font-semibold text-slate-400 normal-case">System-derived from operational records</span>
                            </button>

                            <div x-show="open" class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 bg-white">
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                                    <span class="text-[11px] font-semibold text-slate-500 block mb-1">Today Morning UPI Collection</span>
                                    <div class="font-bold text-slate-900 text-base">₹ {{ number_format($todayUpiCollection, 2) }}</div>
                                    <span class="text-[10px] text-slate-400 mt-1 block">Soundbox verified direct to bank</span>
                                </div>
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                                    <span class="text-[11px] font-semibold text-slate-500 block mb-1">Today Morning Expenses</span>
                                    <div class="font-bold text-slate-900 text-base">₹ {{ number_format($todayExpensesTotal, 2) }}</div>
                                    <span class="text-[10px] text-slate-400 mt-1 block">From expense register</span>
                                </div>
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                                    <span class="text-[11px] font-semibold text-slate-500 block mb-1">Today Bank Cash Deposit Amount</span>
                                    <div class="font-bold text-slate-900 text-base">₹ {{ number_format($todayBankCashDepositsTotal, 2) }}</div>
                                    <span class="text-[10px] text-slate-400 mt-1 block">From fund contra slips</span>
                                </div>
                            </div>
                        </div>

                        <!-- Remarks (Optional) (Mockup Panel 1) -->
                        <div>
                            <label class="block font-semibold text-slate-700 uppercase tracking-wider mb-1">Remarks (Optional)</label>
                            <textarea wire:model="sessionNotes" rows="2" placeholder="Any notes for morning session..." class="w-full p-3 border border-slate-300 rounded-xl text-xs"></textarea>
                        </div>

                        <div class="pt-2">
                            <button type="submit" id="btn_open_session_submit" class="w-full sm:w-auto px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md text-sm transition">
                                Start Morning Session &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <!-- END SESSION VIEW (Matching Mockup Panel 2: End Evening Cash Session) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-4xl mx-auto animate-in fade-in">
                <!-- Ribbon Header (Rule 33, 34) -->
                <div class="bg-amber-600 px-6 py-2 text-white font-bold text-xs uppercase tracking-wider flex items-center justify-between">
                    <span>Evening Session - At the time of Closing</span>
                    <span class="text-[11px] font-normal lowercase">Session reconciliation</span>
                </div>

                <div class="p-6 sm:p-8 space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 gap-2">
                        <div>
                            <h2 class="text-xl font-black text-slate-900">End {{ ucfirst(strtolower($activeSession->session_type)) }} Cash Session</h2>
                            <p class="text-xs text-slate-500">Perform physical cash count and verify variance against system calculated figures.</p>
                        </div>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-amber-50 text-amber-800 font-bold text-xs border border-amber-200 self-start sm:self-center">
                            {{ $activeSession->session_date->format('d M Y') }} - {{ $activeSession->session_type }}
                        </span>
                    </div>

                    <!-- Top 3 Metrics Row (Mockup Panel 2) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                {{ ucfirst(strtolower($activeSession->session_type)) }} Cash in Hand (Physical) *
                            </label>
                            <div class="relative mt-2">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 font-bold text-base">₹</span>
                                <input type="number" step="0.01" wire:model.live.debounce.150ms="physicalClosingCash" placeholder="0.00" class="w-full pl-8 pr-3 py-2 bg-white border border-slate-300 rounded-xl font-black text-xl text-slate-900">
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1 block">Physically counted drawer cash</span>
                        </div>

                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                            <span class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Today UPI Collection</span>
                            <div class="text-xl font-black text-slate-900 mt-3">
                                ₹ {{ number_format($activeSession->actual_upi_collection, 2) }}
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1 block">Soundbox verified direct to bank</span>
                        </div>

                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                            <span class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Today Expenses</span>
                            <div class="text-xl font-black text-slate-900 mt-3">
                                ₹ {{ number_format($activeSession->actual_cash_expenses, 2) }}
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1 block">Cash expenses recorded</span>
                        </div>
                    </div>

                    <!-- Closing Cash Collection Card (Mockup Panel 2) -->
                    @php
                        $expectedVal = $activeSession->computeExpectedClosingCash();
                        $physicalVal = (float) $physicalClosingCash;
                        $calcVariance = round($physicalVal - $expectedVal, 2);
                    @endphp

                    <div class="p-6 bg-slate-50/70 border border-slate-200 rounded-2xl space-y-4">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Closing Cash Collection</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                            <!-- Total Expected Closing Cash (Green highlight box) -->
                            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl">
                                <span class="text-[10px] uppercase font-bold text-emerald-700 block mb-1">Total Expected Closing Cash</span>
                                <div class="text-2xl font-black text-emerald-800" id="active_expected_closing_cash">
                                    ₹ {{ number_format($expectedVal, 2) }}
                                </div>
                            </div>

                            <!-- Actual Closing Cash (Counted) -->
                            <div class="p-4 bg-white border border-slate-200 rounded-2xl">
                                <span class="text-[10px] uppercase font-bold text-slate-500 block mb-1">Actual Closing Cash (Counted) *</span>
                                <div class="text-2xl font-black text-slate-900">
                                    ₹ {{ number_format($physicalVal, 2) }}
                                </div>
                            </div>

                            <!-- Variance Pill -->
                            <div class="p-4 bg-white border border-slate-200 rounded-2xl flex flex-col items-center justify-center">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Variance</span>
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-black {{ abs($calcVariance) < 0.01 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    @if(abs($calcVariance) < 0.01)
                                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                        <span>0.00</span>
                                    @else
                                        <span>{{ $calcVariance >= 0 ? '+' : '' }}{{ number_format($calcVariance, 2) }}</span>
                                    @endif
                                </span>
                            </div>
                        </div>

                        <!-- Variance Reason (Required if variance != 0) (Mockup Panel 2) -->
                        @if ($physicalClosingCash !== null && abs($calcVariance) >= 0.01)
                            <div class="pt-2 animate-in fade-in">
                                <label class="block font-bold text-rose-900 mb-1 text-xs">Variance Reason (Required if variance != 0) *</label>
                                <input type="text" wire:model="varianceReason" id="input_variance_reason" placeholder="Enter reason for variance..." class="w-full p-2.5 border border-rose-300 rounded-xl text-xs bg-white text-rose-950">
                            </div>
                        @endif

                        <div class="pt-3 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="$set('showDepositModal', true)" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-800 border border-indigo-200 rounded-xl font-bold text-xs flex items-center gap-1.5">
                                    <span>+ Cash &rarr; Bank Deposit</span>
                                </button>
                                <button type="button" wire:click="$set('showExpenseModal', true)" class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 rounded-xl font-bold text-xs flex items-center gap-1.5">
                                    <span>+ Record Expense</span>
                                </button>
                            </div>

                            <button type="button" wire:click="closeSession" id="btn_close_session_submit" class="w-full sm:w-auto px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md text-xs">
                                Close {{ $activeSession->session_type }} Session &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    <!-- TAB 2: SESSIONS HISTORY -->
    @if ($currentTab === 'sessions_history')
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-slate-900">Today's Cash Sessions Reconciliations</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                            <th class="py-2.5 px-3">Session</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Opening Cash</th>
                            <th class="py-2.5 px-3">Cash Receipts</th>
                            <th class="py-2.5 px-3">Cash Expenses</th>
                            <th class="py-2.5 px-3">Bank Deposit</th>
                            <th class="py-2.5 px-3">Expected</th>
                            <th class="py-2.5 px-3">Physical Count</th>
                            <th class="py-2.5 px-3">Variance</th>
                            <th class="py-2.5 px-3">Variance Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($allTodaySessions as $s)
                            <tr>
                                <td class="py-3 px-3 font-bold text-slate-900">{{ $s->session_type }}</td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $s->status === 'OPEN' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $s->status }}
                                    </span>
                                </td>
                                <td class="py-3 px-3">₹ {{ number_format($s->opening_physical_cash, 2) }}</td>
                                <td class="py-3 px-3 text-emerald-700 font-semibold">+₹ {{ number_format($s->actual_cash_receipts, 2) }}</td>
                                <td class="py-3 px-3 text-rose-700 font-semibold">-₹ {{ number_format($s->actual_cash_expenses, 2) }}</td>
                                <td class="py-3 px-3 text-indigo-700 font-semibold">-₹ {{ number_format($s->actual_bank_cash_deposit, 2) }}</td>
                                <td class="py-3 px-3 font-bold text-slate-800">₹ {{ number_format($s->expected_closing_cash, 2) }}</td>
                                <td class="py-3 px-3 font-bold text-slate-900">
                                    {{ $s->closing_physical_cash !== null ? '₹ ' . number_format($s->closing_physical_cash, 2) : '-' }}
                                </td>
                                <td class="py-3 px-3 font-bold {{ abs($s->cash_variance) < 0.01 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $s->closing_physical_cash !== null ? ($s->cash_variance >= 0 ? '+' : '') . '₹ ' . number_format($s->cash_variance, 2) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-slate-500 italic">{{ $s->variance_reason ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-400">No sessions recorded yet for this date.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- TAB 3: EXPENSES (Rule 37) -->
    @if ($currentTab === 'expenses')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Expense Form Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Record Clinic Expense</h3>
                    <p class="text-xs text-slate-500">Posts Dr Expense, Cr Cash/Bank Ledger immediately</p>
                </div>

                <form wire:submit.prevent="recordExpense" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Expense Category *</label>
                        <select wire:model="expenseCategoryId" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                            @foreach ($expenseCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Amount (₹) *</label>
                        <input type="number" step="0.01" wire:model="expenseAmount" placeholder="e.g. 500" class="w-full p-2 border border-slate-300 rounded-lg font-bold">
                        @error('expenseAmount') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Payment Mode *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="p-2 border rounded-lg flex items-center gap-2 cursor-pointer {{ $expensePaymentMethod === 'CASH' ? 'bg-blue-50 border-blue-500 font-bold' : '' }}">
                                <input type="radio" wire:model.live="expensePaymentMethod" value="CASH">
                                <span>Cash in Hand</span>
                            </label>
                            <label class="p-2 border rounded-lg flex items-center gap-2 cursor-pointer {{ $expensePaymentMethod === 'BANK' ? 'bg-blue-50 border-blue-500 font-bold' : '' }}">
                                <input type="radio" wire:model.live="expensePaymentMethod" value="BANK">
                                <span>Bank Account</span>
                            </label>
                        </div>
                    </div>

                    @if ($expensePaymentMethod === 'BANK')
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Select Bank Account *</label>
                            <select wire:model="expenseBankAccountId" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                                @foreach ($bankAccounts as $acc)
                                    <option value="{{ $acc['id'] }}">{{ $acc['bank_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Payee / Paid To *</label>
                        <input type="text" wire:model="expensePayee" placeholder="Vendor / Store / Electrician" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('expensePayee') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Notes / Voucher Description</label>
                        <textarea wire:model="expenseNotes" rows="2" class="w-full p-2 border border-slate-300 rounded-lg"></textarea>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl shadow text-xs">
                            Save Expense Voucher &rarr;
                        </button>
                    </div>
                </form>
            </div>

            <!-- Expense List Table -->
            <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Today's Expense Vouchers</h3>
                    <span class="text-xs font-bold text-rose-700">Total: ₹ {{ number_format($todayExpensesTotal, 2) }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-2.5 px-3">Category</th>
                                <th class="py-2.5 px-3">Payee</th>
                                <th class="py-2.5 px-3">Mode</th>
                                <th class="py-2.5 px-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($todayExpenses as $exp)
                                <tr>
                                    <td class="py-2.5 px-3 font-bold text-slate-900">{{ $exp->expenseCategory->name ?? 'General' }}</td>
                                    <td class="py-2.5 px-3 text-slate-700">{{ $exp->payee }}</td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $exp->payment_method === 'CASH' ? 'bg-amber-100 text-amber-900' : 'bg-indigo-100 text-indigo-900' }}">
                                            {{ $exp->payment_method }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-black text-rose-700">₹ {{ number_format($exp->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-slate-400">No expenses recorded yet today.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 4: BANK DEPOSITS (Rule 38) -->
    @if ($currentTab === 'deposits')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Deposit Form Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Cash &rarr; Bank Deposit</h3>
                    <p class="text-xs text-slate-500">Fund Transfer: Dr Bank, Cr Cash Contra Journal</p>
                </div>

                <form wire:submit.prevent="recordDeposit" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Target Bank Account *</label>
                        <select wire:model="depositBankAccountId" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                            @foreach ($bankAccounts as $acc)
                                <option value="{{ $acc['id'] }}">{{ $acc['bank_name'] }} ({{ $acc['account_number'] }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Deposit Amount (₹) *</label>
                        <input type="number" step="0.01" wire:model="depositAmount" placeholder="e.g. 3000" class="w-full p-2 border border-slate-300 rounded-lg font-bold">
                        @error('depositAmount') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Bank Receipt / Deposit Slip No.</label>
                        <input type="text" wire:model="depositReference" placeholder="Deposit slip reference" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Notes (Optional)</label>
                        <textarea wire:model="depositNotes" rows="2" class="w-full p-2 border border-slate-300 rounded-lg"></textarea>
                    </div>

                    <!-- Accounting Contra Preview (Rule 38) -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 font-mono text-[11px] text-slate-700 space-y-0.5">
                        <div class="font-bold text-slate-900">Contra Entry Preview:</div>
                        <div class="text-emerald-700">Dr Bank Account (1002)</div>
                        <div class="text-rose-700">Cr Cash in Hand (1001)</div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow text-xs">
                            Confirm Deposit &rarr;
                        </button>
                    </div>
                </form>
            </div>

            <!-- Deposit History List -->
            <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Today's Bank Cash Deposits</h3>
                    <span class="text-xs font-bold text-indigo-700">Total: ₹ {{ number_format($todayBankCashDepositsTotal, 2) }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Reference / Slip</th>
                                <th class="py-2.5 px-3">Notes</th>
                                <th class="py-2.5 px-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($todayDeposits as $dep)
                                <tr>
                                    <td class="py-2.5 px-3 font-bold text-slate-900">{{ Carbon\Carbon::parse($dep->transfer_date)->format('d M Y') }}</td>
                                    <td class="py-2.5 px-3 text-slate-700">{{ $dep->reference_number ?: '—' }}</td>
                                    <td class="py-2.5 px-3 text-slate-500">{{ $dep->notes ?: 'Bank cash deposit' }}</td>
                                    <td class="py-2.5 px-3 text-right font-black text-indigo-700">₹ {{ number_format($dep->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-slate-400">No bank cash deposits recorded yet today.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Modals for Fast Floating Access -->
    @if ($showDepositModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Deposit Cash to Bank</h3>
                        <p class="text-xs text-slate-500">Fund Transfer: Dr Bank, Cr Cash</p>
                    </div>
                    <button type="button" wire:click="$set('showDepositModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Target Bank Account *</label>
                        <select wire:model="depositBankAccountId" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                            @foreach ($bankAccounts as $acc)
                                <option value="{{ $acc['id'] }}">{{ $acc['bank_name'] }} ({{ $acc['account_number'] }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Deposit Amount (₹) *</label>
                        <input type="number" step="0.01" wire:model="depositAmount" placeholder="e.g. 5000" class="w-full p-2 border border-slate-300 rounded-lg font-bold text-sm">
                        @error('depositAmount') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Bank Receipt / Deposit Slip No.</label>
                        <input type="text" wire:model="depositReference" placeholder="Deposit slip reference" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Notes (Optional)</label>
                        <textarea wire:model="depositNotes" rows="2" class="w-full p-2 border border-slate-300 rounded-lg"></textarea>
                    </div>
                </div>

                <div class="mt-6 pt-3 border-t border-slate-100 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showDepositModal', false)" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-xs">Cancel</button>
                    <button type="button" wire:click="recordDeposit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs shadow">Record Deposit</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showExpenseModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Record Operating Expense</h3>
                        <p class="text-xs text-slate-500">Dr Expense, Cr Cash/Bank</p>
                    </div>
                    <button type="button" wire:click="$set('showExpenseModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Expense Category *</label>
                        <select wire:model="expenseCategoryId" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                            @foreach ($expenseCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Amount (₹) *</label>
                        <input type="number" step="0.01" wire:model="expenseAmount" placeholder="e.g. 350" class="w-full p-2 border border-slate-300 rounded-lg font-bold text-sm">
                        @error('expenseAmount') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Payment Method *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="p-2 border rounded-lg flex items-center gap-2 cursor-pointer {{ $expensePaymentMethod === 'CASH' ? 'bg-blue-50 border-blue-500 font-bold' : '' }}">
                                <input type="radio" wire:model.live="expensePaymentMethod" value="CASH">
                                <span>Cash in Hand</span>
                            </label>
                            <label class="p-2 border rounded-lg flex items-center gap-2 cursor-pointer {{ $expensePaymentMethod === 'BANK' ? 'bg-blue-50 border-blue-500 font-bold' : '' }}">
                                <input type="radio" wire:model.live="expensePaymentMethod" value="BANK">
                                <span>Bank Account</span>
                            </label>
                        </div>
                    </div>

                    @if ($expensePaymentMethod === 'BANK')
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Select Bank Account *</label>
                            <select wire:model="expenseBankAccountId" class="w-full p-2 border border-slate-300 rounded-lg bg-white">
                                @foreach ($bankAccounts as $acc)
                                    <option value="{{ $acc['id'] }}">{{ $acc['bank_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Paid To / Payee *</label>
                        <input type="text" wire:model="expensePayee" placeholder="Vendor / Store / Person" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('expensePayee') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Description / Bill Notes</label>
                        <textarea wire:model="expenseNotes" rows="2" class="w-full p-2 border border-slate-300 rounded-lg"></textarea>
                    </div>
                </div>

                <div class="mt-6 pt-3 border-t border-slate-100 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showExpenseModal', false)" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-xs">Cancel</button>
                    <button type="button" wire:click="recordExpense" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg text-xs shadow">Save Expense</button>
                </div>
            </div>
        </div>
    @endif
</div>
