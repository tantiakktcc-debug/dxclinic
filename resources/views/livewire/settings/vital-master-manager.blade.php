<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Vital Master</h1>
            <p class="text-xs text-slate-500 mt-0.5">Configure clinical vital parameters, units, and standard adult normal reference ranges (Revision 24 Section 5)</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="resetToDefaults" onclick="return confirm('Reset all vital parameters to medical defaults?') || event.stopImmediatePropagation()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition">
                ↺ Reset Defaults
            </button>
            <button type="button" wire:click="saveVitals" id="btn_save_vitals" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Save Vital Settings</span>
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

    <!-- Vital Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider text-[10px] font-bold">
                    <th class="py-3 px-4">Parameter Name</th>
                    <th class="py-3 px-4">Measurement Unit</th>
                    <th class="py-3 px-4">Normal Min</th>
                    <th class="py-3 px-4">Normal Max</th>
                    <th class="py-3 px-4">Clinical Reference</th>
                    <th class="py-3 px-4 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @foreach($vitals as $key => $v)
                    <tr class="hover:bg-slate-50/70 transition" wire:key="vital-{{ $key }}">
                        <td class="py-3 px-4">
                            <div class="font-bold text-slate-900 text-sm">{{ $v['name'] }}</div>
                            <div class="text-[10px] font-mono text-slate-400 uppercase tracking-wider">{{ $key }}</div>
                        </td>
                        <td class="py-3 px-4 font-mono font-bold text-slate-600">
                            {{ $v['unit'] }}
                        </td>
                        <td class="py-3 px-4">
                            @if($v['min_normal'] !== null)
                                <input type="number" step="0.1" wire:model="vitals.{{ $key }}.min_normal" class="w-24 py-1 px-2 border border-slate-300 rounded-lg text-xs font-mono font-bold">
                            @else
                                <span class="text-slate-400 text-xs italic">N/A</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            @if($v['max_normal'] !== null)
                                <input type="number" step="0.1" wire:model="vitals.{{ $key }}.max_normal" class="w-24 py-1 px-2 border border-slate-300 rounded-lg text-xs font-mono font-bold">
                            @else
                                <span class="text-slate-400 text-xs italic">N/A</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-slate-500 font-mono text-[11px]">
                            @if($v['min_normal'] !== null && $v['max_normal'] !== null)
                                {{ $v['min_normal'] }} &ndash; {{ $v['max_normal'] }} {{ $v['unit'] }}
                            @else
                                Clinical Assessment
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            <button type="button" wire:click="toggleVital('{{ $key }}')" class="cursor-pointer">
                                <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase transition {{ $v['is_active'] ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                    {{ $v['is_active'] ? 'Active' : 'Disabled' }}
                                </span>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Clinical Guidance Card -->
    <div class="p-4 bg-blue-50/60 rounded-2xl border border-blue-200 text-xs text-blue-900 flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <div>
            <div class="font-bold">Clinical Normal Range Policy</div>
            <div class="text-blue-700 text-[11px] mt-0.5 leading-relaxed">
                Normal ranges configured here provide real-time visual cue highlighting in the Arrival Queue Vitals modal when patient measurements deviate outside expected clinical limits.
            </div>
        </div>
    </div>
</div>
