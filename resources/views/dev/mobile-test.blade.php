<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DxClinic Mobile Test & Quick Access Aid</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen font-sans p-4 sm:p-6 flex flex-col items-center justify-center">

    <div class="max-w-md w-full bg-slate-800/90 border border-slate-700/80 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md">
        <!-- Badge -->
        <div class="flex items-center justify-between mb-4">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold tracking-wider uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                Development Only • Rev 21
            </span>
            <span class="text-xs text-slate-400 font-mono">192.168.1.2:8000</span>
        </div>

        <h1 class="text-2xl font-black text-white tracking-tight mb-1">DxClinic Mobile Test</h1>
        <p class="text-xs text-slate-400 mb-6">Quick LAN navigation aid & QR code for physical Android testing.</p>

        <!-- QR Code Card -->
        <div class="bg-white p-5 rounded-2xl flex flex-col items-center justify-center mb-6 shadow-md text-slate-900">
            <!-- Inline SVG QR Code representation of http://192.168.1.2:8000 -->
            <div class="w-48 h-48 bg-slate-50 border-2 border-slate-200 rounded-xl flex flex-col items-center justify-center p-2 text-center">
                <svg class="w-36 h-36 text-slate-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <rect x="2" y="2" width="7" height="7" rx="1.5"></rect>
                    <rect x="15" y="2" width="7" height="7" rx="1.5"></rect>
                    <rect x="2" y="15" width="7" height="7" rx="1.5"></rect>
                    <path d="M5.5 5.5h.01M18.5 5.5h.01M5.5 18.5h.01"></path>
                    <path d="M15 15h2v2h-2zM19 15h2v2h-2zM15 19h2v2h-2zM19 19h2v2h-2zM11 5h2v6h-2zM5 11h6v2H5zM11 15h2v4h-2z"></path>
                </svg>
                <span class="text-[10px] font-bold text-slate-600 mt-1 uppercase tracking-wider">Scan for LAN URL</span>
            </div>
            <p class="text-xs font-mono font-bold text-slate-800 mt-3">http://192.168.1.2:8000</p>
        </div>

        <!-- Quick Access Target Links -->
        <div class="space-y-2.5">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider px-1">Test Target Endpoints</h2>

            <a href="{{ url('/book') }}" 
               class="flex items-center justify-between p-3.5 rounded-xl bg-slate-700/60 hover:bg-teal-600/30 border border-slate-600/60 hover:border-teal-400/50 transition group">
                <div class="flex items-center gap-3">
                    <span class="text-xl">📱</span>
                    <div>
                        <div class="text-sm font-bold text-white group-hover:text-teal-300 transition">PWA Patient Self-Booking</div>
                        <div class="text-[11px] text-slate-400 font-mono">/book</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-teal-300 font-bold">&rarr;</span>
            </a>

            <a href="{{ url('/login') }}" 
               class="flex items-center justify-between p-3.5 rounded-xl bg-slate-700/60 hover:bg-blue-600/30 border border-slate-600/60 hover:border-blue-400/50 transition group">
                <div class="flex items-center gap-3">
                    <span class="text-xl">🔑</span>
                    <div>
                        <div class="text-sm font-bold text-white group-hover:text-blue-300 transition">Staff Login Portal</div>
                        <div class="text-[11px] text-slate-400 font-mono">/login</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-blue-300 font-bold">&rarr;</span>
            </a>

            <a href="{{ url('/operator/queue') }}" 
               class="flex items-center justify-between p-3.5 rounded-xl bg-slate-700/60 hover:bg-indigo-600/30 border border-slate-600/60 hover:border-indigo-400/50 transition group">
                <div class="flex items-center gap-3">
                    <span class="text-xl">📋</span>
                    <div>
                        <div class="text-sm font-bold text-white group-hover:text-indigo-300 transition">OPD Arrival Queue</div>
                        <div class="text-[11px] text-slate-400 font-mono">/operator/queue</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-indigo-300 font-bold">&rarr;</span>
            </a>

            <a href="{{ url('/cash/sessions') }}" 
               class="flex items-center justify-between p-3.5 rounded-xl bg-slate-700/60 hover:bg-emerald-600/30 border border-slate-600/60 hover:border-emerald-400/50 transition group">
                <div class="flex items-center gap-3">
                    <span class="text-xl">💵</span>
                    <div>
                        <div class="text-sm font-bold text-white group-hover:text-emerald-300 transition">Cash Counter & Sessions</div>
                        <div class="text-[11px] text-slate-400 font-mono">/cash/sessions</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-emerald-300 font-bold">&rarr;</span>
            </a>

            <div class="grid grid-cols-3 gap-2 pt-2">
                <a href="{{ url('/about') }}" class="p-2.5 rounded-lg bg-slate-700/40 hover:bg-slate-700 text-center text-xs font-semibold text-slate-300 border border-slate-600/40">
                    ℹ️ About
                </a>
                <a href="{{ url('/privacy-policy') }}" class="p-2.5 rounded-lg bg-slate-700/40 hover:bg-slate-700 text-center text-xs font-semibold text-slate-300 border border-slate-600/40">
                    🛡️ Privacy
                </a>
                <a href="{{ url('/terms-and-conditions') }}" class="p-2.5 rounded-lg bg-slate-700/40 hover:bg-slate-700 text-center text-xs font-semibold text-slate-300 border border-slate-600/40">
                    📄 Terms
                </a>
            </div>
        </div>

        <!-- Footer Notice -->
        <div class="mt-6 pt-4 border-t border-slate-700/80 text-center text-[11px] text-slate-500">
            <p>Authorized Development Server: <strong class="text-slate-400">192.168.1.2</strong></p>
            <p class="mt-1">All rights reserved &copy; 2026 Nexora Solutions • Kundan Kumar</p>
        </div>
    </div>

</body>
</html>
