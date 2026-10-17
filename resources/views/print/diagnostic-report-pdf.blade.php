<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Diagnostic Report' }} - DxClinic</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 12mm 15mm 12mm;
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
            border-bottom: 2px solid #4338ca;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .clinic-name {
            font-size: 16pt;
            font-weight: 900;
            color: #4338ca;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-title {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8.5pt;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            text-align: left;
            padding: 6px 4px;
            border-bottom: 1.5px solid #cbd5e1;
            text-transform: uppercase;
            font-size: 7.5pt;
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
            background-color: #e0e7ff;
            font-weight: 900;
            font-size: 10.5pt;
            color: #3730a3;
            border-top: 2px solid #4338ca;
            border-bottom: 2px solid #4338ca;
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
                    <div class="report-title">{{ $title ?? 'Diagnostic Laboratory Report' }}</div>
                </td>
                <td style="text-align: right; vertical-align: top; font-size: 8pt; color: #64748b;">
                    <div>Date Range: <strong>{{ $report['start_date'] }} to {{ $report['end_date'] }}</strong></div>
                    <div>Generated: {{ now()->format('d M Y, h:i A') }}</div>
                    <div>Doctor Scope: <strong>{{ $doctorScope ?? 'All Doctors' }}</strong></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Data Table (Section 125 Format) -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 11%;">Date</th>
                <th style="width: 12%;">Patient ID</th>
                <th style="width: 20%;">Patient Name</th>
                <th style="width: 27%;">Diagnostic Test / Package</th>
                <th class="text-right" style="width: 10%;">Amount</th>
                <th style="width: 12%;">Doctor</th>
                <th style="width: 8%;">Session</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $r)
                <tr>
                    <td class="font-mono">{{ $r['date_formatted'] }}</td>
                    <td class="font-mono font-bold">{{ $r['patient_id'] }}</td>
                    <td class="font-bold">{{ $r['patient_name'] }}</td>
                    <td>{{ $r['diagnostic_item'] }}</td>
                    <td class="text-right font-bold">₹{{ number_format($r['amount'], 2) }}</td>
                    <td>{{ $r['doctor'] }}</td>
                    <td>{{ $r['session'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #94a3b8;">
                        No diagnostic records found for this criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL COLLECTION:</td>
                <td class="text-right">₹{{ number_format($report['total_collection'], 2) }}</td>
                <td colspan="2" style="font-size: 7.5pt; font-weight: normal; color: #3730a3;">
                    (Billed: ₹{{ number_format($report['total_billed'], 2) }})
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
