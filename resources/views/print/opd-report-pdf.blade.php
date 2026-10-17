<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'OPD Consultation Report' }} - DxClinic</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 10mm 15mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #1e293b;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .clinic-name {
            font-size: 16pt;
            font-weight: 900;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-title {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }
        .meta-table {
            width: 100%;
            margin-top: 6px;
            font-size: 8pt;
            color: #475569;
        }
        .meta-table td {
            padding: 2px 4px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8pt;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            text-align: left;
            padding: 6px 4px;
            border-bottom: 1.5px solid #cbd5e1;
            text-transform: uppercase;
            font-size: 7pt;
        }
        .data-table td {
            padding: 5px 4px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .font-bold { font-weight: bold; }
        .total-row td {
            background-color: #ccfbf1;
            font-weight: 900;
            font-size: 10pt;
            color: #0f766e;
            border-top: 2px solid #0f766e;
            border-bottom: 2px solid #0f766e;
            padding: 8px 4px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 10mm;
            border-top: 1px solid #e2e8f0;
            font-size: 7.5pt;
            color: #64748b;
            padding-top: 4px;
        }
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: top;">
                    <div class="clinic-name">{{ $clinic->name ?? 'DxClinic Varanasi' }}</div>
                    <div class="report-title">{{ $title ?? 'OPD Consultation Report' }}</div>
                </td>
                <td style="text-align: right; vertical-align: top; font-size: 8pt; color: #64748b;">
                    <div>Date Range: <strong>{{ $report['start_date'] }} to {{ $report['end_date'] }}</strong></div>
                    <div>Generated: {{ now()->format('d M Y, h:i A') }}</div>
                    <div>Doctor Scope: <strong>{{ $doctorScope ?? 'All Doctors' }}</strong></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Data Table (Section 124 Format) -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 8%;">Patient ID</th>
                <th style="width: 14%;">Patient Name</th>
                <th class="text-right" style="width: 4%;">Age</th>
                <th style="width: 14%;">Address</th>
                <th style="width: 9%;">City</th>
                <th style="width: 11%;">Mobile</th>
                <th class="text-center" style="width: 5%;">Token</th>
                <th style="width: 6%;">Session</th>
                <th style="width: 6%;">Source</th>
                <th style="width: 9%;">Consultation Type</th>
                <th class="text-right" style="width: 6%;">Paid Fee</th>
                <th style="width: 6%;">Mode</th>
                <th style="width: 8%;">Doctor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $r)
                <tr>
                    <td class="font-mono font-bold">{{ $r['patient_id'] }}</td>
                    <td class="font-bold">{{ $r['patient_name'] }}</td>
                    <td class="text-right">{{ $r['age'] }}</td>
                    <td>{{ $r['address'] }}</td>
                    <td>{{ $r['city'] }}</td>
                    <td class="font-mono">{{ $r['mobile'] }}</td>
                    <td class="text-center font-bold">{{ $r['token'] }}</td>
                    <td>{{ $r['session'] }}</td>
                    <td>{{ $r['booking_source'] }}</td>
                    <td>{{ $r['consultation_type'] }}</td>
                    <td class="text-right font-bold">₹{{ number_format($r['paid_fee'], 2) }}</td>
                    <td>{{ $r['payment_mode'] }}</td>
                    <td>{{ $r['doctor'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center" style="padding: 20px; color: #94a3b8;">
                        No OPD consultations found for this criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="10" class="text-right">TOTAL COLLECTION:</td>
                <td class="text-right">₹{{ number_format($report['total_collection'], 2) }}</td>
                <td colspan="2" style="font-size: 7.5pt; font-weight: normal; color: #334155;">
                    (Settled Payments: ₹{{ number_format($report['total_collection'], 2) }})
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>DxClinic Clinical Management System &bull; Revision 25 &bull; Developer: Kundan Kumar &bull; Copyright &copy; 2026</td>
                <td style="text-align: right;">Page <span class="page-number"></span></td>
            </tr>
        </table>
    </div>
</body>
</html>
