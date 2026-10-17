<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Online Booking / QR Management</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage patient self-booking URLs, generate vector SVG and PNG QR codes, and print OPD reception posters (Revision 24 Section 47-50)</p>
        </div>
        <div>
            <button type="button" wire:click="saveUrls" id="btn_save_qr_urls" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Save URLs & Regenerate</span>
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- QR Security & Privacy Policy (Section 48) -->
    <div class="p-4 bg-blue-50/70 rounded-2xl border border-blue-200 text-xs text-blue-900 flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
        <div>
            <div class="font-bold">QR Data Privacy Invariant (Section 48)</div>
            <div class="text-blue-700 text-[11px] mt-0.5 leading-relaxed">
                QR codes generated below encode <strong>ONLY the self-service web application booking URL</strong>. No patient identifiers, telephone numbers, database IDs, or sensitive medical information are ever encoded inside QR matrix data.
            </div>
        </div>
    </div>

    <!-- Dual QR Code Cards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 1. Local Development Card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-700">LAN / Dev Environment</span>
                    <h2 class="text-base font-bold text-slate-900 mt-1">Local Network Booking QR</h2>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 text-xs mb-1">Local Booking URL</label>
                <input type="url" wire:model="localUrl" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono text-xs text-blue-700">
                @error('localUrl') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
            </div>

            <!-- QR Preview -->
            <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col items-center justify-center">
                <div class="bg-white p-3 rounded-xl border border-slate-300 shadow-xs">
                    {!! $localQrSvg !!}
                </div>
                <div class="mt-3 font-mono text-[11px] text-slate-600 truncate max-w-xs">{{ $localUrl }}</div>
            </div>

            <!-- Actions -->
            <div class="grid grid-cols-3 gap-2 pt-2">
                <a href="{{ route('settings.booking-qr.download-png', ['type' => 'local']) }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs text-center border border-slate-200 transition">
                    ⬇️ PNG
                </a>
                <a href="{{ route('settings.booking-qr.download-svg', ['type' => 'local']) }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs text-center border border-slate-200 transition">
                    ⬇️ SVG
                </a>
                <a href="{{ route('settings.booking-qr.poster', ['type' => 'local']) }}" target="_blank" class="py-2 px-3 bg-blue-50 hover:bg-blue-100 text-blue-800 font-bold rounded-xl text-xs text-center border border-blue-200 transition">
                    🖨️ Poster
                </a>
            </div>
        </div>

        <!-- 2. Production Requested Card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">Owner Production Target</span>
                    <h2 class="text-base font-bold text-slate-900 mt-1">Production Booking QR</h2>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 text-xs mb-1">Production Booking URL</label>
                <input type="url" wire:model="prodUrl" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono text-xs text-blue-700">
                @error('prodUrl') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
            </div>

            <!-- QR Preview -->
            <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col items-center justify-center">
                <div class="bg-white p-3 rounded-xl border border-slate-300 shadow-xs">
                    {!! $prodQrSvg !!}
                </div>
                <div class="mt-3 font-mono text-[11px] text-slate-600 truncate max-w-xs">{{ $prodUrl }}</div>
            </div>

            <!-- Actions -->
            <div class="grid grid-cols-3 gap-2 pt-2">
                <a href="{{ route('settings.booking-qr.download-png', ['type' => 'production']) }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs text-center border border-slate-200 transition">
                    ⬇️ PNG
                </a>
                <a href="{{ route('settings.booking-qr.download-svg', ['type' => 'production']) }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs text-center border border-slate-200 transition">
                    ⬇️ SVG
                </a>
                <a href="{{ route('settings.booking-qr.poster', ['type' => 'production']) }}" target="_blank" class="py-2 px-3 bg-blue-50 hover:bg-blue-100 text-blue-800 font-bold rounded-xl text-xs text-center border border-blue-200 transition">
                    🖨️ Poster
                </a>
            </div>
        </div>
    </div>
</div>
