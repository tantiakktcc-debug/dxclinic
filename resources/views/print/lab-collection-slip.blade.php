<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Requisition / Collection Slip - {{ $slip_code }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #0f172a; padding: 20px; font-size: 13px; }
        .slip-card { max-width: 650px; margin: 0 auto; background: #ffffff; border: 2px dashed #94a3b8; border-radius: 8px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px; }
        .clinic-name { font-size: 18px; font-weight: 800; color: #0f172a; text-transform: uppercase; }
        .clinic-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
        .slip-code-box { text-align: right; }
        .slip-code { font-family: monospace; font-size: 18px; font-weight: 900; color: #0f172a; letter-spacing: 1px; }
        .barcode-mock { font-family: monospace; font-size: 24px; letter-spacing: 4px; font-weight: 300; }
        .patient-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 16px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px; }
        .p-row { display: flex; }
        .p-label { width: 90px; color: #64748b; font-weight: 600; }
        .p-val { font-weight: 700; color: #0f172a; }
        .items-title { font-weight: 800; font-size: 13px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #334155; }
        .test-list { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
        .test-list th { background: #f1f5f9; padding: 8px 10px; text-align: left; font-weight: 700; color: #475569; border: 1px solid #cbd5e1; }
        .test-list td { padding: 8px 10px; border: 1px solid #cbd5e1; }
        .check-box { width: 16px; height: 16px; border: 1.5px solid #64748b; border-radius: 3px; display: inline-block; vertical-align: middle; }
        .phleb-notes { border-top: 1px dashed #cbd5e1; padding-top: 14px; margin-top: 16px; display: flex; justify-content: space-between; font-size: 11px; color: #475569; }
        .sign-area { width: 180px; text-align: center; border-top: 1px solid #94a3b8; padding-top: 4px; margin-top: 30px; }
        .no-print { text-align: center; margin-bottom: 16px; }
        .btn-print { background: #0f172a; color: #ffffff; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; }
        @media print {
            body { background: #ffffff; padding: 0; }
            .slip-card { box-shadow: none; border: 1px dashed #000; border-radius: 0; padding: 16px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Lab Slip</button>
    </div>

    <div class="slip-card">
        <div class="header">
            <div>
                <div class="clinic-name">{{ $clinic_name }}</div>
                <div class="clinic-sub">{{ $clinic_address }}, {{ $clinic_city }}</div>
                <div style="font-weight: 800; color: #2563eb; margin-top: 4px; font-size: 12px;">LAB / SAMPLE COLLECTION SLIP</div>
            </div>
            <div class="slip-code-box">
                <div class="barcode-mock">||||| |||| || |||||</div>
                <div class="slip-code">{{ $slip_code }}</div>
                <div style="font-size: 11px; color: #64748b;">Bill: {{ $bill_number }} | Date: {{ $date }}</div>
            </div>
        </div>

        <div class="patient-box">
            <div class="grid-2">
                <div class="p-row"><span class="p-label">Patient ID:</span><span class="p-val">{{ $patient_id }}</span></div>
                <div class="p-row"><span class="p-label">Patient Name:</span><span class="p-val">{{ $patient_name }}</span></div>
                <div class="p-row"><span class="p-label">Age / Sex:</span><span class="p-val">{{ $age_sex }}</span></div>
                <div class="p-row"><span class="p-label">Mobile:</span><span class="p-val">{{ $mobile }}</span></div>
                <div class="p-row" style="grid-column: span 2;"><span class="p-label">Address:</span><span class="p-val">{{ $address }}</span></div>
            </div>
        </div>

        <div class="items-title">Requisition Tests / Investigations</div>
        <table class="test-list">
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">Done</th>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th>Investigation Name</th>
                    <th style="width: 110px;">Sample / Type</th>
                    <th style="width: 50px; text-align: center;">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $i => $item)
                <tr>
                    <td style="text-align: center;"><div class="check-box"></div></td>
                    <td style="text-align: center;">{{ $i + 1 }}</td>
                    <td><strong>{{ $item['name'] }}</strong></td>
                    <td>{{ $item['type'] }}</td>
                    <td style="text-align: center;">{{ $item['quantity'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="phleb-notes">
            <div>
                <div>Sample Collection Time: __________________</div>
                <div style="margin-top: 8px;">Vial / Specimen ID: ______________________</div>
                <div style="margin-top: 8px; font-size: 10px; color: #64748b;">* Operational collection slip only. Lab results handled via external reporting.</div>
            </div>
            <div>
                <div class="sign-area">Phlebotomist / Technician</div>
            </div>
        </div>
    </div>
</body>
</html>
