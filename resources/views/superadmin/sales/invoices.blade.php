<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commercial Invoices — DxClinic Superadmin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 1.5rem; }
        .topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1e293b; padding-bottom: 1.25rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .brand { display: flex; align-items: center; gap: 0.75rem; }
        .brand h1 { font-size: 1.4rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .brand .badge { background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); color: #38bdf8; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; }
        .topbar-actions { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
        .btn-link { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 0.6rem 0.9rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
        .btn-link:hover { background: #334155; color: #ffffff; }
        .btn-link.active { border-color: #38bdf8; color: #38bdf8; background: rgba(56, 189, 248, 0.1); }
        .btn-issue { background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; padding: 0.6rem 1.1rem; border-radius: 0.5rem; font-weight: 700; text-decoration: none; font-size: 0.85rem; }
        .btn-logout { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 0.6rem 0.9rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
        .btn-logout:hover { background: #334155; color: #ffffff; }

        /* Filter Bar */
        .filter-bar { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; }
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
        .inv-num:hover { text-decoration: underline; }
        .clinic-pill { font-weight: 600; color: #ffffff; display: flex; align-items: center; gap: 0.4rem; }
        .clinic-code-pill { font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #38bdf8; background: #0f172a; padding: 0.1rem 0.4rem; border-radius: 0.25rem; border: 1px solid #1e293b; }
        .money { font-family: 'JetBrains Mono', monospace; font-weight: 700; }

        .badge-status { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .badge-PAID { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-ISSUED { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-CANCELLED { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        .btn-act { padding: 0.3rem 0.55rem; border-radius: 0.35rem; font-size: 0.75rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; }
        .btn-act-view { background: #1e293b; color: #38bdf8; border: 1px solid #334155; }
        .btn-act-print { background: #1e293b; color: #cbd5e1; border: 1px solid #334155; }
        .btn-act-pdf { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .btn-act:hover { background: #334155; }

        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <h1>DxClinic Fleet</h1>
            <span class="badge">Commercial Invoices</span>
        </div>
        <div class="topbar-actions">
            <a href="{{ route('superadmin.dashboard') }}" class="btn-link">🏥 Fleet Dashboard</a>
            <a href="{{ route('superadmin.sales') }}" class="btn-link">📊 Sales</a>
            <a href="{{ route('superadmin.invoices') }}" class="btn-link active">🧾 Invoices</a>
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

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="{{ route('superadmin.invoices') }}" class="filter-form">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice #, clinic name, or code..." class="form-input">
            </div>

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
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="PAID" {{ request('status') === 'PAID' ? 'selected' : '' }}>Paid</option>
                    <option value="ISSUED" {{ request('status') === 'ISSUED' ? 'selected' : '' }}>Issued</option>
                    <option value="CANCELLED" {{ request('status') === 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <button type="submit" class="btn-apply">Filter Invoices</button>
            <a href="{{ route('superadmin.invoices') }}" class="btn-reset">Reset</a>
        </form>
    </div>

    <!-- Invoices Table (Specification 9) -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Clinic</th>
                    <th>Sale Type</th>
                    <th>Plan / Details</th>
                    <th>Gross</th>
                    <th>Discount</th>
                    <th>Net Amount</th>
                    <th>Status</th>
                    <th>Collected</th>
                    <th>Outstanding</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                    @php
                        $isPaid = $inv->status === 'PAID';
                        $isCancelled = $inv->status === 'CANCELLED';
                        $collected = $isPaid ? (float) $inv->net_amount : 0.00;
                        $outstanding = (!$isPaid && !$isCancelled) ? (float) $inv->net_amount : 0.00;
                        $planName = $inv->license?->plan?->name ?? 'Standard';
                    @endphp
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
                            <span style="font-weight: 600; font-size: 0.8rem; color: #cbd5e1;">
                                {{ $inv->formattedSaleType() }}
                            </span>
                        </td>
                        <td>
                            <div style="color: #ffffff; font-weight: 500;">
                                @if($inv->sale_type === 'DOCTOR_ADDON')
                                    Doctor Seat Add-on
                                @else
                                    {{ $planName }}
                                @endif
                            </div>
                            <div style="font-size: 0.72rem; color: #64748b; font-family: 'JetBrains Mono', monospace;">
                                Key: {{ $inv->maskedLicenseKey() }}
                            </div>
                        </td>
                        <td class="money">₹{{ number_format($inv->subtotal, 2) }}</td>
                        <td class="money" style="color: #94a3b8;">₹{{ number_format($inv->discount, 2) }}</td>
                        <td class="money" style="color: #38bdf8; font-weight: 800;">₹{{ number_format($inv->net_amount, 2) }}</td>
                        <td>
                            <span class="badge-status badge-{{ $inv->status }}">
                                {{ $inv->status }}
                            </span>
                            @if($isPaid && $inv->payment_method)
                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 0.2rem;">
                                    {{ $inv->payment_method }}
                                </div>
                            @endif
                        </td>
                        <td class="money" style="color: #34d399;">₹{{ number_format($collected, 2) }}</td>
                        <td class="money" style="color: {{ $outstanding > 0 ? '#fbbf24' : '#64748b' }};">₹{{ number_format($outstanding, 2) }}</td>
                        <td>
                            <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                <a href="{{ route('superadmin.invoices.show', $inv->id) }}" class="btn-act btn-act-view" title="View Details">View</a>
                                <a href="{{ route('superadmin.invoices.print', $inv->id) }}" target="_blank" class="btn-act btn-act-print" title="Print Invoice">Print</a>
                                <a href="{{ route('superadmin.invoices.pdf', [$inv->id, 'download' => 1]) }}" class="btn-act btn-act-pdf" title="Download Vector PDF">PDF</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align: center; color: #64748b; padding: 2.5rem;">
                            No commercial invoices found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div>
        {{ $invoices->links() }}
    </div>
</body>
</html>
