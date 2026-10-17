<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'DxClinic') }} {{ isset($title) ? '- ' . $title : '' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-800 h-full flex flex-col overflow-hidden" 
      x-data="{ 
          sidebarOpen: false, 
          appNavCollapsed: (function() {
              try {
                  return localStorage.getItem('dxclinic_sidebar_collapsed') === 'true';
              } catch(e) {
                  return false;
              }
          })(),
          toggleSidebar() {
              this.appNavCollapsed = !this.appNavCollapsed;
              try {
                  localStorage.setItem('dxclinic_sidebar_collapsed', this.appNavCollapsed ? 'true' : 'false');
              } catch(e) {}
          }
      }" 
      @toggle-app-nav.window="toggleSidebar()"
      @keydown.escape.window="sidebarOpen = false"
      @keydown.window="if ($event.altKey && ($event.key === 's' || $event.key === 'S')) { toggleSidebar(); $event.preventDefault(); }">

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 h-16 shrink-0 z-30 sticky top-0 px-4 sm:px-6 flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-3">
            <!-- Mobile Menu Toggle Button -->
            <button type="button" @click.stop="sidebarOpen = !sidebarOpen" id="btn_open_mobile_sidebar" aria-label="Open menu" class="md:hidden text-slate-600 hover:text-slate-900 p-2 rounded-lg hover:bg-slate-100 cursor-pointer">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>

            <!-- Desktop Sidebar Hide / Unhide Toggle Button (Requirement C1-C3) -->
            <button type="button"
                    id="btn_toggle_desktop_sidebar"
                    @click="toggleSidebar()"
                    :aria-expanded="!appNavCollapsed"
                    aria-controls="appNavigationSidebar"
                    :aria-label="appNavCollapsed ? 'Show Sidebar' : 'Hide Sidebar'"
                    :title="appNavCollapsed ? 'Show / Expand Sidebar (Alt+S)' : 'Hide / Collapse Sidebar (Alt+S)'"
                    class="hidden md:inline-flex items-center justify-center p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors border border-slate-200 cursor-pointer">
                <!-- Icon when expanded (click to collapse/hide) -->
                <svg x-show="!appNavCollapsed" class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                </svg>
                <!-- Icon when collapsed (click to expand/show) -->
                <svg x-show="appNavCollapsed" class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                </svg>
            </button>

            <!-- Brand / Logo (Cross Icon + DxClinic) -->
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                <div class="h-9 w-9 rounded-xl bg-blue-600 flex items-center justify-center font-black text-white text-lg tracking-wider shadow-sm">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                </div>
                <div class="leading-none" x-show="!appNavCollapsed">
                    <span class="text-xl font-black tracking-tight text-slate-900">DxClinic</span>
                    <span class="text-[10px] block text-slate-400 font-semibold uppercase tracking-wider mt-0.5">Clinical Management</span>
                </div>
            </a>
        </div>

        <!-- Center Date & Session Pill -->
        <div class="hidden lg:flex items-center gap-2.5">
            <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-full border border-slate-200">
                {{ now()->format('l, d M Y') }}
            </span>
            <span class="text-xs font-bold text-blue-700 bg-blue-50 px-3 py-1.5 rounded-full border border-blue-200 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                OPD Active
            </span>
        </div>

        <!-- Right Side: Doctor Profile & Actions -->
        <div class="flex items-center gap-3">
            <button type="button" id="btn_open_shortcuts_modal" onclick="window.DxShortcuts ? window.DxShortcuts.openModal() : null" title="Keyboard Shortcuts (F1 or ?)" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 border border-slate-200 px-2.5 py-1.5 rounded-lg transition cursor-pointer">
                <span>⌨</span>
                <span>Shortcuts</span>
                <kbd class="text-[10px] bg-white border border-slate-300 rounded px-1 text-slate-500 font-mono font-bold">F1</kbd>
            </button>

            @php
                $authoritativeClinic = null;
                if (auth()->check()) {
                    $authUser = auth()->user();
                    if ($authUser->role === 'SUPERADMIN') {
                        $authoritativeClinic = \App\Services\TenantContext::clinic() ?? $authUser->clinic;
                    } else {
                        $authoritativeClinic = $authUser->clinic ?? ($authUser->clinic_id ? \App\Models\Clinic::find($authUser->clinic_id) : null) ?? $currentClinic;
                    }
                } else {
                    $authoritativeClinic = $currentClinic ?? null;
                }
                $pwaSlug = $authoritativeClinic?->public_slug;
            @endphp
            @if ($pwaSlug)
            <a href="{{ route('pwa.book', ['public_slug' => $pwaSlug]) }}" target="_blank" class="hidden sm:inline-flex items-center gap-1.5 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-lg transition">
                <span>📱 Patient PWA</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            </a>
            @php
                $liveQueueTarget = route('queue.live', ['public_slug' => $pwaSlug]);
            @endphp
            <button type="button" 
                    id="btn_open_live_queue_window"
                    onclick="window.open('{{ $liveQueueTarget }}', 'DxClinicLiveQueue', 'width=1280,height=800,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes')" 
                    title="Open Live Queue Monitor in a separate window"
                    class="hidden sm:inline-flex items-center gap-1.5 text-xs font-black text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 px-3 py-1.5 rounded-lg shadow-2xs transition cursor-pointer">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Open Live Queue</span>
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            </button>
            @endif

            <!-- User Profile & Authentication Status -->
            <div class="flex items-center gap-2.5 pl-3 border-l border-slate-200">
                @if (auth()->check())
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center font-black text-blue-700 text-xs shadow-inner">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="hidden sm:block text-left leading-tight">
                        <span class="text-xs font-bold text-slate-800 block">{{ auth()->user()->name }}</span>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-black uppercase tracking-wider {{ auth()->user()->isAdmin() ? 'bg-purple-100 text-purple-700' : (auth()->user()->isOperator() ? 'bg-teal-100 text-teal-800' : 'bg-blue-100 text-blue-800') }}">
                                {{ auth()->user()->role ?? 'ADMIN' }}
                            </span>
                            <a href="{{ route('logout') }}" class="text-[10px] text-slate-400 hover:text-rose-600 underline font-semibold">Sign Out</a>
                        </div>
                    </div>
                @else
                    <div class="w-8 h-8 rounded-full bg-slate-200 border border-slate-300 flex items-center justify-center font-bold text-slate-700 text-xs shadow-inner">
                        👨‍⚕️
                    </div>
                    <div class="hidden sm:block text-left leading-tight">
                        <span class="text-xs font-bold text-slate-800 block">DxClinic Staff</span>
                        <a href="{{ route('login') }}" class="text-[10px] text-blue-600 hover:underline font-bold block">Sign In &rarr;</a>
                    </div>
                @endif
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden relative">
        <!-- Mobile Sidebar Backdrop Overlay (Revision 32 Phase 16 & 17) -->
        <div x-show="sidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-200" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="transition-opacity ease-linear duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             @click="sidebarOpen = false" 
             id="mobile_sidebar_backdrop"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-35 md:hidden"
             style="display: none;"></div>

        <!-- Sidebar Navigation (Revision 27 Clean Redesign + Revision 32 Mobile Controls + Requirement C Rail) -->
        <aside id="appNavigationSidebar"
               :class="(sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0') + (appNavCollapsed ? ' md:!w-16' : ' md:w-64')" 
               @click.outside="if (sidebarOpen && window.innerWidth < 768) sidebarOpen = false"
               x-data="{ 
                   reportsOpen: {{ request()->routeIs('reports.*', 'cash.daily-report') ? 'true' : 'false' }},
                   helpOpen: {{ request()->routeIs('help', 'about') ? 'true' : 'false' }},
                   settingsOpen: {{ request()->routeIs('settings.*') ? 'true' : 'false' }}
               }"
               class="fixed md:static inset-y-0 left-0 w-64 bg-white border-r border-slate-200 flex flex-col justify-between shrink-0 z-40 transition-all duration-200 ease-in-out md:translate-x-0 shadow-xl md:shadow-none">
            
            <!-- Mobile Menu Header with Close Button (Revision 32 Phase 16) -->
            <div class="md:hidden flex items-center justify-between px-4 py-3.5 border-b border-slate-200 bg-slate-50 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="h-8 w-8 rounded-xl bg-blue-600 flex items-center justify-center font-black text-white text-base shadow-sm">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                    </div>
                    <div>
                        <span class="text-base font-black tracking-tight text-slate-900 block leading-tight">DxClinic</span>
                        <span class="text-[9px] uppercase font-bold text-slate-400">Navigation</span>
                    </div>
                </div>
                <button type="button" @click="sidebarOpen = false" id="btn_close_mobile_sidebar" aria-label="Close menu" class="p-2 text-slate-500 hover:text-slate-900 rounded-lg hover:bg-slate-200 text-lg font-bold flex items-center justify-center transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="py-4 space-y-1 overflow-y-auto flex-1 transition-all duration-150" :class="appNavCollapsed ? 'px-2' : 'px-3'" @click="if (window.innerWidth < 768 && $event.target.closest('a')) sidebarOpen = false">
                @php
                    $userRole = auth()->check() ? strtoupper(auth()->user()->role ?? 'ADMIN') : 'ADMIN';
                @endphp

                <!-- ==============================================
                     1. FREQUENT DAILY WORK (DIRECTLY VISIBLE)
                     ============================================== -->
                <div x-show="!appNavCollapsed" class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mb-1.5">Daily Work</div>

                <!-- Dashboard (All Roles) - Requirement C6: Identifiable in collapsed rail -->
                <a href="{{ route('dashboard') }}" 
                   id="nav_item_dashboard"
                   title="Dashboard (Alt+D)"
                   class="group flex items-center rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700 border border-blue-200/60 font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                   :class="appNavCollapsed ? 'justify-center !px-2 py-2.5' : 'justify-between px-3 py-2'">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0 text-slate-500 {{ request()->routeIs('dashboard') ? '!text-blue-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        <span x-show="!appNavCollapsed">Dashboard</span>
                    </div>
                    <kbd x-show="!appNavCollapsed" class="text-[9px] font-mono text-slate-400 group-hover:text-slate-600 bg-slate-100 px-1 py-0.5 rounded border border-slate-200">Alt+D</kbd>
                </a>

                <!-- Operator Booking (Admin & Operator) -->
                @if ($userRole !== 'DOCTOR')
                    <a href="{{ route('operator.booking') }}" 
                       title="OPD Booking (Alt+B)"
                       class="group flex items-center rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('operator.booking') ? 'bg-teal-50 text-teal-700 border border-teal-200/60 font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                       :class="appNavCollapsed ? 'justify-center !px-2 py-2.5' : 'justify-between px-3 py-2'">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 shrink-0 text-slate-500 {{ request()->routeIs('operator.booking') ? '!text-teal-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                            <span x-show="!appNavCollapsed">OPD Booking</span>
                        </div>
                        <kbd x-show="!appNavCollapsed" class="text-[9px] font-mono text-slate-400 group-hover:text-slate-600 bg-slate-100 px-1 py-0.5 rounded border border-slate-200">Alt+B</kbd>
                    </a>
                @endif

                <!-- Appointments / OPD Queue (All Roles) -->
                <a href="{{ route('operator.queue', ['public_slug' => $pwaSlug ?? 'clinic']) }}" 
                   title="OPD Queue (Alt+Q)"
                   class="group flex items-center rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('operator.queue') ? 'bg-blue-50 text-blue-700 border border-blue-200/60 font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                   :class="appNavCollapsed ? 'justify-center !px-2 py-2.5' : 'justify-between px-3 py-2'">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0 text-slate-500 {{ request()->routeIs('operator.queue') ? '!text-blue-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span x-show="!appNavCollapsed">OPD Queue</span>
                    </div>
                    <kbd x-show="!appNavCollapsed" class="text-[9px] font-mono text-slate-400 group-hover:text-slate-600 bg-slate-100 px-1 py-0.5 rounded border border-slate-200">Alt+Q</kbd>
                </a>

                <!-- WhatsApp Operator Desk (Admin & Operator) -->
                @if ($userRole !== 'DOCTOR')
                    <a href="{{ route('operator.whatsapp-desk') }}" 
                       id="nav_item_whatsapp_desk"
                       title="WhatsApp Desk (Alt+W)"
                       class="group flex items-center rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('operator.whatsapp-desk*') || request()->routeIs('whatsapp.desk*') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60 font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                       :class="appNavCollapsed ? 'justify-center !px-2 py-2.5' : 'justify-between px-3 py-2'">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 shrink-0 text-slate-500 {{ request()->routeIs('operator.whatsapp-desk*') || request()->routeIs('whatsapp.desk*') ? '!text-emerald-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                            <span x-show="!appNavCollapsed">WhatsApp Desk</span>
                        </div>
                        <kbd x-show="!appNavCollapsed" class="text-[9px] font-mono text-slate-400 group-hover:text-slate-600 bg-slate-100 px-1 py-0.5 rounded border border-slate-200">Alt+W</kbd>
                    </a>
                @endif

                <!-- Patients (All Roles) -->
                <a href="{{ route('patients') }}" 
                   title="Patients (Alt+P)"
                   class="group flex items-center rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('patients') ? 'bg-blue-50 text-blue-700 border border-blue-200/60 font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                   :class="appNavCollapsed ? 'justify-center !px-2 py-2.5' : 'justify-between px-3 py-2'">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0 text-slate-500 {{ request()->routeIs('patients') ? '!text-blue-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <span x-show="!appNavCollapsed">Patients</span>
                    </div>
                    <kbd x-show="!appNavCollapsed" class="text-[9px] font-mono text-slate-400 group-hover:text-slate-600 bg-slate-100 px-1 py-0.5 rounded border border-slate-200">Alt+P</kbd>
                </a>

                <!-- Consultation Billing (All Roles) -->
                <a href="{{ route('operator.consultation') }}" 
                   title="Consultation Billing (Alt+O)"
                   class="group flex items-center rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('operator.consultation') ? 'bg-blue-50 text-blue-700 border border-blue-200/60 font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                   :class="appNavCollapsed ? 'justify-center !px-2 py-2.5' : 'justify-between px-3 py-2'">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0 text-slate-500 {{ request()->routeIs('operator.consultation') ? '!text-blue-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span x-show="!appNavCollapsed">Consultation Billing</span>
                    </div>
                    <kbd x-show="!appNavCollapsed" class="text-[9px] font-mono text-slate-400 group-hover:text-slate-600 bg-slate-100 px-1 py-0.5 rounded border border-slate-200">Alt+O</kbd>
                </a>

                <!-- Diagnostic Billing (Admin & Operator) -->
                @if ($userRole !== 'DOCTOR')
                    <a href="{{ route('operator.diagnostics') }}" 
                       title="Diagnostic Billing (Alt+T)"
                       class="group flex items-center rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('operator.diagnostics') ? 'bg-blue-50 text-blue-700 border border-blue-200/60 font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                       :class="appNavCollapsed ? 'justify-center !px-2 py-2.5' : 'justify-between px-3 py-2'">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 shrink-0 text-slate-500 {{ request()->routeIs('operator.diagnostics') ? '!text-blue-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                            <span x-show="!appNavCollapsed">Diagnostic Billing</span>
                        </div>
                        <kbd x-show="!appNavCollapsed" class="text-[9px] font-mono text-slate-400 group-hover:text-slate-600 bg-slate-100 px-1 py-0.5 rounded border border-slate-200">Alt+T</kbd>
                    </a>
                @endif

                <!-- Diagnostic Lab (All Roles - Revision 36) -->
                <a href="{{ route('lab.worklist') }}" 
                   title="Diagnostic Lab (Alt+L)"
                   class="group flex items-center rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('lab.worklist*') ? 'bg-purple-50 text-purple-700 border border-purple-200/60 font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                   :class="appNavCollapsed ? 'justify-center !px-2 py-2.5' : 'justify-between px-3 py-2'">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0 text-slate-500 {{ request()->routeIs('lab.worklist*') ? '!text-purple-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        <span x-show="!appNavCollapsed">Diagnostic Lab</span>
                    </div>
                    <kbd x-show="!appNavCollapsed" class="text-[9px] font-mono text-slate-400 group-hover:text-slate-600 bg-slate-100 px-1 py-0.5 rounded border border-slate-200">Alt+L</kbd>
                </a>

                <!-- Cash Counter (Admin & Operator) -->
                @if ($userRole !== 'DOCTOR')
                    <a href="{{ route('cash.sessions') }}" 
                       title="Cash Counter (Alt+C)"
                       class="group flex items-center rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('cash.sessions') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60 font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                       :class="appNavCollapsed ? 'justify-center !px-2 py-2.5' : 'justify-between px-3 py-2'">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 shrink-0 text-slate-500 {{ request()->routeIs('cash.sessions') ? '!text-emerald-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <span x-show="!appNavCollapsed">Cash Counter</span>
                        </div>
                        <kbd x-show="!appNavCollapsed" class="text-[9px] font-mono text-slate-400 group-hover:text-slate-600 bg-slate-100 px-1 py-0.5 rounded border border-slate-200">Alt+C</kbd>
                    </a>
                @endif

                <!-- ==============================================
                     2. LESS FREQUENT FUNCTIONS (COLLAPSIBLE REPORTS)
                     ============================================== -->
                @if ($userRole !== 'DOCTOR')
                    <div class="pt-2">
                        <button @click="if (appNavCollapsed) { appNavCollapsed = false; localStorage.setItem('dxclinic_sidebar_collapsed', 'false'); } reportsOpen = !reportsOpen" 
                                :title="appNavCollapsed ? 'Reports' : ''"
                                class="w-full flex items-center rounded-xl text-xs font-bold uppercase tracking-wider text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors"
                                :class="appNavCollapsed ? 'justify-center px-1 py-2' : 'justify-between px-3 py-2'">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span x-show="!appNavCollapsed">Reports</span>
                            </span>
                            <svg x-show="!appNavCollapsed" class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="reportsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="!appNavCollapsed && reportsOpen" x-collapse class="pl-2 pr-1 pt-1 space-y-1">
                            <a href="{{ route('reports.opd') }}" 
                               class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('reports.opd') ? 'bg-teal-50 text-teal-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>OPD Reports</span>
                            </a>
                            <a href="{{ route('reports.diagnostics') }}" 
                               class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('reports.diagnostics') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>Diagnostic Reports</span>
                            </a>
                            <a href="{{ route('reports.reconciliation') }}" 
                               class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('reports.reconciliation') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>Reconciliation</span>
                            </a>
                            <a href="{{ route('cash.daily-report') }}" 
                               class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('cash.daily-report') ? 'bg-purple-50 text-purple-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>Daily Cash Report</span>
                            </a>
                        </div>
                    </div>
                @endif

                <!-- ==============================================
                     3. HELP & GUIDE (NEAR BOTTOM, COLLAPSIBLE)
                     ============================================== -->
                <div class="pt-2">
                    <button @click="if (appNavCollapsed) { appNavCollapsed = false; localStorage.setItem('dxclinic_sidebar_collapsed', 'false'); } helpOpen = !helpOpen" 
                            :title="appNavCollapsed ? 'Help & Guide' : ''"
                            class="w-full flex items-center rounded-xl text-xs font-bold uppercase tracking-wider text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors"
                            :class="appNavCollapsed ? 'justify-center px-1 py-2' : 'justify-between px-3 py-2'">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span x-show="!appNavCollapsed">Help & Guide</span>
                        </span>
                        <svg x-show="!appNavCollapsed" class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="helpOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="!appNavCollapsed && helpOpen" x-collapse class="pl-2 pr-1 pt-1 space-y-1">
                        <a href="{{ route('help', 'faq') }}" 
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('help') && request()->route('tab') === 'faq' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            <span>FAQ</span>
                        </a>
                        <a href="{{ route('help', 'manual') }}" 
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('help') && request()->route('tab') === 'manual' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            <span>User Manual</span>
                        </a>
                        <a href="{{ route('help', 'quick') }}" 
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('help') && request()->route('tab') === 'quick' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            <span>Quick Guide</span>
                        </a>
                        <a href="{{ route('about') }}" 
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('about') ? 'bg-slate-100 text-slate-900 font-bold' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800' }}">
                            <span>About System</span>
                        </a>
                    </div>
                </div>

                <!-- ==============================================
                     4. SETTINGS (COLLAPSED AT BOTTOM, ADMIN ONLY)
                     ============================================== -->
                @if ($userRole === 'ADMIN')
                    <div class="pt-2">
                        <button @click="if (appNavCollapsed) { appNavCollapsed = false; localStorage.setItem('dxclinic_sidebar_collapsed', 'false'); } settingsOpen = !settingsOpen" 
                                :title="appNavCollapsed ? 'Settings' : ''"
                                class="w-full flex items-center rounded-xl text-xs font-bold uppercase tracking-wider text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors"
                                :class="appNavCollapsed ? 'justify-center px-1 py-2' : 'justify-between px-3 py-2'">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <span x-show="!appNavCollapsed">Settings</span>
                            </span>
                            <svg x-show="!appNavCollapsed" class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="settingsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="!appNavCollapsed && settingsOpen" x-collapse class="pl-2 pr-1 pt-1 space-y-1">
                            <a href="{{ route('settings.clinic-doctor') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.clinic-doctor') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>🏥 Clinic & Doctor</span>
                            </a>
                            <a href="{{ route('settings.subscription') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.subscription') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>💳 License & Subscription</span>
                            </a>
                            <a href="{{ route('settings.prescription-pad-templates') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.prescription-pad*') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>📝 Prescription Pad Designer</span>
                            </a>
                            <a href="{{ route('settings.fee-master') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.fee-master') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>🏷️ Fee Master</span>
                            </a>
                            <a href="{{ route('settings.booking-types') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.booking-types*') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>📅 Booking Types</span>
                            </a>
                            <a href="{{ route('settings.vital-master') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.vital-master') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>🩺 Vital Master</span>
                            </a>
                            <a href="{{ route('settings.users') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.users') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>👥 Users & Operators</span>
                            </a>
                            <a href="{{ route('settings.branding') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.branding') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>🖨️ Print & Branding</span>
                            </a>
                            <a href="{{ route('settings.bank-accounts') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.bank-accounts') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>🏦 Bank Accounts</span>
                            </a>
                            <a href="{{ route('settings.expense-categories') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.expense-categories') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>📑 Expense Categories</span>
                            </a>
                            <a href="{{ route('settings.booking-qr') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.booking-qr') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>📱 Online Booking / QR</span>
                            </a>
                            <a href="{{ route('settings.backup') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.backup') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>💾 Backup & Restore</span>
                            </a>
                            <a href="{{ route('settings.onboarding') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.onboarding') ? 'bg-amber-50 text-amber-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>📋 Onboarding Wizard</span>
                            </a>
                            <a href="{{ route('settings.whatsapp') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('settings.whatsapp') ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span>💬 WhatsApp Automation</span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar Bottom Info & Auth State -->
            <div class="p-3 border-t border-slate-200 bg-slate-50/70 space-y-2">
                @if (auth()->check())
                    <div x-show="!appNavCollapsed" class="bg-white rounded-xl p-2.5 border border-slate-200 text-xs flex items-center justify-between shadow-2xs">
                        <div class="truncate mr-2">
                            <span class="font-bold text-slate-800 block truncate">{{ auth()->user()->name }}</span>
                            <span class="text-[10px] text-slate-500 font-semibold">{{ auth()->user()->role }}</span>
                        </div>
                        <a href="{{ route('logout') }}" id="btn_sidebar_logout" class="px-2 py-1 rounded bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-600 text-[11px] font-bold transition">
                            Logout
                        </a>
                    </div>
                    <div x-show="appNavCollapsed" class="flex justify-center">
                        <a href="{{ route('logout') }}" id="btn_sidebar_logout_mini" title="Logout ({{ auth()->user()->name }})" class="p-2 rounded-xl text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        </a>
                    </div>
                @else
                    <div x-show="!appNavCollapsed" class="bg-white rounded-xl p-2.5 border border-slate-200 text-xs">
                        <div class="font-bold text-slate-800">DxClinic v19</div>
                        <div class="text-[11px] text-slate-500 flex justify-between items-center mt-1">
                            <span>Nexora Solutions</span>
                            <a href="{{ route('login') }}" class="text-blue-600 font-bold hover:underline">Login &rarr;</a>
                        </div>
                    </div>
                    <div x-show="appNavCollapsed" class="flex justify-center">
                        <a href="{{ route('login') }}" title="Login" class="p-2 rounded-xl text-blue-600 hover:bg-blue-50 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                        </a>
                    </div>
                @endif
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">
            <!-- Flash Message Alerts -->
            @if (session()->has('success'))
                <div class="mb-5 flex items-center justify-between p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium shadow-xs">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="mb-5 flex items-center justify-between p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium shadow-xs">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
                </div>
            @endif

            @if (session()->has('warning'))
                <div class="mb-5 flex items-center justify-between p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium shadow-xs">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span>{{ session('warning') }}</span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-amber-600 hover:text-amber-900 font-bold">&times;</button>
                </div>
            @endif

            {{ $slot ?? '' }}
            @yield('content')

            <!-- Professional Developer & Legal Footer (Section 7, 8, 12) -->
            <footer class="mt-12 pt-6 pb-6 border-t border-slate-200 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-center sm:text-left">
                    <span>&copy; 2026 <strong class="text-slate-700">Nexora Solutions</strong>. Developed by <strong class="text-slate-700">Kundan Kumar</strong>. Licensed under GNU AGPL-3.0.</span>
                </div>
                <div class="flex items-center gap-3 text-slate-600 font-semibold text-xs">
                    <a href="{{ route('about') }}" class="hover:text-blue-600 transition">About Developer</a>
                    <span class="text-slate-300">&bull;</span>
                    <a href="{{ route('privacy.policy') }}" class="hover:text-blue-600 transition">Privacy Policy</a>
                    <span class="text-slate-300">&bull;</span>
                    <a href="{{ route('terms.conditions') }}" class="hover:text-blue-600 transition">Terms & Conditions</a>
                </div>
            </footer>
        </main>
    </div>

    @auth
        <livewire:operator.operator-assistant />
    @endauth

    <script src="{{ asset('js/dx-shortcuts.js') }}"></script>
    @livewireScripts
</body>
</html>

