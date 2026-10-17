@extends('components.layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <!-- Breadcrumb / Header -->
    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">About DxClinic & Developer Information</h1>
            <p class="text-xs text-slate-500 mt-0.5">Authoritative ownership, brand architecture, and verified developer contact channels.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold">
            &larr; Return to Dashboard
        </a>
    </div>

    <!-- Application Identity Card -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-black text-2xl shadow-md">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-900">DxClinic — Clinical Management Suite</h2>
                <span class="text-xs font-semibold text-blue-700 bg-blue-50 px-2.5 py-0.5 rounded-full border border-blue-200">Enterprise Edition v19</span>
                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                    Local-first, zero-telemetry clinic operations system engineered for OPD queue management, consultation & diagnostic billing, physical Soundbox verification, and mathematical cash reconciliation.
                </p>
            </div>
        </div>
    </div>

    <!-- Verified Developer & Authenticated User Section (Section 6, 7, 55) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 to-indigo-800 px-6 py-4 text-white">
            <h3 class="text-xs font-bold uppercase tracking-wider text-blue-100">Verified Source of Truth</h3>
            <h2 class="text-lg font-black tracking-tight">Developer & Architecture Leadership</h2>
        </div>

        <div class="p-6 space-y-6">
            <!-- Developer Identity Card -->
            <div class="flex items-center gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
                <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">{{ $developerName }}</h3>
                    <p class="text-xs font-semibold text-blue-600">{{ $developerRole }}</p>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">Brand & Company: <strong class="text-slate-800">{{ $company }}</strong></p>
                </div>
            </div>

            <!-- Verified Contact Matrix -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Phone -->
                <div class="p-4 rounded-xl border border-slate-200 bg-white hover:border-blue-300 transition">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        <span>Direct Support Phone</span>
                    </div>
                    <a href="tel:{{ str_replace(' ', '', $phone) }}" class="text-sm font-bold text-blue-700 hover:underline block mt-1">
                        {{ $phone }}
                    </a>
                </div>

                <!-- Email -->
                <div class="p-4 rounded-xl border border-slate-200 bg-white hover:border-blue-300 transition">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <span>Official Support Email</span>
                    </div>
                    <a href="mailto:{{ $email }}" class="text-sm font-bold text-blue-700 hover:underline block mt-1 truncate">
                        {{ $email }}
                    </a>
                </div>

                <!-- Website -->
                <div class="p-4 rounded-xl border border-slate-200 bg-white hover:border-blue-300 transition">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                        <span>Official Website</span>
                    </div>
                    <a href="{{ $website }}" target="_blank" rel="noopener noreferrer" class="text-sm font-bold text-blue-700 hover:underline block mt-1 flex items-center gap-1">
                        <span>{{ str_replace('http://', '', $website) }}</span>
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Legal & Operational Policies Navigation Card -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-4">
        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Legal Disclosures & Policies</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="{{ route('privacy.policy') }}" class="p-4 rounded-xl border border-slate-200 hover:bg-slate-50 transition flex items-center justify-between group">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 group-hover:text-blue-700 transition">Privacy Policy</h4>
                        <p class="text-xs text-slate-500">Patient data processing & zero-telemetry guarantee</p>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-blue-700 text-lg">&rarr;</span>
            </a>

            <a href="{{ route('terms.conditions') }}" class="p-4 rounded-xl border border-slate-200 hover:bg-slate-50 transition flex items-center justify-between group">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 group-hover:text-blue-700 transition">Terms & Conditions</h4>
                        <p class="text-xs text-slate-500">Clinical operations, token rules & liability boundaries</p>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-blue-700 text-lg">&rarr;</span>
            </a>
        </div>

        <div class="pt-2 text-center text-xs text-slate-500">
            <span>Official Corporate Portal: </span>
            <a href="{{ $website }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 font-bold hover:underline">{{ $website }}</a>
        </div>
    </div>

    <!-- Verified Legal Ownership Notice (Section 8) -->
    <div class="text-center py-4 text-xs text-slate-500">
        <p class="font-semibold">{{ $copyright }}</p>
        <p class="text-[11px] text-slate-400 mt-0.5">Authoritative ownership established across Nexora PDF Suite, Nexora Network Tools, and DxClinic.</p>
    </div>
</div>
@endsection
