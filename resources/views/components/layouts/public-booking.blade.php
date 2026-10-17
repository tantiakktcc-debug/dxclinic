<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'DxClinic') }} {{ isset($title) ? '- ' . $title : '- Online OPD Token Booking' }}</title>

    <!-- PWA & Mobile Web Capabilities -->
    <meta name="theme-color" content="#0d9488">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DxClinic Token">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-slate-100 text-slate-800 min-h-full flex flex-col justify-between">

    <!-- Minimalist Public Patient Header (Zero authenticated navigation) -->
    <header class="bg-white border-b border-slate-200 py-3.5 px-4 sm:px-6 shadow-2xs sticky top-0 z-20">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="h-9 w-9 rounded-xl bg-teal-600 flex items-center justify-center font-black text-white text-lg tracking-wider shadow-sm">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                </div>
                <div>
                    <span class="text-base font-black tracking-tight text-slate-900 block leading-tight">{{ \App\Services\TenantContext::getClinic()?->name ?? 'DxClinic' }}</span>
                    <span class="text-[10px] text-teal-700 font-semibold uppercase tracking-wider block">OPD Token Booking</span>
                </div>
            </div>
            <div class="flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[11px] font-bold text-emerald-800">Booking Open</span>
            </div>
        </div>
    </header>

    <!-- Main Public Content (Scoped Patient Booking Form) -->
    <main class="flex-1 py-6 px-3 sm:px-6">
        <div class="max-w-2xl mx-auto">
            {{ $slot }}
        </div>
    </main>

    <!-- Public Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 px-4 text-center text-xs text-slate-400">
        <div class="max-w-2xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>&copy; {{ date('Y') }} {{ \App\Services\TenantContext::getClinic()?->name ?? 'DxClinic' }}. All rights reserved.</span>
            <span class="text-[11px] text-slate-500 font-medium">Verified OPD Clinic System</span>
        </div>
    </footer>

    <script src="{{ asset('js/dx-shortcuts.js') }}"></script>
    @livewireScripts
</body>
</html>
