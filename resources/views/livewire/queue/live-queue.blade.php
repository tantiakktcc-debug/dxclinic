<div class="min-h-screen flex flex-col transition-colors duration-200"
     :class="theme === 'night' ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900'"
     wire:poll.6s="refreshQueue"
     x-data="{
        theme: (function() {
            try {
                var stored = localStorage.getItem('dxclinic_livequeue_theme');
                return (stored === 'night' || stored === 'dark') ? 'night' : 'day';
            } catch(e) {
                return 'day';
            }
        })(),
        toggleTheme() {
            this.theme = (this.theme === 'day') ? 'night' : 'day';
            try {
                localStorage.setItem('dxclinic_livequeue_theme', this.theme);
            } catch(e) {}
            if (this.theme === 'night') {
                document.documentElement.classList.add('theme-night', 'dark');
            } else {
                document.documentElement.classList.remove('theme-night', 'dark');
            }
        },
        soundOn: @entangle('soundEnabled'),
        isFullscreen: false,
        toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().then(() => { this.isFullscreen = true; });
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen().then(() => { this.isFullscreen = false; });
                }
            }
        },
        playChime() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.4);
            } catch(e) {}
        }
     }"
     @play-token-sound.window="playChime()"
     id="live_queue_window_container">

    <!-- Top Operational Header (Section 53, 54, Requirement 14 & 15 Day/Night) -->
    <header class="px-6 py-4 sticky top-0 z-40 transition-colors duration-200"
            :class="theme === 'night' ? 'bg-slate-900/90 backdrop-blur border-b border-slate-800 shadow-xl' : 'bg-white/95 backdrop-blur border-b border-slate-200 shadow-xs'">
        <div class="flex flex-wrap items-center justify-between gap-4">
            
            <!-- Left: Brand & Clinic Info -->
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center font-black text-white text-xl shadow-lg shadow-blue-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-black tracking-tight flex items-center gap-2"
                            :class="theme === 'night' ? 'text-white' : 'text-slate-900'">
                            <span>DxClinic</span>
                            <span class="text-xs uppercase px-2 py-0.5 rounded-full font-bold tracking-wider"
                                  :class="theme === 'night' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-emerald-50 text-emerald-800 border border-emerald-300'">
                                LIVE QUEUE
                            </span>
                        </h1>
                    </div>
                    <p class="text-xs font-medium flex items-center gap-2 mt-0.5"
                       :class="theme === 'night' ? 'text-slate-400' : 'text-slate-500'">
                        <span class="font-semibold" :class="theme === 'night' ? 'text-slate-200' : 'text-slate-800'">{{ $clinic->name }}</span>
                        <span>•</span>
                        <span>{{ \Carbon\Carbon::parse($filterDate)->format('l, d M Y') }}</span>
                    </p>
                </div>
            </div>

            <!-- Center: Connection State & Last Updated (Section 53, 54) -->
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold"
                     :class="theme === 'night' ? 'bg-slate-950/70 border border-slate-800' : 'bg-slate-100 border border-slate-200'">
                    @if($connectionState === 'CONNECTED')
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span class="font-bold" :class="theme === 'night' ? 'text-emerald-400' : 'text-emerald-700'">REALTIME CONNECTED</span>
                    @elseif($connectionState === 'RECONNECTING')
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                        <span class="font-bold text-amber-500">RECONNECTING...</span>
                    @elseif($connectionState === 'FALLBACK REFRESH')
                        <span class="h-2.5 w-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                        <span class="font-bold text-blue-500">FALLBACK REFRESH</span>
                    @else
                        <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                        <span class="font-bold text-rose-500">OFFLINE</span>
                    @endif
                </div>

                <div class="text-xs font-mono px-3 py-1.5 rounded-lg"
                     :class="theme === 'night' ? 'text-slate-400 bg-slate-950/70 border border-slate-800' : 'text-slate-600 bg-slate-100 border border-slate-200'">
                    Updated: <span class="font-bold" :class="theme === 'night' ? 'text-slate-200' : 'text-slate-900'">{{ $lastUpdated }}</span>
                </div>
            </div>

            <!-- Right: Controls (Day/Night Theme, Sound, Fullscreen, Close) -->
            <div class="flex items-center gap-2">
                <!-- Theme Switcher (Rule 14-18: Obvious DAY / NIGHT Switcher) -->
                <button type="button" 
                        @click="toggleTheme()" 
                        id="btn_live_queue_theme_toggle"
                        :title="theme === 'day' ? 'Switch to Night mode' : 'Switch to Day mode'"
                        class="px-3 py-1.5 rounded-lg border text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                        :class="theme === 'night' ? 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-amber-300' : 'bg-slate-100 hover:bg-slate-200 border-slate-300 text-slate-700'">
                    <span x-show="theme === 'day'" class="flex items-center gap-1">
                        <span class="text-amber-500 text-sm">☀</span>
                        <span>Day</span>
                    </span>
                    <span x-show="theme === 'night'" class="flex items-center gap-1" style="display: none;">
                        <span class="text-indigo-400 text-sm">☾</span>
                        <span>Night</span>
                    </span>
                </button>

                <!-- Sound Toggle -->
                <button type="button" 
                        wire:click="toggleSound" 
                        id="btn_live_queue_sound_toggle"
                        class="px-3 py-1.5 rounded-lg border text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                        :class="$soundEnabled 
                            ? 'bg-amber-500/20 border-amber-500/40 text-amber-500' 
                            : (theme === 'night' ? 'bg-slate-800 border-slate-700 text-slate-400 hover:text-slate-200' : 'bg-slate-100 border-slate-300 text-slate-600 hover:text-slate-900')">
                    @if($soundEnabled)
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.984 5.984 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.983 3.983 0 00-1.172-2.828 1 1 0 010-1.415z"/></svg>
                        <span>Sound ON</span>
                    @else
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM12.293 7.293a1 1 0 011.414 0L15 8.586l1.293-1.293a1 1 0 111.414 1.414L16.414 10l1.293 1.293a1 1 0 01-1.414 1.414L15 11.414l-1.293 1.293a1 1 0 01-1.414-1.414L13.586 10l-1.293-1.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        <span>Sound OFF</span>
                    @endif
                </button>

                <!-- Fullscreen Toggle (Section 71) -->
                <button type="button" 
                        @click="toggleFullscreen()" 
                        id="btn_live_queue_fullscreen_toggle"
                        title="Toggle Fullscreen"
                        class="px-3 py-1.5 rounded-lg border text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                        :class="theme === 'night' ? 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-300' : 'bg-slate-100 hover:bg-slate-200 border-slate-300 text-slate-700'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                    <span>Full Screen</span>
                </button>

                <!-- Close Window (Section 35) -->
                <button type="button" 
                        onclick="window.close()" 
                        id="btn_live_queue_close"
                        title="Close Live Queue Window (does not logout)"
                        class="px-3 py-1.5 rounded-lg border text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                        :class="theme === 'night' ? 'bg-rose-900/40 hover:bg-rose-900/60 border-rose-800 text-rose-300' : 'bg-rose-50 hover:bg-rose-100 border-rose-200 text-rose-700'">
                    <span>✕</span>
                    <span>Close</span>
                </button>
            </div>
        </div>

        <!-- Metrics Grid Bar (Section 43, Consistent Day/Night Theme) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3 mt-4">
            <div class="p-2.5 rounded-xl border flex flex-col"
                 :class="theme === 'night' ? 'bg-slate-950/80 border-slate-800' : 'bg-white border-slate-200 shadow-2xs'">
                <span class="text-[10px] font-bold tracking-wider uppercase"
                      :class="theme === 'night' ? 'text-slate-400' : 'text-slate-500'">Total Today</span>
                <span class="text-xl font-black mt-0.5" id="metric_total"
                      :class="theme === 'night' ? 'text-white' : 'text-slate-900'">{{ $metrics['total'] ?? 0 }}</span>
            </div>

            <div class="p-2.5 rounded-xl border flex flex-col"
                 :class="theme === 'night' ? 'bg-slate-950/80 border-amber-900/40 text-amber-400' : 'bg-amber-50/80 border-amber-200 text-amber-800 shadow-2xs'">
                <span class="text-[10px] font-bold tracking-wider uppercase">Waiting</span>
                <span class="text-xl font-black mt-0.5" id="metric_waiting"
                      :class="theme === 'night' ? 'text-amber-300' : 'text-amber-700'">{{ $metrics['waiting'] ?? 0 }}</span>
            </div>

            <div class="p-2.5 rounded-xl border flex flex-col"
                 :class="theme === 'night' ? 'bg-slate-950/80 border-emerald-900/40 text-emerald-400' : 'bg-emerald-50/80 border-emerald-200 text-emerald-800 shadow-2xs'">
                <span class="text-[10px] font-bold tracking-wider uppercase">Arrived</span>
                <span class="text-xl font-black mt-0.5" id="metric_arrived"
                      :class="theme === 'night' ? 'text-emerald-300' : 'text-emerald-700'">{{ $metrics['arrived'] ?? 0 }}</span>
            </div>

            <div class="p-2.5 rounded-xl border flex flex-col"
                 :class="theme === 'night' ? 'bg-slate-950/80 border-purple-900/40 text-purple-400' : 'bg-purple-50/80 border-purple-200 text-purple-800 shadow-2xs'">
                <span class="text-[10px] font-bold tracking-wider uppercase">In Consultation</span>
                <span class="text-xl font-black mt-0.5" id="metric_in_consultation"
                      :class="theme === 'night' ? 'text-purple-300' : 'text-purple-700'">{{ $metrics['in_consultation'] ?? 0 }}</span>
            </div>

            <div class="p-2.5 rounded-xl border flex flex-col"
                 :class="theme === 'night' ? 'bg-slate-950/80 border-blue-900/40 text-blue-400' : 'bg-blue-50/80 border-blue-200 text-blue-800 shadow-2xs'">
                <span class="text-[10px] font-bold tracking-wider uppercase">Completed</span>
                <span class="text-xl font-black mt-0.5" id="metric_completed"
                      :class="theme === 'night' ? 'text-blue-300' : 'text-blue-700'">{{ $metrics['completed'] ?? 0 }}</span>
            </div>

            <div class="p-2.5 rounded-xl border flex flex-col"
                 :class="theme === 'night' ? 'bg-slate-950/80 border-rose-900/40 text-rose-400' : 'bg-rose-50/80 border-rose-200 text-rose-800 shadow-2xs'">
                <span class="text-[10px] font-bold tracking-wider uppercase">Cancelled</span>
                <span class="text-xl font-black mt-0.5" id="metric_cancelled"
                      :class="theme === 'night' ? 'text-rose-400' : 'text-rose-700'">{{ $metrics['cancelled'] ?? 0 }}</span>
            </div>

            <div class="p-2.5 rounded-xl border flex flex-col"
                 :class="theme === 'night' ? 'bg-slate-950/80 border-slate-800 text-slate-500' : 'bg-slate-100/80 border-slate-200 text-slate-600 shadow-2xs'">
                <span class="text-[10px] font-bold tracking-wider uppercase">Absent</span>
                <span class="text-xl font-black mt-0.5" id="metric_absent"
                      :class="theme === 'night' ? 'text-slate-400' : 'text-slate-700'">{{ $metrics['absent'] ?? 0 }}</span>
            </div>
        </div>

        <!-- Filter Bar (Section 40, 41, Themed) -->
        <div class="flex flex-wrap items-center justify-between gap-3 mt-3 pt-3 border-t"
             :class="theme === 'night' ? 'border-slate-800/60' : 'border-slate-200'">
            <!-- Date Filter & Session Filter -->
            <div class="flex flex-wrap items-center gap-3 text-xs font-semibold">
                <div class="flex items-center gap-2">
                    <span :class="theme === 'night' ? 'text-slate-400' : 'text-slate-600'">Date:</span>
                    <input type="date" 
                           wire:model.live="filterDate" 
                           id="filter_live_queue_date"
                           class="rounded-lg px-2.5 py-1 text-xs focus:ring-1 focus:ring-blue-500 font-mono"
                           :class="theme === 'night' ? 'bg-slate-950 border border-slate-700 text-slate-200' : 'bg-white border border-slate-300 text-slate-900'">
                </div>

                <div class="flex items-center gap-1.5">
                    <span class="mr-1" :class="theme === 'night' ? 'text-slate-400' : 'text-slate-600'">Session:</span>
                    <button type="button" 
                            wire:click="setSessionFilter('ALL')"
                            id="btn_filter_session_all"
                            class="px-2.5 py-1 rounded-md text-xs font-bold transition cursor-pointer"
                            :class="$filterSession === 'ALL' 
                                ? 'bg-blue-600 text-white shadow-xs' 
                                : (theme === 'night' ? 'bg-slate-800/80 text-slate-300 hover:bg-slate-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200')">
                        All Sessions
                    </button>
                    @if(isset($sessions) && is_array($sessions))
                        @foreach($sessions as $key => $sData)
                            <button type="button" 
                                    wire:click="setSessionFilter('{{ $key }}')"
                                    id="btn_filter_session_{{ strtolower($key) }}"
                                    class="px-2.5 py-1 rounded-md text-xs font-bold transition cursor-pointer"
                                    :class="$filterSession === '{{ $key }}' 
                                        ? 'bg-blue-600 text-white shadow-xs' 
                                        : (theme === 'night' ? 'bg-slate-800/80 text-slate-300 hover:bg-slate-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200')">
                                {{ $sData['label'] ?? $key }}
                            </button>
                        @endforeach
                    @else
                        @foreach(['MORNING' => 'Morning', 'EVENING' => 'Evening'] as $key => $label)
                            <button type="button" 
                                    wire:click="setSessionFilter('{{ $key }}')"
                                    id="btn_filter_session_{{ strtolower($key) }}"
                                    class="px-2.5 py-1 rounded-md text-xs font-bold transition cursor-pointer"
                                    :class="$filterSession === '{{ $key }}' 
                                        ? 'bg-blue-600 text-white shadow-xs' 
                                        : (theme === 'night' ? 'bg-slate-800/80 text-slate-300 hover:bg-slate-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200')">
                                {{ $label }}
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- Doctor Filter (Admin/Operator) -->
            @if($userRole !== 'DOCTOR' && $activeDoctors->count() > 1)
                <div class="flex items-center gap-2 text-xs">
                    <span :class="theme === 'night' ? 'text-slate-400' : 'text-slate-600'">Doctor Filter:</span>
                    <select wire:change="setDoctorFilter($event.target.value)" 
                            id="select_filter_doctor"
                            class="rounded-lg px-2.5 py-1 text-xs focus:ring-1 focus:ring-blue-500"
                            :class="theme === 'night' ? 'bg-slate-950 border border-slate-700 text-slate-200' : 'bg-white border border-slate-300 text-slate-900'">
                        <option value="">All Doctors ({{ $activeDoctors->count() }})</option>
                        @foreach($activeDoctors as $doc)
                            <option value="{{ $doc->id }}" @selected($filterDoctorId === $doc->id)>
                                Dr. {{ ltrim($doc->name, 'Dr. ') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </header>

    @if(session('error'))
        <div id="live_queue_error_banner" class="mx-6 mt-4 p-4 rounded-xl text-xs font-bold flex items-center justify-between"
             :class="theme === 'night' ? 'bg-rose-950/80 border border-rose-800 text-rose-200' : 'bg-rose-50 border border-rose-200 text-rose-800'">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" wire:click="$refresh" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
        </div>
    @endif
    @if(session('success'))
        <div id="live_queue_success_banner" class="mx-6 mt-4 p-4 rounded-xl text-xs font-bold flex items-center justify-between"
             :class="theme === 'night' ? 'bg-emerald-950/80 border border-emerald-800 text-emerald-200' : 'bg-emerald-50 border border-emerald-200 text-emerald-800'">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" wire:click="$refresh" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
        </div>
    @endif

    <!-- Main Live Queue Display Table (Section 37, 38, 39, 42, Consistent Theming) -->
    <main class="flex-1 p-6 overflow-y-auto">
        @if(count($queue) === 0)
            <div class="min-h-[50vh] flex flex-col items-center justify-center text-center p-8 rounded-2xl border mt-4"
                 :class="theme === 'night' ? 'bg-slate-900/40 border-slate-800/60' : 'bg-white border-slate-200 shadow-xs'">
                <div class="h-16 w-16 rounded-2xl flex items-center justify-center mb-3"
                     :class="theme === 'night' ? 'bg-slate-800/80 text-slate-500' : 'bg-slate-100 text-slate-400'">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <h2 class="text-lg font-bold" :class="theme === 'night' ? 'text-slate-300' : 'text-slate-800'">No Patients in Current Queue</h2>
                <p class="text-sm max-w-sm mt-1" :class="theme === 'night' ? 'text-slate-500' : 'text-slate-500'">Appointments booked via Reception, PWA, or WhatsApp will appear here in real time with authoritative token numbers.</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border"
                 :class="theme === 'night' ? 'border-slate-800 shadow-2xl bg-slate-900/60' : 'border-slate-200 shadow-sm bg-white'">
                <table class="w-full text-left border-collapse" id="table_live_queue">
                    <thead>
                        <tr class="border-b text-[11px] font-black uppercase tracking-wider"
                            :class="theme === 'night' ? 'border-slate-800 bg-slate-900 text-slate-400' : 'border-slate-200 bg-slate-100 text-slate-600'">
                            <th class="py-3.5 px-4 w-28 text-center">Token</th>
                            <th class="py-3.5 px-4">Patient Details</th>
                            <th class="py-3.5 px-4">Doctor</th>
                            <th class="py-3.5 px-3">Session</th>
                            <th class="py-3.5 px-4">Booking Source</th>
                            <th class="py-3.5 px-3 text-center">Fee</th>
                            <th class="py-3.5 px-3 text-center">Doctor Seen</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y font-medium text-sm"
                           :class="theme === 'night' ? 'divide-slate-800/80' : 'divide-slate-200'">
                        @foreach($queue as $item)
                            <tr wire:key="live-queue-row-{{ $item['id'] }}"
                                class="transition duration-150"
                                :class="theme === 'night' 
                                    ? 'hover:bg-slate-800/40 {{ $newlyAddedTokenNumber === $item['token_number'] ? 'bg-emerald-950/40 ring-1 ring-emerald-500/50' : '' }}' 
                                    : 'hover:bg-slate-50/80 {{ $newlyAddedTokenNumber === $item['token_number'] ? 'bg-emerald-50 ring-1 ring-emerald-300' : '' }}'"
                                id="queue_row_token_{{ $item['formatted_token'] }}">
                                
                                <!-- 1. Authoritative Token (Section 37, 38) -->
                                <td class="py-3.5 px-4 text-center">
                                    <div class="inline-block px-3.5 py-1.5 rounded-xl border shadow-2xs"
                                         :class="theme === 'night' ? 'bg-slate-950 border-slate-700/80 text-amber-400' : 'bg-amber-50 border-amber-300 text-amber-800'">
                                        <span class="text-xl font-black tracking-wider font-mono">
                                            {{ $item['formatted_token'] }}
                                        </span>
                                    </div>
                                </td>

                                <!-- 2. Patient Name & ID (Section 37) -->
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-base leading-tight"
                                         :class="theme === 'night' ? 'text-white' : 'text-slate-900'">
                                        {{ $item['patient_name'] }}
                                    </div>
                                    <div class="text-xs flex items-center gap-2 mt-0.5"
                                         :class="theme === 'night' ? 'text-slate-400' : 'text-slate-500'">
                                        <span class="font-mono font-semibold" :class="theme === 'night' ? 'text-slate-300' : 'text-slate-700'">{{ $item['patient_id'] }}</span>
                                        @if(!empty($item['city']))
                                            <span>•</span>
                                            <span>{{ $item['city'] }}</span>
                                        @endif
                                    </div>
                                </td>

                                <!-- 3. Doctor (Section 37) -->
                                <td class="py-3.5 px-4"
                                    :class="theme === 'night' ? 'text-slate-300' : 'text-slate-700'">
                                    <span class="font-semibold" :class="theme === 'night' ? 'text-slate-200' : 'text-slate-900'">{{ $item['doctor_name'] }}</span>
                                </td>

                                <!-- 4. Session (Section 40) -->
                                <td class="py-3.5 px-3">
                                    <span class="text-xs font-semibold uppercase px-2.5 py-1 rounded-md border"
                                          :class="theme === 'night' ? 'bg-slate-800 text-slate-300 border-slate-700' : 'bg-slate-100 text-slate-700 border-slate-200'">
                                        {{ $item['session'] }}
                                    </span>
                                </td>

                                <!-- 5. Booking Source Indicator (Section 39) -->
                                <td class="py-3.5 px-4">
                                    @php $src = strtoupper($item['booking_source']); @endphp
                                    @if($src === 'WHATSAPP')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border shadow-xs"
                                              :class="theme === 'night' ? 'bg-emerald-950/80 text-emerald-300 border-emerald-700/60' : 'bg-emerald-50 text-emerald-800 border-emerald-300'">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>WhatsApp</span>
                                        </span>
                                    @elseif($src === 'ONLINE')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border shadow-xs"
                                              :class="theme === 'night' ? 'bg-teal-950/80 text-teal-300 border-teal-700/60' : 'bg-teal-50 text-teal-800 border-teal-300'">
                                            <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
                                            <span>Patient PWA</span>
                                        </span>
                                    @elseif($src === 'TELEPHONIC')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border shadow-xs"
                                              :class="theme === 'night' ? 'bg-purple-950/80 text-purple-300 border-purple-700/60' : 'bg-purple-50 text-purple-800 border-purple-300'">
                                            <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                            <span>Telephonic</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border shadow-xs"
                                              :class="theme === 'night' ? 'bg-blue-950/80 text-blue-300 border-blue-700/60' : 'bg-blue-50 text-blue-800 border-blue-300'">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                            <span>Reception / Clinic</span>
                                        </span>
                                    @endif
                                </td>

                                <!-- 6. Fee Status Indicator (PAID / UNPAID / PARTIAL / REFUNDED) -->
                                <td class="py-3.5 px-3 text-center" id="fee_status_{{ $item['formatted_token'] }}">
                                    @php $fee = $item['fee_status'] ?? 'UNPAID'; @endphp
                                    @if($fee === 'PAID')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-emerald-100 text-emerald-800 border-emerald-300'">
                                            PAID
                                        </span>
                                    @elseif($fee === 'PARTIAL')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-amber-100 text-amber-800 border-amber-300'">
                                            PARTIAL
                                        </span>
                                    @elseif($fee === 'REFUNDED')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-rose-500/20 text-rose-300 border-rose-500/40' : 'bg-rose-100 text-rose-800 border-rose-300'">
                                            REFUNDED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-slate-800 text-slate-400 border-slate-700' : 'bg-slate-100 text-slate-600 border-slate-300'">
                                            UNPAID
                                        </span>
                                    @endif
                                </td>

                                <!-- 7. Doctor Consultation Seen Status (SEEN / NOT SEEN) -->
                                <td class="py-3.5 px-3 text-center">
                                    @php $seen = $item['doctor_seen_status'] ?? 'NOT SEEN'; @endphp
                                    @if($seen === 'SEEN')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-teal-500/20 text-teal-300 border-teal-500/40' : 'bg-teal-100 text-teal-800 border-teal-300'">
                                            SEEN
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-slate-800 text-slate-400 border-slate-700' : 'bg-slate-100 text-slate-600 border-slate-300'">
                                            NOT SEEN
                                        </span>
                                    @endif
                                </td>

                                <!-- 8. Arrival/Visit Status Badge (Section 42) -->
                                <td class="py-3.5 px-4 text-center">
                                    @php $st = $item['computed_status']; @endphp
                                    @if($st === 'ARRIVED')
                                        <span class="px-3 py-1 rounded-lg text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-emerald-100 text-emerald-800 border-emerald-300'">
                                            ARRIVED
                                        </span>
                                    @elseif($st === 'IN_PROGRESS')
                                        <span class="px-3 py-1 rounded-lg text-xs font-bold border animate-pulse"
                                              :class="theme === 'night' ? 'bg-violet-500/20 text-violet-300 border-violet-500/40' : 'bg-violet-100 text-violet-800 border-violet-300'">
                                            IN CONSULTATION
                                        </span>
                                    @elseif($st === 'COMPLETED')
                                        <span class="px-3 py-1 rounded-lg text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-slate-800 text-slate-400 border-slate-700' : 'bg-slate-100 text-slate-700 border-slate-300'">
                                            COMPLETED
                                        </span>
                                    @elseif($st === 'CANCELLED')
                                        <span class="px-3 py-1 rounded-lg text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-rose-500/20 text-rose-400 border-rose-500/40' : 'bg-rose-100 text-rose-800 border-rose-300'">
                                            CANCELLED
                                        </span>
                                    @elseif($st === 'ABSENT')
                                        <span class="px-3 py-1 rounded-lg text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-amber-500/20 text-amber-400 border-amber-500/40' : 'bg-amber-100 text-amber-800 border-amber-300'">
                                            ABSENT
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-lg text-xs font-bold border"
                                              :class="theme === 'night' ? 'bg-sky-500/20 text-sky-300 border-sky-500/40' : 'bg-sky-100 text-sky-800 border-sky-300'">
                                            BOOKED
                                        </span>
                                    @endif
                                </td>

                                <!-- 9. Authoritative Action: Mark Seen & Fee Gate (Part 1, G, I, J, K, AY) -->
                                <td class="py-3.5 px-4 text-center">
                                    @if(($item['doctor_seen_status'] ?? '') === 'NOT SEEN' && !in_array($item['computed_status'], ['CANCELLED', 'ABSENT']))
                                        @if($userRole === 'DOCTOR' && auth()->user()?->doctor_id && (int) auth()->user()->doctor_id !== (int) $item['doctor_id'])
                                            <span class="text-xs" :class="theme === 'night' ? 'text-slate-500' : 'text-slate-400'">—</span>
                                        @else
                                            <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                                @if(($item['fee_status'] ?? 'UNPAID') !== 'PAID')
                                                    <a href="{{ route('operator.consultation', ['appointmentId' => $item['id']]) }}"
                                                       id="btn_fee_pad_{{ $item['formatted_token'] }}"
                                                       title="Consultation fee must be paid before consultation"
                                                       class="px-2.5 py-1 text-xs font-bold rounded-lg transition inline-flex items-center gap-1 border"
                                                       :class="theme === 'night' ? 'text-amber-300 bg-amber-500/20 hover:bg-amber-500/30 border-amber-500/40' : 'text-amber-800 bg-amber-100 hover:bg-amber-200 border-amber-300'">
                                                        <span>FEE & PAD</span>
                                                    </a>
                                                    <button type="button" 
                                                            disabled
                                                            id="btn_mark_seen_{{ $item['formatted_token'] }}"
                                                            title="Mark Seen is disabled: required consultation fee is unpaid/pending"
                                                            class="px-2.5 py-1 text-xs font-bold rounded-lg cursor-not-allowed opacity-50 flex items-center gap-1 border"
                                                            :class="theme === 'night' ? 'text-slate-400 bg-slate-800/80 border-slate-700' : 'text-slate-500 bg-slate-100 border-slate-300'">
                                                        <span>Mark Seen</span>
                                                        <span>🔒</span>
                                                    </button>
                                                @else
                                                    <button type="button" 
                                                            wire:click="markSeen({{ $item['id'] }})"
                                                            id="btn_mark_seen_{{ $item['formatted_token'] }}"
                                                            title="Start consultation and mark patient seen in clinical workflow"
                                                            class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-lg shadow-xs transition flex items-center justify-center gap-1 mx-auto cursor-pointer">
                                                        <span>Mark Seen</span>
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-xs font-mono" :class="theme === 'night' ? 'text-slate-500' : 'text-slate-400'">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </main>

    <!-- New Token Alert Toast Notification (Section 55, 105) -->
    @if($showNewTokenToast)
        <div class="fixed bottom-6 right-6 z-50 animate-bounce transition duration-300"
             x-data="{ show: true }"
             x-init="setTimeout(() => { $wire.dismissToast() }, 8000)"
             id="new_token_toast_notification">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-4 rounded-2xl shadow-2xl border border-emerald-400/40 flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl font-black">
                    🎫
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-200">New Token Added</div>
                    <div class="text-lg font-black tracking-tight">
                        Token #{{ sprintf('%03d', $newlyAddedTokenNumber) }} — {{ $newlyAddedPatientName }}
                    </div>
                    <div class="text-xs text-emerald-100 flex items-center gap-2 mt-0.5">
                        <span>Source: <strong>{{ $newlyAddedTokenSource }}</strong></span>
                        <span>•</span>
                        <span>Unified Daily Sequence</span>
                    </div>
                </div>
                <button type="button" 
                        wire:click="dismissToast" 
                        class="text-white/80 hover:text-white text-lg font-bold p-1 cursor-pointer">
                    ✕
                </button>
            </div>
        </div>
    @endif
</div>
