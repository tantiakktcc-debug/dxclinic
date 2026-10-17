<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Fleet Dashboard — DxClinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 1.5rem; }
        .topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1e293b; padding-bottom: 1.25rem; margin-bottom: 1.5rem; }
        .brand { display: flex; align-items: center; gap: 0.75rem; }
        .brand h1 { font-size: 1.4rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .brand .badge { background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); color: #38bdf8; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; }
        .topbar-actions { display: flex; align-items: center; gap: 1rem; }
        .btn-issue { background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 0.5rem; font-weight: 700; text-decoration: none; font-size: 0.9rem; transition: opacity 0.2s; }
        .btn-issue:hover { opacity: 0.9; }
        .btn-logout { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 0.65rem 1rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
        .btn-logout:hover { background: #334155; color: #ffffff; }

        /* Metrics */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .metric-card { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; }
        .metric-label { font-size: 0.8rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .metric-val { font-size: 1.75rem; font-weight: 800; color: #ffffff; margin-top: 0.35rem; font-family: 'JetBrains Mono', monospace; }

        /* Search & Filter */
        .filter-bar { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1rem 1.25rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
        .search-input { background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 0.6rem 0.9rem; color: #f8fafc; font-size: 0.9rem; min-width: 280px; }
        .filter-select { background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 0.6rem 0.9rem; color: #f8fafc; font-size: 0.9rem; }
        .btn-filter { background: #2563eb; color: #ffffff; border: none; border-radius: 0.5rem; padding: 0.6rem 1.2rem; font-weight: 600; cursor: pointer; font-size: 0.85rem; }

        /* Table */
        .table-wrap { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem; }
        th { background: #0f172a; padding: 0.85rem 1rem; color: #94a3b8; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #1e293b; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #1e293b; color: #cbd5e1; vertical-align: middle; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .clinic-id { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #38bdf8; font-size: 0.95rem; }
        .clinic-name { font-weight: 600; color: #ffffff; }
        .clinic-loc { font-size: 0.8rem; color: #64748b; }

        /* Badges */
        .badge-status { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-ACTIVE { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-TRIAL { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); }
        .status-EXPIRED { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-SUSPENDED { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-REVOKED { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid #475569; }

        .badge-act { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 0.35rem; font-size: 0.75rem; font-weight: 700; }
        .act-ACTIVATED { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .act-PENDING { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }

        .key-box { display: inline-flex; align-items: center; gap: 0.5rem; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: #94a3b8; }
        .btn-reveal { background: #1e293b; border: 1px solid #334155; color: #38bdf8; padding: 0.2rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; cursor: pointer; font-weight: 600; }
        .btn-reveal:hover { background: #334155; }

        .actions-dropdown { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; }
        .btn-act { padding: 0.3rem 0.6rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid transparent; text-decoration: none; }
        .btn-act-renew { background: rgba(16, 185, 129, 0.15); color: #34d399; border-color: rgba(16, 185, 129, 0.3); }
        .btn-act-suspend { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border-color: rgba(245, 158, 11, 0.3); }
        .btn-act-reactivate { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border-color: rgba(56, 189, 248, 0.3); }
        .btn-act-revoke { background: rgba(239, 68, 68, 0.15); color: #f87171; border-color: rgba(239, 68, 68, 0.3); }
        .btn-act-hist { background: #1e293b; color: #cbd5e1; border-color: #334155; }

        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.9rem; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); align-items: center; justify-content: center; }
        .modal-content { background: #131d2e; border: 1px solid #27354a; border-radius: 1rem; width: 90%; max-width: 500px; padding: 2rem; position: relative; }
        .modal-title { font-size: 1.25rem; font-weight: 800; color: #ffffff; margin-bottom: 1rem; }
        .modal-close { position: absolute; right: 1.5rem; top: 1.5rem; background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">
            <h1>DxClinic Fleet</h1>
            <span class="badge">Superadmin Control</span>
        </div>
        <div class="topbar-actions">
            <button type="button" onclick="window.DxShortcuts ? window.DxShortcuts.openModal() : null" class="btn-logout" style="display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer;" title="Keyboard Shortcuts (F1 or ?)">
                <span>⌨ Shortcuts</span>
                <kbd style="font-size: 0.7rem; background: #0f172a; padding: 0.1rem 0.3rem; border-radius: 0.2rem; border: 1px solid #334155; color: #38bdf8;">F1</kbd>
            </button>
            <a href="{{ route('superadmin.sales') }}" class="btn-logout" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; border-color: rgba(16, 185, 129, 0.4); color: #34d399;">
                <span>📊 Sales</span>
            </a>
            <a href="{{ route('superadmin.invoices') }}" class="btn-logout" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                <span>🧾 Invoices</span>
            </a>
            <a href="{{ route('superadmin.collections') }}" class="btn-logout" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                <span>💰 Collections</span>
            </a>
            <a href="{{ route('superadmin.expenses') }}" class="btn-logout" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                <span>📉 Expenses</span>
            </a>
            <a href="{{ route('superadmin.license-requests') }}" class="btn-logout" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; border-color: rgba(59, 130, 246, 0.5); color: #60a5fa;">
                <span>📋 License Requests</span>
            </a>
            <a href="{{ route('superadmin.license-history') }}" class="btn-logout" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                <span>📜 License History</span>
            </a>
            <a href="{{ route('superadmin.licenses.issue') }}" class="btn-issue">+ Issue New License</a>
            <form action="{{ route('superadmin.logout') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn-logout">Sign Out</button>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- Demo Tenant Management Card (Revision 37) -->
    <div style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9)); border: 1px solid rgba(56, 189, 248, 0.2); border-radius: 0.75rem; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 44px; height: 44px; border-radius: 0.5rem; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                🏥
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <span style="font-weight: 800; font-size: 1.1rem; color: #ffffff;">Demo Clinic Tenant</span>
                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #38bdf8; background: #0f172a; padding: 0.15rem 0.5rem; border-radius: 0.3rem; border: 1px solid #1e293b;">democlinic</span>
                    @if ($demoClinic && $demoClinic->isDemoPaused())
                        <span style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;">PAUSED</span>
                    @else
                        <span style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;">ACTIVE</span>
                    @endif
                </div>
                <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.25rem;">
                    Isolated demonstration sandbox • Hard limit: 2 Doctor seats • Doctors: {{ $demoClinic ? $demoClinic->activeDoctorCount() : 2 }} / 2 (demodoctor01, demodoctor02) • Public PWA: <a href="{{ url('/book/' . ($demoClinic->public_slug ?? 'democlinic')) }}" target="_blank" style="color: #38bdf8; text-decoration: underline;">/book/{{ $demoClinic->public_slug ?? 'democlinic' }}</a>
                </div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
            @if ($demoClinic && $demoClinic->isDemoPaused())
                <form action="{{ route('superadmin.demo.resume') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn-act" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="return confirm('Resume Demo Clinic? Staff and public booking will become accessible.')">
                        ▶ Resume Demo
                    </button>
                </form>
            @else
                <form action="{{ route('superadmin.demo.pause') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn-act" style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="return confirm('Pause Demo Clinic? All logins and public booking will be blocked with a controlled maintenance notice.')">
                        ⏸ Pause Demo
                    </button>
                </form>
            @endif

            <button type="button" class="btn-act" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="openResetDemoModal()">
                🔄 Reset Demo Data
            </button>
        </div>
    </div>

    <!-- Metrics -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-label">Total Clinics</div>
            <div class="metric-val">{{ $metrics['total_clinics'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Active Licenses</div>
            <div class="metric-val" style="color: #34d399;">{{ $metrics['active_licenses'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Pending Activation</div>
            <div class="metric-val" style="color: #fbbf24;">{{ $metrics['pending_activations'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Expired Licenses</div>
            <div class="metric-val" style="color: #f87171;">{{ $metrics['expired_licenses'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Suspended Licenses</div>
            <div class="metric-val" style="color: #f59e0b;">{{ $metrics['suspended_licenses'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Platform Net Billed</div>
            <div class="metric-val" style="font-size: 1.5rem;">₹{{ number_format($metrics['total_revenue'], 0) }}</div>
        </div>
    </div>

    <!-- Search & Filter -->
    <form action="{{ route('superadmin.dashboard') }}" method="GET" class="filter-bar">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search by Clinic ID, Name, City, Phone, Key Prefix..." class="search-input">
        <select name="status" class="filter-select">
            <option value="ALL" {{ $statusFilter === 'ALL' ? 'selected' : '' }}>All Statuses</option>
            <option value="ACTIVE" {{ $statusFilter === 'ACTIVE' ? 'selected' : '' }}>Active</option>
            <option value="TRIAL" {{ $statusFilter === 'TRIAL' ? 'selected' : '' }}>Trial</option>
            <option value="PENDING_ACTIVATION" {{ $statusFilter === 'PENDING_ACTIVATION' ? 'selected' : '' }}>Pending Activation</option>
            <option value="ACTIVATED" {{ $statusFilter === 'ACTIVATED' ? 'selected' : '' }}>Activated</option>
            <option value="EXPIRED" {{ $statusFilter === 'EXPIRED' ? 'selected' : '' }}>Expired</option>
            <option value="SUSPENDED" {{ $statusFilter === 'SUSPENDED' ? 'selected' : '' }}>Suspended</option>
            <option value="REVOKED" {{ $statusFilter === 'REVOKED' ? 'selected' : '' }}>Revoked</option>
        </select>
        <button type="submit" class="btn-filter">Filter Fleet</button>
        @if ($q || $statusFilter !== 'ALL')
            <a href="{{ route('superadmin.dashboard') }}" style="color: #94a3b8; font-size: 0.85rem; text-decoration: none;">Clear</a>
        @endif
    </form>

    <!-- Clinic Fleet Table -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Clinic ID</th>
                    <th>Facility & Location</th>
                    <th>License Status</th>
                    <th>License Key (Masked)</th>
                    <th>Plan & Validity</th>
                    <th>Doctor Capacity</th>
                    <th>Activation</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clinics as $clinic)
                    @php
                        $license = $clinic->latestLicense;
                        $status = $license ? $license->status : 'NONE';
                        $adminUser = $clinic->users->firstWhere('role', 'ADMIN');
                    @endphp
                    <tr>
                        <td>
                            <span class="clinic-id">{{ $clinic->clinic_code }}</span>
                        </td>
                        <td>
                            <div class="clinic-name">{{ $clinic->name }}</div>
                            <div class="clinic-loc">{{ $clinic->city }} • {{ $clinic->phone ?? 'No phone' }}</div>
                            @if ($adminUser)
                                <div style="font-size: 0.75rem; color: #38bdf8; margin-top: 0.2rem;">
                                    Owner: <strong>{{ $adminUser->name }}</strong> ({{ $adminUser->login_id }})
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge-status status-{{ $status }}">{{ $status }}</span>
                        </td>
                        <td>
                            @if ($license)
                                <div class="key-box">
                                    <span>{{ $license->masked_key }}</span>
                                    <button type="button" class="btn-reveal" onclick="revealKey({{ $license->id }})">Reveal</button>
                                </div>
                            @else
                                <span style="color: #64748b;">No license</span>
                            @endif
                        </td>
                        <td>
                            @if ($license && $license->plan)
                                <div style="font-weight: 700; color: #ffffff;">{{ $license->plan->name }}</div>
                                <div style="font-size: 0.75rem; color: #94a3b8; font-family: 'JetBrains Mono', monospace;">
                                    Valid: {{ $license->end_date ? $license->end_date->format('d-m-Y') : '-' }}
                                </div>
                                <div style="font-size: 0.75rem; font-weight: 700; margin-top: 0.2rem; color: {{ $license->remainingDays() > 30 ? '#34d399' : ($license->remainingDays() > 0 ? '#fbbf24' : '#f87171') }}; font-family: 'JetBrains Mono', monospace;">
                                    {{ $license->remainingDaysFormatted() }}
                                </div>
                            @else
                                <span style="color: #64748b;">-</span>
                            @endif
                        </td>
                        <td>
                            @if ($license)
                                <div style="font-weight: 700; color: #34d399; font-size: 0.95rem;">
                                    {{ $clinic->activeDoctorCount() }} / {{ $clinic->totalDoctorCapacity() }} Seats
                                </div>
                                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;">
                                    <span>Inc: {{ $license->included_doctor_seats ?? 1 }}</span> • 
                                    <span>Addon: {{ $license->additional_doctor_seats ?? 0 }}</span>
                                </div>
                                <div style="font-size: 0.72rem; color: {{ $clinic->availableDoctorSeats() > 0 ? '#38bdf8' : '#f87171' }}; font-weight: 600;">
                                    Avail: {{ $clinic->availableDoctorSeats() }}
                                </div>
                            @else
                                <span style="color: #64748b;">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge-act act-{{ $clinic->activation_status === 'ACTIVATED' ? 'ACTIVATED' : 'PENDING' }}">
                                {{ $clinic->activation_status }}
                            </span>
                            @if (!$clinic->isActivated())
                                <form action="{{ route('superadmin.clinics.mark-activated', $clinic) }}" method="POST" style="margin-top: 0.25rem;">
                                    @csrf
                                    <button type="submit" class="btn-reveal" onclick="return confirm('Mark this clinic as activated directly?')">Mark Active</button>
                                </form>
                            @endif
                        </td>
                        <td>
                            <div class="actions-dropdown">
                                @if ($license)
                                    @if ($status !== 'REVOKED')
                                        <a href="{{ route('superadmin.clinics.renew-form', $clinic) }}" class="btn-act btn-act-renew">Renew License</a>
                                    @else
                                        <span class="btn-act" style="background: rgba(100, 116, 139, 0.2); color: #94a3b8; cursor: not-allowed;">Revoked</span>
                                    @endif
                                    @if ($clinic->isDemo())
                                        <span class="btn-act" style="background: rgba(100, 116, 139, 0.2); color: #94a3b8; cursor: not-allowed;" title="Demo capacity is strictly 2 doctors">Demo (Cap 2)</span>
                                    @else
                                        <button type="button" class="btn-act" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8; border-color: rgba(14, 165, 233, 0.3);" onclick="openAddSeatsModal({{ $clinic->id }}, '{{ $clinic->clinic_code }}', '{{ addslashes($clinic->name) }}', '{{ addslashes($license?->plan?->name ?? 'Standard') }}', '{{ $license?->end_date ? $license->end_date->format('d-M-Y') : 'N/A' }}', {{ $license?->included_doctor_seats ?? 1 }}, {{ $clinic->activeDoctorSeatAddons()->count() }}, {{ $clinic->totalDoctorCapacity() }})">+ Doctor Seat</button>
                                    @endif
                                    @if ($status === 'ACTIVE' || $status === 'TRIAL')
                                        <form action="{{ route('superadmin.clinics.suspend', $clinic) }}" method="POST" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn-act btn-act-suspend" onclick="return confirm('Suspend license for clinic {{ $clinic->clinic_code }}?')">Suspend</button>
                                        </form>
                                    @elseif ($status === 'SUSPENDED')
                                        <form action="{{ route('superadmin.clinics.reactivate', $clinic) }}" method="POST" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn-act btn-act-reactivate" onclick="return confirm('Reactivate license for clinic {{ $clinic->clinic_code }}?')">Reactivate</button>
                                        </form>
                                    @endif
                                    @if ($status !== 'REVOKED')
                                        <form action="{{ route('superadmin.clinics.revoke', $clinic) }}" method="POST" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn-act btn-act-revoke" onclick="return confirm('Permanently REVOKE license for clinic {{ $clinic->clinic_code }}?')">Revoke</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('superadmin.clinics.history', $clinic) }}" class="btn-act btn-act-hist">History</a>
                                    <a href="{{ route('superadmin.clinics.sales', $clinic) }}" class="btn-act" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border-color: rgba(16, 185, 129, 0.3);">Sales</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: #64748b;">
                            No clinics match your search criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $clinics->links() }}
    </div>

    <!-- Reveal Key Modal -->
    <div id="revealModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('revealModal')">&times;</button>
            <div class="modal-title">Decrypted License Key</div>
            <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.25rem;">
                Authorized Developer Access: This decrypted key is fetched securely from encrypted storage.
            </p>
            <div style="background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; text-align: center;">
                <div id="revealedClinic" style="font-size: 0.85rem; color: #38bdf8; font-weight: 700; margin-bottom: 0.5rem;"></div>
                <div id="revealedKey" style="font-family: 'JetBrains Mono', monospace; font-size: 1.2rem; font-weight: 800; color: #ffffff; letter-spacing: 0.05em;"></div>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn-issue" style="width: 100%; text-align: center;" onclick="copyRevealedKey()">Copy Key</button>
                <button type="button" class="btn-logout" style="width: 100%; text-align: center;" onclick="closeModal('revealModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Renew Modal -->
    <div id="renewModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('renewModal')">&times;</button>
            <div class="modal-title">Renew Clinic Subscription</div>
            <div id="renewClinicName" style="color: #38bdf8; font-weight: 700; font-size: 0.95rem; margin-bottom: 1rem;"></div>
            <form id="renewForm" method="POST">
                @csrf
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Duration (Months)</label>
                    <select name="duration_months" class="filter-select" style="width: 100%;" required>
                        <option value="1">1 Month</option>
                        <option value="3">3 Months</option>
                        <option value="6">6 Months</option>
                        <option value="12" selected>12 Months (1 Year)</option>
                        <option value="36">36 Months (3 Years)</option>
                        <option value="60">60 Months (5 Years)</option>
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Renewal Amount (₹)</label>
                    <input type="number" step="0.01" name="amount" value="14999.00" class="search-input" style="width: 100%;" required>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Audit Notes</label>
                    <input type="text" name="notes" placeholder="e.g. Annual renewal payment received" class="search-input" style="width: 100%;">
                </div>
                <button type="submit" class="btn-issue" style="width: 100%; border: none; cursor: pointer;">Confirm Renewal →</button>
            </form>
        </div>
    </div>

    <!-- Add Doctor Seats Modal (Revision 37) -->
    <div id="addSeatsModal" class="modal">
        <div class="modal-content" style="max-width: 540px;">
            <button class="modal-close" onclick="closeModal('addSeatsModal')">&times;</button>
            <div class="modal-title">Mid-Term Additional Doctor Purchase</div>
            <p style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 1rem;">
                Adds a yearly doctor seat entitlement during the active license period. The permanent License Key remains unchanged.
            </p>

            <div style="background: #0b0f19; border: 1px solid #1e293b; border-radius: 0.5rem; padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                    <span style="color: #94a3b8;">Clinic:</span>
                    <span id="addSeatsClinicName" style="color: #38bdf8; font-weight: 700;"></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                    <span style="color: #94a3b8;">Current Plan:</span>
                    <span id="addSeatsPlan" style="color: #ffffff; font-weight: 600;"></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                    <span style="color: #94a3b8;">License Valid Until:</span>
                    <span id="addSeatsValidUntil" style="color: #ffffff; font-family: 'JetBrains Mono', monospace;"></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                    <span style="color: #94a3b8;">Base Included Seats:</span>
                    <span id="addSeatsBaseSeats" style="color: #ffffff;"></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                    <span style="color: #94a3b8;">Active Paid Add-On Seats:</span>
                    <span id="addSeatsActiveAddons" style="color: #38bdf8;"></span>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px solid #1e293b; padding-top: 0.35rem; margin-top: 0.35rem;">
                    <span style="color: #94a3b8; font-weight: 600;">Effective Doctor Capacity:</span>
                    <span id="addSeatsEffectiveCap" style="color: #34d399; font-weight: 700;"></span>
                </div>
            </div>

            <form id="addSeatsForm" method="POST">
                @csrf
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Number of Additional Doctors *</label>
                        <input type="number" id="seatsCountInput" name="seats_count" value="1" min="1" max="20" class="search-input" style="width: 100%;" required oninput="updateAddonPrice()">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Yearly Price (₹2,999/yr/seat)</label>
                        <div id="seatsPriceDisplay" style="background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 0.6rem 0.9rem; color: #34d399; font-weight: 700; font-family: 'JetBrains Mono', monospace; font-size: 0.95rem;">
                            1 × ₹2,999 = ₹2,999
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Payment Method *</label>
                        <select name="payment_method" class="filter-select" style="width: 100%;" required>
                            <option value="UPI" selected>UPI</option>
                            <option value="BANK_TRANSFER">Bank Transfer (NEFT/IMPS)</option>
                            <option value="CASH">Cash</option>
                            <option value="CHEQUE">Cheque</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Payment Reference / UTR</label>
                        <input type="text" name="payment_reference" placeholder="e.g. UPI/1234567890" class="search-input" style="width: 100%;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Payment Date</label>
                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" class="search-input" style="width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Audit Notes</label>
                        <input type="text" name="notes" placeholder="e.g. Added 2nd doctor Dr. S. Rao" class="search-input" style="width: 100%;">
                    </div>
                </div>

                <button type="submit" class="btn-issue" style="width: 100%; border: none; cursor: pointer; padding: 0.75rem; font-size: 0.95rem;">Confirm Payment & Add Doctor Seat</button>
            </form>
        </div>
    </div>

    <!-- Reset Demo Modal (Revision 37) -->
    <div id="resetDemoModal" class="modal">
        <div class="modal-content" style="max-width: 520px; border-color: rgba(239, 68, 68, 0.4);">
            <button class="modal-close" onclick="closeModal('resetDemoModal')">&times;</button>
            <div class="modal-title" style="color: #f87171;">⚠️ Confirm Demo Data Reset</div>
            <p style="font-size: 0.9rem; color: #cbd5e1; line-height: 1.6; margin-bottom: 1.25rem;">
                This will permanently delete all demo clinical, appointment, billing, diagnostic, prescription, and accounting data and recreate the standard Demo Clinic dataset.
            </p>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: #fca5a5;">
                🛡️ <strong>Safety Guarantee:</strong> This action strictly targets <code>democlinic</code> and does NOT affect any real clinic.
            </div>
            <form action="{{ route('superadmin.demo.reset') }}" method="POST">
                @csrf
                <div style="display: flex; gap: 0.75rem;">
                    <button type="button" class="btn-logout" style="width: 50%; text-align: center;" onclick="closeModal('resetDemoModal')">Cancel</button>
                    <button type="submit" class="btn-issue" style="width: 50%; text-align: center; background: #dc2626; border-color: #ef4444;">Confirm & Reset Demo Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <button class="modal-close" onclick="closeModal('historyModal')">&times;</button>
            <div class="modal-title">License Audit History</div>
            <div id="histClinicName" style="color: #38bdf8; font-weight: 700; font-size: 0.95rem; margin-bottom: 1rem;"></div>
            <div id="histList" style="max-height: 350px; overflow-y: auto;">
                Loading events...
            </div>
        </div>
    </div>

    <script>
        function revealKey(licenseId) {
            fetch(`/superadmin/licenses/${licenseId}/reveal`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('revealedClinic').innerText = `${data.clinic_code} — ${data.clinic_name}`;
                    document.getElementById('revealedKey').innerText = data.license_key;
                    document.getElementById('revealModal').style.display = 'flex';
                })
                .catch(() => alert('Could not reveal key.'));
        }

        function copyRevealedKey() {
            var key = document.getElementById('revealedKey').innerText;
            navigator.clipboard.writeText(key).then(() => alert('License key copied to clipboard!'));
        }

        function openRenewModal(clinicId, code, name) {
            document.getElementById('renewClinicName').innerText = `${code} — ${name}`;
            document.getElementById('renewForm').action = `/superadmin/clinics/${clinicId}/renew`;
            document.getElementById('renewModal').style.display = 'flex';
        }

        function openAddSeatsModal(clinicId, code, name, plan, validUntil, baseSeats, activeAddons, effectiveCap) {
            document.getElementById('addSeatsClinicName').innerText = `${code} — ${name}`;
            document.getElementById('addSeatsPlan').innerText = plan;
            document.getElementById('addSeatsValidUntil').innerText = validUntil;
            document.getElementById('addSeatsBaseSeats').innerText = `${baseSeats} seat(s)`;
            document.getElementById('addSeatsActiveAddons').innerText = `${activeAddons} seat(s)`;
            document.getElementById('addSeatsEffectiveCap').innerText = `${effectiveCap} seat(s)`;
            document.getElementById('seatsCountInput').value = 1;
            updateAddonPrice();
            document.getElementById('addSeatsForm').action = `/superadmin/clinics/${clinicId}/add-doctor-seats`;
            document.getElementById('addSeatsModal').style.display = 'flex';
        }

        function updateAddonPrice() {
            var count = parseInt(document.getElementById('seatsCountInput').value) || 1;
            var price = count * 2999;
            document.getElementById('seatsPriceDisplay').innerText = `${count} × ₹2,999 = ₹${price.toLocaleString('en-IN')}`;
        }

        function openResetDemoModal() {
            document.getElementById('resetDemoModal').style.display = 'flex';
        }

        function openHistoryModal(clinicId) {
            document.getElementById('historyModal').style.display = 'flex';
            document.getElementById('histList').innerHTML = '<div style="color: #94a3b8;">Loading audit history...</div>';
            fetch(`/superadmin/clinics/${clinicId}/events`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('histClinicName').innerText = `${data.clinic.clinic_code} — ${data.clinic.name}`;
                    if (!data.events || data.events.length === 0) {
                        document.getElementById('histList').innerHTML = '<div style="color: #64748b;">No audit events recorded.</div>';
                        return;
                    }
                    var html = '<table style="width: 100%; font-size: 0.8rem;">';
                    html += '<tr style="border-bottom: 1px solid #27354a;"><th style="padding: 0.4rem;">Date</th><th style="padding: 0.4rem;">Event</th><th style="padding: 0.4rem;">Status</th><th style="padding: 0.4rem;">Notes</th></tr>';
                    data.events.forEach(ev => {
                        html += `<tr style="border-bottom: 1px solid #1a2538;">
                            <td style="padding: 0.4rem; color: #94a3b8;">${new Date(ev.created_at).toLocaleString()}</td>
                            <td style="padding: 0.4rem; font-weight: 700; color: #38bdf8;">${ev.event_type}</td>
                            <td style="padding: 0.4rem;">${ev.new_status}</td>
                            <td style="padding: 0.4rem; color: #cbd5e1;">${ev.notes || '-'}</td>
                        </tr>`;
                    });
                    html += '</table>';
                    document.getElementById('histList').innerHTML = html;
                });
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        };
    </script>
    <script src="/js/dx-shortcuts.js"></script>
</body>
</html>
