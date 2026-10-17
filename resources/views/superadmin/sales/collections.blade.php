<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections & Payment Realization — DxClinic Superadmin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 1.5rem; }
        .topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1e293b; padding-bottom: 1.25rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .brand { display: flex; align-items: center; gap: 0.75rem; }
        .brand h1 { font-size: 1.4rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .brand .badge { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; }
        .topbar-actions { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
        .btn-link { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 0.6rem 0.9rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
        .btn-link:hover { background: #334155; color: #ffffff; }
        .btn-link.active { border-color: #38bdf8; color: #38bdf8; background: rgba(56, 189, 248, 0.1); }
        .btn-issue { background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; padding: 0.6rem 1.1rem; border-radius: 0.5rem; font-weight: 700; text-decoration: none; font-size: 0.85rem; }
        .btn-logout { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 0.6rem 0.9rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
        .btn-logout:hover { background: #334155; color: #ffffff; }

        /* KPI Cards Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi-card { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; position: relative; overflow: hidden; }
        .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #334155; }
        .kpi-card.total::before { background: #10b981; }
        .kpi-card.cash::before { background: #38bdf8; }
        .kpi-card.upi::before { background: #6366f1; }
        .kpi-card.bank::before { background: #3b82f6; }
        .kpi-card.cheque::before { background: #f59e0b; }
        .kpi-card.net::before { background: #34d399; }
        .kpi-label { font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; justify-content: space-between; }
        .kpi-val { font-size: 1.5rem; font-weight: 800; color: #ffffff; margin-top: 0.4rem; font-family: 'JetBrains Mono', monospace; }

        /* Filter & View Tabs */
        .filter-bar { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; }
        .view-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.85rem; border-bottom: 1px solid #1e293b; flex-wrap: wrap; }
        .view-tab { background: #0f172a; border: 1px solid #27354a; color: #94a3b8; padding: 0.4rem 0.85rem; border-radius: 0.35rem; font-size: 0.8rem; font-weight: 600; text-decoration: none; }
        .view-tab:hover { background: #1e293b; color: #ffffff; }
        .view-tab.active { background: #2563eb; color: #ffffff; border-color: #3b82f6; }

        .filter-form { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: 0.3rem; }
        .form-label { font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .form-select, .form-input { background: #0b0f19; border: 1px solid #27354a; border-radius: 0.4rem; padding: 0.5rem 0.75rem; color: #f8fafc; font-size: 0.85rem; min-width: 150px; }
        .btn-apply { background: #2563eb; color: #ffffff; border: none; border-radius: 0.4rem; padding: 0.55rem 1.1rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; }
        .btn-reset { background: #1e293b; color: #94a3b8; border: 1px solid #334155; border-radius: 0.4rem; padding: 0.55rem 0.9rem; font-weight: 600; font-size: 0.85rem; text-decoration: none; }

        /* Table */
        .table-wrap { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; overflow-x: auto; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; }
        th { background: #0f172a; padding: 0.85rem 1rem; color: #94a3b8; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #1e293b; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #1e293b; color: #cbd5e1; vertical-align: middle; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .inv-num { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #38bdf8; text-decoration: none; }
        .clinic-pill { font-weight: 600; color: #ffffff; display: flex; align-items: center; gap: 0.4rem; }
        .clinic-code-pill { font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #38bdf8; background: #0f172a; padding: 0.1rem 0.4rem; border-radius: 0.25rem; border: 1px solid #1e293b; }
        .money { font-family: 'JetBrains Mono', monospace; font-weight: 700; }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <h1>DxClinic Fleet</h1>
            <span class="badge">Collections Ledger</span>
        </div>
        <div class="topbar-actions">
            <a href="{{ route('superadmin.dashboard') }}" class="btn-link">🏥 Fleet Dashboard</a>
            <a href="{{ route('superadmin.sales') }}" class="btn-link">📊 Sales</a>
            <a href="{{ route('superadmin.invoices') }}" class="btn-link">🧾 Invoices</a>
            <a href="{{ route('superadmin.collections') }}" class="btn-link active">💰 Collections</a>
            <a href="{{ route('superadmin.expenses') }}" class="btn-link">📉 Expenses</a>
            <a href="{{ route('superadmin.license-requests') }}" class="btn-link">📋 License Requests</a>
            <a href="{{ route('superadmin.license-history') }}" class="btn-link">📜 License History</a>
            <a href="{{ route('superadmin.licenses.issue') }}" class="btn-issue">+ Issue License</a>
            <form action="{{ route('superadmin.logout') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn-logout">Sign Out</button>
            </form>
        </div>
    </div>

    <!-- Collection KPI Cards (Specification 11) -->
    <div class="kpi-grid">
        <div class="kpi-card total">
            <div class="kpi-label"><span>Total Collection</span><span>💰</span></div>
            <div class="kpi-val">₹{{ number_format($totalCollection, 2) }}</div>
        </div>
        <div class="kpi-card cash">
            <div class="kpi-label"><span>Cash</span><span>💵</span></div>
            <div class="kpi-val">₹{{ number_format($cashTotal, 2) }}</div>
        </div>
        <div class="kpi-card upi">
            <div class="kpi-label"><span>UPI</span><span>📱</span></div>
            <div class="kpi-val">₹{{ number_format($upiTotal, 2) }}</div>
        </div>
        <div class="kpi-card bank">
            <div class="kpi-label"><span>Bank Transfer</span><span>🏛</span></div>
            <div class="kpi-val">₹{{ number_format($bankTransferTotal, 2) }}</div>
        </div>
        <div class="kpi-card cheque">
            <div class="kpi-label"><span>Cheque</span><span>📑</span></div>
            <div class="kpi-val">₹{{ number_format($chequeTotal, 2) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><span>Other</span><span>⚡</span></div>
            <div class="kpi-val">₹{{ number_format($otherTotal, 2) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><span>Refunds</span><span>🔄</span></div>
            <div class="kpi-val">₹{{ number_format($refunds, 2) }}</div>
        </div>
        <div class="kpi-card net">
            <div class="kpi-label"><span>Net Collection</span><span>✨</span></div>
            <div class="kpi-val" style="color: #34d399;">₹{{ number_format($netCollection, 2) }}</div>
        </div>
    </div>

    <!-- Filter Bar & Granularity View Switcher (Specification 11) -->
    <div class="filter-bar">
        <div class="view-tabs">
            <span style="font-size: 0.8rem; color: #64748b; align-self: center; margin-right: 0.5rem; font-weight: 600;">View Mode:</span>
            <a href="{{ route('superadmin.collections', array_merge(request()->query(), ['view_type' => 'transactions'])) }}" class="view-tab {{ $viewType === 'transactions' ? 'active' : '' }}">Individual Transactions</a>
            <a href="{{ route('superadmin.collections', array_merge(request()->query(), ['view_type' => 'daily'])) }}" class="view-tab {{ $viewType === 'daily' ? 'active' : '' }}">Daily Totals</a>
            <a href="{{ route('superadmin.collections', array_merge(request()->query(), ['view_type' => 'monthly'])) }}" class="view-tab {{ $viewType === 'monthly' ? 'active' : '' }}">Monthly Totals</a>
            <a href="{{ route('superadmin.collections', array_merge(request()->query(), ['view_type' => 'fy'])) }}" class="view-tab {{ $viewType === 'fy' ? 'active' : '' }}">Financial Year Totals</a>
        </div>

        <form method="GET" action="{{ route('superadmin.collections') }}" class="filter-form">
            <input type="hidden" name="view_type" value="{{ $viewType }}">

            <div class="form-group">
                <label class="form-label">Clinic</label>
                <select name="clinic_id" class="form-select">
                    <option value="">All Clinics</option>
                    @foreach($clinics as $c)
                        <option value="{{ $c->id }}" {{ request('clinic_id') == $c->id ? 'selected' : '' }}>{{ $c->clinic_code }} - {{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <select name="payment_method" class="form-select">
                    <option value="">All Methods</option>
                    <option value="CASH" {{ request('payment_method') === 'CASH' ? 'selected' : '' }}>Cash</option>
                    <option value="UPI" {{ request('payment_method') === 'UPI' ? 'selected' : '' }}>UPI</option>
                    <option value="BANK_TRANSFER" {{ request('payment_method') === 'BANK_TRANSFER' ? 'selected' : '' }}>Bank Transfer</option>
                    <option value="CHEQUE" {{ request('payment_method') === 'CHEQUE' ? 'selected' : '' }}>Cheque</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date', $startDate ? $startDate->format('Y-m-d') : '') }}" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date', $endDate ? $endDate->format('Y-m-d') : '') }}" class="form-input">
            </div>

            <button type="submit" class="btn-apply">Filter Collections</button>
            <a href="{{ route('superadmin.collections', ['view_type' => $viewType]) }}" class="btn-reset">Reset</a>
        </form>
    </div>

    <!-- Data Presentation based on View Type -->
    @if($viewType === 'transactions')
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Receipt Date</th>
                        <th>Invoice #</th>
                        <th>Clinic</th>
                        <th>Sale Type</th>
                        <th>Payment Method</th>
                        <th>Reference #</th>
                        <th style="text-align: right;">Amount Collected</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                        <tr>
                            <td>{{ $t->payment_date ? $t->payment_date->format('d M Y') : '-' }}</td>
                            <td>
                                <a href="{{ route('superadmin.invoices.show', $t->id) }}" class="inv-num">
                                    {{ $t->invoice_number }}
                                </a>
                            </td>
                            <td>
                                <div class="clinic-pill">
                                    <span class="clinic-code-pill">{{ $t->clinic->clinic_code }}</span>
                                    <span>{{ $t->clinic->name }}</span>
                                </div>
                            </td>
                            <td>{{ $t->formattedSaleType() }}</td>
                            <td>
                                <span style="font-weight: 700; color: #ffffff;">{{ $t->payment_method ?: 'N/A' }}</span>
                            </td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #94a3b8;">
                                {{ $t->payment_reference ?: '-' }}
                            </td>
                            <td style="text-align: right;" class="money" style="color: #34d399;">
                                ₹{{ number_format($t->net_amount, 2) }}
                            </td>
                            <td>
                                <a href="{{ route('superadmin.invoices.show', $t->id) }}" style="color: #38bdf8; text-decoration: none; font-weight: 600; font-size: 0.8rem;">
                                    View Invoice
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: #64748b; padding: 2.5rem;">
                                No collection transactions recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>
            {{ $transactions->links() }}
        </div>
    @else
        <!-- Aggregated Totals View (Daily / Monthly / FY) -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Transactions</th>
                        <th style="text-align: right;">Cash</th>
                        <th style="text-align: right;">UPI</th>
                        <th style="text-align: right;">Bank Transfer</th>
                        <th style="text-align: right;">Cheque</th>
                        <th style="text-align: right;">Other</th>
                        <th style="text-align: right;">Total Realized</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedData as $row)
                        <tr>
                            <td>
                                <strong style="color: #ffffff; font-family: 'JetBrains Mono', monospace;">{{ $row['label'] }}</strong>
                            </td>
                            <td>{{ $row['count'] }} payment(s)</td>
                            <td style="text-align: right;" class="money">₹{{ number_format($row['cash'], 2) }}</td>
                            <td style="text-align: right;" class="money">₹{{ number_format($row['upi'], 2) }}</td>
                            <td style="text-align: right;" class="money">₹{{ number_format($row['bank'], 2) }}</td>
                            <td style="text-align: right;" class="money">₹{{ number_format($row['cheque'], 2) }}</td>
                            <td style="text-align: right;" class="money">₹{{ number_format($row['other'], 2) }}</td>
                            <td style="text-align: right; color: #34d399; font-weight: 800;" class="money">
                                ₹{{ number_format($row['total'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: #64748b; padding: 2.5rem;">
                                No collection records found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
