<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Environment Temporarily Unavailable — DxClinic</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background-color: #f8fafc;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            max-width: 520px;
            width: 100%;
            padding: 2.5rem;
            text-align: center;
        }
        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: #fef3c7;
            color: #d97706;
            margin-bottom: 1.5rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }
        p {
            font-size: 1rem;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 9999px;
            background-color: #f1f5f9;
            color: #475569;
            margin-bottom: 1rem;
        }
        .button {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }
        .button:hover {
            background-color: #0369a1;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrapper">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="6" y="4" width="4" height="16"></rect>
                <rect x="14" y="4" width="4" height="16"></rect>
            </svg>
        </div>
        <div>
            <span class="badge">DxClinic Demo Environment</span>
        </div>
        <h1>Demonstration Paused</h1>
        <p>The DxClinic demonstration environment is temporarily unavailable. Please try again later.</p>
        <a href="/" class="button">Return to DxClinic</a>
    </div>
</body>
</html>
