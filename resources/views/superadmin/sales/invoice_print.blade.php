<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice — {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background: #f8fafc; color: #0f172a; padding: 2rem; font-size: 14px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 2.5rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0284c7; padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .logo-area h1 { font-size: 24px; font-weight: 800; color: #0284c7; letter-spacing: -0.5px; }
        .logo-area p { font-size: 12px; color: #64748b; margin-top: 4px; }
        .inv-meta { text-align: right; }
        .inv-meta h2 { font-size: 20px; font-weight: 700; color: #0f172a; }
        .inv-meta p { font-size: 13px; color: #64748b; margin-top: 3px; }
        
        .addresses { display: flex; justify-content: space-between; margin-bottom: 2rem; gap: 2rem; }
        .addr-col { flex: 1; }
        .addr-col h3 { font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 8px; font-weight: 700; }
        .addr-col p { font-size: 14px; color: #1e293b; line-height: 1.5; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        th { background: #f1f5f9; padding: 10px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; text-align: left; border-bottom: 1px solid #cbd5e1; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; color: #334155; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .totals { display: flex; justify-content: flex-end; margin-bottom: 2rem; }
        .totals-table { width: 320px; }
        .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; color: #475569; }
        .totals-row.grand-total { border-top: 2px solid #0f172a; padding-top: 10px; margin-top: 6px; font-size: 18px; font-weight: 800; color: #0f172a; }

        .payment-info { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 1rem; margin-bottom: 2rem; font-size: 13px; }
        .payment-info h4 { font-size: 12px; text-transform: uppercase; color: #475569; margin-bottom: 6px; font-weight: 700; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-PAID { background: #dcfce7; color: #15803d; }
        .badge-ISSUED { background: #fef3c7; color: #b45309; }
        .badge-CANCELLED { background: #fee2e2; color: #b91c1c; }

        .footer { text-align: center; border-top: 1px solid #e2e8f0; padding-top: 1.5rem; font-size: 12px; color: #94a3b8; }

        @media print {
            body { background: #ffffff; padding: 0; }
            .invoice-box { border: none; box-shadow: none; padding: 0; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="max-width: 800px; margin: 0 auto 1.5rem auto; display: flex; justify-content: space-between; align-items: center;">
        <button onclick="window.print()" style="background: #0284c7; color: #ffffff; border: none; padding: 0.65rem 1.25rem; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 14px;">
            🖨 Print Invoice
        </button>
        <button onclick="window.close()" style="background: #64748b; color: #ffffff; border: none; padding: 0.65rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">
            Close Window
        </button>
    </div>

    <div class="invoice-box">
        <!-- Header -->
        <div class="header">
            <div class="logo-area">
                <h1>DxClinic Platform</h1>
                <p>Advanced Clinical OPD & Multi-Tenant Platform Engine</p>
                <p>Support: support@dxclinic.local • Web: dxclinic.local</p>
            </div>
            <div class="inv-meta">
                <h2>COMMERCIAL INVOICE</h2>
                <p><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Date:</strong> {{ $invoice->invoice_date ? $invoice->invoice_date->format('d M Y') : '-' }}</p>
                <p><strong>Status:</strong> <span class="badge badge-{{ $invoice->status }}">{{ $invoice->status }}</span></p>
            </div>
        </div>

        <!-- Addresses -->
        <div class="addresses">
            <div class="addr-col">
                <h3>Billed To (Customer Clinic)</h3>
                <p><strong>{{ $invoice->clinic->name }}</strong></p>
                <p>Clinic Code: <strong>{{ $invoice->clinic->clinic_code }}</strong></p>
                <p>Phone: {{ $invoice->clinic->phone ?? 'N/A' }}</p>
                <p>{{ $invoice->clinic->address ?? '' }}{{ $invoice->clinic->city ? ', ' . $invoice->clinic->city : '' }}</p>
            </div>
            <div class="addr-col" style="text-align: right;">
                <h3>Subscription Details</h3>
                <p>Sale Type: <strong>{{ $invoice->formattedSaleType() }}</strong></p>
                <p>Plan: <strong>{{ $invoice->license?->plan?->name ?? 'Standard License' }}</strong></p>
                <p>License Key: <strong style="font-family: monospace;">{{ $invoice->maskedLicenseKey() }}</strong></p>
            </div>
        </div>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 50%;">Item & Description</th>
                    <th class="text-center" style="width: 15%;">Quantity</th>
                    <th class="text-right" style="width: 15%;">Unit Price (₹)</th>
                    <th class="text-right" style="width: 15%;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $idx => $item)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $item->description }}</strong>
                        </td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($item->amount, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="totals-table">
                <div class="totals-row">
                    <span>Subtotal (Gross):</span>
                    <span>₹{{ number_format($invoice->subtotal, 2) }}</span>
                </div>
                <div class="totals-row">
                    <span>Discount:</span>
                    <span>- ₹{{ number_format($invoice->discount, 2) }}</span>
                </div>
                <div class="totals-row grand-total">
                    <span>Net Amount:</span>
                    <span>₹{{ number_format($invoice->net_amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="payment-info">
            <h4>Payment Reconciliation</h4>
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong>Payment Method:</strong> {{ $invoice->payment_method ?: 'N/A' }}
                </div>
                <div>
                    <strong>Payment Reference:</strong> {{ $invoice->payment_reference ?: 'Direct Fulfillment' }}
                </div>
                <div>
                    <strong>Payment Date:</strong> {{ $invoice->payment_date ? $invoice->payment_date->format('d M Y') : 'Pending' }}
                </div>
            </div>
        </div>

        @if($invoice->notes)
            <div style="font-size: 12px; color: #64748b; margin-bottom: 2rem; font-style: italic;">
                Notes: {{ $invoice->notes }}
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated commercial document. Immutable record generated by DxClinic Platform Architecture.</p>
            <p style="margin-top: 4px;">For billing queries, contact support@dxclinic.local</p>
        </div>
    </div>
</body>
</html>
