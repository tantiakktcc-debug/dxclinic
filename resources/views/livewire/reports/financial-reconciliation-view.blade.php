<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4 no-print">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Complete Financial Reconciliation</h1>
            <p class="text-xs text-slate-500 mt-0.5">Authoritative double-entry audit: Cash Reconciliation, Book Bank Balances, General Ledger Equilibrium, and Period Registers (Revision 24 Section 18-29, 68-73)</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Report</span>
            </button>
        </div>
    </div>

    <!-- Filter Bar (Section 25 & 26) -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4 no-print">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-slate-600 mr-1">Period:</span>
            @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'half_yearly' => 'Half-Yearly', 'fy' => 'Financial Year', 'custom' => 'Custom'] as $pKey => $pLabel)
                <button type="button" wire:click="applyPeriod('{{ $pKey }}')" class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $period === $pKey ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                    {{ $pLabel }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                <span>From:</span>
                <input type="date" wire:model.live="startDate" class="py-1 px-2 border border-slate-300 rounded-lg text-xs font-mono font-medium">
                <span>To:</span>
                <input type="date" wire:model.live="endDate" class="py-1 px-2 border border-slate-300 rounded-lg text-xs font-mono font-medium">
            </div>
            <div class="w-32">
                <select wire:model.live="sessionFilter" class="w-full py-1.5 px-2 text-xs border border-slate-300 rounded-lg bg-white font-medium">
                    <option value="ALL">All Sessions</option>
                    <option value="morning">Morning</option>
                    <option value="evening">Evening</option>
                    <option value="single">Sunday</option>
                </select>
            </div>
        </div>
    </div>

    <!-- System Equilibrium Badges (Section 2 & 22) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Physical Cash Reconciliation Status -->
        <div class="p-4 rounded-2xl border {{ $isCashBalanced ? 'bg-emerald-50/70 border-emerald-200 text-emerald-950' : 'bg-amber-50/70 border-amber-200 text-amber-950' }} flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full {{ $isCashBalanced ? 'bg-emerald-500' : 'bg-amber-500' }} animate-pulse"></div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Physical Cash Drawer Reconciliation</div>
                    <div class="text-sm font-black mt-0.5">Status: {{ $isCashBalanced ? 'BALANCED' : 'VARIANCE DETECTED' }}</div>
                </div>
            </div>
            <div class="text-right font-mono text-xs">
                <div>Expected: ₹ {{ number_format($expectedClosingCash, 2) }}</div>
                <div class="font-bold {{ abs($cashVariance) < 0.01 ? 'text-emerald-700' : 'text-amber-700' }}">Variance: ₹ {{ number_format($cashVariance, 2) }}</div>
            </div>
        </div>

        <!-- Double-Entry GL Equilibrium Status -->
        <div class="p-4 rounded-2xl border {{ $isLedgerBalanced ? 'bg-emerald-50/70 border-emerald-200 text-emerald-950' : 'bg-rose-50/70 border-rose-200 text-rose-950' }} flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full {{ $isLedgerBalanced ? 'bg-emerald-500' : 'bg-rose-500' }}"></div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">General Ledger Equilibrium (Total Debits = Total Credits)</div>
                    <div class="text-sm font-black mt-0.5">Status: {{ $isLedgerBalanced ? 'BALANCED (₹0.00 Diff)' : 'REQUIRES REVIEW' }}</div>
                </div>
            </div>
            <div class="text-right font-mono text-xs">
                <div>Debits: ₹ {{ number_format($totalDebits, 2) }}</div>
                <div>Credits: ₹ {{ number_format($totalCredits, 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Financial KPIs Grid (Section 68) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Prominent Today's Cash Collection (Section 15) -->
        <div class="p-4 rounded-2xl bg-amber-50 border-2 border-amber-300 shadow-xs">
            <div class="text-[10px] font-black uppercase tracking-wider text-amber-800">Cash Collection</div>
            <div class="text-lg font-black text-amber-950 mt-1 font-mono">₹ {{ number_format($cashCollection, 2) }}</div>
            <div class="text-[10px] text-amber-700 mt-1 font-medium">Settled Cash Only</div>
        </div>

        <!-- Today's UPI Collection -->
        <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 shadow-xs">
            <div class="text-[10px] font-black uppercase tracking-wider text-blue-700">UPI Collection</div>
            <div class="text-lg font-black text-blue-950 mt-1 font-mono">₹ {{ number_format($upiCollection, 2) }}</div>
            <div class="text-[10px] text-blue-600 mt-1 font-medium">Bank Ledger Direct</div>
        </div>

        <!-- Total Collection -->
        <div class="p-4 rounded-2xl bg-slate-900 text-white shadow-xs">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Collections</div>
            <div class="text-lg font-black text-white mt-1 font-mono">₹ {{ number_format($totalCollection, 2) }}</div>
            <div class="text-[10px] text-slate-300 mt-1 font-medium">Cash + UPI Received</div>
        </div>

        <!-- Total Revenue -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-500">Gross Revenue</div>
            <div class="text-lg font-black text-slate-900 mt-1 font-mono">₹ {{ number_format($totalRevenue, 2) }}</div>
            <div class="text-[10px] text-slate-400 mt-1 font-medium">Consultation + Diagnostics</div>
        </div>

        <!-- Total Expenses -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-500">Total Expenses</div>
            <div class="text-lg font-black text-rose-700 mt-1 font-mono">₹ {{ number_format($totalExpenses, 2) }}</div>
            <div class="text-[10px] text-slate-400 mt-1 font-medium">Cash ₹{{ number_format($cashExpenses, 0) }} | Bank ₹{{ number_format($bankExpenses, 0) }}</div>
        </div>

        <!-- Operating Surplus -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-500">Operating Surplus</div>
            <div class="text-lg font-black {{ $operatingSurplus >= 0 ? 'text-emerald-700' : 'text-rose-700' }} mt-1 font-mono">₹ {{ number_format($operatingSurplus, 2) }}</div>
            <div class="text-[10px] text-slate-400 mt-1 font-medium">Revenue &minus; Expenses</div>
        </div>
    </div>

    <!-- Mathematical Cash Formula & Bank Balances (Section 22 & 23) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cash Formula Breakdown -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
            <div class="border-b border-slate-100 pb-2 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900">Physical Cash Equation Breakdown (Section 22, 72)</h3>
                <span class="text-[10px] text-slate-400 uppercase font-mono font-bold">UPI Excluded</span>
            </div>
            <div class="text-xs space-y-2 font-mono">
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-600 font-sans">Opening Physical Cash (Drawer)</span>
                    <span class="font-bold text-slate-900">+ ₹ {{ number_format($openingPhysicalCash, 2) }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50 text-amber-800">
                    <span class="text-slate-600 font-sans">Cash Collections (Settled Payments)</span>
                    <span class="font-bold">+ ₹ {{ number_format($cashCollection, 2) }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50 text-rose-700">
                    <span class="text-slate-600 font-sans">Cash Expenses Paid</span>
                    <span class="font-bold">&minus; ₹ {{ number_format($cashExpenses, 2) }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50 text-blue-700">
                    <span class="text-slate-600 font-sans">Cash Deposited to Bank (Contra Transfer)</span>
                    <span class="font-bold">&minus; ₹ {{ number_format($totalCashDeposited, 2) }}</span>
                </div>
                <div class="flex justify-between py-2 pt-2 border-t-2 border-slate-200 font-bold text-sm">
                    <span class="text-slate-900 font-sans">Expected Closing Cash</span>
                    <span class="font-black text-slate-900">= ₹ {{ number_format($expectedClosingCash, 2) }}</span>
                </div>
                <div class="flex justify-between py-1 text-slate-700">
                    <span class="text-slate-600 font-sans">Actual Physical Cash Count</span>
                    <span class="font-bold">₹ {{ number_format($closingPhysicalCash, 2) }}</span>
                </div>
                <div class="flex justify-between py-1 border-t border-slate-200 font-bold {{ abs($cashVariance) < 0.01 ? 'text-emerald-700' : 'text-amber-700' }}">
                    <span class="font-sans">Drawer Variance</span>
                    <span>₹ {{ number_format($cashVariance, 2) }} ({{ $isCashBalanced ? 'Balanced' : 'Requires Reason' }})</span>
                </div>
            </div>
        </div>

        <!-- Book Bank Balances (Section 23) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
            <div class="border-b border-slate-100 pb-2 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900">Book Bank Balances (Section 23)</h3>
                <span class="text-[10px] text-slate-400 uppercase font-mono font-bold">General Ledger</span>
            </div>
            <div class="p-2.5 bg-blue-50/60 rounded-xl border border-blue-200 text-[11px] text-blue-900 leading-relaxed">
                <strong>Notice:</strong> This is the internal <strong>Book Bank Balance</strong> derived authoritatively from accounting journals. It is not an automated bank statement balance.
            </div>
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="text-slate-500 uppercase text-[10px] font-bold border-b border-slate-100">
                        <th class="py-1.5">Bank / Account</th>
                        <th class="py-1.5">GL Code</th>
                        <th class="py-1.5 text-right">Book Balance (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    @forelse($bankAccounts as $ba)
                        <tr>
                            <td class="py-2 font-sans font-bold text-slate-900">
                                {{ $ba->bank_name }} <span class="text-slate-400 text-[10px]">(*{{ substr($ba->account_number, -4) }})</span>
                            </td>
                            <td class="py-2 text-slate-600 font-mono text-[11px]">{{ $ba->ledgerAccount?->code ?? '-' }}</td>
                            <td class="py-2 text-right font-black text-slate-900">₹ {{ number_format($ba->book_balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-center text-slate-400 font-sans">No bank accounts configured.</td></tr>
                    @endforelse
                    <tr class="font-bold border-t-2 border-slate-200">
                        <td class="py-2 font-sans">Total Book Bank Holdings</td>
                        <td></td>
                        <td class="py-2 text-right font-black text-blue-700">₹ {{ number_format($totalBookBankBalance, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Drill-Down Registers (Section 69, 70) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-3 bg-slate-50 border-b border-slate-200 flex gap-2 overflow-x-auto text-xs no-print">
            <button type="button" wire:click="setDrilldown('payments')" class="px-3.5 py-1.5 rounded-xl font-bold transition whitespace-nowrap {{ $activeDrilldown === 'payments' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100' }}">
                💳 Settled Payments ({{ $payments->count() }})
            </button>
            <button type="button" wire:click="setDrilldown('expenses')" class="px-3.5 py-1.5 rounded-xl font-bold transition whitespace-nowrap {{ $activeDrilldown === 'expenses' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100' }}">
                🧾 Expenses ({{ $expenses->count() }})
            </button>
            <button type="button" wire:click="setDrilldown('transfers')" class="px-3.5 py-1.5 rounded-xl font-bold transition whitespace-nowrap {{ $activeDrilldown === 'transfers' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100' }}">
                🏦 Bank Deposits / Transfers ({{ $transfers->count() }})
            </button>
            <button type="button" wire:click="setDrilldown('sessions')" class="px-3.5 py-1.5 rounded-xl font-bold transition whitespace-nowrap {{ $activeDrilldown === 'sessions' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100' }}">
                🕒 Cash Sessions ({{ $sessions->count() }})
            </button>
        </div>

        <div class="p-4">
            <!-- 1. Payments Register -->
            @if($activeDrilldown === 'payments')
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[10px] font-bold">
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Bill / Receipt</th>
                                <th class="py-2.5 px-3">Patient</th>
                                <th class="py-2.5 px-3">Method</th>
                                <th class="py-2.5 px-3">Session</th>
                                <th class="py-2.5 px-3 text-right">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($payments as $p)
                                <tr class="hover:bg-slate-50/70" wire:key="p-{{ $p->id }}">
                                    <td class="py-2 px-3 font-mono text-[11px]">{{ $p->payment_date->format('d/m/Y') }}</td>
                                    <td class="py-2 px-3 font-mono font-bold text-slate-900">{{ $p->bill?->bill_number ?? '-' }}</td>
                                    <td class="py-2 px-3">{{ $p->patient?->name ?? 'Walk-in' }}</td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $p->payment_method === 'CASH' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $p->payment_method }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 capitalize text-slate-500">{{ $p->cashSession?->session_type ?? 'Desk' }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">₹ {{ number_format($p->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-slate-400">No payment records found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- 2. Expenses Register -->
            @if($activeDrilldown === 'expenses')
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[10px] font-bold">
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Category</th>
                                <th class="py-2.5 px-3">Payee / Description</th>
                                <th class="py-2.5 px-3">Method</th>
                                <th class="py-2.5 px-3 text-right">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($expenses as $e)
                                <tr class="hover:bg-slate-50/70" wire:key="e-{{ $e->id }}">
                                    <td class="py-2 px-3 font-mono text-[11px]">{{ $e->expense_date->format('d/m/Y') }}</td>
                                    <td class="py-2 px-3 font-bold text-slate-900">{{ $e->expenseCategory?->name ?? 'Uncategorized' }}</td>
                                    <td class="py-2 px-3">{{ $e->payee }} @if($e->notes)&bull; <span class="text-slate-400">{{ $e->notes }}</span>@endif</td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $e->payment_method === 'CASH' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $e->payment_method }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono font-bold text-rose-700">₹ {{ number_format($e->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-400">No expense records found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- 3. Fund Transfers Register -->
            @if($activeDrilldown === 'transfers')
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[10px] font-bold">
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">From Account</th>
                                <th class="py-2.5 px-3">To Account (Bank)</th>
                                <th class="py-2.5 px-3">Reference / Notes</th>
                                <th class="py-2.5 px-3 text-right">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($transfers as $t)
                                <tr class="hover:bg-slate-50/70" wire:key="t-{{ $t->id }}">
                                    <td class="py-2 px-3 font-mono text-[11px]">{{ $t->transfer_date->format('d/m/Y') }}</td>
                                    <td class="py-2 px-3 font-mono font-bold text-slate-800">{{ $t->fromLedgerAccount?->name ?? 'Cash (1001)' }}</td>
                                    <td class="py-2 px-3 font-mono font-bold text-blue-800">{{ $t->toLedgerAccount?->name ?? 'Bank' }}</td>
                                    <td class="py-2 px-3 text-slate-500">{{ $t->notes ?: 'Cash Deposit' }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">₹ {{ number_format($t->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-400">No bank deposits / transfers found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- 4. Cash Sessions Register -->
            @if($activeDrilldown === 'sessions')
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[10px] font-bold">
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Session</th>
                                <th class="py-2.5 px-3">Opening Cash (₹)</th>
                                <th class="py-2.5 px-3">Closing Cash (₹)</th>
                                <th class="py-2.5 px-3">Variance (₹)</th>
                                <th class="py-2.5 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($sessions as $s)
                                <tr class="hover:bg-slate-50/70" wire:key="s-{{ $s->id }}">
                                    <td class="py-2 px-3 font-mono text-[11px]">{{ $s->session_date->format('d/m/Y') }}</td>
                                    <td class="py-2 px-3 font-bold capitalize text-slate-900">{{ $s->session_type }}</td>
                                    <td class="py-2 px-3 font-mono">₹ {{ number_format($s->opening_cash, 2) }}</td>
                                    <td class="py-2 px-3 font-mono">₹ {{ number_format($s->closing_cash ?? 0, 2) }}</td>
                                    <td class="py-2 px-3 font-mono font-bold {{ abs($s->cash_variance ?? 0) < 0.01 ? 'text-emerald-700' : 'text-amber-700' }}">
                                        ₹ {{ number_format($s->cash_variance ?? 0, 2) }}
                                    </td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $s->status === 'CLOSED' ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ $s->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-slate-400">No cash sessions found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
