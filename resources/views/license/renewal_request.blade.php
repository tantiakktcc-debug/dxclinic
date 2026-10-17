<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renew Your License — DxClinic</title>
    <meta name="description" content="Request a license renewal for your DxClinic clinic. Quick and easy — just verify your clinic identity and choose a plan.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary:#070b14;--bg-card:#0d1525;--bg-input:#111b2e;--border:#1a2744;--border-focus:#3b82f6;--text-primary:#f1f5f9;--text-secondary:#94a3b8;--text-muted:#64748b;--accent:#8b5cf6;--accent-glow:rgba(139,92,246,0.15);--success:#10b981;--warning:#f59e0b;--danger:#ef4444; }
        * { box-sizing:border-box;margin:0;padding:0;font-family:'Inter',-apple-system,sans-serif; }
        body { background:var(--bg-primary);color:var(--text-primary);min-height:100vh; }
        .hero { padding:3rem 1.5rem 1rem;text-align:center;background:linear-gradient(180deg,rgba(139,92,246,0.08) 0%,transparent 100%); }
        .hero-badge { display:inline-flex;align-items:center;gap:0.5rem;background:var(--accent-glow);border:1px solid rgba(139,92,246,0.3);color:var(--accent);padding:0.35rem 1rem;border-radius:9999px;font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:1rem; }
        .hero h1 { font-size:clamp(1.75rem,4vw,2.5rem);font-weight:900;letter-spacing:-0.03em;margin-bottom:0.5rem;background:linear-gradient(135deg,#fff 40%,#8b5cf6);-webkit-background-clip:text;-webkit-text-fill-color:transparent; }
        .hero p { color:var(--text-secondary);font-size:1.05rem;max-width:540px;margin:0 auto;line-height:1.6; }
        .form-container { max-width:580px;margin:1.5rem auto 3rem;padding:0 1.5rem; }
        .card { background:var(--bg-card);border:1px solid var(--border);border-radius:1.25rem;padding:2rem; }
        .card+.card { margin-top:1.5rem; }
        .card-title { font-size:1.05rem;font-weight:700;color:var(--accent);margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem; }
        .card-title .num { width:28px;height:28px;background:var(--accent-glow);border:1px solid rgba(139,92,246,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:800; }
        .field-grid { display:grid;grid-template-columns:1fr 1fr;gap:1rem; }
        .field-grid .full { grid-column:1/-1; }
        .field { display:flex;flex-direction:column;gap:0.35rem; }
        .field label { font-size:0.85rem;font-weight:600;color:var(--text-secondary); }
        .field label .req { color:var(--danger);margin-left:2px; }
        .field input,.field select { background:var(--bg-input);border:1px solid var(--border);color:var(--text-primary);padding:0.65rem 0.85rem;border-radius:0.5rem;font-size:0.95rem;transition:border-color 0.2s,box-shadow 0.2s; }
        .field input:focus,.field select:focus { outline:none;border-color:var(--border-focus);box-shadow:0 0 0 3px var(--accent-glow); }
        .plans-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;margin-bottom:1rem; }
        .plan-card { background:var(--bg-input);border:2px solid var(--border);border-radius:0.75rem;padding:1rem;text-align:center;cursor:pointer;transition:all 0.2s;position:relative; }
        .plan-card:hover { border-color:rgba(139,92,246,0.4); }
        .plan-card.selected { border-color:var(--accent);background:var(--accent-glow); }
        .plan-card input[type="radio"] { position:absolute;opacity:0; }
        .plan-name { font-weight:700;font-size:0.95rem;margin-bottom:0.25rem; }
        .plan-price { font-family:'JetBrains Mono',monospace;font-weight:700;font-size:1.15rem;color:var(--accent); }
        .plan-duration { font-size:0.8rem;color:var(--text-muted); }
        .info-box { background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2);border-radius:0.5rem;padding:0.85rem 1rem;font-size:0.85rem;color:var(--text-secondary);line-height:1.5;margin-bottom:1rem; }
        .alert-error { background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;padding:0.85rem 1rem;border-radius:0.5rem;font-size:0.9rem;margin-bottom:1.25rem; }
        .submit-bar { margin-top:1.5rem;text-align:center; }
        .btn-submit { background:linear-gradient(135deg,#7c3aed,#8b5cf6);color:#fff;border:none;padding:0.85rem 2.5rem;border-radius:0.75rem;font-size:1.05rem;font-weight:700;cursor:pointer;transition:all 0.3s;box-shadow:0 4px 15px rgba(139,92,246,0.3); }
        .btn-submit:hover { transform:translateY(-1px);box-shadow:0 6px 20px rgba(139,92,246,0.4); }
        .footer-links { text-align:center;margin-top:1.5rem;padding-bottom:2rem; }
        .footer-links a { color:var(--text-muted);font-size:0.85rem;text-decoration:none;margin:0 0.75rem; }
        .footer-links a:hover { color:var(--accent); }
        .hp-field { position:absolute;left:-9999px;opacity:0;height:0;width:0;overflow:hidden; }
        @media (max-width:600px) { .field-grid{grid-template-columns:1fr;} .plans-grid{grid-template-columns:1fr 1fr;} .card{padding:1.25rem;} }
    </style>
</head>
<body>
    <div class="hero">
        <div class="hero-badge">🔄 License Renewal</div>
        <h1>Renew Your DxClinic License</h1>
        <p>Verify your clinic identity and select a renewal plan. Your existing credentials and data remain unchanged.</p>
    </div>

    <div class="form-container">
        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    <div>⚠️ {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('license.renewal-request.submit') }}">
            @csrf
            <div class="hp-field">
                <input type="text" name="website_url" tabindex="-1" autocomplete="off">
            </div>

            {{-- 1. Verify Identity --}}
            <div class="card">
                <div class="card-title"><span class="num">1</span> Verify Your Clinic</div>
                <div class="info-box">
                    ℹ️ Enter the Clinic ID and registered mobile number that was provided when your DxClinic license was originally issued. Your existing login credentials and clinic data will <strong>not</strong> change.
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label>Clinic ID <span class="req">*</span></label>
                        <input type="text" name="clinic_code" value="{{ old('clinic_code') }}" required placeholder="e.g. NX0007" style="text-transform: uppercase;">
                    </div>
                    <div class="field">
                        <label>Registered Mobile <span class="req">*</span></label>
                        <input type="tel" name="mobile" value="{{ old('mobile') }}" required placeholder="e.g. 9876543210">
                    </div>
                    <div class="field">
                        <label>Contact Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="e.g. clinic@example.com">
                    </div>
                    <div class="field">
                        <label>Contact Name</label>
                        <input type="text" name="owner_name" value="{{ old('owner_name') }}" placeholder="e.g. Dr. Raj Kumar">
                    </div>
                </div>
            </div>

            {{-- 2. Plan Selection --}}
            <div class="card">
                <div class="card-title"><span class="num">2</span> Select Renewal Plan</div>
                <div class="plans-grid">
                    @foreach ($plans as $plan)
                        <label class="plan-card {{ old('plan_id') == $plan->id ? 'selected' : '' }}" onclick="this.querySelector('input').checked=true;document.querySelectorAll('.plan-card').forEach(c=>c.classList.remove('selected'));this.classList.add('selected');">
                            <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'checked' : '' }} required>
                            <div class="plan-name">{{ $plan->name }}</div>
                            <div class="plan-price">₹{{ number_format($plan->price, 0) }}</div>
                            <div class="plan-duration">{{ $plan->duration_months }} months</div>
                        </label>
                    @endforeach
                </div>

                <div class="field" style="margin-top: 1rem;">
                    <label>Total Doctors Needed</label>
                    <input type="number" name="requested_doctor_count" value="{{ old('requested_doctor_count', 1) }}" min="1" max="50" style="max-width: 120px;">
                </div>
            </div>

            <div class="submit-bar">
                <button type="submit" class="btn-submit">Submit Renewal Request →</button>
            </div>
        </form>

        <div class="footer-links">
            <a href="{{ route('license.request') }}">New License Request</a>
            <a href="{{ route('license.pricing') }}">View Pricing Plans</a>
            <a href="{{ route('login') }}">Clinic Login</a>
        </div>
    </div>
</body>
</html>
