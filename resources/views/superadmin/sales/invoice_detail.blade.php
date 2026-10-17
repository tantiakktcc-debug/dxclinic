<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }} — DxClinic Commercial</title>
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

        .invoice-card { background: #131d2e; border: 1px solid #1e293b; border-radius: 1rem; padding: 2rem; max-width: 900px; margin: 0 auto; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); }
        .inv-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #1e293b; padding-bottom: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .inv-title { font-size: 1.75rem; font-weight: 800; color: #ffffff; font-family: 'JetBrains Mono', monospace; }
        .inv-meta { text-align: right; }
        @media (max-width: 600px) { .inv-meta { text-align: left; } }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
        .info-block { background: #0f172a; border: 1px solid #1e293b; border-radius: 0.5rem; padding: 1.25rem; }
        .info-title { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.65rem; }
        .info-line { margin-bottom: 0.35rem; font-size: 0.85rem; color: #cbd5e1; }
        .info-line strong { color: #ffffff; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
        th { background: #0f172a; padding: 0.85rem 1rem; color: #94a3b8; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #1e293b; text-align: left; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #1e293b; color: #cbd5e1; font-size: 0.85rem; }
        .money { font-family: 'JetBrains Mono', monospace; font-weight: 700; }

        .summary-box { background: #0f172a; border: 1px solid #1e293b; border-radius: 0.5rem; padding: 1.25rem; margin-left: auto; width: 100%; max-width: 380px; margin-bottom: 1.5rem; }
        .summary-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.85rem; color: #cbd5e1; }
        .summary-row.total { border-top: 1px solid #1e293b; margin-top: 0.5rem; padding-top: 0.75rem; font-size: 1.15rem; font-weight: 800; color: #38bdf8; }

        .badge-status { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
        .badge-PAID { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
        .badge-ISSUED { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .badge-CANCELLED { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }

        .actions-bar { display: flex; gap: 0.75rem; justify-content: flex-end; align-items: center; border-top: 1px solid #1e293b; padding-top: 1.5rem; flex-wrap: wrap; }
        .btn-act { padding: 0.65rem 1.25rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 700; text-decoration: none; cursor: pointer; border: 1px solid transparent; }
        .btn-print { background: #1e293b; border-color: #334155; color: #f8fafc; }
        .btn-pdf { background: #2563eb; color: #ffffff; }
        .btn-cancel { background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3); color: #f87171; }

        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.9rem; }

        /* Cancellation Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.75); align-items: center; justify-content: center; }
        .modal-content { background: #131d2e; border: 1px solid #27354a; border-radius: 0.75rem; width: 90%; max-width: 500px; padding: 2rem; position: relative; }
        .modal-title { font-size: 1.2rem; font-weight: 800; color: #ffffff; margin-bottom: 0.75rem; }
        .modal-close { position: absolute; right: 1.5rem; top: 1.5rem; background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <h1>DxClinic Fleet</h1>
            <span class="badge">Invoice #{{ $invoice->invoice_number }}</span>
        </div>
        <div class="topbar-actions">
            <a href="{{ route('superadmin.invoices') }}" class="btn-link">← Back to Invoices</a>
            <a href="{{ route('superadmin.sales') }}" class="btn-link">📊 Sales Dashboard</a>
            <a href="{{ route('superadmin.collections') }}" class="btn-link">💰 Collections</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="invoice-card">
        <!-- Invoice Header -->
        <div class="inv-header">
            <div>
                <span class="badge-status badge-{{ $invoice->status }}">{{ $invoice->status }}</span>
                <div class="inv-title" style="margin-top: 0.5rem;">{{ $invoice->invoice_number }}</div>
                <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 0.25rem;">
                    Platform Commercial Sale • <strong>{{ $invoice->formattedSaleType() }}</strong>
                </div>
            </div>
            <div class="inv-meta">
                <div style="font-size: 0.8rem; color: #94a3b8;">Invoice Date</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: #ffffff; font-family: 'JetBrains Mono', monospace;">
                    {{ $invoice->invoice_date ? $invoice->invoice_date->format('d F Y') : '-' }}
                </div>
                @if($invoice->createdBy)
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.35rem;">
                        Issued by: {{ $invoice->createdBy->name }} ({{ $invoice->createdBy->email }})
                    </div>
                @endif
            </div>
        </div>

        @if($invoice->isCancelled())
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: #f87171; font-size: 0.85rem;">
                <strong>Invoice Cancelled / Credit Note Issued</strong>
                <p style="margin-top: 0.25rem;">Cancelled on {{ $invoice->cancelled_at ? $invoice->cancelled_at->format('d M Y, H:i') : '' }} by {{ $invoice->cancelledBy?->name ?? 'Superadmin' }}.</p>
                <p style="margin-top: 0.25rem; font-style: italic;">Reason: {{ $invoice->cancellation_reason }}</p>
            </div>
        @endif

        <!-- Customer & License Details Grid -->
        <div class="grid-2">
            <!-- Clinic Details -->
            <div class="info-block">
                <div class="info-title">Billed To (Clinic Tenant)</div>
                <div class="info-line"><strong>{{ $invoice->clinic->name }}</strong></div>
                <div class="info-line">Clinic ID: <span style="font-family: 'JetBrains Mono', monospace; color: #38bdf8; font-weight: 700;">{{ $invoice->clinic->clinic_code }}</span></div>
                <div class="info-line">Phone: {{ $invoice->clinic->phone ?? 'N/A' }}</div>
                <div class="info-line">Address: {{ $invoice->clinic->address ?? '-' }}, {{ $invoice->clinic->city ?? '-' }}</div>
            </div>

            <!-- License & Transaction Details -->
            <div class="info-block">
                <div class="info-title">Commercial License Reference</div>
                <div class="info-line">Plan: <strong>{{ $invoice->license?->plan?->name ?? 'Standard Plan' }}</strong></div>
                <div class="info-line">License Key: <span style="font-family: 'JetBrains Mono', monospace; color: #94a3b8;">{{ $invoice->maskedLicenseKey() }}</span></div>
                <div class="info-line">Source: <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #cbd5e1;">{{ $invoice->source_type }} #{{ $invoice->source_id }}</span></div>
                <div class="info-line">Payment Method: <strong>{{ $invoice->payment_method ?: 'N/A' }}</strong></div>
                @if($invoice->payment_reference)
                    <div class="info-line">Payment Reference: <span style="font-family: 'JetBrains Mono', monospace;">{{ $invoice->payment_reference }}</span></div>
                @endif
                <div class="info-line">Payment Date: {{ $invoice->payment_date ? $invoice->payment_date->format('d M Y') : 'Pending' }}</div>
            </div>
        </div>

        <!-- Line Items Table -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Description</th>
                    <th>Type</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items as $idx => $item)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>
                            <strong style="color: #ffffff;">{{ $item->description }}</strong>
                        </td>
                        <td>
                            <span style="font-size: 0.72rem; color: #94a3b8; font-family: 'JetBrains Mono', monospace;">{{ $item->item_type }}</span>
                        </td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td style="text-align: right;" class="money">₹{{ number_format($item->unit_price, 2) }}</td>
                        <td style="text-align: right;" class="money" style="color: #ffffff;">₹{{ number_format($item->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #64748b;">No line items found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Summary Totals -->
        <div class="summary-box">
            <div class="summary-row">
                <span>Subtotal (Gross):</span>
                <span class="money">₹{{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            <div class="summary-row">
                <span>Discount Applied:</span>
                <span class="money" style="color: #94a3b8;">- ₹{{ number_format($invoice->discount, 2) }}</span>
            </div>
            <div class="summary-row total">
                <span>Net Amount:</span>
                <span class="money">₹{{ number_format($invoice->net_amount, 2) }}</span>
            </div>
        </div>

        @if($invoice->notes)
            <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 1.5rem; font-style: italic;">
                Note: {{ $invoice->notes }}
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="actions-bar">
            @if(!$invoice->isCancelled())
                <button type="button" class="btn-act btn-cancel" onclick="document.getElementById('cancelModal').style.display='flex'">
                    ✕ Cancel Invoice / Credit Note
                </button>
            @endif
            <a href="{{ route('superadmin.invoices.print', $invoice->id) }}" target="_blank" class="btn-act btn-print">
                🖨 Print Invoice
            </a>
            <a href="{{ route('superadmin.invoices.pdf', [$invoice->id, 'download' => 1]) }}" class="btn-act btn-pdf">
                ⬇ Download Vector PDF
            </a>
        </div>
    </div>

    <!-- Cancellation Modal (Controlled cancellation / Credit Note) -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <button type="button" class="modal-close" onclick="document.getElementById('cancelModal').style.display='none'">&times;</button>
            <div class="modal-title">Cancel Invoice {{ $invoice->invoice_number }}</div>
            <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.25rem;">
                Issued invoices are immutable and cannot be deleted or directly edited. Cancelling will issue a controlled cancellation/credit-note audit record.
            </p>
            <form action="{{ route('superadmin.invoices.cancel', $invoice->id) }}" method="POST">
                @csrf
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #cbd5e1; margin-bottom: 0.4rem;">
                        Reason for Cancellation *
                    </label>
                    <textarea name="cancellation_reason" rows="3" required placeholder="Enter commercial reason for cancelling this invoice..." style="width: 100%; background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 0.75rem; color: #f8fafc; font-size: 0.85rem;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn-act btn-print" onclick="document.getElementById('cancelModal').style.display='none'">Dismiss</button>
                    <button type="submit" class="btn-act btn-cancel">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
