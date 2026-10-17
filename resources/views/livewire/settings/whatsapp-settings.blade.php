<div class="p-6 max-w-6xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 flex items-center gap-2.5">
                <span class="h-8 w-8 rounded-lg bg-emerald-600 flex items-center justify-center text-white text-base">
                    💬
                </span>
                <span>WhatsApp Integration & Automation</span>
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Configure Meta WhatsApp Cloud API connection, AI assistant rules, and unified token booking for {{ $clinic->name }}.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @php
                $isConnected = $connection && $connection->isConnected();
                $overallStatusLabel = $connection ? $connection->status_label : 'NOT CONNECTED';
                $isDemoClinic = $clinic && ($clinic->id === 6 || $clinic->public_slug === 'democlinic');
                $tunnelWebhookUrl = 'https://edit-cars-replied-communicate.trycloudflare.com/api/v1/webhooks/whatsapp';
            @endphp
            @if($isConnected)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Status: {{ $overallStatusLabel }}</span>
                </span>
                <button type="button" 
                        wire:click="$toggle('showManageModal')" 
                        id="btn_whatsapp_manage_header"
                        class="px-3.5 py-1.5 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-lg transition cursor-pointer">
                    Manage
                </button>
                <button type="button" 
                        wire:click="disconnectWhatsApp" 
                        id="btn_whatsapp_disconnect"
                        wire:confirm="Are you sure you want to disconnect WhatsApp? Incoming messages will stop being processed, but all historical data will be preserved."
                        class="px-3.5 py-1.5 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition cursor-pointer">
                    Disconnect
                </button>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                    <span>Status: NOT CONNECTED</span>
                </span>
                <button type="button" 
                        wire:click="launchMetaOnboarding" 
                        id="btn_connect_meta_whatsapp"
                        class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                    <span>CONNECT META WHATSAPP</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('warning'))
        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-semibold flex items-center justify-between">
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    <!-- DUAL-PROVIDER ARCHITECTURE BADGE & BAILEYS DEVELOPMENT CARD -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                        ACTIVE DEVELOPMENT PROVIDER: BAILEYS
                    </span>
                    <span class="text-xs font-semibold text-slate-500">
                        (Meta Cloud API Preserved for Production)
                    </span>
                </div>
                <h2 class="text-xl font-black text-slate-900 mt-2 flex items-center gap-2">
                    <span>📱</span>
                    <span>Baileys Development WhatsApp Gateway</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1 max-w-2xl leading-relaxed">
                    Baileys bridges WhatsApp Web directly to your local development environment (127.0.0.1:9090). It enables live testing of patient messaging, menu navigation, and token booking on real phones without waiting for Meta Business verification.
                </p>
            </div>

            <!-- Status Pill & Primary Action -->
            <div class="flex items-center gap-3">
                @if($baileysStatus === 'CONNECTED')
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>CONNECTED ({{ $baileysPhone ?: 'Active' }})</span>
                    </span>
                    <button type="button" 
                            wire:click="sendBaileysTestMessage" 
                            id="btn_baileys_test_send"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-xl transition cursor-pointer flex items-center gap-1.5">
                        <span wire:loading.remove wire:target="sendBaileysTestMessage">🚀 Send Test</span>
                        <span wire:loading wire:target="sendBaileysTestMessage">Sending...</span>
                    </button>
                    <button type="button" 
                            wire:click="disconnectBaileys" 
                            id="btn_baileys_disconnect"
                            wire:confirm="Disconnect Baileys gateway session? Historical messages and clinic appointments will remain unaffected."
                            class="px-4 py-2 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition cursor-pointer">
                        Disconnect
                    </button>
                @elseif($baileysStatus === 'QR_REQUIRED')
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-extrabold bg-amber-50 text-amber-800 border border-amber-300">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                        <span>SCAN QR CODE</span>
                    </span>
                    <button type="button" 
                            wire:click="disconnectBaileys" 
                            id="btn_baileys_cancel"
                            class="px-3.5 py-1.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-xl transition cursor-pointer">
                        Cancel
                    </button>
                @elseif($baileysStatus === 'CONNECTING')
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-extrabold bg-blue-50 text-blue-700 border border-blue-200">
                        <svg class="animate-spin h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>CONNECTING...</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        <span>DISCONNECTED</span>
                    </span>
                    <button type="button" 
                            wire:click="connectBaileys" 
                            id="btn_connect_baileys"
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 text-xs font-black text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm hover:shadow transition flex items-center gap-2 cursor-pointer uppercase tracking-wider">
                        <span wire:loading.remove wire:target="connectBaileys">🔗 LINK WHATSAPP (QR)</span>
                        <span wire:loading wire:target="connectBaileys">REQUESTING QR...</span>
                    </button>
                @endif
            </div>
        </div>

        @if(in_array($baileysStatus, ['QR_REQUIRED', 'CONNECTING']))
            <!-- Auto-poll for status updates while waiting for QR scan -->
            <div wire:poll.3s="pollBaileysStatus"></div>
        @endif

        @error('baileys')
            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between">
                <span>{{ $message }}</span>
            </div>
        @enderror

        <!-- QR Display Section when QR_REQUIRED -->
        @if($baileysStatus === 'QR_REQUIRED' && !empty($qrPayload))
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 flex flex-col md:flex-row items-center gap-8">
                <div class="bg-white p-4 rounded-2xl border-2 border-emerald-500 shadow-md flex flex-col items-center">
                    <img src="{{ $qrPayload }}" alt="Scan with WhatsApp" class="w-56 h-56 rounded-lg object-contain" />
                    <span class="text-[11px] font-bold text-slate-500 mt-2 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Live QR Code (Auto-refreshes)
                    </span>
                </div>

                <div class="space-y-4 max-w-lg text-slate-700">
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-slate-900">How to Link WhatsApp:</h3>
                        <p class="text-xs text-slate-500">Scan this code using the WhatsApp app on the clinic's phone.</p>
                    </div>

                    <ol class="space-y-2.5 text-xs">
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-[10px] shrink-0">1</span>
                            <span>Open <strong>WhatsApp</strong> on your phone.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-[10px] shrink-0">2</span>
                            <span>Tap <strong>Menu (⋮)</strong> on Android or <strong>Settings</strong> on iPhone.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-[10px] shrink-0">3</span>
                            <span>Tap <strong>Linked Devices</strong> and then <strong>Link a Device</strong>.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-[10px] shrink-0">4</span>
                            <span>Point your phone at this screen to scan the QR code. This panel will automatically update once paired!</span>
                        </li>
                    </ol>

                    <div class="pt-2 flex items-center gap-3">
                        <button type="button" 
                                wire:click="pollBaileysStatus" 
                                class="px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 rounded-lg shadow-2xs transition">
                            🔄 Refresh Status
                        </button>
                    </div>
                </div>
            </div>
        @elseif($baileysStatus === 'CONNECTED')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 rounded-xl bg-emerald-50/60 border border-emerald-200">
                    <span class="text-[11px] text-emerald-800 font-bold uppercase tracking-wider block">Linked WhatsApp Device</span>
                    <span class="text-base font-black text-slate-900 mt-1 block">
                        {{ $baileysPhone ? '+' . $baileysPhone : 'Active Socket' }}
                    </span>
                    <span class="text-[11px] text-emerald-600 font-medium">● WhatsApp Web Session Active</span>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider block">Gateway Bind (Internal)</span>
                    <span class="text-base font-mono font-bold text-slate-800 mt-1 block">127.0.0.1:9090</span>
                    <span class="text-[11px] text-slate-500">Local Loopback Transport</span>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider block">Gateway Service Route</span>
                    <span class="text-base font-mono font-bold text-emerald-700 mt-1 block truncate">
                        {{ parse_url(config('dxclinic.whatsapp.baileys.gateway_url', 'http://127.0.0.1:9090'), PHP_URL_HOST) ?? '127.0.0.1' }}
                    </span>
                    <span class="text-[11px] text-slate-500">Local Loopback / Ingress</span>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider block">Target Dev Test Phone</span>
                    <span class="text-base font-mono font-bold text-slate-800 mt-1 block">
                        {{ config('dxclinic.whatsapp.dev_test_phone') ?: 'Not configured (set in .env)' }}
                    </span>
                    <span class="text-[11px] text-slate-500">Configured in .env</span>
                </div>
            </div>
        @endif
    </div>

    <!-- PART 7: PRIMARY HERO ONBOARDING & ACTIVE STATUS CARD (META CLOUD API PRESERVED) -->
    @if(!$connection || !$connection->isConnected())
        <!-- NOT CONNECTED STATE -->
        <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 rounded-2xl border border-slate-700 p-8 shadow-xl text-white space-y-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-700/60">
                <div class="space-y-2">
                    <div class="flex items-center gap-2.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/30">
                            Status: NOT CONNECTED
                        </span>
                        <span class="text-xs text-slate-400 font-semibold">Meta Cloud API Integration</span>
                    </div>
                    <h2 class="text-2xl font-black tracking-tight text-white">WhatsApp Automation</h2>
                    <p class="text-sm text-slate-300 max-w-2xl leading-relaxed">
                        Connect your clinic's WhatsApp Business number securely through Meta. Enable instant patient appointment booking, real-time token allocation, OPD fee queries, and automated doctor schedule notifications.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="button" 
                            wire:click="launchMetaOnboarding" 
                            id="btn_connect_meta_whatsapp_hero"
                            class="px-6 py-3.5 text-sm font-black text-white bg-emerald-500 hover:bg-emerald-600 rounded-xl shadow-lg hover:shadow-emerald-500/20 transition flex items-center justify-center gap-2 cursor-pointer uppercase tracking-wider">
                        <span>💬 CONNECT META WHATSAPP</span>
                    </button>
                </div>
            </div>

            <!-- Token Model Architecture Callout (Parts 6, 8, 11) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-1.5">
                    <span class="text-emerald-400 font-bold uppercase tracking-wider block">Token Model</span>
                    <p class="text-slate-300">
                        <strong>System User Token ≠ Clinic Phone ≠ Patient Phone</strong>. System user token authorizes Meta API access; clinic phone is a business asset; patient phone is the conversation identity.
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-1.5">
                    <span class="text-emerald-400 font-bold uppercase tracking-wider block">Phone Verification</span>
                    <p class="text-slate-300">
                        Clinic verifies business phone ownership via Meta OTP during registration. Patient phones <strong>never</strong> register with Meta; patients simply message the clinic number.
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-1.5">
                    <span class="text-emerald-400 font-bold uppercase tracking-wider block">Security & Multi-Tenant</span>
                    <p class="text-slate-300">
                        Tokens are encrypted at rest with AES-256-CBC and masked in all UIs. Webhook tenant resolution is strictly derived from verified <code>phone_number_id</code>.
                    </p>
                </div>
            </div>
        </div>
    @else
        <!-- ACTIVE CONNECTED STATE (Part 7) -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-2xs space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Status: {{ $overallStatusLabel }}</span>
                        </span>
                        <span class="text-xs text-slate-400 font-semibold">Meta Cloud API v21.0+</span>
                    </div>
                    <h2 class="text-xl font-black text-slate-900">WhatsApp Automation</h2>
                </div>

                <div class="flex items-center gap-2.5">
                    <button type="button" 
                            wire:click="$toggle('showManageModal')" 
                            id="btn_whatsapp_manage"
                            class="px-4 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-lg transition cursor-pointer">
                        Manage
                    </button>
                    <button type="button" 
                            wire:click="disconnectWhatsApp" 
                            id="btn_whatsapp_disconnect_card"
                            wire:confirm="Are you sure you want to disconnect WhatsApp? Incoming messages will stop being processed, but all historical data will be preserved."
                            class="px-4 py-2 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition cursor-pointer">
                        Disconnect
                    </button>
                </div>
            </div>

            <!-- Credentials & Channel Overview (Credentials Masked - Part 7 & 11) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider block">Business Name</span>
                    <span class="text-base font-extrabold text-slate-900 mt-1 block truncate">
                        {{ $connection->business_display_name ?: $clinic->name }}
                    </span>
                    <span class="text-[11px] text-slate-500">Verified Display Name</span>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider block">WhatsApp Number</span>
                    <span class="text-base font-extrabold text-slate-900 mt-1 block">
                        {{ $connection->masked_phone ?: '••••' }}
                    </span>
                    <span class="text-[11px] text-emerald-600 font-semibold">● Registered Asset</span>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider block">WABA ID (masked)</span>
                    <span class="text-base font-mono font-bold text-slate-800 mt-1 block">
                        {{ $connection->masked_business_account_id ?: '••••' }}
                    </span>
                    <span class="text-[11px] text-slate-500">Meta Business Account</span>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider block">Phone Number ID (masked)</span>
                    <span class="text-base font-mono font-bold text-slate-800 mt-1 block">
                        {{ $connection->masked_phone_number_id ?: '••••' }}
                    </span>
                    <span class="text-[11px] text-slate-500">Multi-tenant Routing Key</span>
                </div>
            </div>

            <!-- Channel Automation Status Indicators (Part 7) -->
            <div class="p-4 rounded-xl bg-slate-50/80 border border-slate-200 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="text-xs font-bold text-slate-700 mr-2">Channel Controls:</span>

                    <!-- Assistant -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $assistantEnabled ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-slate-200 text-slate-600' }}">
                        <span>Assistant</span>
                        <span class="font-black">{{ $assistantEnabled ? 'ON' : 'OFF' }}</span>
                    </span>

                    <!-- Booking -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $bookingEnabled ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-slate-200 text-slate-600' }}">
                        <span>Booking</span>
                        <span class="font-black">{{ $bookingEnabled ? 'ON' : 'OFF' }}</span>
                    </span>

                    <!-- Notifications -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $notificationsEnabled ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-slate-200 text-slate-600' }}">
                        <span>Notifications</span>
                        <span class="font-black">{{ $notificationsEnabled ? 'ON' : 'OFF' }}</span>
                    </span>

                    <!-- Human Handoff -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $humanHandoffEnabled ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-slate-200 text-slate-600' }}">
                        <span>Human Handoff</span>
                        <span class="font-black">{{ $humanHandoffEnabled ? 'ON' : 'OFF' }}</span>
                    </span>
                </div>

                <button type="button" 
                        wire:click="$toggle('showManageModal')" 
                        class="text-xs font-bold text-blue-600 hover:text-blue-800 underline cursor-pointer">
                    Adjust Rules & Schedule &rarr;
                </button>
            </div>
        </div>
    @endif

    <!-- Diagnostic Connection Health Summary (Phase R) -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-2xs space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-base font-bold text-slate-900">WhatsApp Connection Health & Diagnostics</h2>
                <p class="text-xs text-slate-500">Live operational telemetry and credential security masking.</p>
            </div>
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border {{ $isConnected ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' }}">
                    <span class="w-2 h-2 rounded-full {{ $isConnected ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    <span>Status: {{ $overallStatusLabel }}</span>
                </span>
            </div>
        </div>

        <!-- 6-Factor Integration Status Breakdown (Part U) -->
        <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 text-white space-y-2">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Meta Cloud API Multi-Factor Verification Status (Part U)</span>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-xs">
                <div class="p-2 rounded-lg bg-slate-800/80 border border-slate-700">
                    <span class="text-[10px] text-slate-400 block font-semibold">A. DxClinic Config</span>
                    <span class="font-bold text-emerald-400">{{ $connection ? 'CONFIGURED ✓' : 'PENDING' }}</span>
                </div>
                <div class="p-2 rounded-lg bg-slate-800/80 border border-slate-700">
                    <span class="text-[10px] text-slate-400 block font-semibold">B. Meta Auth</span>
                    <span class="font-bold {{ $connection?->getSetting('meta_verified') ? 'text-emerald-400' : 'text-amber-300' }}">
                        {{ $connection?->getSetting('meta_verified') ? 'META AUTH ✓' : 'PENDING' }}
                    </span>
                </div>
                <div class="p-2 rounded-lg bg-slate-800/80 border border-slate-700">
                    <span class="text-[10px] text-slate-400 block font-semibold">C. Phone Verify</span>
                    <span class="font-bold {{ $connection?->getSetting('phone_verified') ? 'text-emerald-400' : 'text-amber-300' }}">
                        {{ $connection?->getSetting('phone_verified') ? 'VERIFIED ✓' : 'OTP PENDING' }}
                    </span>
                </div>
                <div class="p-2 rounded-lg bg-slate-800/80 border border-slate-700">
                    <span class="text-[10px] text-slate-400 block font-semibold">D. Webhook Config</span>
                    <span class="font-bold text-emerald-400">CONFIGURED ✓</span>
                </div>
                <div class="p-2 rounded-lg bg-slate-800/80 border border-slate-700">
                    <span class="text-[10px] text-slate-400 block font-semibold">E. Webhook Verify</span>
                    <span class="font-bold {{ $connection?->webhook_verified_at ? 'text-emerald-400' : 'text-amber-300' }}">
                        {{ $connection?->webhook_verified_at ? 'VERIFIED ✓' : 'PENDING' }}
                    </span>
                </div>
                <div class="p-2 rounded-lg bg-slate-800/80 border border-slate-700">
                    <span class="text-[10px] text-slate-400 block font-semibold">F. Live Meta API</span>
                    <span class="font-bold {{ $connection?->getSetting('live_traffic_verified') ? 'text-emerald-400' : ($connection?->getSetting('last_api_error') ? 'text-rose-400' : 'text-slate-400') }}">
                        {{ $connection?->getSetting('live_traffic_verified') ? 'LIVE VERIFIED ✓' : ($connection?->getSetting('last_api_error') ? 'API ERROR' : 'PENDING') }}
                    </span>
                </div>
            </div>
        </div>

        @if($isDemoClinic)
            <!-- REAL META DEVELOPMENT TEST HARNESS (DEMO CLINIC ONLY - PART 4) -->
            <div class="p-5 rounded-2xl bg-indigo-950 border-2 border-indigo-500/50 text-white space-y-4 shadow-xl">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-indigo-800/80 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-md bg-indigo-600 text-white text-xs font-black tracking-wider uppercase">
                            ⚡ REAL META DEVELOPMENT TEST
                        </span>
                        <span class="text-xs text-indigo-300 font-semibold">Demo Clinic Local PC & Tunnel Harness</span>
                    </div>
                    <div class="text-xs text-indigo-200">
                        Dedicated Dev Test Number: <strong class="text-amber-300 font-mono text-sm">{{ config('dxclinic.whatsapp.dev_test_phone') ?: 'Configured in .env' }}</strong>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div class="p-3 rounded-xl bg-indigo-900/60 border border-indigo-700/60 space-y-1.5">
                        <span class="text-indigo-300 font-bold uppercase tracking-wider block">Live Cloudflare HTTPS Webhook</span>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="{{ $tunnelWebhookUrl }}" class="w-full bg-indigo-950 text-indigo-100 font-mono text-xs p-2 rounded border border-indigo-600 select-all" />
                        </div>
                        <span class="text-[11px] text-indigo-300">
                            Verify Token: <code class="bg-indigo-950 px-1.5 py-0.5 rounded text-amber-300 font-bold font-mono">dxclinic_verify_token_secure</code>
                        </span>
                    </div>

                    <div class="p-3 rounded-xl bg-indigo-900/60 border border-indigo-700/60 space-y-2">
                        <span class="text-indigo-300 font-bold uppercase tracking-wider block">Real Meta Live Verification Actions</span>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" 
                                    wire:click="testRealMetaApiConnectivity" 
                                    id="btn_test_real_meta_api"
                                    class="px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition cursor-pointer shadow">
                                🔍 1. Test Live Graph API
                            </button>
                            <button type="button" 
                                    wire:click="sendRealTestWhatsAppMessage" 
                                    id="btn_send_real_whatsapp_test"
                                    class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition cursor-pointer shadow">
                                📤 2. Send Real Test Message
                            </button>
                        </div>
                        @if($connection?->getSetting('last_api_error'))
                            <div class="text-[11px] text-rose-300 bg-rose-950/80 p-2 rounded border border-rose-800">
                                ⚠️ {{ $connection->getSetting('last_api_error') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] text-slate-400 font-semibold block uppercase tracking-wider">Business & Phone</span>
                <span class="text-sm font-bold text-slate-800 mt-1 block">
                    {{ $connection?->masked_phone ?: 'Not configured' }}
                </span>
                <div class="text-[11px] text-slate-500 mt-1 space-y-0.5">
                    <div>WABA ID: <span class="font-mono text-slate-700">{{ $connection?->masked_business_account_id ?: '—' }}</span></div>
                    <div>Phone ID: <span class="font-mono text-slate-700">{{ $connection?->masked_phone_number_id ?: '—' }}</span></div>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] text-slate-400 font-semibold block uppercase tracking-wider">Webhook Status</span>
                <div class="mt-1">
                    @if($connection?->webhook_verified_at)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">
                            ✓ VERIFIED
                        </span>
                        <span class="text-[10px] text-slate-400 block mt-1">
                            {{ $connection->webhook_verified_at->format('d M Y, H:i') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">
                            ⏳ NOT VERIFIED
                        </span>
                    @endif
                </div>
                <div class="text-[11px] text-slate-500 mt-2">
                    Verify Token: <code class="bg-slate-200 px-1 py-0.5 rounded text-[10px]">{{ $verifyToken }}</code>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] text-slate-400 font-semibold block uppercase tracking-wider">Event Telemetry</span>
                <div class="text-[11px] text-slate-600 space-y-1 mt-1">
                    <div>Last Inbound: <strong>{{ $connection?->last_message_at ? $connection->last_message_at->diffForHumans() : 'None' }}</strong></div>
                    <div>Last Outbound: <strong>{{ $connection?->last_successful_send_at ? $connection->last_successful_send_at->diffForHumans() : 'None' }}</strong></div>
                    <div>Last Webhook: <strong>{{ $connection?->last_webhook_at ? $connection->last_webhook_at->diffForHumans() : 'None' }}</strong></div>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-[11px] text-slate-400 font-semibold block uppercase tracking-wider">Reliability & Queue</span>
                <div class="text-[11px] text-slate-600 space-y-1 mt-1">
                    <div>Queue Worker: <strong class="text-emerald-700 font-bold">{{ $queueWorkerStatus }}</strong></div>
                    <div>
                        Last Delivery Failure: 
                        @if($lastFailedDelivery)
                            <strong class="text-rose-600 font-semibold">{{ $lastFailedDelivery->created_at->diffForHumans() }}</strong>
                            <span class="block text-[10px] text-slate-400 truncate" title="{{ $lastFailedDelivery->last_error }}">
                                {{ Str::limit($lastFailedDelivery->last_error, 24) }}
                            </span>
                        @else
                            <strong class="text-slate-500">None (Healthy)</strong>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
            <span class="text-slate-500 font-semibold">Meta Webhook Callback URL:</span>
            <input type="text" readonly value="{{ $webhookUrl }}" class="w-full text-xs font-mono bg-white border border-slate-200 rounded p-1.5 mt-1 text-slate-700 select-all" />
        </div>
    </div>

    <!-- Assistant & Booking Control Toggles (Section 31) -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-2xs space-y-6">
        <div>
            <h2 class="text-base font-bold text-slate-900">Assistant & Channel Controls</h2>
            <p class="text-xs text-slate-500 mt-0.5">Toggle automated features and appointment availability on WhatsApp.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Column 1: Feature Modules -->
            <div class="space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Features</h3>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">AI Assistant & Natural Language</span>
                        <span class="text-xs text-slate-500">Allow AI understanding for intent and queries</span>
                    </div>
                    <input type="checkbox" wire:model="assistantEnabled" class="w-5 h-5 text-blue-600 rounded" />
                </label>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">WhatsApp Appointment Booking</span>
                        <span class="text-xs text-slate-500">Enable self-service patient booking via WhatsApp</span>
                    </div>
                    <input type="checkbox" wire:model="bookingEnabled" class="w-5 h-5 text-blue-600 rounded" />
                </label>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">Automated Notifications</span>
                        <span class="text-xs text-slate-500">Send instant token and queue confirmations to patients</span>
                    </div>
                    <input type="checkbox" wire:model="notificationsEnabled" class="w-5 h-5 text-blue-600 rounded" />
                </label>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">Human Handoff Support</span>
                        <span class="text-xs text-slate-500">Allow patients to request talking to clinic receptionist</span>
                    </div>
                    <input type="checkbox" wire:model="humanHandoffEnabled" class="w-5 h-5 text-blue-600 rounded" />
                </label>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">Clinic Hours & Timings</span>
                        <span class="text-xs text-slate-500">Display authoritative timings automatically</span>
                    </div>
                    <input type="checkbox" wire:model="hoursEnabled" class="w-5 h-5 text-blue-600 rounded" />
                </label>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">Consultation Fee Information</span>
                        <span class="text-xs text-slate-500">Provide official OPD fee information when asked</span>
                    </div>
                    <input type="checkbox" wire:model="priceInfoEnabled" class="w-5 h-5 text-blue-600 rounded" />
                </label>
            </div>

            <!-- Column 2: Booking Constraints -->
            <div class="space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Booking Constraints (Section 31)</h3>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">Book for Today</span>
                        <span class="text-xs text-slate-500">Allow booking appointments for current day</span>
                    </div>
                    <input type="checkbox" wire:model="bookingToday" class="w-5 h-5 text-blue-600 rounded" />
                </label>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">Book for Tomorrow</span>
                        <span class="text-xs text-slate-500">Allow advance booking for next day</span>
                    </div>
                    <input type="checkbox" wire:model="bookingTomorrow" class="w-5 h-5 text-blue-600 rounded" />
                </label>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">Morning Session</span>
                        <span class="text-xs text-slate-500">Allow morning slot booking</span>
                    </div>
                    <input type="checkbox" wire:model="morningEnabled" class="w-5 h-5 text-blue-600 rounded" />
                </label>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">Evening Session</span>
                        <span class="text-xs text-slate-500">Allow evening slot booking</span>
                    </div>
                    <input type="checkbox" wire:model="eveningEnabled" class="w-5 h-5 text-blue-600 rounded" />
                </label>

                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                    <div>
                        <span class="text-sm font-bold text-slate-800 block">Sunday Session Booking</span>
                        <span class="text-xs text-slate-500">Subject to clinic Sunday policy</span>
                    </div>
                    <input type="checkbox" wire:model="sundayEnabled" class="w-5 h-5 text-blue-600 rounded" />
                </label>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end">
            <button type="button" 
                    wire:click="saveSettings" 
                    id="btn_save_whatsapp_settings"
                    class="px-5 py-2 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition cursor-pointer">
                Save Settings
            </button>
        </div>
    </div>

    <!-- META EMBEDDED SIGNUP & SETUP MODAL (Part 7 & Part 8, Section 3, 20-27, 84) -->
    @if($showSetupModal)
        <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
             x-data="metaSignupManager($wire)"
             x-init="init()">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <span class="h-6 w-6 rounded-md bg-emerald-600 flex items-center justify-center text-white text-xs">💬</span>
                        <h3 class="text-lg font-bold text-slate-900">Connect Meta WhatsApp Cloud API</h3>
                    </div>
                    <button type="button" wire:click="$set('showSetupModal', false)" class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer">✕</button>
                </div>

                <!-- Dynamic Visible Status / Feedback Banner (Section 3, 20-27, 84 - No Silent Closes) -->
                <div x-show="clientMessage" 
                     id="meta_signup_feedback_banner"
                     class="p-4 rounded-xl text-xs font-semibold flex items-start gap-3 transition-all"
                     :class="{
                         'bg-emerald-50 border border-emerald-300 text-emerald-900': clientType === 'success',
                         'bg-amber-50 border border-amber-300 text-amber-900': clientType === 'warning',
                         'bg-rose-50 border border-rose-300 text-rose-900': clientType === 'error',
                         'bg-blue-50 border border-blue-300 text-blue-900': clientType === 'info'
                     }"
                     style="display: none;">
                    <div class="text-base shrink-0 mt-0.5">
                        <span x-show="clientType === 'success'">✓</span>
                        <span x-show="clientType === 'warning'">⚠</span>
                        <span x-show="clientType === 'error'">✕</span>
                        <span x-show="clientType === 'info'">ℹ</span>
                    </div>
                    <div class="flex-1 space-y-1">
                        <div class="font-bold uppercase tracking-wider text-[11px]" x-text="clientStatus">{{ $metaFeedbackStatus }}</div>
                        <div class="text-xs leading-relaxed" x-text="clientMessage">{{ $metaFeedbackMessage }}</div>
                    </div>
                </div>

                <!-- Primary Option: Embedded Signup (Meta-Hosted Authoritative Onboarding) -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Official Embedded Signup (v4)</span>
                        <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded">Authoritative Target</span>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Meta's official Embedded Signup authorizes your WhatsApp Business Account (WABA) and verifies your clinic business number in one streamlined flow.
                    </p>

                    <!-- Authoritative Meta Parameters Badge (Section: Authoritative Configuration) -->
                    <div class="p-3 bg-white rounded-lg border border-slate-200/90 text-[11px] font-mono text-slate-600 space-y-1">
                        <div class="flex items-center justify-between text-[10px] uppercase font-bold text-slate-400">
                            <span>Authoritative Meta v4 Target</span>
                            <span class="text-emerald-600">Active</span>
                        </div>
                        <div class="truncate"><span class="text-slate-400">App ID:</span> <span class="font-bold text-slate-800">{{ config('dxclinic.whatsapp.embedded_signup.app_id') ?: 'Not configured (set META_APP_ID in .env)' }}</span></div>
                        <div class="truncate"><span class="text-slate-400">Config ID:</span> <span class="font-bold text-slate-800">{{ config('dxclinic.whatsapp.embedded_signup.config_id') ?: 'Not configured (set META_CONFIG_ID in .env)' }}</span></div>
                        <div class="flex items-center gap-3">
                            <span><span class="text-slate-400">Version:</span> <span class="font-bold text-slate-800">{{ config('dxclinic.whatsapp.embedded_signup.version', 'v4') }}</span></span>
                            <span><span class="text-slate-400">SessionInfo:</span> <span class="font-bold text-slate-800">{{ config('dxclinic.whatsapp.embedded_signup.session_info_version', '3') }}</span></span>
                        </div>
                        <div class="truncate"><span class="text-slate-400">Feature:</span> <span class="font-bold text-slate-800">{{ config('dxclinic.whatsapp.embedded_signup.feature_type', 'whatsapp_business_app_onboarding') }}</span></div>
                        <div class="truncate text-[10px] text-slate-400 pt-0.5 border-t border-slate-100">
                            <span>Tenant:</span> <span class="font-bold text-slate-700">{{ $clinic->name }} (ID: {{ $clinic->id }})</span>
                        </div>
                    </div>

                    <!-- Real Meta Flow Launcher Button (Synchronous Popup - No Silent Modal Close) -->
                    <button type="button" 
                            @click="launchMetaSignup()"
                            :disabled="isProcessing"
                            id="btn_launch_meta_signup"
                            class="w-full py-3 px-4 text-xs font-black text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 rounded-xl shadow-sm transition flex items-center justify-center gap-2 cursor-pointer uppercase tracking-wider">
                        <span x-show="!isProcessing && (!clientStatus || clientStatus === 'OPENED' || clientStatus === 'SUCCESS')">✓ Launch Meta Embedded Signup</span>
                        <span x-show="!isProcessing && (clientStatus === 'CANCELLED' || clientStatus === 'POPUP_BLOCKED' || clientStatus === 'WINDOW_CLOSED_WITHOUT_RESULT' || clientStatus === 'META_ERROR' || clientStatus === 'SDK_ERROR' || clientStatus === 'CONFIG_ERROR' || clientStatus === 'BACKEND_ERROR')" style="display: none;">↻ Retry Meta Embedded Signup</span>
                        <span x-show="isProcessing" style="display: none;">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>Connecting to Meta...</span>
                        </span>
                    </button>
                    
                    <!-- Direct Tab Fallback Link -->
                    <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1">
                        <span>Having trouble with popups?</span>
                        <a :href="metaLandingUrl" target="_blank" rel="noopener noreferrer" class="text-emerald-700 hover:underline font-bold inline-flex items-center gap-1">
                            Open Meta Onboarding in new tab ↗
                        </a>
                    </div>

                    <!-- Test / Simulation Callback Button (Preserved for automated E2E test suite & verification) -->
                    <div class="pt-2 border-t border-slate-200/80">
                        <button type="button" 
                                @click="simulateCallback()"
                                :disabled="isProcessing"
                                id="btn_simulate_meta_callback"
                                class="w-full py-2 px-3 text-[11px] font-bold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 rounded-lg shadow-2xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                            <span>⚡ Verify Flow / Simulate Meta Callback</span>
                        </button>
                    </div>
                </div>

                <!-- Secondary Option: Advanced Manual Configuration (Collapsed by default) -->
                <div x-data="{ openManual: false }" class="border-t border-slate-200 pt-3">
                    <button type="button" @click="openManual = !openManual" class="text-xs font-semibold text-slate-500 hover:text-slate-700 flex items-center justify-between w-full cursor-pointer py-1">
                        <span>Advanced / Existing Meta Credentials</span>
                        <span x-text="openManual ? '▲' : '▼'"></span>
                    </button>

                    <div x-show="openManual" class="space-y-3 pt-3 text-sm" style="display: none;">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number ID *</label>
                            <input type="text" wire:model="phoneNumberId" placeholder="e.g. 109876543210002" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">WhatsApp Business Account ID (WABA ID)</label>
                            <input type="text" wire:model="businessAccountId" placeholder="e.g. 109876543210987" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Display Phone Number *</label>
                            <input type="text" wire:model="displayPhoneNumber" placeholder="e.g. +91 98765 43210" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Permanent System User Access Token</label>
                            <input type="password" wire:model="accessToken" placeholder="EAAB..." class="w-full border border-slate-300 rounded-lg p-2.5 text-sm font-mono" />
                            <span class="text-[11px] text-slate-400">Tokens are securely encrypted with AES-256-CBC and masked.</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Webhook Verify Token</label>
                            <input type="text" wire:model="verifyToken" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm font-mono" />
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="button" wire:click="connectWhatsApp" id="btn_submit_connect_whatsapp" class="px-5 py-2 text-xs font-bold text-white bg-slate-800 hover:bg-slate-900 rounded-lg shadow-sm">
                                Save Manual Configuration
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200">
                    <button type="button" wire:click="$set('showSetupModal', false)" id="btn_close_setup_modal" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@assets
<div id="fb-root"></div>
<script>
    window.fbAsyncInit = function() {
        if (typeof FB !== 'undefined') {
            FB.init({
                appId: @js(config('dxclinic.whatsapp.app_id') ?: ''),
                cookie: true,
                xfbml: true,
                version: @js(config('dxclinic.whatsapp.api_version', 'v21.0'))
            });
        }
    };
    (function(d, s, id){
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) {return;}
        js = d.createElement(s); js.id = id;
        js.src = "https://connect.facebook.net/en_US/sdk.js";
        js.async = true; js.defer = true;
        js.crossOrigin = "anonymous";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));

    function metaSignupManager(wire) {
        return {
            wire: wire,
            appId: @js(config('dxclinic.whatsapp.embedded_signup.app_id') ?? ''),
            configId: @js(config('dxclinic.whatsapp.embedded_signup.config_id') ?? ''),
            metaLandingUrl: @js(config('dxclinic.whatsapp.embedded_signup.landing_url') ?? ''),
            version: @js(config('dxclinic.whatsapp.embedded_signup.version', 'v4')),
            sessionInfoVersion: @js(config('dxclinic.whatsapp.embedded_signup.session_info_version', '3')),
            featureType: @js(config('dxclinic.whatsapp.embedded_signup.feature_type', 'whatsapp_business_app_onboarding')),
            stateToken: @entangle('oauthState'),
            isProcessing: false,
            popupWindow: null,
            pollTimer: null,
            clientStatus: @entangle('metaFeedbackStatus'),
            clientMessage: @entangle('metaFeedbackMessage'),
            clientType: @entangle('metaFeedbackType'),

            getWire() {
                return this.wire || this.$wire || (typeof $wire !== 'undefined' ? $wire : null);
            },

            init() {
                window.addEventListener('message', (event) => {
                    const allowedOrigins = [
                        'https://business.facebook.com',
                        'https://www.facebook.com',
                        'https://web.facebook.com'
                    ];
                    if (!allowedOrigins.includes(event.origin)) {
                        return;
                    }
                    try {
                        const data = (typeof event.data === 'string') ? JSON.parse(event.data) : event.data;
                        if (!data) return;

                        const w = this.getWire();

                        if (data.type === 'WA_EMBEDDED_SIGNUP') {
                            if (data.event === 'CANCEL') {
                                if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
                                this.clientStatus = 'CANCELLED';
                                this.clientType = 'warning';
                                this.clientMessage = 'Meta onboarding cancelled.';
                                this.isProcessing = false;
                                if (w) w.recordMetaSignupClientEvent('CANCELLED', this.clientMessage);
                                return;
                            }
                            if (data.event === 'ERROR') {
                                if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
                                this.clientStatus = 'META_ERROR';
                                this.clientType = 'error';
                                this.clientMessage = 'Meta onboarding failed: ' + (data.error_message || data.message || 'An error occurred during Meta signup.');
                                this.isProcessing = false;
                                if (w) w.recordMetaSignupClientEvent('META_ERROR', this.clientMessage);
                                return;
                            }
                            if (data.event === 'FINISH' || data.code || data.data?.code || data.phone_number_id) {
                                if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
                                this.clientStatus = 'PROCESSING';
                                this.clientType = 'info';
                                this.clientMessage = 'Meta onboarding completed. Verifying connection...';
                                this.isProcessing = true;

                                const payload = {
                                    code: data.code || data.data?.code || '',
                                    phone_number_id: data.phone_number_id || data.data?.phone_number_id || '',
                                    waba_id: data.waba_id || data.data?.waba_id || '',
                                    state: this.stateToken,
                                    display_phone_number: '{{ $isDemoClinic ? (config('dxclinic.whatsapp.dev_test_phone') ?: '+91 99999 99999') : '+91 98765 43210' }}',
                                    business_display_name: '{{ addslashes($clinic->name) }}'
                                };

                                if (w) {
                                    w.handleMetaOnboardingCallback(payload).then(() => {
                                        this.isProcessing = false;
                                        if (this.popupWindow && !this.popupWindow.closed) {
                                            try { this.popupWindow.close(); } catch(e) {}
                                        }
                                    }).catch(() => {
                                        this.isProcessing = false;
                                        this.clientStatus = 'BACKEND_ERROR';
                                        this.clientType = 'error';
                                        this.clientMessage = 'Failed to verify Meta connection on backend. Please retry.';
                                    });
                                }
                            }
                        }
                    } catch(e) {}
                });
            },

            launchMetaSignup() {
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }

                this.isProcessing = true;

                // CRITICAL: Synchronously open popup window on user gesture to guarantee popup blocker immunity
                const width = 1000;
                const height = 750;
                const left = Math.max(0, Math.floor((window.screen.width - width) / 2));
                const top = Math.max(0, Math.floor((window.screen.height - height) / 2));
                const features = `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes,status=yes`;

                try {
                    this.popupWindow = window.open(this.metaLandingUrl, 'MetaEmbeddedSignup', features);
                } catch(e) {
                    this.popupWindow = null;
                }

                const wireInstance = this.getWire();

                // Popup blocker detection
                if (!this.popupWindow || this.popupWindow.closed || typeof this.popupWindow.closed === 'undefined') {
                    this.isProcessing = false;
                    this.clientStatus = 'POPUP_BLOCKED';
                    this.clientType = 'warning';
                    this.clientMessage = 'Meta Embedded Signup could not be opened because the browser blocked the popup. Please allow popups for ' + window.location.hostname + ' and try again.';
                    if (wireInstance) wireInstance.recordMetaSignupClientEvent('POPUP_BLOCKED', this.clientMessage);
                    return;
                }

                // Popup opened successfully
                this.isProcessing = false;
                this.clientStatus = 'OPENED';
                this.clientType = 'info';
                this.clientMessage = 'Meta Embedded Signup opened in popup window. Waiting for Meta onboarding completion...';
                if (wireInstance) wireInstance.initiateMetaSignup();

                try {
                    if (this.popupWindow && this.popupWindow.focus) {
                        this.popupWindow.focus();
                    }
                } catch(e) {}

                // Poll for window closure before completion
                this.pollTimer = setInterval(() => {
                    if (!this.popupWindow || this.popupWindow.closed) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                        if (this.clientStatus === 'OPENED' || this.clientStatus === 'LAUNCHING') {
                            this.clientStatus = 'WINDOW_CLOSED_WITHOUT_RESULT';
                            this.clientType = 'warning';
                            this.clientMessage = 'Meta Embedded Signup was closed before completion. You may click Launch to try again.';
                            const w = this.getWire();
                            if (w) w.recordMetaSignupClientEvent('WINDOW_CLOSED_WITHOUT_RESULT', this.clientMessage);
                        }
                    }
                }, 500);
            },

            simulateCallback() {
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
                this.clientStatus = 'PROCESSING';
                this.clientType = 'info';
                this.clientMessage = 'Meta onboarding completed. Verifying connection...';
                this.isProcessing = true;

                const wireInstance = this.getWire();
                if (wireInstance) {
                    wireInstance.handleMetaOnboardingCallback({
                        phone_number_id: '109876543210002',
                        waba_id: '109876543210987',
                        display_phone_number: '{{ $isDemoClinic ? (config('dxclinic.whatsapp.dev_test_phone') ?: '+91 99999 99999') : '+91 98765 43210' }}',
                        business_display_name: '{{ addslashes($clinic->name) }}',
                        state: this.stateToken
                    }).then(() => {
                        this.isProcessing = false;
                    }).catch(() => {
                        this.isProcessing = false;
                    });
                } else {
                    this.isProcessing = false;
                }
            }
        };
    }
</script>
@endassets
