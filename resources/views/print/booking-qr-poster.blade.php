<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Booking Poster - {{ $clinic->name ?? 'DxClinic' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        .poster {
            width: 180mm;
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .clinic-header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .clinic-name {
            font-size: 26px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .clinic-address {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }
        .badge {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 6px 18px;
            border-radius: 9999px;
            margin-bottom: 16px;
        }
        .title {
            font-size: 32px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 12px;
        }
        .qr-wrapper {
            margin: 20px auto;
            display: inline-block;
            padding: 16px;
            background: #ffffff;
            border: 3px solid #0f172a;
            border-radius: 16px;
        }
        .url-box {
            font-family: monospace;
            font-size: 13px;
            font-weight: 700;
            color: #2563eb;
            background: #eff6ff;
            padding: 8px 14px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 24px;
            max-width: 90%;
            word-break: break-all;
        }
        .steps-container {
            text-align: left;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
        }
        .steps-title {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #334155;
            margin-bottom: 12px;
        }
        .step-list {
            list-style: none;
            counter-reset: step-counter;
        }
        .step-item {
            counter-increment: step-counter;
            font-size: 12px;
            line-height: 1.5;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .step-num {
            background: #0f172a;
            color: #ffffff;
            font-weight: 900;
            font-size: 11px;
            min-width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .disclaimer-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 12px;
            font-size: 11px;
            color: #991b1b;
            font-weight: 600;
            line-height: 1.4;
        }
        .no-print {
            position: fixed;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 10px;
        }
        .btn {
            background: #0f172a;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .btn-blue { background: #2563eb; }
        @media print {
            body { background: #ffffff; padding: 0; }
            .poster { box-shadow: none; border-color: #000; width: 100%; border-radius: 0; padding: 10mm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" class="btn btn-blue" onclick="window.print()">🖨️ Print Poster</button>
        <button type="button" class="btn" onclick="window.close()">Close</button>
    </div>

    <div class="poster">
        <div class="clinic-header">
            <div class="clinic-name">{{ $clinic->name ?? 'DxClinic' }}</div>
            <div class="clinic-address">{{ $clinic->address ?? '' }} {{ $clinic->city ?? '' }} | Tel: {{ $clinic->phone ?? '' }}</div>
        </div>

        <div>
            <div class="badge">Self-Service OPD Token</div>
            <h1 class="title">SCAN TO BOOK APPOINTMENT</h1>
        </div>

        <div class="qr-wrapper">
            {!! $qrSvg !!}
        </div>

        <div>
            <div class="url-box">{{ $url }}</div>
        </div>

        <!-- Section 50 Workflow steps -->
        <div class="steps-container">
            <div class="steps-title">Simple 7-Step Deskless Booking:</div>
            <ol class="step-list">
                <li class="step-item"><span class="step-num">1</span><span><strong>Scan QR Code</strong> with your mobile camera or Google Lens to open the Clinic Booking App.</span></li>
                <li class="step-item"><span class="step-num">2</span><span><strong>Register or Select Patient</strong> using your mobile number and patient name.</span></li>
                <li class="step-item"><span class="step-num">3</span><span><strong>Select Doctor</strong> (if multiple doctors are available) and choose your preferred date/session.</span></li>
                <li class="step-item"><span class="step-num">4</span><span><strong>Get Instant OPD Token</strong> allocated from the clinic daily token queue.</span></li>
                <li class="step-item"><span class="step-num">5</span><span><strong>Download Token PDF</strong> or save the confirmation token screen on your phone.</span></li>
                <li class="step-item"><span class="step-num">6</span><span><strong>Show Token at Clinic Counter</strong> when you arrive for physical check-in and vital recording.</span></li>
                <li class="step-item"><span class="step-num">7</span><span><strong>Pay Consultation Fee at Clinic Counter</strong> via Cash or in-clinic Soundbox UPI.</span></li>
            </ol>
        </div>

        <!-- Section 10 & 50 Notice: Online Booking is NOT online payment -->
        <div class="disclaimer-box">
            IMPORTANT: Online Booking is strictly an appointment and token reservation service. No online payment gateway is used. Consultation fees are paid in person at the clinic reception counter upon arrival.
        </div>
    </div>
</body>
</html>
