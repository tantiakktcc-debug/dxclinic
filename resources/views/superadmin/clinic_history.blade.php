<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription History — {{ $clinic->clinic_code }} — DxClinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 2rem 1.5rem; }
        .container { max-width: 1000px; margin: 0 auto; }
        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; border-bottom: 1px solid #1e293b; padding-bottom: 1.25rem; }
        .back-link { color: #38bdf8; text-decoration: none; font-size: 0.9rem; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
        .title { font-size: 1.5rem; font-weight: 800; color: #ffffff; }

        .table-wrap { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; overflow-x: auto; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; }
        th { background: #0f172a; padding: 0.85rem 1rem; color: #94a3b8; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #1e293b; white-space: nowrap; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #1e293b; color: #cbd5e1; vertical-align: middle; white-space: nowrap; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .val-mono { font-family: 'JetBrains Mono', monospace; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-ACTIVE { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .status-RENEWED { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
        .status-EXPIRED { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .status-SUSPENDED { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div>
                <a href="{{ route('superadmin.dashboard') }}" class="back-link">← Return to Superadmin Fleet</a>
                <h1 class="title" style="margin-top: 0.5rem;">Subscription History — {{ $clinic->clinic_code }}</h1>
                <p style="font-size: 0.85rem; color: #94a3b8;">{{ $clinic->name }} ({{ $clinic->city }} • {{ $clinic->phone }})</p>
            </div>
            <div>
                <a href="{{ route('superadmin.clinics.renew-form', $clinic) }}" style="background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 0.5rem; font-weight: 700; text-decoration: none; font-size: 0.9rem;">+ Renew License</a>
            </div>
        </div>

        <h2 style="font-size: 1.1rem; font-weight: 800; color: #ffffff; margin-bottom: 0.75rem;">Paid Subscription Periods</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Period ID</th>
                        <th>Status</th>
                        <th>Plan</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Doctor Seats</th>
                        <th>Net Amount</th>
                        <th>Payment Method</th>
                        <th>Issued At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($licenses as $lic)
                        <tr>
                            <td class="val-mono">#{{ $lic->id }}</td>
                            <td><span class="badge status-{{ $lic->status }}">{{ $lic->status }}</span></td>
                            <td style="font-weight: 700; color: #ffffff;">{{ $lic->plan?->name ?? 'Standard' }}</td>
                            <td class="val-mono">{{ $lic->start_date ? $lic->start_date->format('d-m-Y') : '-' }}</td>
                            <td class="val-mono" style="font-weight: 700; color: #38bdf8;">{{ $lic->end_date ? $lic->end_date->format('d-m-Y') : '-' }}</td>
                            <td>{{ (int)($lic->included_doctor_seats ?? 1) + (int)($lic->additional_doctor_seats ?? 0) }} Seats (Inc: {{ $lic->included_doctor_seats ?? 1 }}, Addon: {{ $lic->additional_doctor_seats ?? 0 }})</td>
                            <td class="val-mono" style="font-weight: 800; color: #34d399;">₹{{ number_format($lic->net_amount, 2) }}</td>
                            <td>{{ $lic->payment_method ?? 'DIRECT' }}</td>
                            <td class="val-mono" style="color: #94a3b8;">{{ $lic->created_at->format('d-m-Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align: center; padding: 2rem;">No subscription periods recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2 style="font-size: 1.1rem; font-weight: 800; color: #ffffff; margin-bottom: 0.75rem;">Renewal Transactions (Immutable)</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Renewal #</th>
                        <th>Date</th>
                        <th>Plan</th>
                        <th>Previous End</th>
                        <th>New End</th>
                        <th>Base</th>
                        <th>Doctor Seats</th>
                        <th>Net Amount</th>
                        <th>Method & Ref</th>
                        <th>Superadmin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($renewals as $ren)
                        <tr>
                            <td class="val-mono" style="font-weight: 800; color: #34d399;">#{{ $ren->renewal_number }}</td>
                            <td class="val-mono">{{ $ren->payment_date ? $ren->payment_date->format('d-m-Y') : '-' }}</td>
                            <td style="font-weight: 700; color: #ffffff;">{{ $ren->plan?->name }}</td>
                            <td class="val-mono" style="color: #94a3b8;">{{ $ren->previous_end_date ? $ren->previous_end_date->format('d-m-Y') : '-' }}</td>
                            <td class="val-mono" style="font-weight: 700; color: #38bdf8;">{{ $ren->new_end_date ? $ren->new_end_date->format('d-m-Y') : '-' }}</td>
                            <td class="val-mono">₹{{ number_format($ren->base_amount, 2) }}</td>
                            <td class="val-mono">₹{{ number_format($ren->total_doctor_seat_amount, 2) }}</td>
                            <td class="val-mono" style="font-weight: 800; color: #34d399;">₹{{ number_format($ren->net_amount, 2) }}</td>
                            <td>
                                <div>{{ $ren->payment_method }}</div>
                                <div style="font-size: 0.72rem; color: #94a3b8;" class="val-mono">{{ $ren->payment_reference ?? 'No Ref' }}</div>
                            </td>
                            <td>{{ $ren->renewedBy?->username ?? 'Superadmin' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" style="text-align: center; padding: 2rem; color: #64748b;">No renewal transactions recorded for this clinic.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
