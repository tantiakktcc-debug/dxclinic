<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Sign In — DxClinic Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #090d16; color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: 1.25rem; max-width: 440px; width: 100%; padding: 2.75rem 2.25rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); }
        .logo-wrap { text-align: center; margin-bottom: 2rem; }
        .logo-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.25); color: #38bdf8; padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; }
        h1 { font-size: 1.75rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        p.subtitle { color: #94a3b8; font-size: 0.9rem; margin-top: 0.35rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.825rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.45rem; }
        input[type="email"], input[type="password"] { width: 100%; background: #1e293b; border: 1px solid #334155; border-radius: 0.625rem; padding: 0.75rem 1rem; font-size: 0.95rem; color: #ffffff; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
        input[type="email"]:focus, input[type="password"]:focus { border-color: #38bdf8; box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2); }
        .btn-submit { width: 100%; background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; border: none; border-radius: 0.625rem; padding: 0.85rem; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: transform 0.1s, opacity 0.2s; margin-top: 0.5rem; }
        .btn-submit:hover { opacity: 0.95; }
        .btn-submit:active { transform: scale(0.99); }
        .alert-error { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.85rem; margin-bottom: 1.25rem; }
        .footer-note { text-align: center; margin-top: 2rem; font-size: 0.75rem; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-wrap">
            <div class="logo-badge">Platform Developer Access</div>
            <h1>Superadmin Sign In</h1>
            <p class="subtitle">Multi-Tenant Licensing & Fleet Operations</p>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('superadmin.login.submit') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Developer Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', 'superadmin@dxclinic.local') }}" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.825rem; color: #94a3b8; cursor: pointer; margin-bottom: 0;">
                    <input type="checkbox" name="remember" value="1" style="accent-color: #0284c7;"> Remember Session
                </label>
            </div>

            <button type="submit" class="btn-submit">Authenticate to Superadmin</button>
        </form>

        <div class="footer-note">
            DxClinic Multi-Tenant Kernel • Authorized Developers Only
        </div>
    </div>
</body>
</html>
