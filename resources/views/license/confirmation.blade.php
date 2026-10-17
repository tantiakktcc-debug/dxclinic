<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Submitted — DxClinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing:border-box;margin:0;padding:0;font-family:'Inter',-apple-system,sans-serif; }
        body { background:#070b14;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem; }
        .card { max-width:520px;width:100%;background:#0d1525;border:1px solid #1a2744;border-radius:1.25rem;padding:2.5rem;text-align:center;box-shadow:0 20px 40px rgba(0,0,0,0.4); }
        .check-icon { width:72px;height:72px;background:rgba(16,185,129,0.12);border:2px solid rgba(16,185,129,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1.25rem; }
        h1 { font-size:1.5rem;font-weight:800;margin-bottom:0.5rem; }
        .subtitle { color:#94a3b8;font-size:0.95rem;line-height:1.6;margin-bottom:1.5rem; }
        .ref-box { background:#080d16;border:1px solid #1f293d;border-radius:0.75rem;padding:1.25rem;margin-bottom:1.5rem; }
        .ref-label { font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;margin-bottom:0.35rem; }
        .ref-value { font-family:'JetBrains Mono',monospace;font-size:1.3rem;font-weight:800;color:#3b82f6; }
        .ref-clinic { font-size:0.9rem;color:#94a3b8;margin-top:0.35rem; }
        .info-text { background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);border-radius:0.5rem;padding:0.85rem;font-size:0.85rem;color:#94a3b8;line-height:1.5;text-align:left;margin-bottom:1.5rem; }
        .btn-group { display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap; }
        .btn { padding:0.65rem 1.5rem;border-radius:0.5rem;font-weight:700;font-size:0.9rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;transition:all 0.2s; }
        .btn-primary { background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;border:none; }
        .btn-secondary { background:transparent;color:#94a3b8;border:1px solid #334155; }
        .btn-secondary:hover { background:#1e293b;color:#fff; }
    </style>
</head>
<body>
    <div class="card">
        <div class="check-icon">✓</div>
        <h1>{{ $requestType === 'INITIAL' ? 'License Request' : 'Renewal Request' }} Submitted</h1>
        <p class="subtitle">Your request has been received and will be processed by our team after payment confirmation.</p>

        <div class="ref-box">
            <div class="ref-label">Request Number</div>
            <div class="ref-value">{{ $requestNumber }}</div>
            <div class="ref-clinic">{{ $clinicName }}</div>
        </div>

        <div class="info-text">
            📋 <strong>Next Steps:</strong><br>
            1. Our team will review your request<br>
            2. Complete payment as instructed by our representative<br>
            3. Your {{ $requestType === 'INITIAL' ? 'clinic license and credentials will be generated' : 'license will be renewed immediately' }}<br><br>
            @if ($requestType === 'RENEWAL')
                🔒 Your existing login credentials, clinic data, and patient records remain <strong>completely unchanged</strong>.
            @else
                📝 Please keep your request number <strong>{{ $requestNumber }}</strong> for reference.
            @endif
        </div>

        <div class="btn-group">
            @if ($requestType === 'INITIAL')
                <a href="{{ route('license.request') }}" class="btn btn-primary">Submit Another Request</a>
            @else
                <a href="{{ route('license.renewal-request') }}" class="btn btn-primary">Submit Another Renewal</a>
            @endif
            <a href="{{ route('login') }}" class="btn btn-secondary">Go to Clinic Login</a>
        </div>
    </div>
</body>
</html>
