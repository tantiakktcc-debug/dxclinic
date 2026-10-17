<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commercial History — {{ $clinic->name }} ({{ $clinic->clinic_code }})</title>
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

        .clinic-hero { background: #131d2e; border: 1px solid #1e293b; border-radius: 1rem; padding: 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .clinic-hero h2 { font-size: 1.5rem; font-weight: 800; color: #ffffff; }
        .clinic-hero p { font-size: 0.85rem; color: #94a3b8; margin-top: 0.25rem; }

        /* KPI Cards Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi-card { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; }
        .kpi-label { font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-val { font-size: 1.5rem; font-weight: 800; color: #ffffff; margin-top: 0.4rem; font-family: 'JetBrains Mono', monospace; }

        /* Stable License Identity Box (Specification 13) */
        .license-identity-box { background: #0f172a; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .key-text { font-family: 'JetBrains Mono', monospace; font-size: 1.1rem; font-weight: 800; color: #38bdf8; }

        /* Timeline */
        .timeline-container { background: #131d2e; border: 1px solid #1e293b; border-radius: 1rem; padding: 1.5rem; }
        .timeline-title { font-size: 1.1rem; font-weight: 800; color: #ffffff; margin-bottom: 1.5rem; border-bottom: 1px solid #1e293b; padding-bottom: 0.75rem; }
        .timeline { position: relative; padding-left: 2rem; }
        .timeline::before { content: ''; position: absolute; left: 7px; top: 0; bottom: 0; width: 2px; background: #1e293b; }
        .timeline-item { position: relative; margin-bottom: 1.5rem; }
        .timeline-dot { position: absolute; left: -2rem; top: 3px; width: 16px; height: 16px; border-radius: 50%; background: #0f172a; border: 3px solid #38bdf8; }
        .timeline-dot.invoice { border-color: #38bdf8; }
        .timeline-dot.renewal { border-color: #34d399; }
        .timeline-dot.addon { border-color: #fbbf24; }
        .timeline-dot.initial { border-color: #a855f7; }
        .timeline-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 0.5rem; padding: 1rem 1.25rem; }
        .timeline-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.5rem; }
        .timeline-item-title { font-size: 0.95rem; font-weight: 700; color: #ffffff; }
        .timeline-date { font-size: 0.75rem; color: #64748b; font-family: 'JetBrains Mono', monospace; }
        .timeline-details { font-size: 0.825rem; color: #94a3b8; }
        .timeline-amount { font-family: 'JetBrains Mono', monospace; font-weight: 800; color: #34d399; font-size: 0.95rem; }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <h1>DxClinic Fleet</h1>
            <span class="badge">Per-Clinic Commercial History</span>
        </div>
        <div class="topbar-actions">
            <a href="{{ route('superadmin.sales') }}" class="btn-link">← Sales Dashboard</a>
            <a href="{{ route('superadmin.invoices') }}" class="btn-link">🧾 Invoices</a>
            <a href="{{ route('superadmin.collections') }}" class="btn-link">💰 Collections</a>
            <a href="{{ route('superadmin.dashboard') }}" class="btn-link">🏥 Fleet Dashboard</a>
        </div>
    </div>

    <!-- Clinic Hero Summary -->
    <div class="clinic-hero">
        <div>
            <h2>{{ $clinic->name }}</h2>
            <p>
                Clinic ID: <strong style="font-family: 'JetBrains Mono', monospace; color: #38bdf8;">{{ $clinic->clinic_code }}</strong> • 
                Phone: {{ $clinic->phone ?? 'N/A' }} • 
                City: {{ $clinic->city ?? '-' }}
            </p>
        </div>
        <div style="text-align: right;">
            <a href="{{ route('superadmin.clinics.history', $clinic) }}" class="btn-link" style="border-color: #3b82f6; color: #60a5fa;">
                📜 Operational Audit Trail →
            </a>
        </div>
    </div>

    <!-- Stable License Identity (Specification 13) -->
    <div class="license-identity-box">
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">
                Permanent License Identity
            </div>
            <div class="key-text" style="margin-top: 0.35rem;">
                {{ $clinic->activeLicense ? $clinic->activeLicense->masked_key : 'NXCL-••••-••••-••••-••••' }}
            </div>
            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                Stable single license identity retained across all renewals and mid-term expansions.
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 0.75rem; color: #94a3b8;">Doctor Seats Entitlement</div>
            <div style="font-size: 1.1rem; font-weight: 800; color: #ffffff; margin-top: 0.25rem;">
                {{ $clinic->totalDoctorCapacity() }} Total Doctor Capacity
            </div>
        </div>
    </div>

    <!-- Commercial Financial Totals -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Gross Sales</div>
            <div class="kpi-val">₹{{ number_format($grossSales, 2) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Discounts</div>
            <div class="kpi-val">₹{{ number_format($discounts, 2) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Net Sales</div>
            <div class="kpi-val">₹{{ number_format($netSales, 2) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Collected</div>
            <div class="kpi-val" style="color: #34d399;">₹{{ number_format($collected, 2) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Outstanding</div>
            <div class="kpi-val" style="color: {{ $outstanding > 0 ? '#fbbf24' : '#64748b' }};">₹{{ number_format($outstanding, 2) }}</div>
        </div>
    </div>

    <!-- Chronological Commercial Timeline (Specification 12) -->
    <div class="timeline-container">
        <div class="timeline-title">Chronological Commercial & Billing History</div>
        <div class="timeline">
            @forelse($sortedTimeline as $item)
                @php
                    $dotClass = match($item['type']) {
                        'INITIAL_LICENSE' => 'initial',
                        'RENEWAL_LICENSE' => 'renewal',
                        'DOCTOR_ADDON' => 'addon',
                        'INVOICE' => 'invoice',
                        default => '',
                    };
                @endphp
                <div class="timeline-item">
                    <div class="timeline-dot {{ $dotClass }}"></div>
                    <div class="timeline-card">
                        <div class="timeline-header">
                            <div>
                                <span class="timeline-item-title">{{ $item['title'] }}</span>
                                <span style="margin-left: 0.5rem; font-size: 0.72rem; padding: 0.15rem 0.45rem; border-radius: 4px; background: rgba(255,255,255,0.05); color: #cbd5e1; font-weight: 600;">
                                    {{ $item['status'] }}
                                </span>
                            </div>
                            <div class="timeline-amount">
                                ₹{{ number_format($item['amount'], 2) }}
                            </div>
                        </div>
                        <div class="timeline-details">
                            {{ $item['details'] }}
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                            <span class="timeline-date">{{ $item['date']->format('d M Y, H:i') }}</span>
                            @if(!empty($item['link']))
                                <a href="{{ $item['link'] }}" style="color: #38bdf8; font-size: 0.8rem; text-decoration: none; font-weight: 600;">
                                    View Invoice →
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div style="color: #64748b; padding: 2rem; text-align: center;">
                    No commercial events recorded for this clinic.
                </div>
            @endforelse
        </div>
    </div>
</body>
</html>
