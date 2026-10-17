<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Permanent Password — DxClinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0f172a; color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; max-width: 460px; width: 100%; padding: 2.5rem 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .header { text-align: center; margin-bottom: 2rem; }
        .badge { display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); color: #38bdf8; padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem; }
        h1 { font-size: 1.6rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        p.subtitle { color: #94a3b8; font-size: 0.9rem; margin-top: 0.35rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.825rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.45rem; }
        input[type="password"] { width: 100%; background: #0f172a; border: 1px solid #334155; border-radius: 0.625rem; padding: 0.75rem 1rem; font-size: 0.95rem; color: #ffffff; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
        input[type="password"]:focus { border-color: #38bdf8; box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2); }
        .btn-submit { width: 100%; background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; border: none; border-radius: 0.625rem; padding: 0.85rem; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: opacity 0.2s; margin-top: 0.5rem; }
        .btn-submit:hover { opacity: 0.95; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.85rem; margin-bottom: 1.25rem; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.85rem; margin-bottom: 1.25rem; }
        .helper-text { font-size: 0.8rem; color: #64748b; margin-top: 0.4rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="badge">Step 4 of 5 • Security Setup</div>
            <h1>Set Permanent Password</h1>
            <p class="subtitle">Please replace your temporary password with a secure, permanent password.</p>
        </div>

        @if (session('success'))
            <div class="alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('force-password-change.submit') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="password">New Permanent Password</label>
                <input type="password" id="password" name="password" required autofocus autocomplete="new-password" placeholder="At least 8 characters">
                <div class="helper-text">Must contain at least 8 characters.</div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" placeholder="Re-type new password">
            </div>

            <button type="submit" class="btn-submit">Save Password & Continue →</button>
        </form>
    </div>
</body>
</html>
