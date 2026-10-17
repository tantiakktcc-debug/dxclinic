<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Plans — DxClinic</title>
    <meta name="description" content="DxClinic pricing plans for clinic management. Quarterly, Half-Yearly, Yearly, and multi-year options. Start with a free trial.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#070b14;--card:#0d1525;--input:#111b2e;--border:#1a2744;--text:#f1f5f9;--muted:#94a3b8;--dim:#64748b;--accent:#3b82f6;--glow:rgba(59,130,246,0.15);--success:#10b981; }
        * { box-sizing:border-box;margin:0;padding:0;font-family:'Inter',-apple-system,sans-serif; }
        body { background:var(--bg);color:var(--text);min-height:100vh; }
        .hero { padding:3.5rem 1.5rem 1.5rem;text-align:center;background:linear-gradient(180deg,rgba(59,130,246,0.08) 0%,transparent 100%); }
        .hero h1 { font-size:clamp(2rem,5vw,3rem);font-weight:900;letter-spacing:-0.03em;background:linear-gradient(135deg,#fff 40%,#3b82f6);-webkit-background-clip:text;-webkit-text-fill-color:transparent; }
        .hero p { color:var(--muted);font-size:1.1rem;margin-top:0.5rem;max-width:520px;margin-left:auto;margin-right:auto; }
        .grid { max-width:960px;margin:2rem auto;padding:0 1.5rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.25rem; }
        .plan { background:var(--card);border:1px solid var(--border);border-radius:1.25rem;padding:2rem;text-align:center;position:relative;transition:all 0.3s; }
        .plan:hover { border-color:rgba(59,130,246,0.4);transform:translateY(-2px);box-shadow:0 10px 30px rgba(0,0,0,0.3); }
        .plan.featured { border-color:var(--accent);box-shadow:0 0 0 1px var(--accent),0 10px 30px rgba(59,130,246,0.15); }
        .plan.featured::before { content:'POPULAR';position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--accent);color:#fff;padding:0.2rem 0.85rem;border-radius:9999px;font-size:0.7rem;font-weight:800;letter-spacing:0.05em; }
        .plan-name { font-size:1.1rem;font-weight:700;margin-bottom:0.5rem;color:var(--muted); }
        .plan-price { font-family:'JetBrains Mono',monospace;font-size:2rem;font-weight:800;color:var(--text);margin-bottom:0.25rem; }
        .plan-period { font-size:0.85rem;color:var(--dim);margin-bottom:1.25rem; }
        .plan-features { list-style:none;text-align:left;margin-bottom:1.5rem; }
        .plan-features li { padding:0.4rem 0;font-size:0.88rem;color:var(--muted);display:flex;align-items:center;gap:0.5rem; }
        .plan-features li::before { content:'✓';color:var(--success);font-weight:700;font-size:0.9rem; }
        .plan-btn { display:block;width:100%;padding:0.7rem;background:var(--input);border:1px solid var(--border);border-radius:0.5rem;color:var(--text);font-weight:700;font-size:0.9rem;text-decoration:none;text-align:center;transition:all 0.2s; }
        .plan-btn:hover { background:var(--glow);border-color:var(--accent);color:var(--accent); }
        .plan.featured .plan-btn { background:linear-gradient(135deg,#2563eb,#3b82f6);border-color:transparent;color:#fff; }
        .plan.featured .plan-btn:hover { box-shadow:0 4px 15px rgba(59,130,246,0.4); }
        .per-month { display:block;font-size:0.8rem;color:var(--dim);font-weight:500;margin-top:0.25rem; }
        .doctor-note { max-width:700px;margin:2rem auto;padding:0 1.5rem; }
        .doctor-note .box { background:var(--card);border:1px solid var(--border);border-radius:0.75rem;padding:1.5rem;text-align:center; }
        .doctor-note h3 { font-size:1rem;font-weight:700;color:var(--accent);margin-bottom:0.5rem; }
        .doctor-note p { color:var(--muted);font-size:0.9rem;line-height:1.6; }
        .footer-links { text-align:center;padding:2rem 0 3rem; }
        .footer-links a { color:var(--dim);font-size:0.85rem;text-decoration:none;margin:0 0.75rem; }
        .footer-links a:hover { color:var(--accent); }
    </style>
</head>
<body>
    <div class="hero">
        <h1>Simple, Transparent Pricing</h1>
        <p>Choose the plan that fits your clinic. All plans include full access to DxClinic features.</p>
    </div>

    <div class="grid">
        @foreach ($plans as $plan)
            @php
                $isFeatured = $plan->code === 'YEARLY';
                $perMonth = $plan->duration_months > 0 ? round($plan->price / $plan->duration_months) : 0;
            @endphp
            <div class="plan {{ $isFeatured ? 'featured' : '' }}">
                <div class="plan-name">{{ $plan->name }}</div>
                <div class="plan-price">₹{{ number_format($plan->price, 0) }}</div>
                <div class="plan-period">
                    {{ $plan->duration_months }} months
                    <span class="per-month">≈ ₹{{ number_format($perMonth, 0) }}/month</span>
                </div>
                <ul class="plan-features">
                    <li>{{ $plan->included_doctor_seats ?? 1 }} doctor included</li>
                    <li>Unlimited patients & appointments</li>
                    <li>Full OPD + Diagnostic billing</li>
                    <li>Prescription pad designer</li>
                    <li>Financial reports & reconciliation</li>
                    <li>Online patient booking (QR)</li>
                    @if ($plan->duration_months >= 12)
                        <li>Add extra doctors (₹{{ number_format($plan->additional_doctor_seat_price ?? 2999, 0) }}/year each)</li>
                    @endif
                </ul>
                <a href="{{ route('license.request', ['plan_id' => $plan->id]) }}" class="plan-btn">Get Started →</a>
            </div>
        @endforeach
    </div>

    <div class="doctor-note">
        <div class="box">
            <h3>🎁 Free Trial Available</h3>
            <p>Every new license purchase comes with an optional <strong>1 month free trial</strong> that stacks on top of your paid plan. A 12-month yearly plan becomes 13 months of access.</p>
        </div>
    </div>

    <div class="footer-links">
        <a href="{{ route('license.request') }}">Request a License</a>
        <a href="{{ route('license.renewal-request') }}">Renew Existing License</a>
        <a href="{{ route('login') }}">Clinic Login</a>
    </div>
</body>
</html>
