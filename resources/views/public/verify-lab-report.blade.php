<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Verification — {{ $report->report_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .container {
            width: 100%;
            max-width: 480px;
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            color: white;
            padding: 32px 24px;
            text-align: center;
            position: relative;
        }
        .badge-verified {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .clinic-name {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin-bottom: 4px;
        }
        .clinic-sub {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
        }
        .body-card {
            padding: 28px 24px;
        }
        .data-grid {
            display: grid;
            gap: 16px;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 1px dashed var(--border);
        }
        .data-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .data-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .data-value {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
            text-align: right;
        }
        .mono {
            font-family: 'JetBrains Mono', monospace;
            color: #065f46;
        }
        .footer-note {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="badge-verified">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Authentic Clinical Report
            </div>
            <h1 class="clinic-name">{{ $report->clinic->name ?? 'DxClinic Diagnostics' }}</h1>
            <p class="clinic-sub">{{ $report->clinic->address ?? 'Clinical Diagnostic Division' }}</p>
        </div>

        <div class="body-card">
            <div class="data-grid">
                <div class="data-row">
                    <span class="data-label">Report Number</span>
                    <span class="data-value mono">{{ $report->report_number }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Patient Masked</span>
                    <span class="data-value">
                        @php
                            $pname = $report->patient->name ?? 'Patient';
                            $parts = explode(' ', trim($pname));
                            $masked = implode(' ', array_map(function($p) {
                                return strlen($p) > 1 ? substr($p, 0, 1) . str_repeat('*', strlen($p) - 1) : $p;
                            }, $parts));
                        @endphp
                        {{ $masked }} ({{ $report->patient->gender ?? 'U' }}, {{ $report->patient->age ?? '-' }}Y)
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Ordering Doctor</span>
                    <span class="data-value">Dr. {{ $report->doctor->name ?? 'Attending Clinician' }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Report Status</span>
                    <span class="data-value" style="color: #059669;">
                        {{ $report->status === 'RELEASED' ? 'FINAL RELEASED' : $report->status }}
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Released At</span>
                    <span class="data-value">{{ $report->released_at ? $report->released_at->format('d M Y, h:i A') : 'N/A' }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Released By</span>
                    <span class="data-value">{{ $report->releasedBy->name ?? 'Authorized Signatory' }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Security Hash</span>
                    <span class="data-value mono" style="font-size: 11px;">{{ substr($report->verification_hash ?? '', 0, 16) }}...</span>
                </div>
            </div>

            <div class="footer-note">
                This verification record confirms that the diagnostic report was officially generated and verified within the DxClinic Clinical Laboratory System.
            </div>
        </div>
    </div>
</body>
</html>
