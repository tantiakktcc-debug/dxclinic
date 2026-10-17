<div class="p-6 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
        <div>
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl border border-blue-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Prescription Pad Templates</h1>
                    <p class="text-xs text-slate-500 mt-0.5">Multi-doctor prescription stationery designer, mm positioning, proofing & calibration</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <button wire:click="openPrinterModal" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-colors flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Printer Calibration
            </button>

            @if($selectedDoctorId)
                <button wire:click="createFromLegacy({{ $selectedDoctorId }})" class="px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold rounded-xl border border-amber-200 transition-colors flex items-center gap-1.5" title="Create a new draft from current legacy Doctor Pad settings. Legacy remains active until you activate.">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    Import From Legacy
                </button>
            @endif

            <button wire:click="openCreateModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                New Template
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-xl flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session()->has('info'))
        <div class="p-4 bg-blue-50 border border-blue-200 text-blue-800 text-xs font-semibold rounded-xl flex items-center justify-between">
            <span>{{ session('info') }}</span>
        </div>
    @endif

    <!-- Doctor Filter Tabs & Search -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
            <button wire:click="$set('selectedDoctorId', null)" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-colors whitespace-nowrap {{ is_null($selectedDoctorId) ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' }}">
                All Doctors
            </button>
            @foreach($doctors as $doc)
                <button wire:click="$set('selectedDoctorId', {{ $doc->id }})" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-colors whitespace-nowrap {{ $selectedDoctorId === $doc->id ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' }}">
                    {{ $doc->display_name }}
                </button>
            @endforeach
        </div>

        <div class="w-full md:w-64">
            <input type="text" wire:model.live.debounce.250ms="search" placeholder="Search templates..." class="w-full px-3.5 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-hidden focus:border-blue-500">
        </div>
    </div>

    <!-- Templates Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="p-3">Doctor</th>
                        <th class="p-3">Template Name</th>
                        <th class="p-3 text-center">Version</th>
                        <th class="p-3">Paper Size</th>
                        <th class="p-3 text-center">Orientation</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-center">Field Count</th>
                        <th class="p-3">Created</th>
                        <th class="p-3">Activated</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($templates as $tpl)
                        <tr class="hover:bg-slate-50/50 transition-colors {{ $tpl->is_active ? 'bg-blue-50/20' : '' }}">
                            <td class="p-3 font-semibold text-slate-900">
                                <div>{{ $tpl->doctor?->display_name ?? 'Doctor' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $tpl->doctor?->specialization ?? '' }}</div>
                            </td>
                            <td class="p-3 font-bold text-slate-900">
                                {{ $tpl->name }}
                                @if($tpl->description)
                                    <div class="text-[10px] font-normal text-slate-400 max-w-xs truncate">{{ $tpl->description }}</div>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">v{{ $tpl->version }}</span>
                            </td>
                            <td class="p-3 font-medium text-slate-700">
                                {{ $tpl->paper_width_mm }} × {{ $tpl->paper_height_mm }} mm
                            </td>
                            <td class="p-3 text-center capitalize text-slate-600 font-medium">
                                {{ $tpl->orientation }}
                            </td>
                            <td class="p-3 text-center">
                                @if($tpl->status === 'ACTIVE')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">ACTIVE</span>
                                @elseif($tpl->status === 'DRAFT')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">DRAFT</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">ARCHIVED</span>
                                @endif
                            </td>
                            <td class="p-3 text-center font-semibold text-slate-700">
                                {{ $tpl->elements->count() }}
                            </td>
                            <td class="p-3 text-[11px] text-slate-500 whitespace-nowrap">
                                <div>{{ $tpl->created_at?->format('d M Y') }}</div>
                                <div class="text-[10px] text-slate-400">{{ $tpl->created_at?->format('h:i A') }}</div>
                            </td>
                            <td class="p-3 text-[11px] text-slate-500 whitespace-nowrap">
                                @if($tpl->activated_at)
                                    <div class="text-emerald-700 font-semibold">{{ $tpl->activated_at->format('d M Y') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $tpl->activated_at->format('h:i A') }}</div>
                                @else
                                    <span class="text-slate-300 font-medium">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end flex-wrap gap-1.5">
                                    @if($tpl->status === 'DRAFT')
                                        <a href="{{ route('settings.prescription-pad-designer', $tpl->id) }}" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-[11px] font-bold transition-colors">
                                            Edit Draft
                                        </a>
                                    @else
                                        <a href="{{ route('settings.prescription-pad-designer', $tpl->id) }}" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-medium transition-colors" title="View template definition">
                                            View Version
                                        </a>
                                    @endif

                                    <button wire:click="duplicateTemplate({{ $tpl->id }})" title="Duplicate to create a new draft version" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold transition-colors">
                                        Duplicate
                                    </button>

                                    <a href="{{ route('settings.prescription-pad-designer', $tpl->id) }}?preview=1" class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-[11px] font-semibold transition-colors" title="Visual Preview">
                                        Preview
                                    </a>

                                    <a href="{{ route('pdf.prescription-template', ['templateId' => $tpl->id, 'mode' => 'proof']) }}" target="_blank" class="px-2 py-1 bg-sky-50 hover:bg-sky-100 text-sky-800 rounded-lg text-[11px] font-semibold transition-colors" title="View Proof PDF with reference background">
                                        Proof
                                    </a>

                                    <a href="{{ route('pdf.prescription-template', ['templateId' => $tpl->id, 'mode' => 'production']) }}" target="_blank" class="px-2 py-1 bg-teal-50 hover:bg-teal-100 text-teal-800 rounded-lg text-[11px] font-semibold transition-colors" title="Test Print PDF (data fields only at 100% actual size)">
                                        Test Print
                                    </a>

                                    @if($tpl->status !== 'ACTIVE')
                                        <button wire:click="activateTemplate({{ $tpl->id }})" wire:confirm="Activate this template? The current active template for {{ $tpl->doctor?->display_name }} will be safely archived." title="Make this the active template for this doctor" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 rounded-lg text-[11px] font-bold transition-colors">
                                            Activate
                                        </button>
                                    @endif

                                    @if($tpl->status === 'ACTIVE')
                                        <button wire:click="archiveTemplate({{ $tpl->id }})" wire:confirm="Archive this template? Doctor will fall back to legacy Doctor Pad unless another template is active." title="Archive this template" class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg text-[11px] font-semibold transition-colors">
                                            Archive
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-8 text-center text-slate-400">
                                No prescription pad templates configured yet. Click "New Template" or "Import From Legacy" to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Template Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden animate-in fade-in duration-150">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="font-bold text-slate-900 text-sm">Create Prescription Pad Template</h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form wire:submit="createTemplate" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Select Doctor *</label>
                        <select wire:model="newDoctorId" class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-hidden focus:border-blue-500">
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->display_name }} ({{ $doc->specialization }})</option>
                            @endforeach
                        </select>
                        @error('newDoctorId') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Template Name *</label>
                        <input type="text" wire:model="newName" placeholder="e.g. Dr. Sharma Standard Pad" class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-hidden focus:border-blue-500">
                        @error('newName') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Paper Size</label>
                            <select wire:model.live="newPaperSize" class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-hidden focus:border-blue-500">
                                <option value="A4">A4 (210 × 297 mm)</option>
                                <option value="A5">A5 (148 × 210 mm)</option>
                                <option value="Letter">Letter (215.9 × 279.4 mm)</option>
                                <option value="Half Letter">Half Letter (139.7 × 215.9 mm)</option>
                                <option value="Custom">Custom Size (mm)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Orientation</label>
                            <select wire:model="newOrientation" class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-hidden focus:border-blue-500">
                                <option value="portrait">Portrait</option>
                                <option value="landscape">Landscape</option>
                            </select>
                        </div>
                    </div>

                    @if($newPaperSize === 'Custom')
                        <div class="grid grid-cols-2 gap-3 bg-amber-50/50 p-3 rounded-xl border border-amber-100">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Width (mm)</label>
                                <input type="number" step="0.1" wire:model="newCustomWidthMm" class="w-full text-xs p-2 bg-white border border-slate-200 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Height (mm)</label>
                                <input type="number" step="0.1" wire:model="newCustomHeightMm" class="w-full text-xs p-2 bg-white border border-slate-200 rounded-lg">
                            </div>
                        </div>
                    @endif

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs">Create & Open Designer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Printer Calibration Modal -->
    @if($showPrinterModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden animate-in fade-in duration-150">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="font-bold text-slate-900 text-sm">Printer Calibration Settings</h3>
                    <button wire:click="$set('showPrinterModal', false)" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form wire:submit="savePrinterCalibration" class="p-5 space-y-4">
                    <p class="text-xs text-slate-500">Fine-tune physical print offsets for your clinic's printer hardware. These offsets apply globally at print time without altering template coordinates.</p>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">X Offset (mm)</label>
                            <input type="number" step="0.1" wire:model="printerOffsetX" class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-400 block mt-0.5">+ shifts right, - shifts left</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Y Offset (mm)</label>
                            <input type="number" step="0.1" wire:model="printerOffsetY" class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-400 block mt-0.5">+ shifts down, - shifts up</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Scale X (1.0 = 100%)</label>
                            <input type="number" step="0.001" wire:model="printerScaleX" class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Scale Y (1.0 = 100%)</label>
                            <input type="number" step="0.001" wire:model="printerScaleY" class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showPrinterModal', false)" class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs">Save Calibration</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
