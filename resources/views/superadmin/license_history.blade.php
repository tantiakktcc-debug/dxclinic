<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Request & Issuance History — DxClinic Fleet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 1.5rem; }
        .topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1e293b; padding-bottom: 1.25rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .brand { display: flex; align-items: center; gap: 0.75rem; }
        .brand h1 { font-size: 1.4rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .back-link { color: #38bdf8; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }

        /* Metric Counters */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .metric-card { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.1rem 1.25rem; }
        .metric-title { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .metric-value { font-size: 1.6rem; font-weight: 800; color: #ffffff; margin-top: 0.3rem; font-family: 'JetBrains Mono', monospace; }

        /* Filter Bar */
        .filter-bar { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .search-input { background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 0.6rem 0.9rem; color: #f8fafc; font-size: 0.85rem; min-width: 280px; flex: 1; }
        .filter-select { background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 0.6rem 0.9rem; color: #f8fafc; font-size: 0.85rem; }
        .date-input { background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 0.55rem 0.75rem; color: #f8fafc; font-size: 0.85rem; }
        .btn-filter { background: #2563eb; color: #ffffff; border: none; border-radius: 0.5rem; padding: 0.6rem 1.2rem; font-weight: 700; cursor: pointer; font-size: 0.85rem; }
        .btn-filter:hover { background: #1d4ed8; }

        /* Table */
        .table-wrap { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; }
        th { background: #0f172a; padding: 0.85rem 1rem; color: #94a3b8; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #1e293b; white-space: nowrap; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #1e293b; color: #cbd5e1; vertical-align: middle; white-space: nowrap; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .clinic-id { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #38bdf8; }
        .val-mono { font-family: 'JetBrains Mono', monospace; }
        .link { color: #38bdf8; text-decoration: none; font-weight: 700; }
        .link:hover { text-decoration: underline; }

        /* Badges */
        .badge { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .badge-initial { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); }
        .badge-renewal { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }
        .badge-addon { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        .badge-status-fulfilled { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-status-pending { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-status-submitted { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-status-rejected { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-status-cancelled { background: rgba(100, 116, 139, 0.15); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }

        .btn-view { background: rgba(56, 189, 248, 0.1); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); padding: 0.35rem 0.75rem; border-radius: 0.4rem; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-view:hover { background: rgba(56, 189, 248, 0.2); }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">
            <h1 class="page-title">License Request & Issuance History</h1>
            <span style="font-size: 0.9rem; color: #94a3b8; margin-left: 0.5rem;">• DxClinic Fleet</span>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('superadmin.license-requests') }}" class="back-link" style="color: #60a5fa;">📋 Active License Requests</a>
            <a href="{{ route('superadmin.dashboard') }}" class="back-link">← Return to Fleet Dashboard</a>
        </div>
    </div>

    <!-- Metrics Counters -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-title">Total Requests & Lifecycle Records</div>
            <div class="metric-value">{{ $metrics['total'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Fulfilled & Provisioned</div>
            <div class="metric-value" style="color: #34d399;">{{ $metrics['fulfilled'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Pending / In Review</div>
            <div class="metric-value" style="color: #fbbf24;">{{ $metrics['pending'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Rejected / Cancelled</div>
            <div class="metric-value" style="color: #94a3b8;">{{ $metrics['rejected'] + $metrics['cancelled'] }}</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form action="{{ route('superadmin.license-history') }}" method="GET" class="filter-bar" id="historyFilterForm">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search by Request #, Clinic Name, ID (NX0004), Slug, Owner, Phone, Email, Key..." class="search-input" id="historySearchInput">

        <select name="status" class="filter-select" id="historyStatusFilter">
            <option value="ALL" {{ $statusFilter === 'ALL' ? 'selected' : '' }}>All Statuses</option>
            <option value="FULFILLED" {{ $statusFilter === 'FULFILLED' ? 'selected' : '' }}>Fulfilled</option>
            <option value="PENDING" {{ $statusFilter === 'PENDING' ? 'selected' : '' }}>Pending / In Review</option>
            <option value="SUBMITTED" {{ $statusFilter === 'SUBMITTED' ? 'selected' : '' }}>Submitted</option>
            <option value="UNDER_REVIEW" {{ $statusFilter === 'UNDER_REVIEW' ? 'selected' : '' }}>Under Review</option>
            <option value="REJECTED" {{ $statusFilter === 'REJECTED' ? 'selected' : '' }}>Rejected</option>
            <option value="CANCELLED" {{ $statusFilter === 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
        </select>

        <select name="type" class="filter-select" id="historyTypeFilter">
            <option value="ALL" {{ $typeFilter === 'ALL' ? 'selected' : '' }}>All Types</option>
            <option value="INITIAL" {{ $typeFilter === 'INITIAL' ? 'selected' : '' }}>Initial License</option>
            <option value="RENEWAL" {{ $typeFilter === 'RENEWAL' ? 'selected' : '' }}>License Renewal</option>
            <option value="DOCTOR_SEAT_ADDON" {{ $typeFilter === 'DOCTOR_SEAT_ADDON' ? 'selected' : '' }}>Doctor Seat Add-on</option>
        </select>

        <div style="display: flex; align-items: center; gap: 0.3rem;">
            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">From:</span>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="date-input" id="historyDateFrom">
        </div>
        <div style="display: flex; align-items: center; gap: 0.3rem;">
            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">To:</span>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="date-input" id="historyDateTo">
        </div>

        <button type="submit" class="btn-filter" id="btnFilterHistory">Filter History</button>
        @if ($q || $statusFilter !== 'ALL' || $typeFilter !== 'ALL' || $dateFrom || $dateTo)
            <a href="{{ route('superadmin.license-history') }}" style="color: #94a3b8; font-size: 0.85rem; text-decoration: none;" id="btnClearHistoryFilters">Clear</a>
        @endif
    </form>

    <!-- History Table -->
    <div class="table-wrap">
        <table id="historyTable">
            <thead>
                <tr>
                    <th>Request #</th>
                    <th>Type</th>
                    <th>Clinic</th>
                    <th>Clinic ID</th>
                    <th>Requestor</th>
                    <th>Plan & Duration</th>
                    <th>Doctors</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Completed / Fulfilled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                    @php
                        $badgeType = match (strtoupper($req->request_type)) {
                            'INITIAL' => 'badge-initial',
                            'RENEWAL' => 'badge-renewal',
                            'DOCTOR_SEAT_ADDON' => 'badge-addon',
                            default => 'badge-initial',
                        };
                        $typeLabel = match (strtoupper($req->request_type)) {
                            'INITIAL' => 'Initial License',
                            'RENEWAL' => 'Renewal',
                            'DOCTOR_SEAT_ADDON' => 'Doctor Seat Add-on',
                            default => $req->request_type,
                        };
                        $statusClass = match (strtoupper($req->status)) {
                            'FULFILLED' => 'badge-status-fulfilled',
                            'PENDING' => 'badge-status-pending',
                            'SUBMITTED' => 'badge-status-submitted',
                            'UNDER_REVIEW' => 'badge-status-pending',
                            'PAYMENT_PENDING' => 'badge-status-pending',
                            'REJECTED' => 'badge-status-rejected',
                            'CANCELLED' => 'badge-status-cancelled',
                            default => 'badge-status-cancelled',
                        };
                        $clinicCode = $req->clinic_code ?? $req->fulfilledClinic?->clinic_code ?? $req->clinic?->clinic_code ?? 'Pending';
                        $publicSlug = $req->fulfilledClinic?->public_slug ?? $req->clinic?->public_slug ?? null;
                    @endphp
                    <tr id="history_row_{{ $req->request_number }}">
                        <td>
                            <a href="{{ route('superadmin.license-history.show', $req) }}" class="link val-mono" style="font-weight: 800;">
                                #{{ $req->request_number }}
                            </a>
                        </td>
                        <td>
                            <span class="badge {{ $badgeType }}">{{ $typeLabel }}</span>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #ffffff;">{{ $req->clinic_name }}</div>
                            @if ($publicSlug)
                                <div style="font-size: 0.75rem; color: #94a3b8;">slug: {{ $publicSlug }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="clinic-id">{{ $clinicCode }}</span>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #f1f5f9;">{{ $req->owner_name }}</div>
                            <div style="font-size: 0.75rem; color: #94a3b8;" class="val-mono">{{ $req->mobile }}</div>
                        </td>
                        <td>
                            @if ($req->isDoctorSeatAddon())
                                <div style="font-weight: 700; color: #fbbf24;">Doctor Seat Expansion</div>
                                <div style="font-size: 0.75rem; color: #94a3b8;">+{{ $req->additional_doctor_count ?: $req->requested_doctor_count }} Seats</div>
                            @else
                                <div style="font-weight: 700; color: #ffffff;">{{ $req->plan_name_snapshot ?? $req->plan?->name ?? 'Standard' }}</div>
                                <div style="font-size: 0.75rem; color: #94a3b8;">
                                    {{ $req->plan_duration_months }} Months{{ $req->trial_months > 0 ? " + {$req->trial_months} Mo Trial" : '' }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($req->isDoctorSeatAddon())
                                <span class="val-mono" style="color: #fbbf24; font-weight: 700;">+{{ $req->additional_doctor_count ?: $req->requested_doctor_count }} Seats</span>
                            @else
                                <div class="val-mono" style="font-weight: 700; color: #ffffff;">{{ $req->requested_doctor_count }} Doctor(s)</div>
                                <div style="font-size: 0.72rem; color: #64748b;">
                                    ({{ $req->included_doctor_count }} inc + {{ $req->additional_doctor_count }} add)
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="val-mono" style="font-weight: 800; color: #34d399; font-size: 0.95rem;">
                                ₹{{ number_format($req->net_amount, 2) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $statusClass }}">{{ $req->status }}</span>
                        </td>
                        <td>
                            <span class="val-mono" style="font-size: 0.8rem; color: #94a3b8;">
                                {{ $req->created_at->format('d-m-Y H:i') }}
                            </span>
                        </td>
                        <td>
                            @php
                                $completedDate = $req->payment_confirmed_at 
                                    ?? $req->rejected_at 
                                    ?? $req->cancelled_at 
                                    ?? ($req->isFulfilled() ? $req->updated_at : null);
                            @endphp
                            <span class="val-mono" style="font-size: 0.8rem; color: {{ $completedDate ? '#34d399' : '#64748b' }};">
                                {{ $completedDate ? $completedDate->format('d-m-Y H:i') : '—' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('superadmin.license-history.show', $req) }}" class="btn-view" id="btn_view_history_{{ $req->request_number }}">
                                🔍 View Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 3rem; color: #64748b;" id="empty_history_message">
                            No license requests or historical records matching filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $requests->links() }}
    </div>
</body>
</html>
