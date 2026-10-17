<div class="space-y-8">
    <!-- Dashboard Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                Welcome, {{ $doctor ? $doctor->display_name : 'Doctor' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Here's what's happening at your clinic today
            </p>
        </div>
        <div class="flex items-center gap-3">
            <div class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 shadow-xs">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>{{ $formattedDate }}</span>
            </div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-200 text-xs font-bold text-blue-700 shadow-xs">
                <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                <span>{{ ucfirst(strtolower($activeSessionType)) }} Session</span>
            </div>
        </div>
    </div>

    <!-- 4 Key Performance Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Today's Patients -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Today's Patients</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-black text-slate-900 tracking-tight">{{ $todayPatientsCount }}</span>
                <span class="text-xs font-semibold text-emerald-600 flex items-center">
                    <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                    active
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">Tokens queued & registered</p>
        </div>

        <!-- Consultation Revenue -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Consultation Revenue</span>
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-black text-slate-900 tracking-tight">&#8377; {{ number_format($consultationRevenue, 2) }}</span>
            </div>
            <p class="text-xs text-slate-400 mt-1">From OPD consultations today</p>
        </div>

        <!-- Diagnostic Revenue -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Diagnostic Revenue</span>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                </div>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-black text-slate-900 tracking-tight">&#8377; {{ number_format($diagnosticRevenue, 2) }}</span>
            </div>
            <p class="text-xs text-slate-400 mt-1">From lab & investigations</p>
        </div>

        <!-- Total Collections -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Collections</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-black text-emerald-600 tracking-tight">&#8377; {{ number_format($totalCollections, 2) }}</span>
            </div>
            <p class="text-xs text-slate-400 mt-1">Cash & Verified UPI settlements</p>
        </div>
    </div>

    <!-- Operational Quick Actions Grid (10 tiles as per reference mockup) -->
    <div>
        <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Quick Operational Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            
            <!-- 1. OPD Queue -->
            <a href="{{ route('operator.queue', ['public_slug' => \App\Services\TenantContext::clinic()?->public_slug ?? 'clinic']) }}" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center relative">
                <kbd class="absolute top-2 right-2 text-[9px] bg-slate-100 text-slate-500 font-mono font-bold px-1.5 py-0.5 rounded border border-slate-200">Alt+Q</kbd>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">OPD Queue</span>
                <span class="text-xs text-slate-400 mt-1">Manage patient queue</span>
            </a>

            <!-- 2. Consultation Billing -->
            <a href="{{ route('operator.consultation') }}" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center relative">
                <kbd class="absolute top-2 right-2 text-[9px] bg-slate-100 text-slate-500 font-mono font-bold px-1.5 py-0.5 rounded border border-slate-200">Alt+O</kbd>
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">Consultation Billing</span>
                <span class="text-xs text-slate-400 mt-1">Create consultation bills</span>
            </a>

            <!-- 3. Diagnostic Billing -->
            <a href="{{ route('operator.diagnostics') }}" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center relative">
                <kbd class="absolute top-2 right-2 text-[9px] bg-slate-100 text-slate-500 font-mono font-bold px-1.5 py-0.5 rounded border border-slate-200">Alt+T</kbd>
                <div class="w-12 h-12 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">Diagnostic Billing</span>
                <span class="text-xs text-slate-400 mt-1">Create diagnostic bills</span>
            </a>

            <!-- 4. Cash Counter -->
            <a href="{{ route('cash.sessions') }}" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center relative">
                <kbd class="absolute top-2 right-2 text-[9px] bg-slate-100 text-slate-500 font-mono font-bold px-1.5 py-0.5 rounded border border-slate-200">Alt+C</kbd>
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">Cash Counter</span>
                <span class="text-xs text-slate-400 mt-1">Manage cash sessions</span>
            </a>

            <!-- 5. Daily Report -->
            <a href="{{ route('cash.daily-report') }}" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center">
                <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">Daily Report</span>
                <span class="text-xs text-slate-400 mt-1">View reports & analytics</span>
            </a>

            <!-- 6. Patients -->
            <a href="{{ route('patients') }}" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center relative">
                <kbd class="absolute top-2 right-2 text-[9px] bg-slate-100 text-slate-500 font-mono font-bold px-1.5 py-0.5 rounded border border-slate-200">Alt+P</kbd>
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">Patients</span>
                <span class="text-xs text-slate-400 mt-1">Manage patient records</span>
            </a>

            <!-- 7. Appointments -->
            <a href="{{ route('pwa.book') }}" target="_blank" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center">
                <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">Appointments</span>
                <span class="text-xs text-slate-400 mt-1">Schedule & manage</span>
            </a>

            <!-- 8. Chatbot Booking -->
            <a href="{{ route('pwa.book') }}" target="_blank" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center">
                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">Chatbot Booking</span>
                <span class="text-xs text-slate-400 mt-1">Online patient booking</span>
            </a>

            <!-- 9. Print Templates -->
            <a href="{{ route('settings.prescription-pad-templates') }}" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">Print Templates</span>
                <span class="text-xs text-slate-400 mt-1">Token, Receipt, Pad</span>
            </a>

            <!-- 10. Settings -->
            <a href="{{ route('settings.clinic-doctor') }}" class="group bg-white rounded-xl border border-slate-200/90 p-4 text-center hover:border-blue-500 hover:shadow-md transition flex flex-col items-center">
                <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center group-hover:scale-105 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <span class="mt-3 font-bold text-sm text-slate-800 group-hover:text-blue-600">Settings</span>
                <span class="text-xs text-slate-400 mt-1">Clinic, Doctor, System</span>
            </a>

        </div>
    </div>

    <!-- Active Session Overview Strip -->
    <div class="bg-gradient-to-r from-blue-900 to-slate-900 rounded-2xl p-6 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 rounded bg-blue-800/60 text-blue-300 text-xs font-bold uppercase tracking-wider mb-2">
                    Current Cash Counter Status
                </span>
                <h3 class="text-xl font-bold">
                    @if($activeCashSession)
                        {{ ucfirst(strtolower($activeCashSession->session_type)) }} Session is OPEN
                    @else
                        No Active Cash Session Open for Today
                    @endif
                </h3>
                <p class="text-slate-300 text-xs mt-1">
                    @if($activeCashSession)
                        Opening Physical Cash: &#8377; {{ number_format($activeCashSession->opening_physical_cash, 2) }} &bull; Cash Receipts: &#8377; {{ number_format($activeCashSession->actual_cash_receipts, 2) }}
                    @else
                        Open the morning session to begin recording receipts, expenses, and token check-ins.
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('cash.sessions') }}" class="bg-white hover:bg-slate-100 text-slate-900 font-bold px-4 py-2.5 rounded-xl text-sm transition shadow-sm">
                    {{ $activeCashSession ? 'Manage Cash Session' : 'Start Session' }} &rarr;
                </a>
            </div>
        </div>
    </div>
</div>
