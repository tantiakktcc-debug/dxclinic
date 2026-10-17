<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Print & Branding Configuration</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage clinic logo file, print positioning, aspect ratio locks, and document safe box rendering (Revision 24 Section 34-40)</p>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Logo Upload Card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5">
            <div>
                <h2 class="text-base font-bold text-slate-900">Clinic Logo File</h2>
                <p class="text-xs text-slate-500 mt-0.5">Upload a high-resolution logo for receipts and reports. Formats: PNG, JPG, JPEG (Max 2MB)</p>
            </div>

            <form wire:submit.prevent="uploadLogo" class="space-y-4">
                <div class="border-2 border-dashed border-slate-300 rounded-2xl p-6 text-center hover:border-blue-400 transition bg-slate-50/50">
                    <input type="file" wire:model="logoFile" accept="image/png,image/jpeg,image/jpg" id="logoFileInput" class="hidden">
                    <label for="logoFileInput" class="cursor-pointer flex flex-col items-center">
                        <svg class="w-10 h-10 text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-xs font-bold text-blue-600 hover:underline">Choose file</span>
                        <span class="text-[11px] text-slate-400 mt-1">PNG, JPG, JPEG up to 2MB</span>
                    </label>
                </div>

                @if($logoFile)
                    <div class="flex items-center gap-3 p-3 bg-blue-50/60 rounded-xl border border-blue-200 text-xs text-blue-900">
                        <span class="font-bold">Selected:</span>
                        <span class="truncate flex-1 font-mono text-[11px]">{{ $logoFile->getClientOriginalName() }}</span>
                        <button type="submit" id="btn_upload_logo" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg shadow-xs transition">
                            Upload Now
                        </button>
                    </div>
                @endif
                @error('logoFile') <span class="text-rose-600 text-xs font-semibold block">{{ $message }}</span> @enderror
            </form>

            <!-- Aspect Ratio Lock Rule (Section 36) -->
            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        Aspect Ratio Status:
                    </span>
                    <span class="px-2.5 py-0.5 rounded font-black text-[10px] uppercase bg-emerald-100 text-emerald-800 tracking-wider">
                        LOCKED (Mandatory)
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Per Section 36, logo aspect ratio is permanently locked. Width and height automatically scale in fixed proportion to guarantee zero image distortion across receipts and reports.
                </p>
            </div>

            <!-- Position Selector (Section 38) -->
            <div>
                <label class="block font-bold text-slate-800 text-xs mb-2">Print Header Alignment (Section 38)</label>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" wire:click="updatePosition('left')" class="py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 {{ $logoPosition === 'left' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                        <span>Left</span>
                    </button>
                    <button type="button" wire:click="updatePosition('center')" class="py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 {{ $logoPosition === 'center' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                        <span>Center</span>
                    </button>
                    <button type="button" wire:click="updatePosition('right')" class="py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 {{ $logoPosition === 'right' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                        <span>Right</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Safe Box Preview Card (Section 37) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Active Document Safe Box Preview</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Section 37: Auto-fitted safe boundary preview</p>
                </div>
                @if($clinic && $clinic->logo_path)
                    <button type="button" wire:click="removeLogo" onclick="return confirm('Remove active clinic logo?') || event.stopImmediatePropagation()" class="text-rose-600 hover:text-rose-800 text-xs font-bold">
                        Remove Logo
                    </button>
                @endif
            </div>

            <!-- Receipt Header Simulation -->
            <div class="border border-slate-300 rounded-xl p-4 bg-slate-50/50 space-y-3">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">A5 Consultation / Diagnostic Receipt Header (120px &times; 44px Max Box)</div>
                <div class="bg-white p-3 rounded-lg border border-slate-200 flex items-center gap-3 {{ $logoPosition === 'center' ? 'flex-col text-center' : ($logoPosition === 'right' ? 'flex-row-reverse text-right' : 'flex-row') }}">
                    <div style="max-width: 120px; max-height: 44px; min-width: 80px; min-height: 40px; display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 4px; padding: 2px;">
                        @if($clinic && $clinic->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($clinic->logo_path))
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($clinic->logo_path) }}" alt="Clinic Logo" style="max-width: 116px; max-height: 40px; width: auto; height: auto; object-fit: contain;">
                        @else
                            <span class="text-[10px] text-slate-400 font-mono italic">No Logo</span>
                        @endif
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 text-sm">{{ $clinic->name }}</div>
                        <div class="text-[10px] text-slate-500">{{ $clinic->address }}, {{ $clinic->city }} | Tel: {{ $clinic->phone }}</div>
                    </div>
                </div>
            </div>

            <!-- Doctor Pad Overlay Notice (Section 40) -->
            <div class="p-4 bg-amber-50/70 rounded-xl border border-amber-200 text-xs text-amber-900 space-y-1">
                <div class="font-bold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>Pre-Printed Doctor Pad Reminder (Section 40)</span>
                </div>
                <p class="text-amber-800 text-[11px] leading-relaxed">
                    Doctor Pad is configured for <strong>8.25 &times; 11.671 inches</strong> in Pre-Printed Overlay Mode. Clinic branding is <strong>STRICTLY SUPPRESSED</strong> on the doctor pad so it prints only patient/vital details directly onto existing pre-printed stationery.
                </p>
            </div>
        </div>
    </div>
</div>
