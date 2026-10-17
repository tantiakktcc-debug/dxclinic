<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Pending Activation — DxClinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0f172a; color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 1rem; max-width: 480px; width: 100%; padding: 2.5rem; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        .icon { width: 64px; height: 64px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; color: #ffffff; }
        p { color: #94a3b8; font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem; }
        .clinic-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: #0f172a; border: 1px solid #334155; padding: 0.5rem 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-weight: 600; color: #38bdf8; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff; padding: 0.75rem 1.5rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .footer { margin-top: 2rem; font-size: 0.8rem; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⏳</div>
        <h1>Clinic Activation Required</h1>
        <div class="clinic-badge">
            <span>{{ $clinic->clinic_code ?? 'NX????' }}</span>
            <span>•</span>
            <span>{{ $clinic->name ?? 'DxClinic Facility' }}</span>
        </div>
        <p>This clinic account is pending first-time license activation. The clinic administrator must sign in and enter the one-time Activation Code provided during license issuance.</p>
        <div>
            <a href="{{ route('login') }}" class="btn">Go to Clinic Login</a>
        </div>
        <div class="footer">DxClinic Multi-Tenant Healthcare Platform</div>
    </div>
</body>
</html>
