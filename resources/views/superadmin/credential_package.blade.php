<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credential Package — {{ $package['clinic']->clinic_code }} — DxClinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 2rem; display: flex; justify-content: center; }
        .container { width: 100%; max-width: 760px; }
        .card { background: #131d2e; border: 1px solid #1e293b; border-radius: 1rem; padding: 2.25rem; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4); }
        .badge-success { display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1.25rem; }
        h1 { font-size: 1.6rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; margin-bottom: 0.35rem; }
        .subtitle { color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .credential-box { background: #0b0f19; border: 1px solid #27354a; border-radius: 0.75rem; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; }
        .cred-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #38bdf8; margin-bottom: 1rem; border-bottom: 1px solid #1f293d; padding-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between; }
        .cred-row { display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0; border-bottom: 1px dashed #1a2538; }
        .cred-row:last-child { border-bottom: none; }
        .cred-label { color: #94a3b8; font-size: 0.88rem; font-weight: 500; }
        .cred-val { font-family: 'JetBrains Mono', monospace; font-size: 0.98rem; font-weight: 700; color: #ffffff; background: #131d2e; padding: 0.25rem 0.65rem; border-radius: 0.375rem; border: 1px solid #27354a; }
        .cred-val-highlight { color: #38bdf8; border-color: rgba(56, 189, 248, 0.4); background: rgba(56, 189, 248, 0.1); }
        .cred-val-warn { color: #f59e0b; border-color: rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.1); }
        .actions-bar { display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.92rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; border: none; }
        .btn-copy { background: #0284c7; color: #ffffff; }
        .btn-copy:hover { background: #0369a1; }
        .btn-print { background: #334155; color: #ffffff; }
        .btn-print:hover { background: #475569; }
        .btn-done { background: transparent; color: #94a3b8; border: 1px solid #334155; }
        .btn-done:hover { background: #1e293b; color: #ffffff; }
        .alert-warning { background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); color: #fde68a; padding: 1rem; border-radius: 0.5rem; font-size: 0.85rem; line-height: 1.5; margin-bottom: 1.5rem; }
        @media print {
            body { background: #ffffff; color: #000000; padding: 0; }
            .card { background: #ffffff; border: 1px solid #cccccc; box-shadow: none; padding: 1.5rem; }
            .credential-box { background: #f8fafc; border: 1px solid #e2e8f0; }
            .cred-val { background: #ffffff; color: #000000; border: 1px solid #cbd5e1; }
            .actions-bar, .alert-warning { display: none; }
            h1 { color: #000000; }
            .cred-title { color: #0284c7; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="badge-success">✓ License Successfully Issued</div>
            <h1>Clinic Onboarding Credential Package</h1>
            <p class="subtitle">Deliver this initial access package securely to the Clinic Administrator.</p>

            <div class="alert-warning">
                ⚠️ <strong>Single-View Notice:</strong> The temporary passwords and activation code will never be shown again in plaintext. Please copy or print this package before leaving this page.
            </div>

            <!-- Clinic & License Summary -->
            <div class="credential-box">
                <div class="cred-title">
                    <span>Facility & License Record</span>
                    <span style="font-size: 0.75rem; color: #34d399;">Active License</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Clinic ID (Tenant Identifier)</span>
                    <span class="cred-val cred-val-highlight">{{ $package['clinic']->clinic_code }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Clinic Facility Name</span>
                    <span class="cred-val">{{ $package['clinic']->name }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Permanent License Key</span>
                    <span class="cred-val" style="font-size: 0.92rem;">{{ $package['raw_license_key'] }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">One-Time Activation Code</span>
                    <span class="cred-val cred-val-highlight">{{ $package['raw_activation_code'] }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Subscription Plan</span>
                    <span class="cred-val">{{ $package['license']->plan->name }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Validity Period</span>
                    <span class="cred-val">
                        {{ $package['license']->start_date->format('d-m-Y') }} to {{ $package['license']->end_date->format('d-m-Y') }}
                        @if(!empty($package['trial_months']) && $package['trial_months'] > 0)
                            <span style="color: #34d399; font-size: 0.8rem; margin-left: 0.35rem;">(+{{ $package['trial_months'] }} mo FREE trial)</span>
                        @endif
                    </span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Direct Patient Booking Link</span>
                    <span class="cred-val" style="font-size: 0.85rem;">{{ $package['clinic']->publicBookingUrl() }}</span>
                </div>
            </div>

            <!-- 1. Administrator Credentials -->
            <div class="credential-box">
                <div class="cred-title">
                    <span>1. Initial ADMINISTRATOR Credentials</span>
                    <span style="font-size: 0.75rem; color: #60a5fa;">Full Clinic Access</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Role</span>
                    <span class="cred-val">ADMINISTRATOR</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Login ID</span>
                    <span class="cred-val cred-val-highlight">{{ $package['admin_user']->login_id }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Temporary Password</span>
                    <span class="cred-val cred-val-warn">{{ $package['raw_temp_password'] }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Email / Username</span>
                    <span class="cred-val" style="font-size: 0.85rem;">{{ $package['admin_user']->email }}</span>
                </div>
            </div>

            <!-- 2. Operator Credentials (if generated) -->
            @if(!empty($package['operator_user']))
            <div class="credential-box">
                <div class="cred-title">
                    <span>2. Initial OPERATOR Credentials</span>
                    <span style="font-size: 0.75rem; color: #a78bfa;">Billing & Registration</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Role</span>
                    <span class="cred-val">OPERATOR</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Login ID</span>
                    <span class="cred-val cred-val-highlight">{{ $package['operator_user']->login_id }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Temporary Password</span>
                    <span class="cred-val cred-val-warn">{{ $package['raw_operator_password'] }}</span>
                </div>
            </div>
            @endif

            <!-- 3. Doctor Credentials (if generated) -->
            @if(!empty($package['doctor_user']))
            <div class="credential-box">
                <div class="cred-title">
                    <span>3. Initial DOCTOR Credentials</span>
                    <span style="font-size: 0.75rem; color: #34d399;">Consultation & Rx</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Role</span>
                    <span class="cred-val">DOCTOR</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Doctor Name</span>
                    <span class="cred-val">{{ $package['doctor_record']?->name ?? 'Doctor' }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Login ID</span>
                    <span class="cred-val cred-val-highlight">{{ $package['doctor_user']->login_id }}</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Temporary Password</span>
                    <span class="cred-val cred-val-warn">{{ $package['raw_doctor_password'] }}</span>
                </div>
            </div>
            @endif

            <div class="actions-bar">
                <button type="button" class="btn btn-copy" onclick="copyPackage()">📋 Copy Package to Clipboard</button>
                <button type="button" class="btn btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
                <a href="{{ route('superadmin.license-requests') }}" class="btn btn-done">Done & Return to Requests →</a>
                <a href="{{ route('superadmin.dashboard') }}" class="btn btn-done">Fleet Dashboard →</a>
            </div>
        </div>
    </div>

    <textarea id="raw-copy-buffer" style="position: absolute; left: -9999px;">
============================================================
DXCLINIC — CLINIC ONBOARDING CREDENTIAL PACKAGE
============================================================
Clinic ID:       {{ $package['clinic']->clinic_code }}
Clinic Name:     {{ $package['clinic']->name }}
License Key:     {{ $package['raw_license_key'] }}
Activation Code: {{ $package['raw_activation_code'] }}
Plan:            {{ $package['license']->plan->name }}
Validity:        {{ $package['license']->start_date->format('d-m-Y') }} to {{ $package['license']->end_date->format('d-m-Y') }}
Public Booking:  {{ $package['clinic']->publicBookingUrl() }}

1. ADMINISTRATOR
Login URL:       {{ url('/login') }}
Clinic ID:       {{ $package['clinic']->clinic_code }}
User ID:         {{ $package['admin_user']->login_id }}
Temporary Pass:  {{ $package['raw_temp_password'] }}

@if(!empty($package['operator_user']))
2. OPERATOR
User ID:         {{ $package['operator_user']->login_id }}
Temporary Pass:  {{ $package['raw_operator_password'] }}
@endif

@if(!empty($package['doctor_user']))
3. DOCTOR
User ID:         {{ $package['doctor_user']->login_id }}
Temporary Pass:  {{ $package['raw_doctor_password'] }}
@endif
============================================================
    </textarea>

    <script>
        function copyPackage() {
            var copyText = document.getElementById("raw-copy-buffer");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value).then(function() {
                alert("Credential Package copied to clipboard!");
            });
        }
    </script>
</body>
</html>
