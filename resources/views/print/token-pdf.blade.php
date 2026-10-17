<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Token - {{ $formatted_token }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #0f172a; padding: 24px; }
        .token-card { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 2px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.06); padding: 32px; text-align: center; }
        .clinic-header { border-bottom: 2px dashed #cbd5e1; padding-bottom: 16px; margin-bottom: 20px; }
        .clinic-name { font-size: 20px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; }
        .clinic-sub { font-size: 12px; color: #64748b; margin-top: 4px; }
        .token-badge { background: #eff6ff; border: 2px solid #3b82f6; border-radius: 12px; padding: 16px; margin: 16px 0; }
        .token-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #3b82f6; letter-spacing: 1px; }
        .token-number { font-size: 56px; font-weight: 900; color: #1e3a8a; line-height: 1.1; margin: 4px 0; font-family: monospace; }
        .token-session { font-size: 13px; font-weight: 600; color: #475569; }
        .payment-alert { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; font-size: 12px; font-weight: 800; padding: 8px 12px; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px; margin: 12px 0; display: inline-block; }
        .details-table { width: 100%; margin-top: 16px; font-size: 13px; text-align: left; border-collapse: collapse; }
        .details-table td { padding: 6px 4px; border-bottom: 1px solid #f1f5f9; }
        .details-table td.label { font-weight: 600; color: #64748b; width: 38%; }
        .details-table td.value { font-weight: 700; color: #1e293b; }
        .qr-section { margin-top: 20px; padding-top: 16px; border-top: 2px dashed #cbd5e1; font-size: 11px; color: #64748b; }
        .qr-placeholder { display: inline-block; padding: 8px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-family: monospace; font-size: 11px; color: #475569; word-break: break-all; margin: 8px 0; }
        .instructions { font-size: 11px; color: #64748b; margin-top: 16px; line-height: 1.4; }
        .no-print { margin-top: 20px; }
        .btn-print { background: #1e3a8a; color: #ffffff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; transition: background 0.2s; }
        .btn-print:hover { background: #1e40af; }
        @media print {
            body { background: #ffffff; padding: 0; }
            .token-card { border: 1px solid #000000; box-shadow: none; border-radius: 0; padding: 16px; max-width: 100%; width: 100%; }
            .no-print { display: none !important; }
            .token-badge { border: 2px solid #000000; background: none; }
            .payment-alert { border: 1px solid #000000; color: #000000; background: none; }
        }
    </style>
</head>
<body>
    <div class="token-card">
        <div class="clinic-header">
            <div class="clinic-name">{{ $clinic_name }}</div>
            <div class="clinic-sub">{{ $clinic_address }}, {{ $clinic_city }} | Tel: {{ $clinic_phone }}</div>
        </div>

        <div class="payment-alert">
            {{ $payment_status }}
        </div>

        <div class="token-badge">
            <div class="token-label">OPD Queue Token</div>
            <div class="token-number">{{ $formatted_token }}</div>
            <div class="token-session">Session: {{ $session_type }} | Date: {{ $appointment_date }}</div>
        </div>

        <table class="details-table">
            <tr>
                <td class="label">Patient ID:</td>
                <td class="value">{{ $patient_id }}</td>
            </tr>
            <tr>
                <td class="label">Patient Name:</td>
                <td class="value">{{ $patient_name }}</td>
            </tr>
            <tr>
                <td class="label">Age / Sex:</td>
                <td class="value">{{ $age_sex }}</td>
            </tr>
            <tr>
                <td class="label">Mobile:</td>
                <td class="value">{{ $mobile }}</td>
            </tr>
            <tr>
                <td class="label">Address:</td>
                <td class="value">{{ $address }}</td>
            </tr>
            <tr>
                <td class="label">Booking Source:</td>
                <td class="value">{{ $booking_source }}</td>
            </tr>
        </table>

        <div class="qr-section">
            <div>Verification Link:</div>
            <div class="qr-placeholder">{{ $verification_url }}</div>
            <div class="instructions">
                <strong>Important:</strong> Please arrive 15 minutes prior to the session start time. Present this token at reception to record your arrival and complete consultation payment.
            </div>
        </div>

        <div class="no-print">
            <button class="btn-print" onclick="window.print()">Print Token Slip</button>
        </div>
    </div>
</body>
</html>
