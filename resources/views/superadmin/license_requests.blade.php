<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Requests — Superadmin — DxClinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing:border-box;margin:0;padding:0;font-family:'Inter',-apple-system,sans-serif; }
        body { background:#0b0f19;color:#f1f5f9;min-height:100vh;padding:1.5rem; }
        .container { max-width:1200px;margin:0 auto; }
        .header { display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem; }
        .header h1 { font-size:1.5rem;font-weight:800;letter-spacing:-0.02em; }
        .header-actions { display:flex;gap:0.75rem;align-items:center; }
        .btn { padding:0.5rem 1rem;border-radius:0.5rem;font-weight:600;font-size:0.85rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;border:none;transition:all 0.2s; }
        .btn-primary { background:#2563eb;color:#fff; }
        .btn-primary:hover { background:#1d4ed8; }
        .btn-ghost { background:transparent;color:#94a3b8;border:1px solid #334155; }
        .btn-ghost:hover { background:#1e293b;color:#fff; }

        /* Metrics */
        .metrics { display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.75rem;margin-bottom:1.5rem; }
        .metric { background:#131d2e;border:1px solid #1e293b;border-radius:0.75rem;padding:1rem;text-align:center; }
        .metric .val { font-size:1.5rem;font-weight:800;font-family:'JetBrains Mono',monospace; }
        .metric .label { font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-top:0.25rem; }
        .metric.pending .val { color:#f59e0b; }
        .metric.fulfilled .val { color:#10b981; }
        .metric.rejected .val { color:#ef4444; }

        /* Filters */
        .filters { display:flex;gap:0.75rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center; }
        .filters input,.filters select { background:#111b2e;border:1px solid #1e293b;color:#f1f5f9;padding:0.5rem 0.75rem;border-radius:0.5rem;font-size:0.85rem; }
        .filters input:focus,.filters select:focus { outline:none;border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,0.15); }

        /* Table */
        .table-wrap { overflow-x:auto;border-radius:0.75rem;border:1px solid #1e293b; }
        table { width:100%;border-collapse:collapse;font-size:0.85rem; }
        thead th { background:#0d1525;padding:0.75rem;text-align:left;font-weight:700;color:#94a3b8;text-transform:uppercase;font-size:0.75rem;letter-spacing:0.05em;border-bottom:1px solid #1e293b; }
        tbody td { padding:0.75rem;border-bottom:1px solid #111b2e;vertical-align:middle; }
        tbody tr:hover { background:rgba(59,130,246,0.05); }
        .badge { display:inline-block;padding:0.2rem 0.55rem;border-radius:9999px;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.03em; }
        .badge-info { background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.3); }
        .badge-warning { background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3); }
        .badge-success { background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3); }
        .badge-danger { background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3); }
        .badge-secondary { background:rgba(100,116,139,0.15);color:#94a3b8;border:1px solid rgba(100,116,139,0.3); }
        .badge-initial { background:rgba(59,130,246,0.15);color:#60a5fa; }
        .badge-renewal { background:rgba(139,92,246,0.15);color:#a78bfa; }
        .mono { font-family:'JetBrains Mono',monospace;font-weight:600; }
        .text-muted { color:#64748b; }
        .link { color:#3b82f6;text-decoration:none;font-weight:600; }
        .link:hover { text-decoration:underline; }

        /* Pagination */
        .pagination { display:flex;justify-content:center;gap:0.5rem;margin-top:1.5rem; }
        .pagination a,.pagination span { padding:0.4rem 0.75rem;border-radius:0.375rem;font-size:0.85rem;text-decoration:none; }
        .pagination a { background:#131d2e;color:#94a3b8;border:1px solid #1e293b; }
        .pagination a:hover { background:#1e293b;color:#fff; }
        .pagination .active { background:#2563eb;color:#fff;border:1px solid #2563eb; }

        /* Alert */
        .alert-success { background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:#34d399;padding:0.75rem 1rem;border-radius:0.5rem;margin-bottom:1rem;font-size:0.85rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 License Requests</h1>
            <div class="header-actions">
                <a href="{{ route('superadmin.dashboard') }}" class="btn btn-ghost">← Fleet Dashboard</a>
                <a href="{{ route('superadmin.license-history') }}" class="btn btn-ghost" id="linkNavLicenseHistory">📜 License & Request History</a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert-success">✓ {{ session('success') }}</div>
        @endif

        {{-- Metrics --}}
        <div class="metrics">
            <div class="metric">
                <div class="val">{{ $metrics['total'] }}</div>
                <div class="label">Total Requests</div>
            </div>
            <div class="metric pending">
                <div class="val">{{ $metrics['pending'] }}</div>
                <div class="label">Pending</div>
            </div>
            <div class="metric fulfilled">
                <div class="val">{{ $metrics['fulfilled'] }}</div>
                <div class="label">Fulfilled</div>
            </div>
            <div class="metric rejected">
                <div class="val">{{ $metrics['rejected'] }}</div>
                <div class="label">Rejected</div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" class="filters">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by name, mobile, email, request #...">
            <select name="status" onchange="this.form.submit()">
                <option value="ALL" {{ $statusFilter === 'ALL' ? 'selected' : '' }}>All Statuses</option>
                <option value="PENDING" {{ $statusFilter === 'PENDING' ? 'selected' : '' }}>Pending</option>
                <option value="SUBMITTED" {{ $statusFilter === 'SUBMITTED' ? 'selected' : '' }}>Submitted</option>
                <option value="UNDER_REVIEW" {{ $statusFilter === 'UNDER_REVIEW' ? 'selected' : '' }}>Under Review</option>
                <option value="FULFILLED" {{ $statusFilter === 'FULFILLED' ? 'selected' : '' }}>Fulfilled</option>
                <option value="REJECTED" {{ $statusFilter === 'REJECTED' ? 'selected' : '' }}>Rejected</option>
            </select>
            <select name="type" onchange="this.form.submit()">
                <option value="ALL" {{ $typeFilter === 'ALL' ? 'selected' : '' }}>All Types</option>
                <option value="INITIAL" {{ $typeFilter === 'INITIAL' ? 'selected' : '' }}>Initial</option>
                <option value="RENEWAL" {{ $typeFilter === 'RENEWAL' ? 'selected' : '' }}>Renewal</option>
                <option value="DOCTOR_SEAT_ADDON" {{ $typeFilter === 'DOCTOR_SEAT_ADDON' ? 'selected' : '' }}>Doctor Seat Add-on</option>
            </select>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        {{-- Requests Table --}}
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Type</th>
                        <th>Clinic</th>
                        <th>Requestor</th>
                        <th>Current Seats</th>
                        <th>Requested Seats</th>
                        <th>New Capacity</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $req)
                        @php
                            $clinicCapacity = $req->clinic?->totalDoctorCapacity() ?? ($req->included_doctor_count ?? 1);
                            $requestedQty = $req->additional_doctor_count ?: $req->requested_doctor_count ?: 1;
                            $newCapacity = $req->isDoctorSeatAddon() ? ($clinicCapacity + $requestedQty) : ($req->requested_doctor_count ?: $clinicCapacity);
                        @endphp
                        <tr>
                            <td><span class="mono">{{ $req->request_number }}</span></td>
                            <td>
                                @if ($req->isInitial())
                                    <span class="badge badge-initial">Initial</span>
                                @elseif ($req->isDoctorSeatAddon())
                                    <span class="badge" style="background:rgba(234,179,8,0.15);color:#facc15;border:1px solid rgba(234,179,8,0.3)">Doctor Seat Add-on</span>
                                @else
                                    <span class="badge badge-renewal">Renewal</span>
                                    @if ($req->clinic_code)
                                        <span class="mono text-muted" style="font-size:0.75rem;display:block;margin-top:2px;">{{ $req->clinic_code }}</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <strong>{{ $req->clinic_name }}</strong>
                                @if ($req->clinic_code)
                                    <span class="mono text-muted" style="font-size:0.75rem;display:block;">{{ $req->clinic_code }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-white font-medium">{{ $req->owner_name }}</span><br>
                                <span class="text-muted" style="font-size:0.8rem;">{{ $req->mobile }}</span>
                            </td>
                            <td class="mono font-bold text-center">
                                {{ $req->clinic ? $req->clinic->totalDoctorCapacity() : ($req->included_doctor_count ?? 1) }}
                            </td>
                            <td class="mono font-bold text-center" style="color:#60a5fa;">
                                +{{ $requestedQty }}
                            </td>
                            <td class="mono font-bold text-center" style="color:#34d399;">
                                {{ $newCapacity }}
                            </td>
                            <td class="mono">₹{{ number_format($req->net_amount, 0) }}</td>
                            <td>
                                @php
                                    $badgeClass = match(strtoupper($req->status)) {
                                        'PENDING' => 'badge-warning',
                                        'SUBMITTED' => 'badge-info',
                                        'UNDER_REVIEW' => 'badge-warning',
                                        'PAYMENT_PENDING' => 'badge-warning',
                                        'FULFILLED' => 'badge-success',
                                        'REJECTED' => 'badge-danger',
                                        'CANCELLED' => 'badge-secondary',
                                        default => 'badge-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $req->status }}</span>
                            </td>
                            <td class="text-muted" style="font-size:0.8rem;">{{ $req->created_at->format('d-m-Y H:i') }}</td>
                            <td>
                                <div style="display:flex;gap:0.5rem;align-items:center;">
                                    <a href="{{ route('superadmin.license-requests.show', $req) }}" class="link">View →</a>
                                    <a href="{{ route('superadmin.license-history.show', $req) }}" class="text-muted link" style="font-size:0.75rem;" title="View Immutable Audit History">Audit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" style="text-align:center;padding:2rem;color:#64748b;">No license requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($requests->hasPages())
            <div class="pagination">
                {{ $requests->links('pagination::simple-default') }}
            </div>
        @endif
    </div>
</body>
</html>
