<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign In — DxClinic</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gradient-to-b from-slate-50 via-slate-100/60 to-slate-100 min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    
    <div class="sm:mx-auto sm:w-full sm:max-w-md px-4">
        <!-- DxClinic Brand Header -->
        <div class="text-center">
            <div class="inline-flex h-14 w-14 rounded-2xl bg-blue-600 items-center justify-center font-black text-white text-2xl shadow-md ring-4 ring-blue-100">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path>
                </svg>
            </div>
            <h1 class="mt-4 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">DxClinic</h1>
            <p class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Clinical Management & OPD System</p>
        </div>

        <!-- Login Card -->
        <div class="mt-8 bg-white py-8 px-6 shadow-sm border border-slate-200/80 rounded-3xl sm:px-10">
            @if (session('status'))
                <div class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs font-medium">
                    <div class="font-bold mb-1">Authentication Error</div>
                    <ul class="list-disc pl-4 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="space-y-4" id="loginForm">
                @csrf

                <!-- Step 1: Clinic Identification -->
                <div>
                    <label for="clinic_code" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                        Clinic ID *
                    </label>
                    <input type="text" name="clinic_code" id="clinic_code" value="{{ old('clinic_code', 'NX0001') }}" required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-mono font-bold uppercase text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 placeholder:text-slate-400"
                           placeholder="NX0001" oninput="lookupClinic(this.value)">
                    <div id="clinic_badge" class="mt-1.5 flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        <span id="clinic_name_display">{{ $defaultClinic ? $defaultClinic->name . ', ' . $defaultClinic->city : 'Sharma Care Clinic, Varanasi' }}</span>
                    </div>
                </div>

                <!-- Step 2: User Identification -->
                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                        User ID / Email *
                    </label>
                    <input type="text" name="email" id="email" value="{{ old('email') }}" required autofocus
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 placeholder:text-slate-400"
                           placeholder="ADM0001 or staff@dxclinic.local">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Password *
                        </label>
                    </div>
                    <input type="password" name="password" id="password" required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 placeholder:text-slate-400"
                           placeholder="••••••••••••">
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-600">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-blue-500">
                        <span>Remember me</span>
                    </label>
                </div>

                <div class="pt-2">
                    <button type="submit" id="btn_submit_login"
                            class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-sm text-sm transition-all focus:ring-4 focus:ring-blue-100">
                        Sign In to Clinic Portal &rarr;
                    </button>
                </div>
            </form>

            <!-- Quick Development Test Fill Buttons (Gated for non-production per Section 14, 52) -->
            @if (config('app.env') !== 'production')
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider text-center mb-3">
                        Local Development Quick Fill (Revision 20)
                    </span>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" onclick="fillCreds('admin@dxclinic.local', 'DxClinic@Admin2026')"
                                class="py-2 px-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-blue-50 hover:border-blue-300 text-slate-700 hover:text-blue-700 text-[11px] font-bold transition text-center">
                            🔑 Admin
                        </button>
                        <button type="button" onclick="fillCreds('operator@dxclinic.local', 'DxClinic@Op2026')"
                                class="py-2 px-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-teal-50 hover:border-teal-300 text-slate-700 hover:text-teal-700 text-[11px] font-bold transition text-center">
                            💼 Operator
                        </button>
                        <button type="button" onclick="fillCreds('doctor@dxclinic.local', 'DxClinic@Doc2026')"
                                class="py-2 px-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-300 text-slate-700 hover:text-indigo-700 text-[11px] font-bold transition text-center">
                            👨‍⚕️ Doctor
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <!-- External Public Links & Legal Footer -->
        <div class="mt-6 text-center space-y-3">
            @php
                $loginPwaSlug = null;
                try {
                    $demoOrFirst = \App\Models\Clinic::where('is_demo', true)->where('demo_status', '!=', 'PAUSED')->first()
                        ?? \App\Models\Clinic::where('is_active', true)->where('is_demo', false)->first()
                        ?? \App\Models\Clinic::first();
                    $loginPwaSlug = $demoOrFirst?->public_slug;
                } catch (\Throwable $e) {}
            @endphp
            @if ($loginPwaSlug)
            <a href="{{ route('pwa.book', ['public_slug' => $loginPwaSlug]) }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800 transition">
                <span>📱 Open Public Patient Self-Booking PWA</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            </a>
            @endif
            
            <div class="flex items-center justify-center gap-3 text-[11px] text-slate-500 font-medium">
                <a href="{{ route('about') }}" class="hover:text-blue-600 transition">About Developer</a>
                <span>&bull;</span>
                <a href="{{ route('privacy.policy') }}" class="hover:text-blue-600 transition">Privacy Policy</a>
                <span>&bull;</span>
                <a href="{{ route('terms.conditions') }}" class="hover:text-blue-600 transition">Terms & Conditions</a>
            </div>

            <p class="text-[11px] text-slate-400">
                &copy; 2026 <strong class="text-slate-600">Nexora Solutions</strong>. Developed by <strong class="text-slate-600">Kundan Kumar</strong>. Licensed under GNU AGPL-3.0.
            </p>
        </div>
    </div>

    <script>
        function fillCreds(email, password, clinicCode) {
            if (clinicCode) {
                document.getElementById('clinic_code').value = clinicCode;
                lookupClinic(clinicCode);
            }
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }

        let lookupTimeout = null;
        function lookupClinic(code) {
            clearTimeout(lookupTimeout);
            code = (code || '').trim().toUpperCase();
            if (code.length < 2) return;

            lookupTimeout = setTimeout(() => {
                fetch('/api/clinic-lookup?code=' + encodeURIComponent(code))
                    .then(res => res.json())
                    .then(data => {
                        const badge = document.getElementById('clinic_badge');
                        const text = document.getElementById('clinic_name_display');
                        if (data.found) {
                            badge.className = 'mt-1.5 flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200';
                            text.innerText = data.name + (data.city ? ', ' + data.city : '');
                        } else {
                            badge.className = 'mt-1.5 flex items-center gap-1.5 text-xs font-semibold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200';
                            text.innerText = 'Clinic ID not recognized';
                        }
                    })
                    .catch(() => {});
            }, 250);
        }
    </script>
</body>
</html>
