<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Pad - {{ $patient->name }} (Token: {{ $appointment->formatted_token }})</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        @page {
            size: 8.25in 11.671in;
            margin: 0;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 8.25in;
            height: 11.671in;
            font-family: Arial, Helvetica, 'DejaVu Sans', sans-serif;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.3;
            background-color: {{ ($isPdf ?? false) ? 'transparent' : '#f1f5f9' }};
        }

        /* Toolbar for on-screen controls (hidden when printed or in PDF) */
        .toolbar {
            max-width: 8.25in;
            margin: 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn {
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-primary { background: #0f172a; color: #ffffff; border-color: #0f172a; }
        .btn-secondary { background: #ffffff; color: #334155; }
        .btn-active { background: #2563eb; color: #ffffff; border-color: #2563eb; }

        /* Outer sheet representing physical pad: 8.25in x 11.671in */
        .pad-page {
            width: 8.25in;
            height: 11.671in;
            margin: {{ ($isPdf ?? false) ? '0' : '0 auto 30px auto' }};
            position: relative;
            background-color: {{ ($isPdf ?? false) ? 'transparent' : '#ffffff' }};
            overflow: hidden;
            box-shadow: {{ ($isPdf ?? false) ? 'none' : '0 4px 12px rgba(0,0,0,0.1)' }};
        }

        /* Optional reference image background in developer preview mode */
        .pad-page.show-reference {
            background-image: url('/images/doctor-pad-reference.jpg');
            background-size: 100% 100%;
            background-repeat: no-repeat;
        }

        /* ============================================================
           MODE A: PRE-PRINTED DOCTOR PAD (DEFAULT)
           Precision Transparent Overlay at Calibrated Coordinates
           ============================================================ */
        .preprinted-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 8.25in;
            height: 11.671in;
            pointer-events: none;
        }

        .overlay-item {
            position: absolute;
            color: #0f172a;
            white-space: nowrap;
        }

        /* Calibrated coordinates (8.25in x 11.671in page origin) */
        .field-patient-name {
            left: 3.28in;
            top: 2.06in;
            font-size: 13px;
            font-weight: 700;
            max-width: 3.5in;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .field-age-sex {
            left: 6.94in;
            top: 2.06in;
            font-size: 12px;
            font-weight: 500;
        }
        .field-address {
            left: 2.85in;
            top: 2.34in;
            font-size: 11.5px;
            font-weight: 500;
            max-width: 3.7in;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .field-date {
            left: 6.72in;
            top: 2.34in;
            font-size: 12px;
            font-weight: 500;
        }
        .field-patient-id {
            left: 4.28in;
            top: 2.76in;
            width: 2.0in;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .field-token-no {
            left: 6.38in;
            top: 2.76in;
            width: 1.5in;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .field-weight {
            left: 1.01in;
            top: 1.96in;
            font-size: 12px;
            font-weight: 500;
        }
        .field-bp {
            left: 1.01in;
            top: 2.29in;
            font-size: 12px;
            font-weight: 500;
        }
        .field-pr {
            left: 1.01in;
            top: 2.62in;
            font-size: 12px;
            font-weight: 500;
        }
        .field-spo2 {
            left: 1.01in;
            top: 2.94in;
            font-size: 12px;
            font-weight: 500;
        }
        .field-temp {
            left: 1.01in;
            top: 3.26in;
            font-size: 12px;
            font-weight: 500;
        }

        /* ============================================================
           MODE B: BLANK PAPER / FULL DOCTOR HEADER
           Used only when printing on plain blank paper
           ============================================================ */
        .blank-paper-layout {
            display: none;
            padding: 32px 36px;
            height: 100%;
        }

        .blank-mode .blank-paper-layout { display: block; }
        .blank-mode .preprinted-overlay { display: none; }

        .clinic-header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .clinic-name {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
        }
        .doctor-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e3a8a;
            margin-top: 4px;
        }
        .clinic-meta {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
        .blank-patient-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .blank-vitals-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 8px 14px;
            margin-bottom: 20px;
        }
        .blank-rx {
            font-size: 28px;
            font-weight: 900;
            font-family: serif;
            color: #1e3a8a;
            margin-top: 10px;
        }

        @media print {
            body {
                background: transparent !important;
                padding: 0;
            }
            .toolbar {
                display: none !important;
            }
            .pad-page {
                box-shadow: none !important;
                margin: 0 !important;
                width: 8.25in;
                height: 11.671in;
            }
            .pad-page.show-reference {
                background-image: none !important;
            }
        }
    </style>
</head>
<body id="padBody" class="preprinted-mode">

    @if(!($isPdf ?? false))
    <!-- Interactive Navigation & Calibration Toolbar -->
    <div class="toolbar" id="toolbar">
        <div class="toolbar-group">
            <span style="font-weight: 800; font-size: 14px;">Doctor Prescription Pad</span>
            <span style="font-size: 11px; color: #64748b;">(8.25" &times; 11.671")</span>
        </div>
        <div class="toolbar-group">
            <label style="font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="radio" name="printMode" value="preprinted" checked onchange="setPrintMode('preprinted')">
                Pre-Printed Pad (Default Overlay)
            </label>
            <label style="font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="radio" name="printMode" value="blank" onchange="setPrintMode('blank')">
                Blank Paper (Full Header)
            </label>

            @if(config('app.env') !== 'production')
            <button type="button" class="btn btn-secondary" id="refToggleBtn" onclick="toggleReferenceOverlay()">
                Toggle Reference Pad Image
            </button>
            @endif

            <a href="{{ route('pdf.doctor-pad', $appointment->id) }}" class="btn btn-secondary" target="_blank">
                Download PDF
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                Print
            </button>
        </div>
    </div>
    @endif

    <!-- Authoritative Pad Sheet: 8.25in x 11.671in -->
    <div class="pad-page" id="padPage">

        <!-- ============================================================
             MODE A: PRE-PRINTED DOCTOR PAD OVERLAY (DEFAULT)
             Precision calibrated to exact physical scan:
             1. Patient Name (x=3.31in, y=2.08in)
             2. Age/Sex (x=6.98in, y=2.08in)
             3. Address (x=2.90in, y=2.37in)
             4. Date (x=6.79in, y=2.37in)
             5. Patient ID (x=4.26in, y=2.80in)
             6. Token No (x=6.35in, y=2.80in)
             7. Weight (x=1.01in, y=1.99in)
             8. BP (x=1.01in, y=2.31in)
             9. PR (x=1.01in, y=2.64in)
             10. SpO2 (x=1.01in, y=2.97in)
             11. Temperature (x=1.01in, y=3.28in)
             ============================================================ -->
        <div class="preprinted-overlay" id="preprintedOverlay">
            <!-- 1. Patient Name -->
            <div class="overlay-item field-patient-name" id="fieldPatientName">{{ $patient->name }}</div>

            <!-- 2. Age/Sex -->
            <div class="overlay-item field-age-sex" id="fieldAgeSex">{{ $patient->age }} yr/{{ substr($patient->sex ?? 'M', 0, 1) }}</div>

            <!-- 3. Address -->
            <div class="overlay-item field-address" id="fieldAddress">{{ $formattedAddress }}</div>

            <!-- 4. Date -->
            <div class="overlay-item field-date" id="fieldDate">{{ $appointment->appointment_date ? $appointment->appointment_date->format('d/m/Y') : now()->format('d/m/Y') }}</div>

            <!-- 5. Patient ID -->
            <div class="overlay-item field-patient-id" id="fieldPatientId">Patient ID:&nbsp;&nbsp;{{ $patient->patient_id ?? ('DXP-' . str_pad($patient->id, 5, '0', STR_PAD_LEFT)) }}</div>

            <!-- 6. Token No -->
            <div class="overlay-item field-token-no" id="fieldTokenNo">Token No:&nbsp;&nbsp;{{ str_pad($appointment->token_number, 3, '0', STR_PAD_LEFT) }}</div>

            <!-- 7. Weight -->
            @if($vital?->weight)
            <div class="overlay-item field-weight" id="fieldWeight">{{ is_numeric($vital->weight) ? number_format((float)$vital->weight, 2) : $vital->weight }} kg</div>
            @endif

            <!-- 8. BP -->
            @php
                $bpSys = $vital->bp_systolic ?? $vital->systolic_bp ?? null;
                $bpDia = $vital->bp_diastolic ?? $vital->diastolic_bp ?? null;
            @endphp
            @if($bpSys && $bpDia)
            <div class="overlay-item field-bp" id="fieldBp">{{ $bpSys }}/{{ $bpDia }}</div>
            @endif

            <!-- 9. PR -->
            @if($vital?->pulse)
            <div class="overlay-item field-pr" id="fieldPr">{{ $vital->pulse }}</div>
            @endif

            <!-- 10. SpO2 -->
            @if($vital?->spo2)
            <div class="overlay-item field-spo2" id="fieldSpo2">{{ $vital->spo2 }}{{ str_contains((string)$vital->spo2, '%') ? '' : '%' }}</div>
            @endif

            <!-- 11. Temperature -->
            @if($vital?->temperature)
            <div class="overlay-item field-temp" id="fieldTemp">{{ is_numeric($vital->temperature) ? number_format((float)$vital->temperature, 2) : $vital->temperature }}&deg;C</div>
            @endif
        </div>

        <!-- ============================================================
             MODE B: BLANK PAPER / FULL DOCTOR HEADER
             ============================================================ -->
        <div class="blank-paper-layout" id="blankLayout">
            <div class="clinic-header">
                <div class="clinic-name">{{ $clinic->name }}</div>
                <div class="doctor-title">{{ \App\Services\DoctorNamePresenter::format($doctor->name) }} &mdash; {{ $doctor->qualification ?? 'MBBS, MD' }} ({{ $doctor->specialization }})</div>
                <div class="clinic-meta">{{ $clinic->address }}, {{ $clinic->city }} | Phone: {{ $clinic->phone }}</div>
            </div>

            <div class="blank-patient-box">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <div><strong>Patient:</strong> {{ $patient->name }}</div>
                    <div><strong>Age / Sex:</strong> {{ $patient->age }} Y / {{ $patient->sex }}</div>
                    <div><strong>Date:</strong> {{ $appointment->appointment_date ? $appointment->appointment_date->format('d/m/Y') : now()->format('d/m/Y') }}</div>
                </div>
                <div style="border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 6px; display: flex; justify-content: space-between;">
                    <div><strong>Address:</strong> {{ $formattedAddress }}</div>
                    <div><strong>ID:</strong> {{ $patient->patient_id ?? ('DXP-' . str_pad($patient->id, 5, '0', STR_PAD_LEFT)) }} | <strong>Token:</strong> {{ $appointment->formatted_token }}</div>
                </div>
            </div>

            <div class="blank-vitals-box">
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); text-align: center; gap: 8px;">
                    <div><span style="font-size: 10px; color: #1e40af; text-transform: uppercase; font-weight: 700;">BP</span><div style="font-weight: 800; font-size: 13px;">{{ ($bpSys && $bpDia) ? "{$bpSys}/{$bpDia}" : '—' }}</div></div>
                    <div><span style="font-size: 10px; color: #1e40af; text-transform: uppercase; font-weight: 700;">Pulse</span><div style="font-weight: 800; font-size: 13px;">{{ $vital?->pulse ?? '—' }}</div></div>
                    <div><span style="font-size: 10px; color: #1e40af; text-transform: uppercase; font-weight: 700;">Weight</span><div style="font-weight: 800; font-size: 13px;">{{ $vital?->weight ? (is_numeric($vital->weight) ? number_format((float)$vital->weight, 2) : $vital->weight) . ' kg' : '—' }}</div></div>
                    <div><span style="font-size: 10px; color: #1e40af; text-transform: uppercase; font-weight: 700;">Temp</span><div style="font-weight: 800; font-size: 13px;">{{ $vital?->temperature ? (is_numeric($vital->temperature) ? number_format((float)$vital->temperature, 2) : $vital->temperature) . ' °C' : '—' }}</div></div>
                    <div><span style="font-size: 10px; color: #1e40af; text-transform: uppercase; font-weight: 700;">SpO2</span><div style="font-weight: 800; font-size: 13px;">{{ $vital?->spo2 ? $vital->spo2 . '%' : '—' }}</div></div>
                </div>
            </div>

            <div class="blank-rx">&#8478;</div>
        </div>

    </div>

    <script>
        function setPrintMode(mode) {
            const body = document.getElementById('padBody');
            if (mode === 'blank') {
                body.classList.remove('preprinted-mode');
                body.classList.add('blank-mode');
            } else {
                body.classList.remove('blank-mode');
                body.classList.add('preprinted-mode');
            }
        }

        function toggleReferenceOverlay() {
            const page = document.getElementById('padPage');
            page.classList.toggle('show-reference');
        }
    </script>
</body>
</html>
