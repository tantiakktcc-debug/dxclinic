<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tax Invoice — {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 20mm;
            size: a4 portrait;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.4;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .brand-title {
            font-size: 22px;
            font-weight: bold;
            color: #0284c7;
        }
        .brand-sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
        }
        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            text-align: right;
            color: #0f172a;
        }
        .invoice-meta {
            text-align: right;
            font-size: 11px;
            color: #475569;
            margin-top: 5px;
        }
        .divider {
            border-bottom: 2px solid #0284c7;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .details-table {
            margin-bottom: 25px;
        }
        .details-table td {
            vertical-align: top;
            width: 50%;
        }
        .section-label {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 5px;
        }
        .clinic-name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .clinic-code {
            font-family: 'Courier', monospace;
            font-weight: bold;
            color: #0284c7;
        }
        .items-table {
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 10px;
            text-transform: uppercase;
            padding: 8px 10px;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-table {
            width: 45%;
            margin-left: auto;
            margin-bottom: 20px;
        }
        .totals-table td {
            padding: 5px 0;
            font-size: 11px;
        }
        .totals-table .grand-total td {
            border-top: 2px solid #0f172a;
            padding-top: 8px;
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .payment-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 10px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-PAID { background-color: #dcfce7; color: #15803d; }
        .badge-ISSUED { background-color: #fef3c7; color: #b45309; }
        .badge-CANCELLED { background-color: #fee2e2; color: #b91c1c; }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="brand-title">DxClinic Platform</div>
                <div class="brand-sub">Clinical OPD & Multi-Tenant Operating Engine</div>
                <div class="brand-sub">support@dxclinic.local • dxclinic.local</div>
            </td>
            <td>
                <div class="invoice-title">COMMERCIAL INVOICE</div>
                <div class="invoice-meta">
                    <strong>Invoice #:</strong> {{ $invoice->invoice_number }}<br>
                    <strong>Date:</strong> {{ $invoice->invoice_date ? $invoice->invoice_date->format('d M Y') : '-' }}<br>
                    <strong>Status:</strong> <span class="badge badge-{{ $invoice->status }}">{{ $invoice->status }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="details-table">
        <tr>
            <td>
                <div class="section-label">Billed To (Clinic Tenant)</div>
                <div class="clinic-name">{{ $invoice->clinic->name }}</div>
                <div>Clinic Code: <span class="clinic-code">{{ $invoice->clinic->clinic_code }}</span></div>
                <div>Phone: {{ $invoice->clinic->phone ?? 'N/A' }}</div>
                <div>{{ $invoice->clinic->address ?? '' }}{{ $invoice->clinic->city ? ', ' . $invoice->clinic->city : '' }}</div>
            </td>
            <td style="text-align: right;">
                <div class="section-label">Commercial Entitlement</div>
                <div>Sale Type: <strong>{{ $invoice->formattedSaleType() }}</strong></div>
                <div>Plan: <strong>{{ $invoice->license?->plan?->name ?? 'Standard License' }}</strong></div>
                <div>License Key: <strong style="font-family: 'Courier', monospace;">{{ $invoice->maskedLicenseKey() }}</strong></div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 55%;">Item & Description</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-right" style="width: 15%;">Unit Price (₹)</th>
                <th class="text-right" style="width: 15%;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $idx => $item)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td><strong>{{ $item->description }}</strong></td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($item->amount, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Subtotal (Gross):</td>
            <td class="text-right">₹{{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>Discount:</td>
            <td class="text-right">- ₹{{ number_format($invoice->discount, 2) }}</td>
        </tr>
        <tr class="grand-total">
            <td>Net Total:</td>
            <td class="text-right">₹{{ number_format($invoice->net_amount, 2) }}</td>
        </tr>
    </table>

    <div class="payment-box">
        <strong>Payment Information:</strong>
        Method: {{ $invoice->payment_method ?: 'N/A' }} | 
        Reference: {{ $invoice->payment_reference ?: 'Direct' }} | 
        Date: {{ $invoice->payment_date ? $invoice->payment_date->format('d M Y') : 'Pending' }}
    </div>

    @if($invoice->notes)
        <div style="font-size: 10px; color: #64748b; font-style: italic; margin-bottom: 10px;">
            Notes: {{ $invoice->notes }}
        </div>
    @endif

    <div class="footer">
        Computer-generated commercial document. Authoritative billing source of DxClinic Platform Architecture.<br>
        For inquiries, please contact platform billing at support@dxclinic.local
    </div>
</body>
</html>
