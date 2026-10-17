<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Sales & Commercial Dashboard — DxClinic</title>
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

        /* Accounting Note Banner */
        .accounting-banner { background: rgba(15, 23, 42, 0.8); border: 1px solid #1e293b; border-left: 4px solid #38bdf8; border-radius: 0.5rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: #94a3b8; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: center; }
        .accounting-formula { font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #cbd5e1; background: #0f172a; padding: 0.4rem 0.8rem; border-radius: 0.35rem; border: 1px solid #1e293b; }

        /* Filter Bar */
        .filter-bar { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; }
        .filter-presets { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem; padding-bottom: 0.85rem; border-bottom: 1px solid #1e293b; }
        .preset-btn { background: #0f172a; border: 1px solid #27354a; color: #94a3b8; padding: 0.35rem 0.75rem; border-radius: 0.35rem; font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: all 0.2s; }
        .preset-btn:hover { background: #1e293b; color: #ffffff; }
        .preset-btn.active { background: #2563eb; color: #ffffff; border-color: #3b82f6; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: 0.3rem; }
        .form-label { font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .form-select, .form-input { background: #0b0f19; border: 1px solid #27354a; border-radius: 0.4rem; padding: 0.5rem 0.75rem; color: #f8fafc; font-size: 0.85rem; min-width: 150px; }
        .btn-apply { background: #2563eb; color: #ffffff; border: none; border-radius: 0.4rem; padding: 0.55rem 1.1rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; }
        .btn-reset { background: #1e293b; color: #94a3b8; border: 1px solid #334155; border-radius: 0.4rem; padding: 0.55rem 0.9rem; font-weight: 600; font-size: 0.85rem; text-decoration: none; }

        /* KPI Cards Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi-card { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; position: relative; overflow: hidden; }
        .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #334155; }
        .kpi-card.sales::before { background: #38bdf8; }
        .kpi-card.net::before { background: #3b82f6; }
        .kpi-card.collected::before { background: #10b981; }
        .kpi-card.outstanding::before { background: #f59e0b; }
        .kpi-card.expenses::before { background: #ef4444; }
        .kpi-card.profit::before { background: #a855f7; }
        .kpi-label { font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; justify-content: space-between; }
        .kpi-val { font-size: 1.65rem; font-weight: 800; color: #ffffff; margin-top: 0.4rem; font-family: 'JetBrains Mono', monospace; }
        .kpi-sub { font-size: 0.75rem; color: #64748b; margin-top: 0.35rem; }

        /* Breakdown Grids */
        .breakdown-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        @media (max-width: 900px) { .breakdown-row { grid-template-columns: 1fr; } }
        .panel { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; }
        .panel-header { font-size: 0.95rem; font-weight: 700; color: #ffffff; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #1e293b; padding-bottom: 0.75rem; }
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0; border-bottom: 1px solid rgba(30, 41, 59, 0.6); font-size: 0.85rem; }
        .list-item:last-child { border-bottom: none; }
        .list-item-title { color: #cbd5e1; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; }
        .list-item-val { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #f8fafc; }

        /* Table */
        .table-wrap { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; overflow-x: auto; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; }
        th { background: #0f172a; padding: 0.85rem 1rem; color: #94a3b8; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #1e293b; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #1e293b; color: #cbd5e1; vertical-align: middle; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .inv-num { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #38bdf8; text-decoration: none; }
        .inv-num:hover { text-decoration: underline; }
        .clinic-pill { font-weight: 600; color: #ffffff; display: flex; align-items: center; gap: 0.4rem; }
        .clinic-code-pill { font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #38bdf8; background: #0f172a; padding: 0.1rem 0.4rem; border-radius: 0.25rem; border: 1px solid #1e293b; }
        .money { font-family: 'JetBrains Mono', monospace; font-weight: 700; }

        .badge-status { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .badge-PAID { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-ISSUED { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-CANCELLED { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        .btn-act { padding: 0.3rem 0.6rem; border-radius: 0.35rem; font-size: 0.75rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; }
        .btn-act-view { background: #1e293b; color: #38bdf8; border: 1px solid #334155; }
        .btn-act-view:hover { background: #334155; }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <h1>DxClinic Fleet</h1>
            <span class="badge">Commercial Accounting</span>
        </div>
        <div class="topbar-actions">
            <a href="{{ route('superadmin.dashboard') }}" class="btn-link">🏥 Fleet Dashboard</a>
            <a href="{{ route('superadmin.sales') }}" class="btn-link active">📊 Sales</a>
            <a href="{{ route('superadmin.invoices') }}" class="btn-link">🧾 Invoices</a>
            <a href="{{ route('superadmin.collections') }}" class="btn-link">💰 Collections</a>
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

    <!-- Accounting Formula Banner (Revision 42 Specification 15 & 17) -->
    <div class="accounting-banner">
        <div>
            <strong style="color: #ffffff;">Platform Financial Accounting Architecture</strong>
            <p style="margin-top: 0.2rem;">Strict platform vs clinic ledger isolation • Authoritative commercial sources: licenses, renewals, seat add-ons, platform expenses.</p>
        </div>
        <div class="accounting-formula">
            Gross Sales - Discounts = Net Sales | Net Sales - Refunds = Net Revenue | Net Revenue - Expenses = Operating Profit
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-presets">
            <span style="font-size: 0.8rem; color: #64748b; align-self: center; margin-right: 0.5rem; font-weight: 600;">Time Preset:</span>
            <a href="{{ route('superadmin.sales', array_merge(request()->except(['range', 'from_date', 'to_date', 'page']), ['range' => 'all'])) }}" class="preset-btn {{ $range === 'all' ? 'active' : '' }}">All Time</a>
            <a href="{{ route('superadmin.sales', array_merge(request()->except(['range', 'from_date', 'to_date', 'page']), ['range' => 'today'])) }}" class="preset-btn {{ $range === 'today' ? 'active' : '' }}">Today</a>
            <a href="{{ route('superadmin.sales', array_merge(request()->except(['range', 'from_date', 'to_date', 'page']), ['range' => 'yesterday'])) }}" class="preset-btn {{ $range === 'yesterday' ? 'active' : '' }}">Yesterday</a>
            <a href="{{ route('superadmin.sales', array_merge(request()->except(['range', 'from_date', 'to_date', 'page']), ['range' => 'this_week'])) }}" class="preset-btn {{ $range === 'this_week' ? 'active' : '' }}">This Week</a>
            <a href="{{ route('superadmin.sales', array_merge(request()->except(['range', 'from_date', 'to_date', 'page']), ['range' => 'this_month'])) }}" class="preset-btn {{ $range === 'this_month' ? 'active' : '' }}">This Month</a>
            <a href="{{ route('superadmin.sales', array_merge(request()->except(['range', 'from_date', 'to_date', 'page']), ['range' => 'quarter'])) }}" class="preset-btn {{ $range === 'quarter' ? 'active' : '' }}">Quarter</a>
            <a href="{{ route('superadmin.sales', array_merge(request()->except(['range', 'from_date', 'to_date', 'page']), ['range' => 'half_year'])) }}" class="preset-btn {{ $range === 'half_year' ? 'active' : '' }}">Half-Year</a>
            <a href="{{ route('superadmin.sales', array_merge(request()->except(['range', 'from_date', 'to_date', 'page']), ['range' => 'financial_year'])) }}" class="preset-btn {{ $range === 'financial_year' ? 'active' : '' }}">Financial Year</a>
        </div>

        <form method="GET" action="{{ route('superadmin.sales') }}" class="filter-form">
            <input type="hidden" name="range" value="{{ $range === 'custom' || request('from_date') ? 'custom' : $range }}">

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
                <label class="form-label">Sale Type</label>
                <select name="sale_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="INITIAL_LICENSE" {{ request('sale_type') === 'INITIAL_LICENSE' ? 'selected' : '' }}>Initial License</option>
                    <option value="LICENSE_RENEWAL" {{ request('sale_type') === 'LICENSE_RENEWAL' ? 'selected' : '' }}>License Renewal</option>
                    <option value="DOCTOR_ADDON" {{ request('sale_type') === 'DOCTOR_ADDON' ? 'selected' : '' }}>Doctor Seat Add-on</option>
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
                    <option value="OTHER" {{ request('payment_method') === 'OTHER' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="PAID" {{ request('status') === 'PAID' ? 'selected' : '' }}>Paid</option>
                    <option value="ISSUED" {{ request('status') === 'ISSUED' ? 'selected' : '' }}>Issued / Pending</option>
                    <option value="CANCELLED" {{ request('status') === 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
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

            <button type="submit" class="btn-apply">Filter</button>
            <a href="{{ route('superadmin.sales') }}" class="btn-reset">Reset</a>
        </form>
    </div>

    <!-- 7 Financial KPI Cards (Specification 8 & 15) -->
    <div class="kpi-grid">
        <!-- 1. Total Sales (Gross) -->
        <div class="kpi-card sales">
            <div class="kpi-label">
                <span>Total Sales (Gross)</span>
                <span>🏷</span>
            </div>
            <div class="kpi-val">₹{{ number_format($grossSales, 2) }}</div>
            <div class="kpi-sub">Before discounts applied</div>
        </div>

        <!-- 2. Net Sales -->
        <div class="kpi-card net">
            <div class="kpi-label">
                <span>Net Sales</span>
                <span>📈</span>
            </div>
            <div class="kpi-val">₹{{ number_format($netSales, 2) }}</div>
            <div class="kpi-sub">Discounts: ₹{{ number_format($discounts, 2) }}</div>
        </div>

        <!-- 3. Collected -->
        <div class="kpi-card collected">
            <div class="kpi-label">
                <span>Collected</span>
                <span>💳</span>
            </div>
            <div class="kpi-val">₹{{ number_format($collected, 2) }}</div>
            <div class="kpi-sub">Realized cash / bank receipts</div>
        </div>

        <!-- 4. Outstanding -->
        <div class="kpi-card outstanding">
            <div class="kpi-label">
                <span>Outstanding</span>
                <span>⏳</span>
            </div>
            <div class="kpi-val">₹{{ number_format($outstanding, 2) }}</div>
            <div class="kpi-sub">Issued pending payment</div>
        </div>

        <!-- 5. Refunds -->
        <div class="kpi-card">
            <div class="kpi-label">
                <span>Refunds</span>
                <span>🔄</span>
            </div>
            <div class="kpi-val">₹{{ number_format($refunds, 2) }}</div>
            <div class="kpi-sub">Controlled credit refunds</div>
        </div>

        <!-- 6. Platform Expenses -->
        <div class="kpi-card expenses">
            <div class="kpi-label">
                <span>Platform Expenses</span>
                <span>🏢</span>
            </div>
            <div class="kpi-val">₹{{ number_format($platformExpenses, 2) }}</div>
            <div class="kpi-sub">Infrastructure & operations</div>
        </div>

        <!-- 7. Operating Profit -->
        <div class="kpi-card profit">
            <div class="kpi-label">
                <span>Operating Profit</span>
                <span>✨</span>
            </div>
            <div class="kpi-val" style="color: {{ $operatingProfit >= 0 ? '#34d399' : '#f87171' }};">
                ₹{{ number_format($operatingProfit, 2) }}
            </div>
            <div class="kpi-sub">Net Revenue - Platform Expenses</div>
        </div>
    </div>

    <!-- Breakdown Grids -->
    <div class="breakdown-row">
        <!-- Sales by Sale Type -->
        <div class="panel">
            <div class="panel-header">
                <span>Sales Revenue by Stream</span>
                <span style="font-size: 0.8rem; color: #94a3b8; font-weight: normal;">Net Sales</span>
            </div>
            <div class="list-item">
                <div class="list-item-title">
                    <span style="color: #38bdf8;">●</span> Initial Licenses
                </div>
                <div class="list-item-val">₹{{ number_format($salesByType['INITIAL_LICENSE'], 2) }}</div>
            </div>
            <div class="list-item">
                <div class="list-item-title">
                    <span style="color: #34d399;">●</span> License Renewals
                </div>
                <div class="list-item-val">₹{{ number_format($salesByType['LICENSE_RENEWAL'], 2) }}</div>
            </div>
            <div class="list-item">
                <div class="list-item-title">
                    <span style="color: #fbbf24;">●</span> Doctor Seat Add-ons
                </div>
                <div class="list-item-val">₹{{ number_format($salesByType['DOCTOR_ADDON'], 2) }}</div>
            </div>
        </div>

        <!-- Collections by Payment Method -->
        <div class="panel">
            <div class="panel-header">
                <span>Collections by Payment Method</span>
                <span style="font-size: 0.8rem; color: #94a3b8; font-weight: normal;">Cash Basis</span>
            </div>
            <div class="list-item">
                <div class="list-item-title"><span>💵</span> Cash</div>
                <div class="list-item-val">₹{{ number_format($collectedByMethod['CASH'], 2) }}</div>
            </div>
            <div class="list-item">
                <div class="list-item-title"><span>📱</span> UPI</div>
                <div class="list-item-val">₹{{ number_format($collectedByMethod['UPI'], 2) }}</div>
            </div>
            <div class="list-item">
                <div class="list-item-title"><span>🏛</span> Bank Transfer</div>
                <div class="list-item-val">₹{{ number_format($collectedByMethod['BANK_TRANSFER'], 2) }}</div>
            </div>
            <div class="list-item">
                <div class="list-item-title"><span>📑</span> Cheque</div>
                <div class="list-item-val">₹{{ number_format($collectedByMethod['CHEQUE'], 2) }}</div>
            </div>
            <div class="list-item">
                <div class="list-item-title"><span>⚡</span> Other / Gateway</div>
                <div class="list-item-val">₹{{ number_format($collectedByMethod['OTHER'], 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Recent Commercial Invoices Preview -->
    <div class="panel">
        <div class="panel-header">
            <span>Recent Invoices</span>
            <a href="{{ route('superadmin.invoices') }}" style="color: #38bdf8; font-size: 0.85rem; text-decoration: none; font-weight: 600;">View All Invoices →</a>
        </div>
        <div class="table-wrap" style="margin-bottom: 0;">
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Clinic</th>
                        <th>Sale Type</th>
                        <th>Gross</th>
                        <th>Discount</th>
                        <th>Net Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentInvoices as $inv)
                        <tr>
                            <td>
                                <a href="{{ route('superadmin.invoices.show', $inv->id) }}" class="inv-num">
                                    {{ $inv->invoice_number }}
                                </a>
                            </td>
                            <td>{{ $inv->invoice_date ? $inv->invoice_date->format('d M Y') : '-' }}</td>
                            <td>
                                <div class="clinic-pill">
                                    <span class="clinic-code-pill">{{ $inv->clinic->clinic_code }}</span>
                                    <span>{{ $inv->clinic->name }}</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.8rem; font-weight: 600; color: #cbd5e1;">
                                    {{ $inv->formattedSaleType() }}
                                </span>
                            </td>
                            <td class="money">₹{{ number_format($inv->subtotal, 2) }}</td>
                            <td class="money" style="color: #94a3b8;">₹{{ number_format($inv->discount, 2) }}</td>
                            <td class="money" style="color: #38bdf8;">₹{{ number_format($inv->net_amount, 2) }}</td>
                            <td>
                                <span style="font-size: 0.75rem; color: #94a3b8;">{{ $inv->payment_method ?: 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="badge-status badge-{{ $inv->status }}">
                                    {{ $inv->status }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem;">
                                    <a href="{{ route('superadmin.invoices.show', $inv->id) }}" class="btn-act btn-act-view">View</a>
                                    <a href="{{ route('superadmin.invoices.print', $inv->id) }}" target="_blank" class="btn-act btn-act-view">Print</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; color: #64748b; padding: 2rem;">No commercial invoices found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
