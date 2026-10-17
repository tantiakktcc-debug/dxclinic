<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Receipt - {{ $bill->bill_number }}</title>
    <style>
        @page {
            size: 148mm 210mm; /* Strict A5 Dimensions (Section 30) */
            margin: 6mm 8mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.35;
        }
        .page-container {
            width: 132mm;
            max-width: 132mm;
            margin: 0 auto;
        }
        .receipt-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 12px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .header-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }
        .logo-box {
            max-width: 120px;
            max-height: 44px;
            display: flex;
            align-items: center;
        }
        .logo-img {
            max-width: 120px;
            max-height: 44px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .clinic-title {
            font-size: 15px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .clinic-sub {
            font-size: 9.5px;
            color: #475569;
            margin-top: 1px;
        }
        .copy-tag {
            font-size: 9.5px;
            font-weight: 800;
            padding: 3px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .tag-patient {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .tag-clinic {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .grid-2 {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .col-half {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 6px;
        }
        .col-half:last-child {
            padding-right: 0;
            padding-left: 6px;
        }
        .info-group {
            background: #f8fafc;
            padding: 6px 8px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        .info-row {
            display: flex;
            margin-bottom: 2px;
            font-size: 10px;
        }
        .info-label {
            width: 70px;
            color: #64748b;
            font-weight: 600;
            flex-shrink: 0;
        }
        .info-val {
            font-weight: 700;
            color: #1e293b;
            flex: 1;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 10.5px;
        }
        .table-custom th {
            background: #f1f5f9;
            padding: 5px 6px;
            text-align: left;
            font-weight: 700;
            color: #334155;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }
        .table-custom td {
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
        }
        .text-right { text-align: right; }
        .totals-area {
            margin-top: 6px;
            display: flex;
            justify-content: flex-end;
        }
        .totals-box {
            width: 190px;
            font-size: 10.5px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 1.5px 0;
        }
        .totals-row.final {
            font-weight: 800;
            font-size: 11.5px;
            border-top: 1.5px solid #0f172a;
            padding-top: 3px;
            margin-top: 2px;
            color: #0f172a;
        }
        .payment-meta {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 5px 8px;
            margin-top: 6px;
            font-size: 9.5px;
            color: #166534;
            display: flex;
            justify-content: space-between;
        }
        .footer {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 9px;
            color: #64748b;
        }
        .sig-box {
            text-align: center;
            border-top: 1px dashed #94a3b8;
            width: 110px;
            padding-top: 2px;
        }
        .page-break {
            page-break-after: always;
            break-after: page;
        }
        .no-print {
            text-align: center;
            margin-bottom: 12px;
            padding: 10px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .btn-group {
            display: inline-flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn {
            background: #0f172a;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-blue { background: #2563eb; }
        .btn-amber { background: #d97706; }
        .btn-outline {
            background: #ffffff;
            color: #0f172a;
            border: 1px solid #cbd5e1;
        }
        @media print {
            body { background: #ffffff; padding: 0; }
            .page-container { width: 100%; max-width: 100%; }
            .receipt-card { box-shadow: none; border: 1px solid #000; border-radius: 0; margin-bottom: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    @php
        $selectedCopy = request('copy', $copy ?? 'patient');
        $hasLogo = !empty($clinic->logo_path);
        $logoBase64 = null;
        if ($hasLogo) {
            $logoFullPath = storage_path('app/public/' . $clinic->logo_path);
            if (file_exists($logoFullPath)) {
                $type = pathinfo($logoFullPath, PATHINFO_EXTENSION);
                $imgData = file_get_contents($logoFullPath);
                $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($imgData);
            }
        }
        $logoPos = $clinic->logo_position ?? 'left';
    @endphp

    <div class="page-container">
        <!-- Interactive Print Toolbar (Screen Only) -->
        <div class="no-print">
            <div class="btn-group">
                <a href="{{ route('print.consultation.receipt', ['billId' => $bill->id, 'copy' => 'patient']) }}" class="btn {{ $selectedCopy === 'patient' ? 'btn-blue' : 'btn-outline' }}">
                    Print Patient Copy
                </a>
                <a href="{{ route('print.consultation.receipt', ['billId' => $bill->id, 'copy' => 'clinic']) }}" class="btn {{ $selectedCopy === 'clinic' ? 'btn-amber' : 'btn-outline' }}">
                    Print Clinic Copy
                </a>
                <a href="{{ route('print.consultation.receipt', ['billId' => $bill->id, 'copy' => 'both']) }}" class="btn {{ $selectedCopy === 'both' ? 'btn-blue' : 'btn-outline' }}">
                    Print Both Copies (A5 x 2)
                </a>
                <button type="button" class="btn" onclick="window.print()">
                    🖨️ Print Document
                </button>
                <a href="{{ route('pdf.consultation-receipt', ['billId' => $bill->id, 'copy' => $selectedCopy]) }}" class="btn btn-outline">
                    ⬇️ Download PDF (A5)
                </a>
            </div>
        </div>

        {{-- PAGE 1: PATIENT COPY --}}
        @if($selectedCopy === 'patient' || $selectedCopy === 'both')
        <div class="receipt-card">
            <div class="header" style="{{ $logoPos === 'center' ? 'flex-direction: column; text-align: center;' : '' }}">
                <div class="header-content" style="{{ $logoPos === 'right' ? 'flex-direction: row-reverse;' : ($logoPos === 'center' ? 'flex-direction: column;' : '') }}">
                    @if($logoBase64)
                        <div class="logo-box">
                            <img src="{{ $logoBase64 }}" alt="Clinic Logo" class="logo-img">
                        </div>
                    @endif
                    <div>
                        <div class="clinic-title">{{ $clinic->name }}</div>
                        <div class="clinic-sub">{{ $clinic->address }}, {{ $clinic->city }} | Tel: {{ $clinic->phone }}</div>
                    </div>
                </div>
                <div class="copy-tag tag-patient" style="{{ $logoPos === 'center' ? 'margin-top: 4px;' : '' }}">
                    Patient Copy
                </div>
            </div>

            <div class="grid-2">
                <div class="col-half">
                    <div class="info-group">
                        <div class="info-row"><span class="info-label">Bill No:</span><span class="info-val">{{ $bill->bill_number }}</span></div>
                        <div class="info-row"><span class="info-label">Date & Time:</span><span class="info-val">{{ $bill->bill_date->format('d/m/Y') }} {{ $bill->created_at->format('H:i') }}</span></div>
                        <div class="info-row"><span class="info-label">Token No:</span><span class="info-val">{{ $bill->appointment ? $bill->appointment->formatted_token : '-' }}</span></div>
                        <div class="info-row"><span class="info-label">Doctor:</span><span class="info-val">{{ \App\Services\DoctorNamePresenter::format($bill->doctor->name) }} ({{ $bill->doctor->specialization ?? 'Consultant' }})</span></div>
                    </div>
                </div>
                <div class="col-half">
                    <div class="info-group">
                        <div class="info-row"><span class="info-label">Patient ID:</span><span class="info-val">{{ $patient->patient_id }}</span></div>
                        <div class="info-row"><span class="info-label">Name:</span><span class="info-val">{{ $patient->name }}</span></div>
                        <div class="info-row"><span class="info-label">Age / Sex:</span><span class="info-val">{{ $patient->age }} Y / {{ $patient->sex }}</span></div>
                        <div class="info-row"><span class="info-label">Address:</span><span class="info-val">{{ $formattedAddress }}</span></div>
                    </div>
                </div>
            </div>

            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Particulars / Description</th>
                        <th class="text-right">Gross (₹)</th>
                        <th class="text-right">Discount (₹)</th>
                        <th class="text-right">Net Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>OPD Consultation Fee</strong> ({{ ucfirst($bill->visit_type) }})</td>
                        <td class="text-right">{{ number_format($bill->gross_amount, 2) }}</td>
                        <td class="text-right">{{ number_format($bill->discount_amount, 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($bill->net_amount, 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>

            <div class="totals-area">
                <div class="totals-box">
                    <div class="totals-row"><span>Total Net:</span><span>₹ {{ number_format($bill->net_amount, 2) }}</span></div>
                    <div class="totals-row"><span>Amount Paid:</span><span>₹ {{ number_format($bill->paid_amount, 2) }}</span></div>
                    <div class="totals-row final"><span>Balance Due:</span><span>₹ {{ number_format($bill->balance_amount, 2) }}</span></div>
                </div>
            </div>

            @if($payment)
            <div class="payment-meta">
                <div><strong>Payment Mode:</strong> {{ $payment->payment_mode }}</div>
                @if($payment->reference_number)
                <div><strong>Ref / UTR:</strong> {{ $payment->reference_number }}</div>
                @endif
                <div><strong>Receipt No:</strong> {{ $payment->receipt?->receipt_number ?? 'REC-' . $payment->id }}</div>
            </div>
            @endif

            <div class="footer">
                <div>Valid for consultation today. Non-refundable.</div>
                <div class="sig-box">Authorized Signature</div>
            </div>
        </div>
        @endif

        {{-- PAGE BREAK FOR BOTH COPIES --}}
        @if($selectedCopy === 'both')
        <div class="page-break"></div>
        @endif

        {{-- PAGE 2: CLINIC COPY --}}
        @if($selectedCopy === 'clinic' || $selectedCopy === 'both')
        <div class="receipt-card">
            <div class="header" style="{{ $logoPos === 'center' ? 'flex-direction: column; text-align: center;' : '' }}">
                <div class="header-content" style="{{ $logoPos === 'right' ? 'flex-direction: row-reverse;' : ($logoPos === 'center' ? 'flex-direction: column;' : '') }}">
                    @if($logoBase64)
                        <div class="logo-box">
                            <img src="{{ $logoBase64 }}" alt="Clinic Logo" class="logo-img">
                        </div>
                    @endif
                    <div>
                        <div class="clinic-title">{{ $clinic->name }}</div>
                        <div class="clinic-sub">Clinical & Audit Record | Bill: {{ $bill->bill_number }}</div>
                    </div>
                </div>
                <div class="copy-tag tag-clinic" style="{{ $logoPos === 'center' ? 'margin-top: 4px;' : '' }}">
                    Clinic Copy
                </div>
            </div>

            <div class="grid-2">
                <div class="col-half">
                    <div class="info-group">
                        <div class="info-row"><span class="info-label">Token:</span><span class="info-val">{{ $bill->appointment ? $bill->appointment->formatted_token : '-' }} ({{ $bill->appointment->session_type ?? 'OPD' }})</span></div>
                        <div class="info-row"><span class="info-label">Date & Time:</span><span class="info-val">{{ $bill->bill_date->format('d/m/Y') }} {{ $bill->created_at->format('H:i') }}</span></div>
                        <div class="info-row"><span class="info-label">Doctor:</span><span class="info-val">{{ \App\Services\DoctorNamePresenter::format($bill->doctor->name) }}</span></div>
                        <div class="info-row"><span class="info-label">Source:</span><span class="info-val">{{ $bill->appointment->booking_source ?? 'CLINIC' }}</span></div>
                    </div>
                </div>
                <div class="col-half">
                    <div class="info-group">
                        <div class="info-row"><span class="info-label">Patient:</span><span class="info-val">{{ $patient->patient_id }} - {{ $patient->name }}</span></div>
                        <div class="info-row"><span class="info-label">Age / Sex:</span><span class="info-val">{{ $patient->age }} Y / {{ $patient->sex }}</span></div>
                        <div class="info-row"><span class="info-label">Address:</span><span class="info-val">{{ $formattedAddress }}</span></div>
                        <div class="info-row"><span class="info-label">Mobile:</span><span class="info-val">{{ $patient->mobile }}</span></div>
                    </div>
                </div>
            </div>

            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Particulars / Description</th>
                        <th class="text-right">Gross (₹)</th>
                        <th class="text-right">Discount (₹)</th>
                        <th class="text-right">Paid (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>OPD Consultation Fee</strong> ({{ ucfirst($bill->visit_type) }})</td>
                        <td class="text-right">{{ number_format($bill->gross_amount, 2) }}</td>
                        <td class="text-right">{{ number_format($bill->discount_amount, 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($bill->paid_amount, 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>

            @if($payment)
            <div class="payment-meta">
                <div><strong>Settlement:</strong> {{ $payment->payment_mode }} (₹ {{ number_format($payment->amount, 2) }})</div>
                @if($payment->reference_number)
                <div><strong>Ref:</strong> {{ $payment->reference_number }}</div>
                @endif
                <div><strong>Session:</strong> {{ $payment->cash_session_id ? 'CS-' . $payment->cash_session_id : 'Active' }}</div>
            </div>
            @endif

            <div class="footer">
                <div>Audit Copy — Retain for daily cash counter reconciliation.</div>
                <div class="sig-box">Desk Operator</div>
            </div>
        </div>
        @endif
    </div>
</body>
</html>
