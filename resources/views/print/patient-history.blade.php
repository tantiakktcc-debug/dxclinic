<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient History — {{ $patient->name }} ({{ $patient->patient_id }})</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #0f172a; padding: 24px; font-size: 12px; line-height: 1.5; }
        .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .clinic-name { font-size: 20px; font-weight: 800; text-transform: uppercase; }
        .clinic-meta { font-size: 10px; color: #64748b; margin-top: 4px; }
        .report-title { font-size: 16px; font-weight: 700; color: #1e3a8a; margin-top: 8px; }

        .patient-info { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
        .info-item { font-size: 11px; }
        .info-label { font-weight: 700; color: #475569; text-transform: uppercase; font-size: 10px; }
        .info-value { font-weight: 600; color: #0f172a; }

        .visit-card { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 12px; page-break-inside: avoid; }
        .visit-header { display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 6px; margin-bottom: 8px; }
        .visit-date { font-weight: 800; font-size: 13px; color: #1e3a8a; }
        .visit-token { font-weight: 700; color: #0f172a; }
        .visit-status { padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-arrived { background: #dbeafe; color: #1e40af; }
        .status-absent { background: #fef2f2; color: #991b1b; }

        .vitals-row { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin-bottom: 8px; }
        .vital-box { text-align: center; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 4px; }
        .vital-name { font-size: 9px; font-weight: 700; color: #1e40af; text-transform: uppercase; }
        .vital-val { font-size: 11px; font-weight: 800; color: #1e3a8a; }

        .billing-row { font-size: 11px; display: flex; justify-content: space-between; padding: 4px 0; }
        .billing-label { color: #475569; font-weight: 600; }
        .billing-value { font-weight: 700; color: #0f172a; }

        .footer { text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 24px; }

        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="clinic-name">{{ $clinic->name ?? 'DxClinic' }}</div>
        @if($clinic)
            <div class="clinic-meta">{{ $clinic->address }}, {{ $clinic->city }} | Phone: {{ $clinic->phone }}</div>
        @endif
        <div class="report-title">Complete Patient History Report</div>
    </div>

    <div class="patient-info">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Patient ID</div>
                <div class="info-value">{{ $patient->patient_id }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Name</div>
                <div class="info-value">{{ $patient->name }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Age / Sex</div>
                <div class="info-value">{{ $patient->age }} Y / {{ $patient->sex }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Mobile</div>
                <div class="info-value">{{ $patient->mobile }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Address</div>
                <div class="info-value">{{ $formattedAddress }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Generated At</div>
                <div class="info-value">{{ $generatedAt }}</div>
            </div>
        </div>
    </div>

    @if($visits->isEmpty())
        <p style="text-align: center; color: #94a3b8; padding: 40px; font-size: 14px;">No visit history available for this patient.</p>
    @else
        @foreach($visits as $visit)
            <div class="visit-card">
                <div class="visit-header">
                    <div>
                        <span class="visit-date">{{ $visit->visit_date?->format('d/m/Y') ?? '—' }}</span>
                        <span style="color: #64748b; margin-left: 6px; font-size: 11px;">{{ $visit->session_type }}</span>
                    </div>
                    <div>
                        <span class="visit-token">Token: #{{ $visit->appointment?->formatted_token ?? '—' }}</span>
                        <span class="visit-status {{ $visit->status === 'COMPLETED' ? 'status-completed' : ($visit->status === 'ARRIVED' ? 'status-arrived' : 'status-absent') }}">
                            {{ $visit->status }}
                        </span>
                    </div>
                </div>

                <div style="font-size: 11px; color: #475569; margin-bottom: 6px;">
                    <strong>Doctor:</strong> {{ \App\Services\DoctorNamePresenter::format($visit->doctor?->name) }}
                    @if($visit->arrived_at) | <strong>Arrived:</strong> {{ $visit->arrived_at->format('H:i') }} @endif
                    @if($visit->completed_at) | <strong>Completed:</strong> {{ $visit->completed_at->format('H:i') }} @endif
                </div>

                @if($visit->vital)
                    <div class="vitals-row">
                        <div class="vital-box">
                            <div class="vital-name">BP</div>
                            <div class="vital-val">{{ ($visit->vital->systolic_bp && $visit->vital->diastolic_bp) ? "{$visit->vital->systolic_bp}/{$visit->vital->diastolic_bp}" : '—' }}</div>
                        </div>
                        <div class="vital-box">
                            <div class="vital-name">Pulse</div>
                            <div class="vital-val">{{ $visit->vital->pulse ?? '—' }}</div>
                        </div>
                        <div class="vital-box">
                            <div class="vital-name">Weight</div>
                            <div class="vital-val">{{ $visit->vital->weight ?? '—' }} kg</div>
                        </div>
                        <div class="vital-box">
                            <div class="vital-name">Temp</div>
                            <div class="vital-val">{{ $visit->vital->temperature ?? '—' }} °F</div>
                        </div>
                        <div class="vital-box">
                            <div class="vital-name">SpO2</div>
                            <div class="vital-val">{{ $visit->vital->spo2 ?? '—' }}%</div>
                        </div>
                        <div class="vital-box">
                            <div class="vital-name">Sugar</div>
                            <div class="vital-val">{{ $visit->vital->blood_sugar ?? '—' }}</div>
                        </div>
                    </div>
                @endif

                @if($visit->consultationBill)
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; padding: 6px 10px; margin-bottom: 4px;">
                        <div class="billing-row">
                            <span class="billing-label">Consultation ({{ $visit->consultationBill->bill_number }})</span>
                            <span class="billing-value">₹ {{ number_format($visit->consultationBill->net_amount, 2) }}</span>
                        </div>
                        @if($visit->consultationBill->waiver_amount > 0)
                            <div class="billing-row">
                                <span class="billing-label">Waiver</span>
                                <span style="color: #059669; font-weight: 600;">₹ {{ number_format($visit->consultationBill->waiver_amount, 2) }}</span>
                            </div>
                        @endif
                        @if($visit->consultationBill->payment)
                            <div class="billing-row">
                                <span class="billing-label">Payment: {{ $visit->consultationBill->payment->payment_method }}</span>
                                <span class="billing-value">{{ $visit->consultationBill->payment->status }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                @foreach($visit->diagnosticBills as $db)
                    <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 4px; padding: 6px 10px; margin-bottom: 4px;">
                        <div class="billing-row">
                            <span class="billing-label">Diagnostic ({{ $db->bill_number }})</span>
                            <span class="billing-value">₹ {{ number_format($db->net_amount, 2) }}</span>
                        </div>
                        <div style="font-size: 10px; color: #7c3aed;">Tests: {{ $db->items->pluck('test_name')->implode(', ') }}</div>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif

    @if(!empty($diagnosticReports) && count($diagnosticReports) > 0)
        <div class="section-title" style="margin-top: 15px; color: #6b21a8; border-color: #6b21a8;">
            RELEASED DIAGNOSTIC LABORATORY REPORTS ({{ count($diagnosticReports) }})
        </div>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11px;">
            <thead>
                <tr style="background: #f3e8ff; color: #581c87; text-align: left;">
                    <th style="padding: 6px; border: 1px solid #d8b4fe;">Report #</th>
                    <th style="padding: 6px; border: 1px solid #d8b4fe;">Date</th>
                    <th style="padding: 6px; border: 1px solid #d8b4fe;">Investigations</th>
                    <th style="padding: 6px; border: 1px solid #d8b4fe;">Released By</th>
                    <th style="padding: 6px; border: 1px solid #d8b4fe;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($diagnosticReports as $rep)
                    <tr>
                        <td style="padding: 6px; border: 1px solid #e9d5ff; font-family: monospace; font-weight: bold; color: #581c87;">{{ $rep->report_number }}</td>
                        <td style="padding: 6px; border: 1px solid #e9d5ff;">{{ $rep->released_at ? $rep->released_at->format('d/m/Y') : '—' }}</td>
                        <td style="padding: 6px; border: 1px solid #e9d5ff;">{{ $rep->order?->items?->map(function($i){ return $i->test_name; })->implode(', ') ?? '—' }}</td>
                        <td style="padding: 6px; border: 1px solid #e9d5ff;">{{ $rep->releasedBy?->name ?? '—' }}</td>
                        <td style="padding: 6px; border: 1px solid #e9d5ff; color: #047857; font-weight: bold;">FINAL RELEASED</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        All rights reserved © 2026 Nexora Solutions. Developed by Kundan Kumar. | Generated: {{ $generatedAt }}
    </div>
</body>
</html>
