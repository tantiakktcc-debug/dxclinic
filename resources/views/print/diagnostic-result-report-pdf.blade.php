<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic Laboratory Report - {{ $report->report_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 15mm 12mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .clinic-name {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .clinic-sub {
            font-size: 10px;
            color: #475569;
            margin-top: 2px;
        }
        .report-badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-final {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .badge-draft {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .badge-verified {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .info-box {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 14px;
            background-color: #f8fafc;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 5px 8px;
            font-size: 10.5px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            color: #64748b;
            width: 18%;
        }
        .info-val {
            color: #0f172a;
            width: 32%;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 16px;
        }
        .results-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #1e3a8a;
        }
        .results-table td {
            padding: 6px 8px;
            font-size: 11px;
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .results-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .flag-high {
            color: #dc2626;
            font-weight: bold;
        }
        .flag-low {
            color: #2563eb;
            font-weight: bold;
        }
        .flag-abnormal {
            color: #d97706;
            font-weight: bold;
        }
        .flag-normal {
            color: #16a34a;
        }
        .val-bold {
            font-weight: bold;
        }
        .watermark {
            position: fixed;
            top: 40%;
            left: 15%;
            transform: rotate(-35deg);
            font-size: 55px;
            font-weight: 900;
            color: rgba(220, 38, 38, 0.12);
            text-transform: uppercase;
            letter-spacing: 4px;
            z-index: -1000;
        }
        .footer-table {
            width: 100%;
            margin-top: 20px;
            border-top: 1px solid #cbd5e1;
            padding-top: 12px;
        }
        .sig-block {
            text-align: center;
            font-size: 10px;
            color: #475569;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-top: 35px;
            padding-top: 4px;
            font-weight: bold;
            color: #0f172a;
        }
        .disclaimer {
            margin-top: 15px;
            padding: 6px 8px;
            background-color: #f1f5f9;
            border-radius: 4px;
            font-size: 8.5px;
            color: #64748b;
            text-align: center;
        }
        .hash-code {
            font-family: monospace;
            font-size: 8px;
            color: #94a3b8;
            word-break: break-all;
            margin-top: 4px;
        }
    </style>
</head>
<body>

    @if ($report->status === 'DRAFT')
        <div class="watermark">DRAFT — NOT FOR RELEASE</div>
    @elseif ($report->status === 'VERIFIED')
        <div class="watermark" style="color: rgba(217, 119, 6, 0.10);">VERIFIED — PENDING RELEASE</div>
    @endif

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 70%; vertical-align: top;">
                <div class="clinic-name">{{ $report->clinic->name }}</div>
                <div class="clinic-sub">
                    {{ $report->clinic->address ?? 'Clinical Diagnostic Division' }}
                    @if ($report->clinic->city) • {{ $report->clinic->city }} @endif
                    @if ($report->clinic->phone) • Ph: {{ $report->clinic->phone }} @endif
                    • Code: <strong>{{ $report->clinic->code }}</strong>
                </div>
                <div style="font-size: 12px; font-weight: bold; color: #0284c7; margin-top: 4px;">
                    DEPARTMENT OF DIAGNOSTIC LABORATORY & PATHOLOGY
                </div>
            </td>
            <td style="width: 30%; text-align: right; vertical-align: top;">
                @if ($report->status === 'FINAL')
                    <span class="report-badge badge-final">✓ FINAL RELEASED</span>
                @elseif ($report->status === 'VERIFIED')
                    <span class="report-badge badge-verified">⏳ VERIFIED</span>
                @else
                    <span class="report-badge badge-draft">⚠ DRAFT</span>
                @endif
                <div style="margin-top: 6px; font-size: 10px; color: #64748b;">
                    Report No: <strong style="color: #0f172a;">{{ $report->report_number }}</strong>
                </div>
                <div style="font-size: 9.5px; color: #64748b;">
                    Date: {{ $report->created_at->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Patient & Order Demographics Box -->
    <div class="info-box">
        <table class="info-table">
            <tr>
                <td class="info-label">Patient Name:</td>
                <td class="info-val"><strong>{{ $report->patient->name }}</strong></td>
                <td class="info-label">Order Number:</td>
                <td class="info-val"><strong>{{ $order->order_number }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">Patient ID:</td>
                <td class="info-val"><strong style="color: #1e3a8a;">{{ $report->patient->patient_id }}</strong></td>
                <td class="info-label">Order Date:</td>
                <td class="info-val">{{ $order->order_date ? $order->order_date->format('d/m/Y H:i') : '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">Age / Sex:</td>
                <td class="info-val">{{ $report->patient->age ?? '—' }} Y / {{ $report->patient->sex ?? '—' }}</td>
                <td class="info-label">Sample ID:</td>
                <td class="info-val">
                    @if ($specimen)
                        <strong style="color: #0f766e;">{{ $specimen->specimen_identifier }}</strong>
                    @else
                        <em>Not Collected</em>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="info-label">Ref. Doctor:</td>
                <td class="info-val">Dr. {{ $report->doctor?->name ?? 'Attending Clinician' }}</td>
                <td class="info-label">Sample Type:</td>
                <td class="info-val">{{ $specimen?->specimen_type ?? 'Blood' }} ({{ $specimen?->collected_at ? $specimen->collected_at->format('d/m H:i') : 'Pending' }})</td>
            </tr>
            @if ($report->patient->formatted_address)
            <tr>
                <td class="info-label">Address:</td>
                <td class="info-val" colspan="3">{{ $report->patient->formatted_address }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Diagnostic Investigations & Test Results -->
    <div style="font-size: 11px; font-weight: bold; color: #1e3a8a; margin-bottom: 4px; text-transform: uppercase;">
        Investigation Findings & Laboratory Results
    </div>

    <table class="results-table">
        <thead>
            <tr>
                <th style="width: 32%;">Investigation / Parameter</th>
                <th style="width: 20%; text-align: center;">Observed Value</th>
                <th style="width: 14%; text-align: center;">Unit</th>
                <th style="width: 20%;">Biological Ref Interval</th>
                <th style="width: 14%; text-align: center;">Interpretation</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($results as $res)
                <tr>
                    <td>
                        <strong>{{ $res->parameter_name }}</strong>
                        @if ($res->is_corrected)
                            <span style="font-size: 8px; color: #b45309; display: block;">[Rev. v{{ $res->version }} — Corrected]</span>
                        @endif
                    </td>
                    <td style="text-align: center;">
                        <span class="{{ $res->isAbnormal() ? 'val-bold' : '' }} {{ $res->abnormal_flag === 'HIGH' ? 'flag-high' : ($res->abnormal_flag === 'LOW' ? 'flag-low' : ($res->abnormal_flag === 'ABNORMAL' ? 'flag-abnormal' : 'flag-normal')) }}">
                            {{ $res->result_value }}
                        </span>
                    </td>
                    <td style="text-align: center; color: #64748b;">
                        {{ $res->unit ?? '—' }}
                    </td>
                    <td style="color: #475569; font-size: 10px;">
                        {{ $res->reference_range ?? '—' }}
                    </td>
                    <td style="text-align: center;">
                        @if ($res->abnormal_flag === 'HIGH')
                            <span class="flag-high">▲ HIGH</span>
                        @elseif ($res->abnormal_flag === 'LOW')
                            <span class="flag-low">▼ LOW</span>
                        @elseif ($res->abnormal_flag === 'ABNORMAL' || $res->abnormal_flag === 'CRITICAL')
                            <span class="flag-abnormal">● {{ $res->abnormal_flag }}</span>
                        @else
                            <span class="flag-normal">NORMAL</span>
                        @endif
                    </td>
                </tr>
                @if ($res->remarks)
                <tr>
                    <td colspan="5" style="background-color: #f8fafc; font-size: 9.5px; color: #475569; padding: 3px 8px;">
                        <em>Remarks: {{ $res->remarks }}</em>
                    </td>
                </tr>
                @endif
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 16px; color: #94a3b8;">
                        No laboratory results recorded for this order.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($report->report_notes)
        <div style="margin-top: 10px; padding: 8px 10px; background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; font-size: 10px;">
            <strong style="color: #1e40af;">Clinical Comments / Impression:</strong>
            <p style="margin-top: 2px; color: #1e293b;">{{ $report->report_notes }}</p>
        </div>
    @endif

    <!-- Signatures & Authorization Section -->
    <table class="footer-table">
        <tr>
            <td style="width: 25%;" class="sig-block">
                @if ($results->first()?->enteredBy)
                    <div style="font-size: 9.5px; color: #64748b;">
                        Recorded: {{ $results->first()->entered_at->format('d/m/Y H:i') }}
                    </div>
                @endif
                <div class="sig-line">
                    {{ $results->first()?->enteredBy?->name ?? 'Medical Lab Technician' }}
                </div>
                <div style="font-size: 9px; color: #64748b;">Laboratory Technician</div>
            </td>
            <td style="width: 25%;" class="sig-block">
                @if ($results->first()?->verifiedBy)
                    <div style="font-size: 9.5px; color: #64748b;">
                        Verified: {{ $results->first()->verified_at?->format('d/m/Y H:i') }}
                    </div>
                @endif
                <div class="sig-line">
                    {{ $results->first()?->verifiedBy?->name ?? 'Consultant Pathologist' }}
                </div>
                <div style="font-size: 9px; color: #64748b;">Verified by Clinician</div>
            </td>
            <td style="width: 25%;" class="sig-block">
                @if ($report->releasedBy)
                    <div style="font-size: 9.5px; color: #64748b;">
                        Released: {{ $report->released_at?->format('d/m/Y H:i') }}
                    </div>
                @endif
                <div class="sig-line">
                    {{ $report->releasedBy?->name ?? 'Medical Director' }}
                </div>
                <div style="font-size: 9px; color: #64748b;">Authorized Signatory</div>
            </td>
            <td style="width: 25%; text-align: center; vertical-align: middle;">
                @if (!empty($qrBase64))
                    <img src="{{ $qrBase64 }}" style="width: 65px; height: 65px; display: inline-block;" alt="Security QR">
                    <div style="font-size: 7.5px; color: #94a3b8; margin-top: 2px;">SCAN TO VERIFY</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Legal / Clinical Disclaimer -->
    <div class="disclaimer">
        This is an electronically generated and validated clinical diagnostic laboratory report from {{ $report->clinic->name }}.
        Investigations are performed under standardized laboratory protocols. Results should be correlated clinically by the attending physician.
        <div class="hash-code">Security Hash: {{ $report->verification_hash }}</div>
    </div>

</body>
</html>
