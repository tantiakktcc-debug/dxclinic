<div id="designerRoot"
     class="flex flex-col bg-slate-100 overflow-hidden select-none"
     :class="isFullScreen ? 'fixed inset-0 z-50 w-screen h-screen' : 'h-[calc(100vh-4rem)]'"
     wire:ignore.self
     x-data="prescriptionPadDesigner({
        paperW: {{ (float)$template->paper_width_mm }},
        paperH: {{ (float)$template->paper_height_mm }},
        gridSizeMm: {{ (float)$gridSizeMm }},
        snapToGrid: {{ $snapToGrid ? 'true' : 'false' }},
        initialSelected: {{ json_encode($selectedIndices) }},
        initialSelectedIndex: {{ $selectedIndex !== null ? $selectedIndex : 'null' }},
        activeLeftTab: '{{ $activeLeftTab }}',
        isFullScreen: {{ $isFullScreen ? 'true' : 'false' }},
        sampleData: {{ json_encode($sampleData) }}
     })"
     @keydown.window="handleKeyDown($event)"
     @keyup.window="handleKeyUp($event)">

    <!-- Top Toolbar -->
    <header class="bg-white border-b border-slate-200 px-4 py-2 flex items-center justify-between shrink-0 shadow-2xs z-20">
        <div class="flex items-center gap-3">
            <button type="button"
                    @click="$dispatch('toggle-app-nav')"
                    id="btn_toggle_app_nav"
                    class="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors border border-slate-200"
                    title="Toggle Global Application Nav">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
            </button>

            <a href="{{ route('settings.prescription-pad-templates') }}" class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors" title="Back to Templates">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>

            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-bold text-slate-900 text-sm tracking-tight">{{ $template->name }}</h1>
                    <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">v{{ $template->version }}</span>
                    @if($template->status === 'ACTIVE')
                        <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">ACTIVE</span>
                    @elseif($template->status === 'DRAFT')
                        <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">DRAFT</span>
                    @else
                        <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">ARCHIVED</span>
                    @endif

                    <!-- Revision 38 Dirty State Indicator -->
                    <span id="dirty_state_indicator" class="ml-1 text-[11px]">
                        @if($isDirty)
                            <span class="inline-flex items-center gap-1 font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200" title="You have unsaved changes">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Unsaved changes
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200" title="All changes saved to database">
                                ✓ Saved
                            </span>
                        @endif
                    </span>
                </div>
                <div class="text-[11px] text-slate-500">
                    {{ $template->doctor?->display_name }} | {{ $template->paper_width_mm }} × {{ $template->paper_height_mm }} mm ({{ ucfirst($template->orientation) }})
                </div>
            </div>
        </div>

        <!-- Middle Controls: Tools, Undo/Redo, Alignments, Viewports, Zoom, Grid -->
        <div class="flex items-center gap-2">
            <!-- Tool Selector (Pointer / Hand) -->
            <div class="flex items-center bg-slate-100 p-0.5 rounded-xl border border-slate-200 text-xs shadow-2xs">
                <button type="button"
                        id="btn_tool_select"
                        @click="setTool('select')"
                        class="px-2.5 py-1 rounded-lg font-bold transition-all flex items-center gap-1.5"
                        :class="activeTool === 'select' ? 'bg-white text-blue-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        title="Selection / Pointer Tool (V)">
                    <span>↖ Select</span>
                    <kbd class="text-[9px] px-1 py-0.2 bg-slate-200 rounded text-slate-600">V</kbd>
                </button>
                <button type="button"
                        id="btn_tool_hand"
                        @click="setTool('hand')"
                        class="px-2.5 py-1 rounded-lg font-bold transition-all flex items-center gap-1.5"
                        :class="activeTool === 'hand' ? 'bg-white text-blue-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        title="Hand / Pan Tool (H or Space + Drag)">
                    <span>✋ Hand</span>
                    <kbd class="text-[9px] px-1 py-0.2 bg-slate-200 rounded text-slate-600">H</kbd>
                </button>
            </div>

            <!-- Revision 38 Undo / Redo History Controls -->
            <div class="flex items-center bg-slate-100 p-0.5 rounded-xl border border-slate-200 text-xs shadow-2xs">
                <button type="button"
                        wire:click="undo"
                        id="btn_undo"
                        class="px-2 py-1 rounded-lg font-bold text-slate-700 hover:text-slate-900 hover:bg-white transition-all flex items-center gap-1"
                        title="Undo (Ctrl+Z)">
                    <span>↶</span>
                    <span class="hidden sm:inline">Undo</span>
                </button>
                <button type="button"
                        wire:click="redo"
                        id="btn_redo"
                        class="px-2 py-1 rounded-lg font-bold text-slate-700 hover:text-slate-900 hover:bg-white transition-all flex items-center gap-1"
                        title="Redo (Ctrl+Shift+Z or Ctrl+Y)">
                    <span>↷</span>
                    <span class="hidden sm:inline">Redo</span>
                </button>
            </div>

            <!-- Panel Toggles -->
            <div class="flex items-center bg-slate-100 p-0.5 rounded-xl border border-slate-200 text-xs">
                <button type="button"
                        @click="showToolbox = !showToolbox" 
                        id="btn_toggle_toolbox"
                        class="px-2.5 py-1 rounded-lg font-bold transition-colors"
                        :class="showToolbox ? 'bg-white text-blue-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                        title="Toggle Sidebar (Toolbox & Layers)">
                    🧰 Sidebar
                </button>
                <button type="button"
                        @click="showProperties = !showProperties" 
                        id="btn_toggle_properties"
                        class="px-2.5 py-1 rounded-lg font-bold transition-colors"
                        :class="showProperties ? 'bg-white text-blue-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                        title="Toggle Properties Panel">
                    ⚙️ Properties
                </button>
            </div>

            <!-- Fullscreen Workspace Toggle -->
            <button type="button"
                    @click="toggleFullScreen()" id="btn_toggle_fullscreen"
                    class="px-2.5 py-1.5 rounded-xl border border-slate-200 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors flex items-center gap-1.5"
                    :title="isFullScreen ? 'Exit Full Screen Workspace' : 'Enter Full Screen Workspace'">
                <span x-text="isFullScreen ? '🪟 Normal' : '🖥️ Full Screen'"></span>
            </button>

            <!-- Proof / Production Mode Toggle -->
            <div class="flex items-center bg-slate-100 p-0.5 rounded-xl border border-slate-200 text-xs">
                <button wire:click="$set('previewMode', 'proof')" 
                        class="px-2.5 py-1 rounded-lg font-bold transition-colors {{ $previewMode === 'proof' ? 'bg-white text-blue-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}"
                        title="Proof Mode shows reference scan background for alignment check">
                    Proof
                </button>
                <button wire:click="$set('previewMode', 'production')" 
                        class="px-2.5 py-1 rounded-lg font-bold transition-colors {{ $previewMode === 'production' ? 'bg-white text-blue-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}"
                        title="Production Mode displays dynamic text/shapes only (no scan)">
                    Production
                </button>
            </div>

            <!-- Grid & Snap Controls -->
            <div class="hidden lg:flex items-center gap-1.5 border-l border-slate-200 pl-2">
                <button wire:click="$toggle('showGrid')" class="px-2 py-1 rounded-lg text-xs font-semibold {{ $showGrid ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-600' }}" title="Toggle millimeter grid">
                    Grid
                </button>
                <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-lg border border-slate-200 text-xs">
                    <span class="text-[10px] text-slate-500 font-bold pl-1">Size:</span>
                    <select id="select_grid_spacing"
                            @change="setGridSpacing($event.target.value)"
                            class="bg-white border-0 rounded text-[11px] font-bold py-0.5 px-1 focus:ring-0 text-slate-700">
                        <option value="1" {{ $gridSizeMm == 1 ? 'selected' : '' }}>1mm</option>
                        <option value="2" {{ $gridSizeMm == 2 ? 'selected' : '' }}>2mm</option>
                        <option value="5" {{ $gridSizeMm == 5 ? 'selected' : '' }}>5mm</option>
                        <option value="10" {{ $gridSizeMm == 10 ? 'selected' : '' }}>10mm</option>
                        <option value="20" {{ $gridSizeMm == 20 ? 'selected' : '' }}>20mm</option>
                    </select>
                </div>
                <button wire:click="$toggle('snapToGrid')" id="btn_toggle_snap" class="px-2 py-1 rounded-lg text-xs font-semibold {{ $snapToGrid ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-600' }}" title="Snap positions to grid">
                    Snap
                </button>
            </div>

            <!-- Custom Guides Controls -->
            <div class="hidden xl:flex items-center gap-1 bg-slate-100 p-0.5 rounded-xl border border-slate-200 text-xs">
                <button type="button" id="btn_add_v_guide" @click="addCustomGuide('vertical')" class="px-2 py-1 rounded-lg font-bold text-slate-700 hover:bg-white text-[11px]" title="Add Vertical Guideline">
                    +V Guide
                </button>
                <button type="button" id="btn_add_h_guide" @click="addCustomGuide('horizontal')" class="px-2 py-1 rounded-lg font-bold text-slate-700 hover:bg-white text-[11px]" title="Add Horizontal Guideline">
                    +H Guide
                </button>
                <button type="button" id="btn_clear_guides" @click="clearCustomGuides()" class="px-1.5 py-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-white text-[10px]" title="Clear All Custom Guidelines">
                    ✕
                </button>
            </div>

            <!-- Zoom Controls & Reset View -->
            <div class="flex items-center bg-slate-100 rounded-xl border border-slate-200 p-0.5 text-xs">
                <button type="button" @click="changeZoom(zoom - 0.25)" id="btn_zoom_out" class="px-2 py-1 text-slate-600 hover:text-slate-900 font-bold">−</button>
                <span id="zoom_percentage_display" class="px-2 text-[11px] font-semibold text-slate-700" x-text="`${Math.round(zoom * 100)}%`">{{ round($zoom * 100) }}%</span>
                <button type="button" @click="changeZoom(zoom + 0.25)" id="btn_zoom_in" class="px-2 py-1 text-slate-600 hover:text-slate-900 font-bold">+</button>
                <button type="button" @click="changeZoom(1.0)" id="btn_zoom_reset" class="px-1.5 py-1 text-[10px] text-slate-400 hover:text-slate-700 font-medium">100%</button>
                <button type="button" id="btn_reset_view" @click="resetView()" class="px-1.5 py-1 text-[10px] bg-slate-200/80 hover:bg-slate-300 rounded text-slate-700 font-bold ml-1" title="Reset View (Pan 0,0 & Zoom 100%)">Reset View</button>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2">
            <!-- Test Print Dropdown -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" id="btn_test_print_dropdown" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Test Print ▾
                </button>
                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-slate-200 py-1 text-xs z-50">
                    <button wire:click="downloadTestPdf('production')" id="btn_test_print_production" @click="open = false" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-semibold text-slate-700">
                        Production PDF (Data Only)
                    </button>
                    <button wire:click="downloadTestPdf('proof')" id="btn_test_print_proof" @click="open = false" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-semibold text-slate-700">
                        Proof PDF (With Scan)
                    </button>
                </div>
            </div>

            @if($template->status === 'DRAFT')
                <button id="btn_save_draft" wire:click="saveDraft" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl shadow-2xs transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    Save Draft
                </button>

                <button wire:click="activate" wire:confirm="Activate this template? Previous active template will be safely archived." class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-2xs transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Activate
                </button>
            @else
                <button wire:click="duplicate" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-2xs transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    Edit (New Draft)
                </button>
            @endif
        </div>
    </header>

    @if(session()->has('success') || $flashMessage)
        <div id="flash_success_banner" class="bg-emerald-600 text-white text-xs font-bold px-4 py-2 flex items-center justify-between shadow-xs z-30 shrink-0">
            <span>✓ {{ session('success') ?? $flashMessage }}</span>
            <button type="button" @click="$el.parentElement.remove()" class="text-white hover:text-emerald-100 font-bold ml-2">✕</button>
        </div>
    @endif
    @if(session()->has('error'))
        <div id="flash_error_banner" class="bg-rose-600 text-white text-xs font-bold px-4 py-2 flex items-center justify-between shadow-xs z-30 shrink-0">
            <span>⚠️ {{ session('error') }}</span>
            <button type="button" @click="$el.parentElement.remove()" class="text-white hover:text-rose-100 font-bold ml-2">✕</button>
        </div>
    @endif

    <!-- Main Workspace Container -->
    <div class="flex-1 flex overflow-hidden">
        <!-- LEFT: Sidebar (Toolbox & Layer Manager Tabs) -->
        <aside x-show="showToolbox" x-transition class="w-72 bg-white border-r border-slate-200 flex flex-col shrink-0 overflow-hidden z-10">
            <!-- Sidebar Tab Navigation -->
            <div class="flex border-b border-slate-200 bg-slate-50 text-xs font-bold">
                <button type="button"
                        id="tab_toolbox"
                        @click="activeLeftTab = 'toolbox'; @this.setLeftTab('toolbox')"
                        class="flex-1 py-2 text-center border-b-2 transition-colors flex items-center justify-center gap-1.5"
                        :class="activeLeftTab === 'toolbox' ? 'border-blue-600 text-blue-700 bg-white' : 'border-transparent text-slate-500 hover:text-slate-800'">
                    <span>🧰 Toolbox</span>
                </button>
                <button type="button"
                        id="tab_layers"
                        @click="activeLeftTab = 'layers'; @this.setLeftTab('layers')"
                        class="flex-1 py-2 text-center border-b-2 transition-colors flex items-center justify-center gap-1.5"
                        :class="activeLeftTab === 'layers' ? 'border-blue-600 text-blue-700 bg-white' : 'border-transparent text-slate-500 hover:text-slate-800'">
                    <span>📑 Layers ({{ count($elements) }})</span>
                </button>
            </div>

            <!-- Tab 1: Field Toolbox -->
            <div x-show="activeLeftTab === 'toolbox'" class="flex-1 overflow-y-auto p-3 space-y-4">
                <!-- Scan Upload Card -->
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-700">Pad Scan (Reference)</span>
                        @if($template->background_path)
                            <span class="text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">Loaded</span>
                        @endif
                    </div>

                    @if($template->background_path)
                        <div class="relative rounded-lg overflow-hidden border border-slate-200 mb-2 max-h-24 bg-white">
                            <img src="{{ asset($template->background_path) }}" alt="Pad Scan" class="w-full h-auto object-contain opacity-80">
                        </div>
                        <div class="flex items-center justify-between text-[10px] text-slate-500 mb-2">
                            <span>{{ $template->background_width_px }} × {{ $template->background_height_px }} px</span>
                            <button wire:click="removeScan" class="text-rose-600 hover:text-rose-800 font-bold">Remove</button>
                        </div>
                    @endif

                    <form wire:submit="uploadScan" class="space-y-2">
                        <input type="file" wire:model="scanFile" accept="image/png,image/jpeg,image/jpg" class="w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        @error('scanFile') <span class="text-rose-600 text-[10px] block">{{ $message }}</span> @enderror
                        @if($scanFile)
                            <button type="submit" class="w-full py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-[11px] font-bold">Upload Scan</button>
                        @endif
                    </form>
                </div>

                <!-- Custom / Static Shapes & Tools -->
                <div class="border border-slate-200 rounded-xl p-2.5 bg-slate-50 space-y-1.5">
                    <span class="text-[11px] font-bold text-slate-600 uppercase block mb-1">Static Elements & Shapes</span>
                    <button type="button" id="btn_add_static_text" @click="createStaticText()" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between transition-colors shadow-2xs">
                        <span>📝 Static Text</span>
                        <span class="font-bold text-blue-600">+</span>
                    </button>
                    <button type="button" id="btn_add_line" @click="createLine()" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between transition-colors shadow-2xs">
                        <span>➖ Horizontal Line</span>
                        <span class="font-bold text-blue-600">+</span>
                    </button>
                    <button type="button" id="btn_add_rectangle" @click="createRectangle()" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between transition-colors shadow-2xs">
                        <span>⬜ Box / Rectangle</span>
                        <span class="font-bold text-blue-600">+</span>
                    </button>
                    <!-- Image / Logo Upload Tool -->
                    <div class="relative">
                        <input type="file"
                               id="image_element_uploader"
                               wire:model="imageUploadFile"
                               accept="image/png,image/jpeg,image/jpg"
                               class="hidden">
                        <button type="button"
                                id="btn_add_image"
                                @click="createImage()"
                                class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between transition-colors shadow-2xs">
                            <span class="flex items-center gap-1.5">
                                <span>🖼️ Image / Logo</span>
                            </span>
                            <span class="font-bold text-blue-600">+</span>
                        </button>
                    </div>
                </div>

                <!-- Field Categories Accordion -->
                @foreach($fieldCatalog as $category => $fields)
                    <div x-data="{ open: true }" class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-2xs">
                        <button type="button" @click="open = !open" class="w-full px-3 py-2 bg-slate-50 hover:bg-slate-100/80 flex items-center justify-between text-left text-xs font-bold text-slate-700 transition-colors">
                            <span>{{ $category }}</span>
                            <span x-text="open ? '−' : '+'" class="text-slate-400 font-mono"></span>
                        </button>
                        <div x-show="open" class="p-2 space-y-1">
                            @foreach($fields as $fKey => $fData)
                                <button type="button" 
                                        id="btn_add_field_{{ str_replace('.', '_', $fKey) }}"
                                        @click="createField('{{ $fKey }}')" 
                                        class="w-full px-2.5 py-1.5 rounded-lg text-left text-xs hover:bg-blue-50 hover:text-blue-700 transition-colors flex items-center justify-between group border border-transparent hover:border-blue-100"
                                        title="Click to add {{ $fData['label'] }} to pad">
                                    <span class="font-medium text-slate-700 group-hover:text-blue-700 truncate mr-2">{{ $fData['label'] }}</span>
                                    <span class="text-slate-300 group-hover:text-blue-500 text-xs font-bold">+</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Tab 2: Revision 38 Professional Layer Management Panel -->
            <div x-show="activeLeftTab === 'layers'" id="layer_panel_container" class="flex-1 overflow-y-auto p-2 space-y-1">
                <div class="px-2 py-1 flex items-center justify-between text-[11px] font-bold text-slate-500 uppercase border-b border-slate-100 pb-1 mb-1">
                    <span>Stack Order (Top → Bottom)</span>
                    <span>Actions</span>
                </div>

                @php
                    $reversedElements = array_reverse($elements, true);
                @endphp

                @forelse($reversedElements as $idx => $elem)
                    @php
                        $isSelected = ($selectedIndex === $idx || (is_array($selectedIndices) && in_array($idx, $selectedIndices)));
                        $typeIcon = match($elem['element_type']) {
                            'IMAGE' => '🖼️',
                            'STATIC_TEXT' => '📝',
                            'LINE' => '➖',
                            'RECTANGLE' => '⬜',
                            default => '🔤'
                        };
                        $shortLabel = match($elem['element_type']) {
                            'STATIC_TEXT' => ($elem['config_json']['static_text'] ?? 'Static Text'),
                            'IMAGE' => 'Image / Logo',
                            'LINE' => 'Line',
                            'RECTANGLE' => 'Box',
                            default => $elem['field_key']
                        };
                    @endphp
                    <div id="layer_row_{{ $idx }}"
                         class="group p-1.5 rounded-lg border text-xs flex items-center justify-between transition-colors cursor-pointer {{ $isSelected ? 'bg-blue-50 border-blue-300 shadow-2xs' : 'bg-white border-slate-200 hover:bg-slate-50' }}"
                         @click="selectElem({{ $idx }}, $event)">
                        <div class="flex items-center gap-1.5 truncate flex-1 mr-2">
                            <span class="text-sm select-none">{{ $typeIcon }}</span>
                            <div class="truncate">
                                <span class="font-bold text-slate-800 truncate block text-[11px]">{{ $shortLabel }}</span>
                                <span class="text-[9px] text-slate-400 font-mono">#{{ $idx + 1 }} · {{ $elem['x_mm'] }},{{ $elem['y_mm'] }}mm</span>
                            </div>
                        </div>

                        <!-- Layer Action Controls -->
                        <div class="flex items-center gap-1 select-none shrink-0" @click.stop>
                            <!-- Visibility Toggle -->
                            <button type="button"
                                    wire:click="toggleVisibility({{ $idx }})"
                                    class="p-1 rounded text-slate-500 hover:bg-slate-200 transition-colors text-xs"
                                    title="{{ $elem['is_visible'] ? 'Hide Element' : 'Show Element' }}">
                                {{ $elem['is_visible'] ? '👁' : '🙈' }}
                            </button>

                            <!-- Lock Toggle -->
                            <button type="button"
                                    wire:click="toggleLock({{ $idx }})"
                                    class="p-1 rounded text-slate-500 hover:bg-slate-200 transition-colors text-xs"
                                    title="{{ $elem['is_locked'] ? 'Unlock Element' : 'Lock Element' }}">
                                {{ $elem['is_locked'] ? '🔒' : '🔓' }}
                            </button>

                            <!-- Up/Down Stacking -->
                            <div class="flex flex-col">
                                <button type="button"
                                        wire:click="bringForward({{ $idx }})"
                                        class="text-[9px] text-slate-400 hover:text-slate-800 leading-none px-0.5"
                                        title="Bring Forward">▲</button>
                                <button type="button"
                                        wire:click="sendBackward({{ $idx }})"
                                        class="text-[9px] text-slate-400 hover:text-slate-800 leading-none px-0.5"
                                        title="Send Backward">▼</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-slate-400 text-xs">
                        No elements placed yet. Add fields from Toolbox.
                    </div>
                @endforelse
            </div>
        </aside>

        <!-- CENTER: Viewport Canvas with Millimeter Rulers, Realtime Transformations, Smart Guides -->
        <main id="designerWorkspace"
              class="flex-1 relative overflow-hidden bg-slate-300/80 select-none cursor-default flex flex-col"
              :class="{
                  'cursor-grab': (activeTool === 'hand' || isSpaceHeld) && !isPanning,
                  'cursor-grabbing': (activeTool === 'hand' || isSpaceHeld) && isPanning,
                  'cursor-crosshair': isMarquee
              }"
              @mousedown="onWorkspaceMouseDown($event)"
              @mousemove="onMouseMove($event)"
              @mouseup="onMouseUp($event)"
              @mouseleave="onMouseUp($event)"
              @wheel.prevent="handleWheel($event)">

            @php
                $basePxPerMm = 3.7795275591;
                $canvasWidthPx = round($template->paper_width_mm * $basePxPerMm);
                $canvasHeightPx = round($template->paper_height_mm * $basePxPerMm);
                $rulerThickness = 20;
            @endphp

            <!-- Viewport Transformed Surface -->
            <div id="viewportSurface"
                 class="flex-1 relative origin-top-left overflow-visible"
                 :style="`transform: translate(${panX}px, ${panY}px) scale(${zoom}); transform-origin: 0 0; will-change: transform;`">

                <div class="inline-flex flex-col p-10">
                    <!-- Top Horizontal Millimeter Ruler -->
                    <div class="flex items-center" style="margin-left: {{ $rulerThickness }}px;">
                        <svg style="width: {{ $canvasWidthPx }}px; height: {{ $rulerThickness }}px;"
                             viewBox="0 0 {{ $template->paper_width_mm }} {{ $rulerThickness }}"
                             class="bg-slate-200 border-t border-r border-slate-400 select-none shadow-xs">
                            @for($m = 0; $m <= $template->paper_width_mm; $m += 10)
                                <line x1="{{ $m }}" y1="8" x2="{{ $m }}" y2="{{ $rulerThickness }}" stroke="#64748b" stroke-width="0.3"/>
                                <text x="{{ $m + 0.5 }}" y="7" font-size="4" fill="#475569" font-family="sans-serif">{{ $m }}</text>
                                @for($sub = 1; $sub < 10; $sub++)
                                    @if($m + $sub <= $template->paper_width_mm)
                                        <line x1="{{ $m + $sub }}" y1="{{ $sub === 5 ? 12 : 15 }}" x2="{{ $m + $sub }}" y2="{{ $rulerThickness }}" stroke="#94a3b8" stroke-width="0.15"/>
                                    @endif
                                @endfor
                            @endfor
                        </svg>
                    </div>

                    <div class="inline-flex items-start">
                        <!-- Left Vertical Millimeter Ruler -->
                        <svg style="width: {{ $rulerThickness }}px; height: {{ $canvasHeightPx }}px;"
                             viewBox="0 0 {{ $rulerThickness }} {{ $template->paper_height_mm }}"
                             class="bg-slate-200 border-l border-b border-slate-400 select-none shrink-0 shadow-xs">
                            @for($m = 0; $m <= $template->paper_height_mm; $m += 10)
                                <line x1="8" y1="{{ $m }}" x2="{{ $rulerThickness }}" y2="{{ $m }}" stroke="#64748b" stroke-width="0.3"/>
                                <text x="1" y="{{ $m + 3 }}" font-size="3.5" fill="#475569" font-family="sans-serif">{{ $m }}</text>
                                @for($sub = 1; $sub < 10; $sub++)
                                    @if($m + $sub <= $template->paper_height_mm)
                                        <line x1="{{ $sub === 5 ? 12 : 15 }}" y1="{{ $m + $sub }}" x2="{{ $rulerThickness }}" y2="{{ $m + $sub }}" stroke="#94a3b8" stroke-width="0.15"/>
                                    @endif
                                @endfor
                            @endfor
                        </svg>

                        <!-- Canvas Container -->
                        <div id="canvasSheetContainer"
                             class="relative bg-white shadow-2xl border border-slate-400"
                             style="width: {{ $canvasWidthPx }}px; height: {{ $canvasHeightPx }}px;">

                            <!-- SVG Vector Canvas -->
                            <svg id="designerCanvas"
                                 class="w-full h-full block"
                                 viewBox="0 0 {{ $template->paper_width_mm }} {{ $template->paper_height_mm }}"
                                 preserveAspectRatio="none"
                                 @contextmenu.prevent="closeContextMenu()">

                                <defs>
                                    <pattern id="grid5mm" width="5" height="5" patternUnits="userSpaceOnUse">
                                        <path d="M 5 0 L 0 0 0 5" fill="none" stroke="#e2e8f0" stroke-width="0.15"/>
                                    </pattern>
                                    <pattern id="grid10mm" width="10" height="10" patternUnits="userSpaceOnUse">
                                        <rect width="10" height="10" fill="url(#grid5mm)"/>
                                        <path d="M 10 0 L 0 0 0 10" fill="none" stroke="#cbd5e1" stroke-width="0.3"/>
                                    </pattern>
                                </defs>

                                <!-- Sheet Background -->
                                <rect id="canvasBackground" width="{{ $template->paper_width_mm }}" height="{{ $template->paper_height_mm }}" fill="#ffffff" @contextmenu.prevent="closeContextMenu()" />

                                <!-- Proof Mode Reference Scan -->
                                @if($previewMode === 'proof' && $template->background_path)
                                    <image href="{{ asset($template->background_path) }}" 
                                           x="0" y="0" 
                                           width="{{ $template->paper_width_mm }}" 
                                           height="{{ $template->paper_height_mm }}" 
                                           preserveAspectRatio="xMidYMid meet" 
                                           opacity="0.9"
                                           pointer-events="none" />
                                @endif

                                <!-- Grid Layer -->
                                @if($showGrid)
                                    <rect width="{{ $template->paper_width_mm }}" height="{{ $template->paper_height_mm }}" fill="url(#grid10mm)" pointer-events="none" />
                                @endif

                                <!-- Rendered Elements -->
                                @foreach($elements as $idx => $elem)
                                    @php
                                        $isSelected = ($selectedIndex === $idx || (is_array($selectedIndices) && in_array($idx, $selectedIndices)));
                                        $isSingleSelected = ($selectedIndex === $idx && count($selectedIndices ?? []) <= 1);
                                        $cfg = $elem['config_json'] ?? [];
                                        $val = '';
                                        if ($elem['element_type'] === 'STATIC_TEXT') {
                                            $val = $cfg['static_text'] ?? 'Static Text';
                                        } elseif ($elem['element_type'] === 'LINE' || $elem['element_type'] === 'RECTANGLE') {
                                            $val = '';
                                        } else {
                                            $rawSample = $sampleData[$elem['field_key']] ?? $elem['field_key'];
                                            $showLabel = (bool)($cfg['show_label'] ?? false);
                                            if ($showLabel && $rawSample !== '' && $rawSample !== null) {
                                                $labelText = !empty($cfg['label_text']) ? $cfg['label_text'] : \App\Services\PrescriptionDataMapper::getDefaultLabelForField($elem['field_key']);
                                                $val = $labelText . ': ' . $rawSample;
                                            } else {
                                                $val = $rawSample;
                                            }
                                        }

                                        $fontPt = (float)($elem['font_size'] ?? 10.0);
                                        $fontSizeMmPx = $fontPt * (25.4 / 72);
                                    @endphp

                                    <g id="elem_{{ $idx }}"
                                       data-element-id="{{ $elem['id'] ?? $idx }}"
                                       data-index="{{ $idx }}"
                                       data-element-key="{{ $elem['field_key'] }}"
                                       data-element-type="{{ $elem['element_type'] }}"
                                       data-x-mm="{{ $elem['x_mm'] }}"
                                       data-y-mm="{{ $elem['y_mm'] }}"
                                       data-width-mm="{{ $elem['width_mm'] }}"
                                       data-height-mm="{{ $elem['height_mm'] }}"
                                       data-rotation="{{ $elem['rotation'] }}"
                                       data-font-size="{{ (float)$elem['font_size'] }}"
                                       data-locked="{{ $elem['is_locked'] ? '1' : '0' }}"
                                       data-visible="{{ $elem['is_visible'] ? '1' : '0' }}"
                                       data-group-id="{{ $cfg['group_id'] ?? '' }}"
                                       data-lock-aspect="{{ (!isset($cfg['lock_aspect_ratio']) || $cfg['lock_aspect_ratio']) ? '1' : '0' }}"
                                       data-aspect-ratio="{{ $cfg['aspect_ratio'] ?? ($elem['width_mm'] / ($elem['height_mm'] ?: 1)) }}"
                                       transform="translate({{ $elem['x_mm'] }}, {{ $elem['y_mm'] }}) rotate({{ $elem['rotation'] }})"
                                       @mousedown="startDrag({{ $idx }}, $event)"
                                       @click="selectElem({{ $idx }}, $event)"
                                       @contextmenu.prevent.stop="openContextMenu({{ $idx }}, $event)"
                                       @dblclick="if ('{{ $elem['element_type'] }}' === 'STATIC_TEXT' && !{{ $elem['is_locked'] ? 'true' : 'false' }}) startInlineEditing({{ $idx }})"
                                       class="group select-none"
                                       :class="activeTool === 'hand' ? 'cursor-grab' : ({{ $elem['is_locked'] ? 'true' : 'false' }} ? 'cursor-not-allowed' : 'cursor-move')"
                                       style="pointer-events: all;">

                                        <!-- Hitbox for unified click & drag targeting -->
                                        <rect class="elem-hitbox" x="0" y="0" width="{{ $elem['width_mm'] }}" height="{{ $elem['height_mm'] }}" fill="#ffffff" opacity="0.001" style="pointer-events: all; cursor: inherit;" />

                                        @if(!$elem['is_visible'])
                                            <!-- Editor-only placeholder for hidden object -->
                                            <rect class="elem-hidden-placeholder"
                                                  x="0" y="0" width="{{ $elem['width_mm'] }}" height="{{ $elem['height_mm'] }}"
                                                  fill="#f8fafc" stroke="#94a3b8" stroke-width="0.35" stroke-dasharray="2,1.5" opacity="0.85" />
                                            <text x="{{ $elem['width_mm'] / 2 }}" y="{{ $elem['height_mm'] / 2 }}"
                                                  font-size="{{ min(4.0, max(2.0, $elem['height_mm'] * 0.4)) }}"
                                                  fill="#64748b" font-family="sans-serif" font-weight="bold"
                                                  text-anchor="middle" dominant-baseline="middle" pointer-events="none">
                                                👁‍🗨 Hidden ({{ $elem['field_key'] }})
                                            </text>
                                        @elseif($elem['element_type'] === 'LINE')
                                            @php
                                                $lStyle = $cfg['line_style'] ?? 'solid';
                                                $lWidth = (float)($cfg['line_width_mm'] ?? 0.5);
                                                $lDash = $lStyle === 'dashed' ? '3,3' : ($lStyle === 'dotted' ? '1,1' : 'none');
                                            @endphp
                                            <line class="elem-line"
                                                  x1="0" y1="{{ $elem['height_mm'] / 2 }}" x2="{{ $elem['width_mm'] }}" y2="{{ $elem['height_mm'] / 2 }}"
                                                  stroke="{{ $elem['color'] }}" 
                                                  stroke-width="{{ $lWidth }}"
                                                  stroke-dasharray="{{ $lDash }}" />
                                        @elseif($elem['element_type'] === 'RECTANGLE')
                                            @php
                                                $bStyle = $cfg['border_style'] ?? 'solid';
                                                $bWidth = (float)($cfg['border_width_mm'] ?? 0.3);
                                                $bRadius = (float)($cfg['border_radius_mm'] ?? 0.0);
                                                $fill = $cfg['fill_color'] ?? 'transparent';
                                                $bDash = $bStyle === 'dashed' ? '3,3' : ($bStyle === 'dotted' ? '1,1' : 'none');
                                            @endphp
                                            <rect class="elem-rect"
                                                  x="0" y="0" width="{{ $elem['width_mm'] }}" height="{{ $elem['height_mm'] }}"
                                                  fill="{{ $fill === 'transparent' ? 'none' : $fill }}" 
                                                  stroke="{{ $elem['color'] }}" 
                                                  stroke-width="{{ $bWidth }}"
                                                  stroke-dasharray="{{ $bDash }}"
                                                  rx="{{ $bRadius }}" ry="{{ $bRadius }}" />
                                        @elseif($elem['element_type'] === 'IMAGE')
                                            @php
                                                $imgPath = $cfg['image_path'] ?? '';
                                                $imgUrl = !empty($imgPath) ? asset($imgPath) : '';
                                                $opacity = isset($cfg['opacity']) ? (float)$cfg['opacity'] : 1.0;
                                            @endphp
                                            @if($imgUrl)
                                                <image class="elem-image"
                                                       href="{{ $imgUrl }}"
                                                       x="0" y="0"
                                                       width="{{ $elem['width_mm'] }}"
                                                       height="{{ $elem['height_mm'] }}"
                                                       preserveAspectRatio="none"
                                                       opacity="{{ $opacity }}" />
                                            @else
                                                <rect width="{{ $elem['width_mm'] }}" height="{{ $elem['height_mm'] }}" fill="#f1f5f9" stroke="#cbd5e1" stroke-dasharray="1,1" />
                                                <text x="{{ $elem['width_mm'] / 2 }}" y="{{ $elem['height_mm'] / 2 }}" font-size="3" fill="#94a3b8" text-anchor="middle" dominant-baseline="middle">Image</text>
                                            @endif
                                        @elseif($elem['element_type'] === 'STATIC_TEXT')
                                            @php
                                                $textAlign = $elem['text_align'] ?? 'left';
                                                $textAnchor = ($textAlign === 'center') ? 'middle' : (($textAlign === 'right') ? 'end' : 'start');
                                                $textX = ($textAlign === 'center') ? ($elem['width_mm'] / 2) : (($textAlign === 'right') ? $elem['width_mm'] : 0);
                                                $lines = explode("\n", $val);
                                                $lineHeight = (float)($elem['line_height'] ?? 1.15);
                                                $dyStep = $fontSizeMmPx * $lineHeight;
                                            @endphp
                                            <text class="elem-rendered-text"
                                                  x-show="inlineEditIndex !== {{ $idx }}"
                                                  x="{{ $textX }}"
                                                  y="0"
                                                  text-anchor="{{ $textAnchor }}"
                                                  dominant-baseline="text-before-edge"
                                                  font-family="{{ $elem['font_family'] }}, sans-serif"
                                                  font-size="{{ $fontSizeMmPx }}"
                                                  font-weight="{{ $elem['font_weight'] }}"
                                                  font-style="{{ $elem['font_style'] }}"
                                                  fill="{{ $elem['color'] }}"
                                                  pointer-events="none"
                                                  user-select="none">@foreach($lines as $lIdx => $line)<tspan xml:space="preserve" x="{{ $textX }}" @if($lIdx > 0) dy="{{ $dyStep }}" @endif>{{ $line }}</tspan>@endforeach</text>
                                            <foreignObject x="0" y="0" width="{{ $elem['width_mm'] }}" height="{{ $elem['height_mm'] }}"
                                                           style="overflow: visible;"
                                                           :style="(inlineEditIndex === {{ $idx }} ? 'pointer-events: auto;' : 'pointer-events: none;') + '; overflow: visible;'">
                                                <div xmlns="http://www.w3.org/1999/xhtml"
                                                     style="display: none; vertical-align: top; margin: 0; padding: 0; white-space: pre-wrap; overflow: visible;"
                                                     class="legacy-fo-div">{{ $val }}</div>
                                                <textarea xmlns="http://www.w3.org/1999/xhtml"
                                                          id="inline_editor_{{ $idx }}"
                                                          x-show="inlineEditIndex === {{ $idx }}"
                                                          @click.stop
                                                          @mousedown.stop
                                                          @blur="commitInlineEditing({{ $idx }}, $event.target.value)"
                                                          @keydown.escape="cancelInlineEditing()"
                                                          @keydown.enter="if (!$event.shiftKey) { commitInlineEditing({{ $idx }}, $event.target.value); $event.preventDefault(); }"
                                                          style="display: block;
                                                                 margin: 0;
                                                                 padding: 0;
                                                                 width: 100%;
                                                                 height: 100%;
                                                                 font-family: {{ $elem['font_family'] }}, sans-serif;
                                                                 font-size: {{ $fontSizeMmPx }}px;
                                                                 font-weight: {{ $elem['font_weight'] }};
                                                                 font-style: {{ $elem['font_style'] }};
                                                                 text-align: {{ $elem['text_align'] }};
                                                                 vertical-align: top;
                                                                 line-height: {{ $elem['line_height'] ?? 1.15 }};
                                                                 color: {{ $elem['color'] }};
                                                                 background: rgba(255, 255, 255, 0.95);
                                                                 border: 1px dashed #3b82f6;
                                                                 outline: none;
                                                                 resize: none;
                                                                 white-space: pre-wrap;
                                                                 box-sizing: border-box;
                                                                 pointer-events: auto;">{{ $val }}</textarea>
                                            </foreignObject>
                                        @else
                                            @php
                                                $textAlign = $elem['text_align'] ?? 'left';
                                                $textAnchor = ($textAlign === 'center') ? 'middle' : (($textAlign === 'right') ? 'end' : 'start');
                                                $textX = ($textAlign === 'center') ? ($elem['width_mm'] / 2) : (($textAlign === 'right') ? $elem['width_mm'] : 0);
                                                $lines = explode("\n", $val);
                                                $lineHeight = (float)($elem['line_height'] ?? 1.15);
                                                $dyStep = $fontSizeMmPx * $lineHeight;
                                            @endphp
                                            <text class="elem-rendered-text"
                                                  x="{{ $textX }}"
                                                  y="0"
                                                  text-anchor="{{ $textAnchor }}"
                                                  dominant-baseline="text-before-edge"
                                                  font-family="{{ $elem['font_family'] }}, sans-serif"
                                                  font-size="{{ $fontSizeMmPx }}"
                                                  font-weight="{{ $elem['font_weight'] }}"
                                                  font-style="{{ $elem['font_style'] }}"
                                                  fill="{{ $elem['color'] }}"
                                                  pointer-events="none"
                                                  user-select="none">@foreach($lines as $lIdx => $line)<tspan xml:space="preserve" x="{{ $textX }}" @if($lIdx > 0) dy="{{ $dyStep }}" @endif>{{ $line }}</tspan>@endforeach</text>
                                            <foreignObject x="0" y="0" width="{{ $elem['width_mm'] }}" height="{{ $elem['height_mm'] }}" style="overflow: visible; pointer-events: none; display: none;">
                                                <div xmlns="http://www.w3.org/1999/xhtml"
                                                     style="display: block;
                                                            margin: 0;
                                                            padding: 0;
                                                            width: 100%;
                                                            height: 100%;
                                                            font-family: {{ $elem['font_family'] }}, sans-serif;
                                                            font-size: {{ $fontSizeMmPx }}px;
                                                            font-weight: {{ $elem['font_weight'] }};
                                                            font-style: {{ $elem['font_style'] }};
                                                            text-align: {{ $elem['text_align'] }};
                                                            vertical-align: top;
                                                            line-height: {{ $elem['line_height'] ?? 1.15 }};
                                                            color: {{ $elem['color'] }};
                                                            white-space: pre-wrap;
                                                            word-break: break-word;
                                                            overflow: visible;
                                                            box-sizing: border-box;
                                                            pointer-events: none;
                                                            user-select: none;">{{ $val }}</div>
                                            </foreignObject>
                                        @endif

                                        <!-- Selection Bounding Box & Handles -->
                                        @if($isSelected)
                                            <rect class="elem-outline"
                                                  x="0" y="0"
                                                  width="{{ $elem['width_mm'] }}"
                                                  height="{{ $elem['height_mm'] }}"
                                                  fill="none"
                                                  pointer-events="none"
                                                  stroke="{{ $elem['is_locked'] ? '#ea580c' : '#2563eb' }}"
                                                  stroke-width="0.35"
                                                  stroke-dasharray="{{ $elem['is_locked'] ? '2,1' : '1.5,1.5' }}" />

                                            @php
                                                $elemOutOfBounds = ($elem['x_mm'] + $elem['width_mm'] > $template->paper_width_mm) || ($elem['y_mm'] + $elem['height_mm'] > $template->paper_height_mm);
                                            @endphp
                                            <text class="elem-coord-badge"
                                                  pointer-events="none"
                                                  x="0" y="-1.5" font-size="2" fill="{{ $elem['is_locked'] ? '#ea580c' : ($elemOutOfBounds ? '#dc2626' : '#2563eb') }}" font-family="sans-serif" font-weight="bold">
                                                @if($elem['is_locked']) 🔒 [LOCKED] @endif @if($elemOutOfBounds) ⚠️ [OUT OF BOUNDS] @endif X: {{ $elem['x_mm'] }}mm, Y: {{ $elem['y_mm'] }}mm | {{ $elem['width_mm'] }}×{{ $elem['height_mm'] }}mm
                                            </text>

                                            @if(!$elem['is_locked'] && $isSingleSelected)
                                                <rect class="elem-handle handle-nw cursor-nwse-resize" x="-1" y="-1" width="2" height="2" fill="#ffffff" stroke="#2563eb" stroke-width="0.3" @mousedown="startResize({{ $idx }}, 'nw', $event)"/>
                                                <rect class="elem-handle handle-n cursor-ns-resize" x="{{ ($elem['width_mm'] / 2) - 1 }}" y="-1" width="2" height="2" fill="#ffffff" stroke="#2563eb" stroke-width="0.3" @mousedown="startResize({{ $idx }}, 'n', $event)"/>
                                                <rect class="elem-handle handle-ne cursor-nesw-resize" x="{{ $elem['width_mm'] - 1 }}" y="-1" width="2" height="2" fill="#ffffff" stroke="#2563eb" stroke-width="0.3" @mousedown="startResize({{ $idx }}, 'ne', $event)"/>
                                                <rect class="elem-handle handle-e cursor-ew-resize" x="{{ $elem['width_mm'] - 1 }}" y="{{ ($elem['height_mm'] / 2) - 1 }}" width="2" height="2" fill="#ffffff" stroke="#2563eb" stroke-width="0.3" @mousedown="startResize({{ $idx }}, 'e', $event)"/>
                                                <rect class="elem-handle handle-se cursor-nwse-resize" x="{{ $elem['width_mm'] - 1 }}" y="{{ $elem['height_mm'] - 1 }}" width="2" height="2" fill="#ffffff" stroke="#2563eb" stroke-width="0.3" @mousedown="startResize({{ $idx }}, 'se', $event)"/>
                                                <rect class="elem-handle handle-s cursor-ns-resize" x="{{ ($elem['width_mm'] / 2) - 1 }}" y="{{ $elem['height_mm'] - 1 }}" width="2" height="2" fill="#ffffff" stroke="#2563eb" stroke-width="0.3" @mousedown="startResize({{ $idx }}, 's', $event)"/>
                                                <rect class="elem-handle handle-sw cursor-nesw-resize" x="-1" y="{{ $elem['height_mm'] - 1 }}" width="2" height="2" fill="#ffffff" stroke="#2563eb" stroke-width="0.3" @mousedown="startResize({{ $idx }}, 'sw', $event)"/>
                                                <rect class="elem-handle handle-w cursor-ew-resize" x="-1" y="{{ ($elem['height_mm'] / 2) - 1 }}" width="2" height="2" fill="#ffffff" stroke="#2563eb" stroke-width="0.3" @mousedown="startResize({{ $idx }}, 'w', $event)"/>
                                            @endif
                                        @else
                                            <rect x="0" y="0"
                                                  width="{{ $elem['width_mm'] }}"
                                                  height="{{ $elem['height_mm'] }}"
                                                  fill="none"
                                                  pointer-events="none"
                                                  stroke="#94a3b8"
                                                  stroke-width="0.2"
                                                  stroke-dasharray="0.8,0.8"
                                                  class="elem-outline elem-outline-unselected opacity-0 group-hover:opacity-100 transition-opacity" />
                                        @endif
                                    </g>
                                @endforeach

                                <!-- Smart Guides Layer (No HTML template tags in SVG) -->
                                <g id="smartGuidesLayer" pointer-events="none">
                                    <line id="smart_guide_v"
                                          x-show="activeGuideV !== null"
                                          x1="0"
                                          y1="0"
                                          x2="0"
                                          :y2="paperH"
                                          :x1="activeGuideV || 0"
                                          :x2="activeGuideV || 0"
                                          stroke="#ec4899"
                                          stroke-width="0.35"
                                          stroke-dasharray="2,1.5" />
                                    <line id="smart_guide_h"
                                          x-show="activeGuideH !== null"
                                          x1="0"
                                          y1="0"
                                          x2="0"
                                          :x2="paperW"
                                          :y1="activeGuideH || 0"
                                          :y2="activeGuideH || 0"
                                          stroke="#ec4899"
                                          stroke-width="0.35"
                                          stroke-dasharray="2,1.5" />
                                </g>

                                <!-- Marquee Selection Rectangle -->
                                <g id="marqueeLayer" pointer-events="none" x-show="isMarquee && (marqueeMmW > 0.5 || marqueeMmH > 0.5)">
                                    <rect :x="marqueeMmX"
                                          :y="marqueeMmY"
                                          :width="marqueeMmW"
                                          :height="marqueeMmH"
                                          fill="#3b82f6"
                                          fill-opacity="0.12"
                                          stroke="#2563eb"
                                          stroke-width="0.3"
                                          stroke-dasharray="1.5,1.5" />
                                </g>

                                <!-- Custom Guidelines Layer -->
                                <g id="customGuidesLayer" pointer-events="none">
                                    <path :d="(customGuides && customGuides.vertical ? customGuides.vertical : []).map(v => `M ${v} 0 L ${v} ${paperH}`).join(' ')" stroke="#06b6d4" stroke-width="0.35" stroke-dasharray="2,2" />
                                    <path :d="(customGuides && customGuides.horizontal ? customGuides.horizontal : []).map(h => `M 0 ${h} L ${paperW} ${h}`).join(' ')" stroke="#06b6d4" stroke-width="0.35" stroke-dasharray="2,2" />
                                </g>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lightweight Status Bar -->
            <footer id="designer_status_bar" class="h-6 bg-slate-200 border-t border-slate-300 px-4 flex items-center justify-between text-[11px] text-slate-600 select-none shrink-0 z-20">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1">
                        <span class="font-bold text-slate-700">Tool:</span>
                        <span x-text="activeTool === 'select' ? '↖ Select' : '✋ Hand'"></span>
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="font-bold text-slate-700">Cursor:</span>
                        <span x-text="`X: ${cursorMmX} mm, Y: ${cursorMmY} mm`"></span>
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="font-bold text-slate-700">Selection:</span>
                        <span id="status_bar_selection">
                            @if(count($selectedIndices ?? []) > 1)
                                {{ count($selectedIndices) }} items selected
                            @elseif(!is_null($selectedIndex) && isset($elements[$selectedIndex]))
                                {{ $elements[$selectedIndex]['element_type'] }} (#{{ $selectedIndex + 1 }})
                            @else
                                None
                            @endif
                        </span>
                    </span>
                </div>

                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1">
                        <span class="font-bold text-slate-700">Doc:</span>
                        <span>{{ $template->paper_width_mm }} × {{ $template->paper_height_mm }} mm ({{ ucfirst($template->orientation) }})</span>
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="font-bold text-slate-700">Snap:</span>
                        <span class="{{ $snapToGrid ? 'text-emerald-700 font-bold' : 'text-slate-500' }}">{{ $snapToGrid ? 'ON (' . $gridSizeMm . 'mm)' : 'OFF' }}</span>
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="font-bold text-slate-700">Zoom:</span>
                        <span x-text="`${Math.round(zoom * 100)}%`"></span>
                    </span>
                </div>
            </footer>

            <!-- Context Menu (Right Click) -->
            <div id="designerContextMenu"
                 x-show="contextMenuOpen"
                 @click.away="closeContextMenu()"
                 class="fixed z-50 bg-white border border-slate-200 rounded-xl shadow-xl py-1.5 w-52 text-xs select-none max-h-[85vh] overflow-y-auto"
                 :style="`top: ${contextMenuY}px; left: ${contextMenuX}px;`"
                 style="display: none;">
                 <button type="button" id="btn_context_cut" @click="@this.cutSelected(); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between">
                     <span>✂️ Cut</span>
                     <kbd class="text-[10px] text-slate-400">Ctrl+X</kbd>
                 </button>
                 <button type="button" id="btn_context_copy" @click="@this.copySelected(); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between">
                     <span>📋 Copy</span>
                     <kbd class="text-[10px] text-slate-400">Ctrl+C</kbd>
                 </button>
                 <button type="button" id="btn_context_paste" @click="@this.paste(); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between">
                     <span>📄 Paste</span>
                     <kbd class="text-[10px] text-slate-400">Ctrl+V</kbd>
                 </button>
                 <button type="button" id="btn_context_duplicate" @click="let t = (contextMenuTargetIndex !== null && contextMenuTargetIndex !== undefined) ? contextMenuTargetIndex : selectedIndex; if (t !== null && t !== undefined) @this.duplicateElement(t); else @this.duplicateSelected(); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between">
                     <span>📑 Duplicate</span>
                     <kbd class="text-[10px] text-slate-400">Ctrl+D</kbd>
                 </button>
                 <div class="border-t border-slate-100 my-1"></div>
                 <button type="button" id="btn_context_bring_forward" @click="let t = (contextMenuTargetIndex !== null && contextMenuTargetIndex !== undefined) ? contextMenuTargetIndex : selectedIndex; if (t !== null) @this.bringForward(t); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700">
                     ▲ Bring Forward
                 </button>
                 <button type="button" id="btn_context_send_backward" @click="let t = (contextMenuTargetIndex !== null && contextMenuTargetIndex !== undefined) ? contextMenuTargetIndex : selectedIndex; if (t !== null) @this.sendBackward(t); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700">
                     ▼ Send Backward
                 </button>
                 <button type="button" id="btn_context_bring_to_front" @click="let t = (contextMenuTargetIndex !== null && contextMenuTargetIndex !== undefined) ? contextMenuTargetIndex : selectedIndex; if (t !== null) @this.bringToFront(t); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700">
                     ⏫ Bring to Front
                 </button>
                 <button type="button" id="btn_context_send_to_back" @click="let t = (contextMenuTargetIndex !== null && contextMenuTargetIndex !== undefined) ? contextMenuTargetIndex : selectedIndex; if (t !== null) @this.sendToBack(t); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700">
                     ⏬ Send to Back
                 </button>
                 <div class="border-t border-slate-100 my-1"></div>
                 <button type="button" id="btn_context_group" @click="@this.groupSelected(); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700">
                     🔗 Group
                 </button>
                 <button type="button" id="btn_context_ungroup" @click="@this.ungroupSelected(); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700">
                     🔓 Ungroup
                 </button>
                 <button type="button" id="btn_context_toggle_lock" @click="let t = (contextMenuTargetIndex !== null && contextMenuTargetIndex !== undefined) ? contextMenuTargetIndex : selectedIndex; if (t !== null) @this.toggleLock(t); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700">
                     🔒 Lock / Unlock
                 </button>
                 <button type="button" id="btn_context_toggle_visibility" @click="let t = (contextMenuTargetIndex !== null && contextMenuTargetIndex !== undefined) ? contextMenuTargetIndex : selectedIndex; if (t !== null) @this.toggleVisibility(t); closeContextMenu();" class="w-full px-3 py-1.5 text-left hover:bg-blue-50 hover:text-blue-700">
                     👁 Hide / Show
                 </button>
                 <div class="border-t border-slate-100 my-1"></div>
                 <button type="button" id="btn_context_delete" @click="deleteSelectedObject()" class="w-full px-3 py-1.5 text-left hover:bg-rose-50 text-rose-600 flex items-center justify-between">
                     <span>🗑 Delete</span>
                     <kbd class="text-[10px] text-rose-400">Del</kbd>
                 </button>
             </div>
        </main>

        <!-- RIGHT: Element Properties Panel -->
        <aside x-show="showProperties" x-transition class="w-72 bg-white border-l border-slate-200 flex flex-col shrink-0 overflow-y-auto z-10 shadow-2xs">
            <div class="p-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Properties</span>
                @if(count($selectedIndices ?? []) > 1)
                    <span id="selected_field_badge" class="text-[10px] text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full font-bold border border-purple-200">
                        {{ count($selectedIndices) }} elements selected
                    </span>
                @elseif(!is_null($selectedIndex) && isset($elements[$selectedIndex]))
                    <span id="selected_field_badge" class="text-[10px] text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full font-bold border border-blue-200">
                        Field #{{ $selectedIndex + 1 }}
                    </span>
                @endif
            </div>

            <!-- Case A: Multi-Selection Property Panel -->
            @if(count($selectedIndices ?? []) > 1)
                <div class="p-4 space-y-4 text-xs" id="multi_selection_panel">
                    <div class="bg-purple-50/70 p-3 rounded-xl border border-purple-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-purple-900 text-xs">Multi-Selection Active</span>
                            <span class="font-bold text-purple-700">{{ count($selectedIndices) }} items</span>
                        </div>
                        <p class="text-[11px] text-purple-700">Actions apply across all selected elements relative to their common bounding box.</p>
                    </div>

                    <!-- Alignment Commands -->
                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-2">
                        <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Align Relative to Bounds</span>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button type="button" wire:click="alignElements('left')" id="btn_align_left" class="p-1.5 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded text-center font-semibold text-[11px] transition-colors shadow-2xs">
                                ⬅ Left
                            </button>
                            <button type="button" wire:click="alignElements('center')" id="btn_align_center" class="p-1.5 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded text-center font-semibold text-[11px] transition-colors shadow-2xs">
                                ↔ Center
                            </button>
                            <button type="button" wire:click="alignElements('right')" id="btn_align_right" class="p-1.5 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded text-center font-semibold text-[11px] transition-colors shadow-2xs">
                                ➡ Right
                            </button>
                            <button type="button" wire:click="alignElements('top')" id="btn_align_top" class="p-1.5 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded text-center font-semibold text-[11px] transition-colors shadow-2xs">
                                ⬆ Top
                            </button>
                            <button type="button" wire:click="alignElements('middle')" id="btn_align_middle" class="p-1.5 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded text-center font-semibold text-[11px] transition-colors shadow-2xs">
                                ↕ Middle
                            </button>
                            <button type="button" wire:click="alignElements('bottom')" id="btn_align_bottom" class="p-1.5 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded text-center font-semibold text-[11px] transition-colors shadow-2xs">
                                ⬇ Bottom
                            </button>
                        </div>
                    </div>

                    <!-- Distribution Commands (3+ elements) -->
                    @if(count($selectedIndices) >= 3)
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-2">
                            <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Distribute Spacing (Physical MM)</span>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="distributeElements('horizontal')" id="btn_distribute_horiz" class="p-2 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded-lg text-center font-semibold text-[11px] transition-colors shadow-2xs">
                                    ⬌ Horizontally
                                </button>
                                <button type="button" wire:click="distributeElements('vertical')" id="btn_distribute_vert" class="p-2 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded-lg text-center font-semibold text-[11px] transition-colors shadow-2xs">
                                    ⬍ Vertically
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Grouping Controls -->
                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-2">
                        <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Group / Unit</span>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="groupSelected" id="btn_group_selected" class="p-2 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded-lg text-center font-semibold text-[11px] transition-colors shadow-2xs flex items-center justify-center gap-1">
                                🔗 Group
                            </button>
                            <button type="button" wire:click="ungroupSelected" id="btn_ungroup_selected" class="p-2 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 rounded-lg text-center font-semibold text-[11px] transition-colors shadow-2xs flex items-center justify-center gap-1">
                                🔓 Ungroup
                            </button>
                        </div>
                    </div>

                    <!-- Common Typography & Styling -->
                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-2">
                        <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Common Properties</span>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Font Size (pt)</label>
                                <input type="number" step="0.1" min="2" max="72" id="multi_prop_font_size"
                                       @input.debounce.150ms="@this.updateMultiProperty('font_size', $event.target.value)"
                                       class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold" placeholder="10.0">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Font Family</label>
                                <select id="multi_prop_font_family" @change="@this.updateMultiProperty('font_family', $event.target.value)" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs">
                                    <option value="">-- Family --</option>
                                    <option value="Noto Sans Devanagari">Noto Sans Devanagari (Hindi/Unicode)</option>
                                    <option value="Helvetica">Helvetica</option>
                                    <option value="Arial">Arial</option>
                                    <option value="Times-Roman">Times New Roman</option>
                                    <option value="Courier">Courier</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Weight</label>
                                <select id="multi_prop_font_weight" @change="@this.updateMultiProperty('font_weight', $event.target.value)" class="w-full p-1 bg-white border border-slate-200 rounded text-[11px]">
                                    <option value="">--</option>
                                    <option value="normal">Normal</option>
                                    <option value="bold">Bold</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Style</label>
                                <select id="multi_prop_font_style" @change="@this.updateMultiProperty('font_style', $event.target.value)" class="w-full p-1 bg-white border border-slate-200 rounded text-[11px]">
                                    <option value="">--</option>
                                    <option value="normal">Normal</option>
                                    <option value="italic">Italic</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Align</label>
                                <select id="multi_prop_text_align" @change="@this.updateMultiProperty('text_align', $event.target.value)" class="w-full p-1 bg-white border border-slate-200 rounded text-[11px]">
                                    <option value="">--</option>
                                    <option value="left">Left</option>
                                    <option value="center">Center</option>
                                    <option value="right">Right</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-[10px] text-slate-500 font-semibold">Text/Line Color:</span>
                            <input type="color" id="multi_prop_color" @input.debounce.150ms="@this.updateMultiProperty('color', $event.target.value)" class="w-7 h-7 p-0 border border-slate-200 rounded cursor-pointer">
                        </div>
                    </div>

                    <!-- Common Actions -->
                    <div class="pt-2 border-t border-slate-200 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="lockSelected(true)" id="btn_lock_all" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 flex items-center justify-center gap-1">
                                🔒 Lock All
                            </button>
                            <button type="button" wire:click="lockSelected(false)" id="btn_unlock_all" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 flex items-center justify-center gap-1">
                                🔓 Unlock All
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="duplicateSelected" id="btn_duplicate_multi" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors flex items-center justify-center gap-1">
                                📋 Duplicate
                            </button>
                            <button type="button" wire:click="deleteSelected" id="btn_delete_multi" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 transition-colors flex items-center justify-center gap-1">
                                🗑 Delete
                            </button>
                        </div>
                    </div>
                </div>

            <!-- Case B: Single Element Properties Panel -->
            @elseif(!is_null($selectedIndex) && isset($elements[$selectedIndex]))
                @php 
                    $cur = $elements[$selectedIndex];
                    $curCfg = $cur['config_json'] ?? [];
                @endphp
                <div class="p-4 space-y-4 text-xs" :key="'elem_props_' + {{ $selectedIndex }}">
                    <!-- Field Key / Name -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="font-bold text-slate-700">Field Name / Type</label>
                            @if($cur['is_locked'])
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                    🔒 LOCKED
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" value="{{ $cur['field_key'] }}" readonly class="w-full p-2 bg-slate-100 border border-slate-200 rounded-lg text-slate-600 font-mono text-[11px]">
                            <span class="px-2 py-1 bg-slate-100 rounded text-[10px] font-bold text-slate-500 uppercase">{{ $cur['element_type'] }}</span>
                        </div>
                    </div>

                    @if($cur['element_type'] === 'FIELD')
                        @php
                            $isFieldLabelOn = (bool)($curCfg['show_label'] ?? false);
                            $defaultFieldLabel = \App\Services\PrescriptionDataMapper::getDefaultLabelForField($cur['field_key']);
                            $fieldLabelText = $curCfg['label_text'] ?? $defaultFieldLabel;
                            $fieldSampleVal = $sampleData[$cur['field_key']] ?? '031';
                            $fieldPreview = $isFieldLabelOn ? ($fieldLabelText . ': ' . $fieldSampleVal) : $fieldSampleVal;
                        @endphp
                        <div class="bg-blue-50/60 p-3 rounded-xl border border-blue-200/80 space-y-3" id="field_label_properties_panel">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-blue-950 text-[11px] uppercase tracking-wider">Field Label Settings</span>
                                <span class="text-[10px] text-blue-700 bg-blue-100 font-mono px-1.5 py-0.5 rounded font-semibold">Prefix</span>
                            </div>

                            <div>
                                <label for="prop_input_label_text" class="block text-[11px] text-slate-700 font-bold mb-1">Label</label>
                                <input type="text"
                                       id="prop_input_label_text"
                                       value="{{ $fieldLabelText }}"
                                       @input.debounce.150ms="updateFieldLabelText($event.target.value, {{ $selectedIndex }})"
                                       class="w-full p-2 bg-white border border-blue-200 rounded-lg text-xs font-semibold text-slate-800 focus:outline-hidden focus:border-blue-500 shadow-2xs"
                                       placeholder="e.g. {{ $defaultFieldLabel }}">
                                <p class="text-[10px] text-slate-500 mt-1">Presentation label only. Field key <code class="font-mono text-slate-700">{{ $cur['field_key'] }}</code> remains unchanged.</p>
                            </div>

                            <div class="flex items-center justify-between p-2.5 bg-white rounded-lg border border-blue-100 shadow-2xs">
                                <div>
                                    <label for="input_show_label" class="text-xs font-bold text-slate-800 cursor-pointer">Show Field Name</label>
                                    <p class="text-[10px] text-slate-500">Prints label followed by exact ": " and value</p>
                                </div>
                                <input type="checkbox"
                                       id="input_show_label"
                                       {{ $isFieldLabelOn ? 'checked' : '' }}
                                       @change="updateFieldShowLabel($event.target.checked, {{ $selectedIndex }})"
                                       class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500 cursor-pointer">
                            </div>

                            <div class="p-2.5 bg-slate-900 text-white rounded-lg shadow-inner">
                                <span class="text-[9px] uppercase tracking-wider text-slate-400 font-bold block mb-1">Preview</span>
                                <div id="field_label_preview_text" class="font-mono text-xs font-bold text-emerald-400 truncate">
                                    {{ $fieldPreview }}
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($cur['element_type'] === 'STATIC_TEXT')
                        <div class="bg-blue-50/60 p-2.5 rounded-xl border border-blue-100 space-y-1">
                            <label class="block font-bold text-blue-900 text-[11px] uppercase tracking-wider">Content / Value *</label>
                            <textarea rows="3"
                                      id="prop_input_static_text"
                                      wire:key="prop_static_text_{{ $selectedIndex }}"
                                      @input.debounce.150ms="updateElementCfg('static_text', $event.target.value)"
                                      class="w-full p-2 bg-white border border-blue-200 rounded-lg focus:outline-hidden focus:border-blue-500 text-xs font-medium text-slate-800"
                                      placeholder="Enter custom static text (e.g. Rx, Instructions, Notes)">{{ $curCfg['static_text'] ?? '' }}</textarea>
                            <p class="text-[10px] text-blue-600">Preserves across add, edit, save, reload, and PDF outputs.</p>
                        </div>
                    @endif

                    @if($cur['element_type'] === 'RECTANGLE')
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-3">
                            <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Box Styling</span>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Border Style</label>
                                    <select @change="updateElementCfg('border_style', $event.target.value)" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs">
                                        <option value="solid" {{ ($curCfg['border_style'] ?? 'solid') === 'solid' ? 'selected' : '' }}>Solid</option>
                                        <option value="dashed" {{ ($curCfg['border_style'] ?? '') === 'dashed' ? 'selected' : '' }}>Dashed</option>
                                        <option value="dotted" {{ ($curCfg['border_style'] ?? '') === 'dotted' ? 'selected' : '' }}>Dotted</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Border Width (mm)</label>
                                    <input type="number" step="0.1" min="0.1" max="10" value="{{ $curCfg['border_width_mm'] ?? 0.3 }}" @input.debounce.150ms="updateElementCfg('border_width_mm', $event.target.value)" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 items-center">
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Border Color</label>
                                    <div class="flex items-center gap-1.5">
                                        <input type="color" value="{{ $cur['color'] }}" @input="updateElementProp('color', $event.target.value)" class="w-7 h-7 p-0 border border-slate-200 rounded cursor-pointer">
                                        <span class="font-mono text-[10px]">{{ $cur['color'] }}</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Corner Radius (mm)</label>
                                    <input type="number" step="0.5" min="0" max="50" value="{{ $curCfg['border_radius_mm'] ?? 0.0 }}" @input.debounce.150ms="updateElementCfg('border_radius_mm', $event.target.value)" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold">
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-[10px] text-slate-500 font-semibold">Fill Color</label>
                                    <button type="button" id="btn_rect_fill_transparent" @click="updateElementCfg('fill_color', 'transparent')" class="text-[9px] text-blue-600 hover:text-blue-800 font-bold">Transparent</button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="color" id="prop_input_fill_color"
                                           value="{{ ($curCfg['fill_color'] ?? 'transparent') === 'transparent' ? '#ffffff' : $curCfg['fill_color'] }}"
                                           @input="updateElementCfg('fill_color', $event.target.value)"
                                           class="w-7 h-7 p-0 border border-slate-200 rounded cursor-pointer">
                                    <select @change="updateElementCfg('fill_color', $event.target.value)" class="p-1.5 bg-white border border-slate-200 rounded-lg text-xs flex-1">
                                        <option value="transparent" {{ ($curCfg['fill_color'] ?? 'transparent') === 'transparent' ? 'selected' : '' }}>None (Transparent)</option>
                                        <option value="#f8fafc" {{ ($curCfg['fill_color'] ?? '') === '#f8fafc' ? 'selected' : '' }}>Light Slate (#f8fafc)</option>
                                        <option value="#f1f5f9" {{ ($curCfg['fill_color'] ?? '') === '#f1f5f9' ? 'selected' : '' }}>Slate (#f1f5f9)</option>
                                        <option value="#e0f2fe" {{ ($curCfg['fill_color'] ?? '') === '#e0f2fe' ? 'selected' : '' }}>Light Sky (#e0f2fe)</option>
                                        <option value="#ecfdf5" {{ ($curCfg['fill_color'] ?? '') === '#ecfdf5' ? 'selected' : '' }}>Light Emerald (#ecfdf5)</option>
                                        <option value="#fef3c7" {{ ($curCfg['fill_color'] ?? '') === '#fef3c7' ? 'selected' : '' }}>Light Amber (#fef3c7)</option>
                                        <option value="#ffffff" {{ ($curCfg['fill_color'] ?? '') === '#ffffff' ? 'selected' : '' }}>White (#ffffff)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($cur['element_type'] === 'LINE')
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-3">
                            <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Line Styling</span>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Line Style</label>
                                    <select @change="updateElementCfg('line_style', $event.target.value)" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs">
                                        <option value="solid" {{ ($curCfg['line_style'] ?? 'solid') === 'solid' ? 'selected' : '' }}>Solid</option>
                                        <option value="dashed" {{ ($curCfg['line_style'] ?? '') === 'dashed' ? 'selected' : '' }}>Dashed</option>
                                        <option value="dotted" {{ ($curCfg['line_style'] ?? '') === 'dotted' ? 'selected' : '' }}>Dotted</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Line Width (mm)</label>
                                    <input type="number" step="0.1" min="0.1" max="10" value="{{ $curCfg['line_width_mm'] ?? 0.5 }}" @input.debounce.150ms="updateElementCfg('line_width_mm', $event.target.value)" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Line Color</label>
                                <div class="flex items-center gap-1.5">
                                    <input type="color" value="{{ $cur['color'] }}" @input="updateElementProp('color', $event.target.value)" class="w-7 h-7 p-0 border border-slate-200 rounded cursor-pointer">
                                    <span class="font-mono text-[10px]">{{ $cur['color'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($cur['element_type'] === 'IMAGE')
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-3">
                            <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Image / Logo Details</span>
                            
                            @if(!empty($curCfg['image_path']))
                                <div class="relative rounded-lg overflow-hidden border border-slate-200 bg-white p-1 max-h-28 flex items-center justify-center">
                                    <img src="{{ asset($curCfg['image_path']) }}" alt="Selected Image" class="max-h-24 max-w-full object-contain" style="opacity: {{ $curCfg['opacity'] ?? 1.0 }};">
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-slate-500">
                                    <span>{{ $curCfg['original_width_px'] ?? '?' }} × {{ $curCfg['original_height_px'] ?? '?' }} px</span>
                                    <span>Aspect: {{ round($curCfg['aspect_ratio'] ?? 1.0, 2) }}:1</span>
                                </div>
                            @endif

                            <div class="flex items-center justify-between p-2 bg-white rounded-lg border border-slate-200">
                                <div>
                                    <label for="prop_lock_aspect" class="text-xs font-bold text-slate-700 cursor-pointer">Lock Aspect Ratio</label>
                                    <p class="text-[9px] text-slate-400">Preserve proportions during resize</p>
                                </div>
                                <input type="checkbox"
                                       id="prop_lock_aspect"
                                       {{ (!isset($curCfg['lock_aspect_ratio']) || $curCfg['lock_aspect_ratio']) ? 'checked' : '' }}
                                       @change="updateElementCfg('lock_aspect_ratio', $event.target.checked); let g = document.getElementById('elem_' + selectedIndex); if (g) g.setAttribute('data-lock-aspect', $event.target.checked ? '1' : '0');"
                                       class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500 cursor-pointer">
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-[10px] text-slate-500 font-semibold">Opacity</label>
                                    <span class="text-[10px] font-mono font-bold text-slate-700" id="prop_opacity_val">{{ round(($curCfg['opacity'] ?? 1.0) * 100) }}%</span>
                                </div>
                                <input type="range" min="0.1" max="1.0" step="0.05"
                                       id="prop_input_opacity"
                                       value="{{ $curCfg['opacity'] ?? 1.0 }}"
                                       @input="updateElementCfg('opacity', $event.target.value); let g = document.getElementById('elem_' + selectedIndex); if (g) { let img = g.querySelector('.elem-image'); if (img) img.setAttribute('opacity', $event.target.value); } document.getElementById('prop_opacity_val').textContent = Math.round($event.target.value * 100) + '%';"
                                       class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer">
                            </div>
                        </div>
                    @endif

                    <!-- Position -->
                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-2">
                        <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Position (Millimeters)</span>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">X (mm)</label>
                                <input type="number" step="0.1" id="prop_input_x" value="{{ $cur['x_mm'] }}" @input.debounce.150ms="updateElementProp('x_mm', $event.target.value, {{ $selectedIndex }})" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Y (mm)</label>
                                <input type="number" step="0.1" id="prop_input_y" value="{{ $cur['y_mm'] }}" @input.debounce.150ms="updateElementProp('y_mm', $event.target.value, {{ $selectedIndex }})" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold">
                            </div>
                        </div>
                        <p class="text-[9px] text-slate-400">Arrow keys move by 0.5 mm. Shift + Arrow moves by 0.1 mm.</p>
                    </div>

                    <!-- Size -->
                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-2">
                        <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Size (Millimeters)</span>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Width (mm)</label>
                                <input type="number" step="0.1" id="prop_input_w" value="{{ $cur['width_mm'] }}" @input.debounce.150ms="updateElementProp('width_mm', $event.target.value, {{ $selectedIndex }})" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Height (mm)</label>
                                <input type="number" step="0.1" id="prop_input_h" value="{{ $cur['height_mm'] }}" @input.debounce.150ms="updateElementProp('height_mm', $event.target.value, {{ $selectedIndex }})" class="w-full p-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold">
                            </div>
                        </div>
                    </div>

                    <!-- Boundary & Overlap Detection Warnings (Section 20 & 21) -->
                    @php
                        $exceedsBounds = ($cur['x_mm'] + $cur['width_mm'] > $template->paper_width_mm) || ($cur['y_mm'] + $cur['height_mm'] > $template->paper_height_mm);
                        $overlappingWith = [];
                        foreach ($elements as $oIdx => $other) {
                            if ($oIdx === $selectedIndex) continue;
                            if (!($other['is_visible'] ?? true)) continue;
                            $x1 = (float)$cur['x_mm'];
                            $y1 = (float)$cur['y_mm'];
                            $w1 = (float)$cur['width_mm'];
                            $h1 = (float)$cur['height_mm'];
                            $x2 = (float)$other['x_mm'];
                            $y2 = (float)$other['y_mm'];
                            $w2 = (float)$other['width_mm'];
                            $h2 = (float)$other['height_mm'];
                            if ($x1 < $x2 + $w2 && $x1 + $w1 > $x2 && $y1 < $y2 + $h2 && $y1 + $h1 > $y2) {
                                $otherLabel = $other['field_key'] ?? $other['element_type'];
                                $overlappingWith[] = '#' . ($oIdx + 1) . " ({$otherLabel})";
                            }
                        }
                    @endphp

                    @if($exceedsBounds)
                        <div id="element_bounds_warning" class="bg-amber-50 border border-amber-300 p-2.5 rounded-xl text-amber-900 text-xs flex items-start gap-2 shadow-2xs">
                            <span class="text-sm">⚠️</span>
                            <div>
                                <span class="font-bold block">Paper Boundary Warning</span>
                                <span class="text-[11px] leading-tight block text-amber-800">
                                    Element extends outside paper bounds ({{ $template->paper_width_mm }} × {{ $template->paper_height_mm }} mm). Coordinates reach {{ round($cur['x_mm'] + $cur['width_mm'], 1) }} × {{ round($cur['y_mm'] + $cur['height_mm'], 1) }} mm.
                                </span>
                            </div>
                        </div>
                    @endif

                    @if(count($overlappingWith) > 0)
                        <div id="element_overlap_warning" class="bg-orange-50 border border-orange-300 p-2.5 rounded-xl text-orange-900 text-xs flex items-start gap-2 shadow-2xs">
                            <span class="text-sm">⚠️</span>
                            <div>
                                <span class="font-bold block">These elements overlap:</span>
                                <span class="text-[11px] leading-tight block text-orange-800">
                                    Overlaps with {{ implode(', ', $overlappingWith) }}. Check positioning to avoid printing collisions.
                                </span>
                            </div>
                        </div>
                    @endif

                    <!-- Rotation Angle -->
                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Rotation</span>
                            <span class="font-mono font-bold text-blue-700 text-xs" id="rotation_degree_badge">{{ $cur['rotation'] }}°</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="range" min="0" max="360" step="15"
                                   id="prop_input_rotation_range"
                                   value="{{ $cur['rotation'] }}"
                                   @input="updateElementProp('rotation', $event.target.value); document.getElementById('rotation_degree_badge').textContent = $event.target.value + '°';"
                                   class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer">
                            <input type="number" min="0" max="360" step="5"
                                   id="prop_input_rotation"
                                   value="{{ $cur['rotation'] }}"
                                   @input.debounce.150ms="updateElementProp('rotation', $event.target.value); document.getElementById('rotation_degree_badge').textContent = $event.target.value + '°';"
                                   class="w-16 p-1 bg-white border border-slate-200 rounded text-center text-xs font-bold">
                        </div>
                        <div class="grid grid-cols-4 gap-1 text-[10px]">
                            <button type="button" id="btn_rot_0" @click="updateElementProp('rotation', 0); document.getElementById('rotation_degree_badge').textContent = '0°';" class="py-1 bg-white border rounded hover:bg-blue-50 font-bold">0°</button>
                            <button type="button" id="btn_rot_90" @click="updateElementProp('rotation', 90); document.getElementById('rotation_degree_badge').textContent = '90°';" class="py-1 bg-white border rounded hover:bg-blue-50 font-bold">90°</button>
                            <button type="button" id="btn_rot_180" @click="updateElementProp('rotation', 180); document.getElementById('rotation_degree_badge').textContent = '180°';" class="py-1 bg-white border rounded hover:bg-blue-50 font-bold">180°</button>
                            <button type="button" id="btn_rot_270" @click="updateElementProp('rotation', 270); document.getElementById('rotation_degree_badge').textContent = '270°';" class="py-1 bg-white border rounded hover:bg-blue-50 font-bold">270°</button>
                        </div>
                    </div>

                    <!-- Page Alignment -->
                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-2">
                        <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Align to Page Document</span>
                        <div class="grid grid-cols-3 gap-1">
                            <button type="button" wire:click="alignElements('left')" class="p-1 bg-white border border-slate-200 rounded text-center text-[10px] font-semibold hover:bg-blue-50 hover:text-blue-700">Left</button>
                            <button type="button" wire:click="alignElements('center')" class="p-1 bg-white border border-slate-200 rounded text-center text-[10px] font-semibold hover:bg-blue-50 hover:text-blue-700">Center</button>
                            <button type="button" wire:click="alignElements('right')" class="p-1 bg-white border border-slate-200 rounded text-center text-[10px] font-semibold hover:bg-blue-50 hover:text-blue-700">Right</button>
                            <button type="button" wire:click="alignElements('top')" class="p-1 bg-white border border-slate-200 rounded text-center text-[10px] font-semibold hover:bg-blue-50 hover:text-blue-700">Top</button>
                            <button type="button" wire:click="alignElements('middle')" class="p-1 bg-white border border-slate-200 rounded text-center text-[10px] font-semibold hover:bg-blue-50 hover:text-blue-700">Middle</button>
                            <button type="button" wire:click="alignElements('bottom')" class="p-1 bg-white border border-slate-200 rounded text-center text-[10px] font-semibold hover:bg-blue-50 hover:text-blue-700">Bottom</button>
                        </div>
                    </div>

                    @if($cur['element_type'] === 'FIELD' || $cur['element_type'] === 'STATIC_TEXT')
                        <div class="space-y-2">
                            <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Typography (Points)</span>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Font Family</label>
                                    <select @change="updateElementProp('font_family', $event.target.value)" class="w-full p-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                                        <option value="Noto Sans Devanagari" {{ $cur['font_family'] === 'Noto Sans Devanagari' ? 'selected' : '' }}>Noto Sans Devanagari (Hindi/Unicode)</option>
                                        <option value="Helvetica" {{ $cur['font_family'] === 'Helvetica' ? 'selected' : '' }}>Helvetica</option>
                                        <option value="Arial" {{ $cur['font_family'] === 'Arial' ? 'selected' : '' }}>Arial</option>
                                        <option value="Times-Roman" {{ $cur['font_family'] === 'Times-Roman' ? 'selected' : '' }}>Times New Roman</option>
                                        <option value="Courier" {{ $cur['font_family'] === 'Courier' ? 'selected' : '' }}>Courier</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Font Size (pt)</label>
                                    <input type="number" step="0.1" min="2" max="72"
                                           id="prop_input_font_size"
                                           wire:key="prop_font_size_{{ $selectedIndex }}_{{ $cur['font_size'] }}"
                                           :value="'{{ $cur['font_size'] }}'"
                                           value="{{ $cur['font_size'] }}"
                                           @input.debounce.100ms="updateElementProp('font_size', $event.target.value)"
                                           class="w-full p-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold">
                                    <label class="flex items-center gap-1.5 text-[10px] text-slate-600 mt-1 cursor-pointer">
                                        <input type="checkbox" id="prop_scale_font_on_resize" x-model="scaleFontOnResize" class="w-3.5 h-3.5 rounded text-blue-600 border-slate-300">
                                        <span>Scale Font on Resize</span>
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Weight</label>
                                    <select @change="updateElementProp('font_weight', $event.target.value)" class="w-full p-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                                        <option value="normal" {{ $cur['font_weight'] === 'normal' ? 'selected' : '' }}>Normal</option>
                                        <option value="bold" {{ $cur['font_weight'] === 'bold' ? 'selected' : '' }}>Bold</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Style</label>
                                    <select @change="updateElementProp('font_style', $event.target.value)" class="w-full p-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                                        <option value="normal" {{ $cur['font_style'] === 'normal' ? 'selected' : '' }}>Normal</option>
                                        <option value="italic" {{ $cur['font_style'] === 'italic' ? 'selected' : '' }}>Italic</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Align</label>
                                    <select @change="updateElementProp('text_align', $event.target.value)" class="w-full p-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                                        <option value="left" {{ $cur['text_align'] === 'left' ? 'selected' : '' }}>Left</option>
                                        <option value="center" {{ $cur['text_align'] === 'center' ? 'selected' : '' }}>Center</option>
                                        <option value="right" {{ $cur['text_align'] === 'right' ? 'selected' : '' }}>Right</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 items-center">
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Color</label>
                                    <div class="flex items-center gap-1.5">
                                        <input type="color" value="{{ $cur['color'] }}" @input="updateElementProp('color', $event.target.value)" class="w-7 h-7 p-0 border border-slate-200 rounded cursor-pointer">
                                        <span class="font-mono text-[10px]">{{ $cur['color'] }}</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500 font-semibold mb-0.5">Line Height</label>
                                    <input type="number" step="0.1" min="0.5" max="3.0" value="{{ $cur['line_height'] }}" @input.debounce.150ms="updateElementProp('line_height', $event.target.value)" class="w-full p-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Layer Ordering -->
                    <div class="pt-2 border-t border-slate-200 space-y-1.5">
                        <span class="font-bold text-slate-700 block text-[11px] uppercase tracking-wider">Layer Stacking</span>
                        <div class="grid grid-cols-2 gap-1.5">
                            <button type="button" wire:click="bringForward({{ $selectedIndex }})" id="btn_bring_forward" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-[11px] font-semibold text-slate-700 flex items-center justify-center gap-1">
                                <span>▲ Forward</span>
                            </button>
                            <button type="button" wire:click="sendBackward({{ $selectedIndex }})" id="btn_send_backward" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-[11px] font-semibold text-slate-700 flex items-center justify-center gap-1">
                                <span>▼ Backward</span>
                            </button>
                            <button type="button" wire:click="bringToFront({{ $selectedIndex }})" id="btn_bring_to_front" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-[11px] font-semibold text-slate-700 flex items-center justify-center gap-1">
                                <span>⏫ To Front</span>
                            </button>
                            <button type="button" wire:click="sendToBack({{ $selectedIndex }})" id="btn_send_to_back" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-[11px] font-semibold text-slate-700 flex items-center justify-center gap-1">
                                <span>⏬ To Back</span>
                            </button>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="pt-2 border-t border-slate-200 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="toggleLock({{ $selectedIndex }})" id="btn_toggle_lock" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ $cur['is_locked'] ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-100 text-slate-700' }}">
                                {{ $cur['is_locked'] ? '🔒 Locked' : '🔓 Unlocked' }}
                            </button>
                            <button type="button" wire:click="toggleVisibility({{ $selectedIndex }})" id="btn_toggle_visibility" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ $cur['is_visible'] ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-400' }}">
                                {{ $cur['is_visible'] ? '👁 Visible' : '🙈 Hidden' }}
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="duplicateElement({{ $selectedIndex }})" id="btn_duplicate_element" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors flex items-center justify-center gap-1">
                                <span>📋 Duplicate</span>
                            </button>
                            <button type="button" wire:click="deleteElement({{ $selectedIndex }})" id="btn_delete_element" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 transition-colors flex items-center justify-center gap-1">
                                <span>🗑 Delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-6 text-center text-slate-400 space-y-2">
                    <svg class="w-8 h-8 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                    <p class="text-xs">Click any field in the canvas or use marquee drag to select elements and adjust positioning, alignment, layers, or styling.</p>
                </div>
            @endif
        </aside>
    </div>
</div>

<script>
function prescriptionPadDesigner(config) {
    return {
        zoom: 1.0,
        selectedIndex: (config.initialSelectedIndex !== undefined && config.initialSelectedIndex !== null) ? Number(config.initialSelectedIndex) : null,
        selectedIndices: config.initialSelected || [],
        activeTool: 'select',
        activeLeftTab: config.activeLeftTab || 'toolbox',
        panX: 0,
        panY: 0,
        isFullScreen: config.isFullScreen || false,
        showToolbox: true,
        showProperties: true,

        // Custom Guides & Context Menu & Inline Edit
        customGuides: { vertical: [], horizontal: [] },
        contextMenuOpen: false,
        contextMenuTargetIndex: null,
        contextMenuX: 0,
        contextMenuY: 0,
        sampleData: config.sampleData || {},
        inlineEditIndex: null,
        scaleFontOnResize: false,
        startFontSize: 10.0,

        // Pan gesture
        isSpaceHeld: false,
        isPanning: false,
        panStartX: 0,
        panStartY: 0,
        startPanX: 0,
        startPanY: 0,

        // Drag state
        isDragging: false,
        dragIndex: null,
        dragStartX: 0,
        dragStartY: 0,
        startElemX: 0,
        startElemY: 0,
        currentElemX: 0,
        currentElemY: 0,

        // Multi drag
        isMultiDragging: false,
        multiDragStartPositions: {},
        hasDragged: false,

        // Marquee
        isMarquee: false,
        marqueeStartMmX: 0,
        marqueeStartMmY: 0,
        marqueeMmX: 0,
        marqueeMmY: 0,
        marqueeMmW: 0,
        marqueeMmH: 0,

        // Smart guides (visual only, single values or null)
        activeGuideV: null,
        activeGuideH: null,

        // Resize
        isResizing: false,
        resizeIndex: null,
        resizeHandle: null,
        resizeStartX: 0,
        resizeStartY: 0,
        startW: 0,
        startH: 0,
        currentX: 0,
        currentY: 0,
        currentW: 0,
        currentH: 0,
        lockAspect: false,
        aspectRatio: 1.0,

        // Cursor coordinates
        cursorMmX: 0,
        cursorMmY: 0,

        paperW: config.paperW,
        paperH: config.paperH,
        snapToGrid: config.snapToGrid,
        gridSizeMm: config.gridSizeMm,

        init() {
            window.addEventListener('element-selected', (e) => {
                let idx = (e.detail && e.detail.index !== undefined) ? Number(e.detail.index) : null;
                if (idx !== null && !isNaN(idx)) {
                    this.selectedIndex = idx;
                    this.selectedIndices = [idx];
                }
            });

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                window.Livewire.hook('morph.updating', ({ el, toEl, skip }) => {
                    if (this.isDragging && this.dragIndex !== null) {
                        let draggingElId = 'elem_' + this.dragIndex;
                        if (el && (el.id === draggingElId || (el.closest && el.closest('#' + draggingElId)))) {
                            if (el.hasAttribute('data-x-mm')) {
                                toEl.setAttribute('data-x-mm', el.getAttribute('data-x-mm'));
                                toEl.setAttribute('data-y-mm', el.getAttribute('data-y-mm'));
                                toEl.setAttribute('transform', el.getAttribute('transform'));
                            }
                        }
                    }
                    if (this.isMultiDragging && this.multiDragStartPositions) {
                        for (let idx in this.multiDragStartPositions) {
                            let multiElId = 'elem_' + idx;
                            if (el && (el.id === multiElId || (el.closest && el.closest('#' + multiElId)))) {
                                if (el.hasAttribute('data-x-mm')) {
                                    toEl.setAttribute('data-x-mm', el.getAttribute('data-x-mm'));
                                    toEl.setAttribute('data-y-mm', el.getAttribute('data-y-mm'));
                                    toEl.setAttribute('transform', el.getAttribute('transform'));
                                }
                            }
                        }
                    }
                });
            }
        },

        setTool(tool) {
            this.activeTool = tool;
        },

        handleWheel(event) {
            let ws = event.currentTarget;
            let rect = ws.getBoundingClientRect();
            let xm = event.clientX - rect.left;
            let ym = event.clientY - rect.top;

            let delta = event.deltaY;
            let factor = delta < 0 ? 1.15 : (1 / 1.15);
            let oldZoom = this.zoom;
            let newZoom = Math.min(3.0, Math.max(0.4, Math.round(oldZoom * factor * 100) / 100));

            if (newZoom === oldZoom) return;

            let newPanX = Math.round(xm - (xm - this.panX) * (newZoom / oldZoom));
            let newPanY = Math.round(ym - (ym - this.panY) * (newZoom / oldZoom));

            this.panX = newPanX;
            this.panY = newPanY;
            this.zoom = newZoom;
        },

        changeZoom(targetZoom) {
            let oldZoom = this.zoom;
            let newZoom = Math.min(3.0, Math.max(0.4, Math.round(targetZoom * 100) / 100));
            if (newZoom === oldZoom) return;
            let ws = document.getElementById('designerWorkspace');
            if (ws) {
                let rect = ws.getBoundingClientRect();
                let xm = rect.width / 2;
                let ym = rect.height / 2;
                this.panX = Math.round(xm - (xm - this.panX) * (newZoom / oldZoom));
                this.panY = Math.round(ym - (ym - this.panY) * (newZoom / oldZoom));
            }
            this.zoom = newZoom;
        },

        resetView() {
            this.panX = 0;
            this.panY = 0;
            this.zoom = 1.0;
        },

        toggleFullScreen() {
            this.isFullScreen = !this.isFullScreen;
        },

        getViewportCenterMm(wMm = 40, hMm = 8) {
            let ws = document.getElementById('designerWorkspace');
            let svg = document.getElementById('designerCanvas');
            if (!ws || !svg) return { x: 25.0, y: 40.0 };

            let wsRect = ws.getBoundingClientRect();
            let svgRect = svg.getBoundingClientRect();

            let viewCenterX = wsRect.left + (wsRect.width / 2);
            let viewCenterY = wsRect.top + (wsRect.height / 2);

            let mmPerPxX = this.paperW / (svgRect.width || 1);
            let mmPerPxY = this.paperH / (svgRect.height || 1);

            let centerMmX = (viewCenterX - svgRect.left) * mmPerPxX;
            let centerMmY = (viewCenterY - svgRect.top) * mmPerPxY;

            let targetX = Math.round(Math.max(2.0, Math.min(this.paperW - wMm - 2.0, centerMmX - (wMm / 2))) * 10) / 10;
            let targetY = Math.round(Math.max(2.0, Math.min(this.paperH - hMm - 2.0, centerMmY - (hMm / 2))) * 10) / 10;

            return { x: targetX, y: targetY };
        },

        createStaticText() {
            let pt = this.getViewportCenterMm(50, 8);
            @this.addStaticText(pt.x, pt.y);
        },

        createLine() {
            let pt = this.getViewportCenterMm(170, 1);
            @this.addLine(pt.x, pt.y);
        },

        createRectangle() {
            let pt = this.getViewportCenterMm(60, 30);
            @this.addRectangle(pt.x, pt.y);
        },

        createImage() {
            let pt = this.getViewportCenterMm(40, 40);
            @this.setPendingImagePlacement(pt.x, pt.y);
            let uploader = document.getElementById('image_element_uploader');
            if (uploader) {
                uploader.click();
            }
        },

        createField(fieldKey) {
            let pt = this.getViewportCenterMm(40, 8);
            @this.addField(fieldKey, pt.x, pt.y);
        },

        setGridSpacing(val) {
            let num = parseFloat(val) || 5.0;
            this.gridSizeMm = num;
            @this.setGridSize(num);
        },

        addCustomGuide(orientation, posMm = null) {
            if (!this.customGuides) this.customGuides = { vertical: [], horizontal: [] };
            if (orientation === 'vertical') {
                let v = posMm !== null ? parseFloat(posMm) : (this.cursorMmX > 0 ? this.cursorMmX : Math.round(this.paperW / 2));
                this.customGuides.vertical.push(Math.round(v * 10) / 10);
            } else if (orientation === 'horizontal') {
                let h = posMm !== null ? parseFloat(posMm) : (this.cursorMmY > 0 ? this.cursorMmY : Math.round(this.paperH / 2));
                this.customGuides.horizontal.push(Math.round(h * 10) / 10);
            }
        },

        removeCustomGuide(orientation, gIdx) {
            if (this.customGuides && this.customGuides[orientation]) {
                this.customGuides[orientation].splice(gIdx, 1);
            }
        },

        clearCustomGuides() {
            this.customGuides = { vertical: [], horizontal: [] };
        },

        startInlineEditing(idx) {
            this.inlineEditIndex = idx;
            this.$nextTick(() => {
                let ta = document.getElementById('inline_editor_' + idx);
                if (ta) {
                    ta.focus();
                    ta.select();
                }
            });
        },

        commitInlineEditing(idx, text) {
            if (this.inlineEditIndex !== idx) return;
            this.inlineEditIndex = null;
            let cleanText = (text || '').replace(/<[^>]*>?/gm, '');
            let g = document.getElementById('elem_' + idx);
            if (g) {
                let foDiv = g.querySelector('foreignObject > div');
                if (foDiv) foDiv.textContent = cleanText;
            }
            @this.updateElementConfig(idx, 'static_text', cleanText);
        },

        cancelInlineEditing() {
            this.inlineEditIndex = null;
        },

        openContextMenu(idx, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            if (idx !== null && idx !== undefined) {
                let numIdx = Number(idx);
                this.contextMenuTargetIndex = numIdx;
                this.selectedIndices = [numIdx];
                this.selectedIndex = numIdx;
                @this.selectElement(numIdx);
            } else {
                this.contextMenuTargetIndex = null;
            }

            let mx = event ? event.clientX : 200;
            let my = event ? event.clientY : 200;
            let winW = window.innerWidth;
            let winH = window.innerHeight;
            let menuH = 380;

            this.contextMenuX = Math.max(10, Math.min(winW - 230, mx));
            this.contextMenuY = (my + menuH > winH - 20) ? Math.max(10, my - menuH) : Math.max(10, my);
            this.contextMenuOpen = true;
        },

        closeContextMenu() {
            this.contextMenuOpen = false;
            this.contextMenuTargetIndex = null;
        },

        deleteSelectedObject() {
            let target = this.contextMenuTargetIndex !== null && this.contextMenuTargetIndex !== undefined
                ? this.contextMenuTargetIndex
                : this.selectedIndex;
            if (target !== null && target !== undefined) {
                let numIdx = Number(target);
                this.selectedIndices = [numIdx];
                this.selectedIndex = numIdx;
                @this.deleteElement(numIdx);
            } else if (Array.isArray(this.selectedIndices) && this.selectedIndices.length > 0) {
                @this.deleteSelected();
            }
            this.contextMenuTargetIndex = null;
            this.closeContextMenu();
        },

        updateFieldShowLabel(show, explicitIdx = null) {
            let idx = (explicitIdx !== null && explicitIdx !== undefined) ? Number(explicitIdx) : this.selectedIndex;
            if (idx === null || isNaN(idx)) return;
            this.selectedIndex = idx;
            let g = document.getElementById('elem_' + idx);
            let labelInput = document.getElementById('prop_input_label_text');
            let labelText = labelInput ? labelInput.value.trim() : '';
            if (g) {
                let key = g.getAttribute('data-element-key') || '';
                let rawVal = this.sampleData ? (this.sampleData[key] || key) : key;
                let displayVal = show ? (labelText + ': ' + rawVal) : rawVal;
                let svgTxt = g.querySelector('.elem-rendered-text');
                if (svgTxt) {
                    let tspans = svgTxt.querySelectorAll('tspan');
                    if (tspans.length > 0) tspans[0].textContent = displayVal;
                }
                let preview = document.getElementById('field_label_preview_text');
                if (preview) preview.textContent = displayVal;
            }
            @this.updateElementConfig(idx, 'show_label', !!show);
        },

        updateFieldLabelText(text, explicitIdx = null) {
            let idx = (explicitIdx !== null && explicitIdx !== undefined) ? Number(explicitIdx) : this.selectedIndex;
            if (idx === null || isNaN(idx)) return;
            this.selectedIndex = idx;
            let g = document.getElementById('elem_' + idx);
            let chk = document.getElementById('input_show_label');
            let isShow = chk ? chk.checked : false;
            if (g) {
                let key = g.getAttribute('data-element-key') || '';
                let rawVal = this.sampleData ? (this.sampleData[key] || key) : key;
                let displayVal = isShow ? (text + ': ' + rawVal) : rawVal;
                let svgTxt = g.querySelector('.elem-rendered-text');
                if (svgTxt && isShow) {
                    let tspans = svgTxt.querySelectorAll('tspan');
                    if (tspans.length > 0) tspans[0].textContent = displayVal;
                }
                let preview = document.getElementById('field_label_preview_text');
                if (preview) preview.textContent = displayVal;
            }
            @this.updateElementConfig(idx, 'label_text', text);
        },

        isInputActive() {
            let el = document.activeElement;
            if (!el) return false;
            let tag = el.tagName ? el.tagName.toLowerCase() : '';
            return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
        },

        selectElem(index, event) {
            if (this.activeTool === 'hand' || this.isSpaceHeld) return;
            if (this.hasDragged) {
                this.hasDragged = false;
                return;
            }

            let isModifier = event && (event.ctrlKey || event.metaKey || event.shiftKey);

            if (isModifier) {
                let cur = Array.isArray(this.selectedIndices) ? [...this.selectedIndices].map(Number) : [];
                let numIdx = Number(index);
                let pos = cur.indexOf(numIdx);
                if (pos >= 0) {
                    cur.splice(pos, 1);
                } else {
                    cur.push(numIdx);
                }
                this.selectedIndices = cur;
                this.selectedIndex = cur.length > 0 ? cur[cur.length - 1] : null;
                @this.selectElements(this.selectedIndices);
            } else {
                let numIdx = Number(index);
                if (this.selectedIndex !== numIdx || !this.selectedIndices || this.selectedIndices.length !== 1 || Number(this.selectedIndices[0]) !== numIdx) {
                    this.selectedIndices = [numIdx];
                    this.selectedIndex = numIdx;
                    @this.selectElement(numIdx);
                }
            }

            if (event && typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
        },

        startDrag(index, event) {
            if (this.activeTool === 'hand' || this.isSpaceHeld) {
                this.startPan(event);
                return;
            }
            if (this.isResizing) return;

            let isModifier = event.ctrlKey || event.metaKey || event.shiftKey;
            if (isModifier) {
                return;
            }

            let g = document.getElementById('elem_' + index);
            if (!g) return;

            let isLocked = g.getAttribute('data-locked') === '1';
            if (isLocked) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            this.hasDragged = false;
            let numIdx = Number(index);

            if (Array.isArray(this.selectedIndices) && this.selectedIndices.some(i => Number(i) === numIdx) && this.selectedIndices.length > 1) {
                this.isMultiDragging = true;
                this.dragStartX = event.clientX;
                this.dragStartY = event.clientY;
                this.multiDragStartPositions = {};

                for (let idx of this.selectedIndices) {
                    let el = document.getElementById('elem_' + idx);
                    if (el && el.getAttribute('data-locked') !== '1') {
                        this.multiDragStartPositions[idx] = {
                            x: parseFloat(el.getAttribute('data-x-mm') || '0'),
                            y: parseFloat(el.getAttribute('data-y-mm') || '0'),
                            w: parseFloat(el.getAttribute('data-width-mm') || '10'),
                            h: parseFloat(el.getAttribute('data-height-mm') || '10')
                        };
                    }
                }
            } else {
                if (this.selectedIndex !== index || !this.selectedIndices || this.selectedIndices.length !== 1) {
                    this.selectedIndex = index;
                    this.selectedIndices = [index];
                    @this.selectElement(index);
                }

                this.isDragging = true;
                this.dragIndex = index;
                this.dragStartX = event.clientX;
                this.dragStartY = event.clientY;
                this.startElemX = parseFloat(g.getAttribute('data-x-mm') || '0');
                this.startElemY = parseFloat(g.getAttribute('data-y-mm') || '0');
                this.currentElemX = this.startElemX;
                this.currentElemY = this.startElemY;
            }

            event.preventDefault();
            event.stopPropagation();
        },

        startResize(index, handle, event) {
            if (this.activeTool === 'hand' || this.isSpaceHeld) return;

            if (this.selectedIndex !== index) {
                this.selectedIndex = index;
                this.selectedIndices = [index];
                @this.selectElement(index);
            }

            let g = document.getElementById('elem_' + index);
            if (!g) return;

            let isLocked = g.getAttribute('data-locked') === '1';
            if (isLocked) return;

            this.isResizing = true;
            this.resizeIndex = index;
            this.resizeHandle = handle;
            this.resizeStartX = event.clientX;
            this.resizeStartY = event.clientY;
            this.startElemX = parseFloat(g.getAttribute('data-x-mm') || '0');
            this.startElemY = parseFloat(g.getAttribute('data-y-mm') || '0');
            this.startW = parseFloat(g.getAttribute('data-width-mm') || '10');
            this.startH = parseFloat(g.getAttribute('data-height-mm') || '10');
            this.startFontSize = parseFloat(g.getAttribute('data-font-size') || '10');
            this.lockAspect = g.getAttribute('data-lock-aspect') === '1';
            let parsedAr = parseFloat(g.getAttribute('data-aspect-ratio') || '0');
            this.aspectRatio = (parsedAr > 0) ? parsedAr : (this.startW / (this.startH || 1));
            this.currentX = this.startElemX;
            this.currentY = this.startElemY;
            this.currentW = this.startW;
            this.currentH = this.startH;

            event.preventDefault();
            event.stopPropagation();
        },

        startPan(event) {
            this.isPanning = true;
            this.panStartX = event.clientX;
            this.panStartY = event.clientY;
            this.startPanX = this.panX;
            this.startPanY = this.panY;
            event.preventDefault();
        },

        onWorkspaceMouseDown(event) {
            this.closeContextMenu();

            if (this.activeTool === 'hand' || this.isSpaceHeld || event.button === 1) {
                this.startPan(event);
                return;
            }

            let isCanvasTarget = event.target.id === 'designerCanvas' || 
                                 event.target.id === 'canvasSheetContainer' || 
                                 event.target.id === 'canvasBackground' ||
                                 event.target.id === 'viewportSurface' || 
                                 event.target.id === 'designerWorkspace';

            if (isCanvasTarget) {
                let svg = document.getElementById('designerCanvas');
                if (svg && (event.target.id === 'designerCanvas' || event.target.id === 'canvasBackground' || event.target.id === 'canvasSheetContainer')) {
                    let rect = svg.getBoundingClientRect();
                    let mmX = (event.clientX - rect.left) * (this.paperW / rect.width);
                    let mmY = (event.clientY - rect.top) * (this.paperH / rect.height);

                    this.isMarquee = true;
                    this.marqueeStartMmX = Math.max(0, Math.min(this.paperW, mmX));
                    this.marqueeStartMmY = Math.max(0, Math.min(this.paperH, mmY));
                    this.marqueeMmX = this.marqueeStartMmX;
                    this.marqueeMmY = this.marqueeStartMmY;
                    this.marqueeMmW = 0;
                    this.marqueeMmH = 0;

                    if (!event.shiftKey && !event.ctrlKey && !event.metaKey) {
                        this.selectedIndices = [];
                        this.selectedIndex = null;
                        @this.deselectElement();
                    }
                } else {
                    this.selectedIndex = null;
                    this.selectedIndices = [];
                    @this.deselectElement();
                }
            }
        },

        updateCursorMm(event) {
            let svg = document.getElementById('designerCanvas');
            if (svg) {
                let rect = svg.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) {
                    let mmX = (event.clientX - rect.left) * (this.paperW / rect.width);
                    let mmY = (event.clientY - rect.top) * (this.paperH / rect.height);
                    this.cursorMmX = Math.round(Math.max(0, Math.min(this.paperW, mmX)) * 10) / 10;
                    this.cursorMmY = Math.round(Math.max(0, Math.min(this.paperH, mmY)) * 10) / 10;
                }
            }
        },

        onMouseMove(event) {
            this.updateCursorMm(event);

            if (this.isPanning) {
                let dx = event.clientX - this.panStartX;
                let dy = event.clientY - this.panStartY;
                this.panX = this.startPanX + dx;
                this.panY = this.startPanY + dy;
                return;
            }

            let svg = document.getElementById('designerCanvas');
            if (!svg) return;
            let rect = svg.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) return;
            let scaleX = this.paperW / rect.width;
            let scaleY = this.paperH / rect.height;

            if (this.isMarquee) {
                let curMmX = Math.max(0, Math.min(this.paperW, (event.clientX - rect.left) * scaleX));
                let curMmY = Math.max(0, Math.min(this.paperH, (event.clientY - rect.top) * scaleY));

                this.marqueeMmX = Math.min(this.marqueeStartMmX, curMmX);
                this.marqueeMmY = Math.min(this.marqueeStartMmY, curMmY);
                this.marqueeMmW = Math.abs(curMmX - this.marqueeStartMmX);
                this.marqueeMmH = Math.abs(curMmY - this.marqueeStartMmY);

                if (this.marqueeMmW > 1 || this.marqueeMmH > 1) {
                    let mRight = this.marqueeMmX + this.marqueeMmW;
                    let mBottom = this.marqueeMmY + this.marqueeMmH;
                    let matches = [];

                    let elems = document.querySelectorAll('[id^="elem_"]');
                    elems.forEach(el => {
                        let idx = parseInt(el.getAttribute('data-index') || '-1');
                        if (idx >= 0) {
                            let ex = parseFloat(el.getAttribute('data-x-mm') || '0');
                            let ey = parseFloat(el.getAttribute('data-y-mm') || '0');
                            let ew = parseFloat(el.getAttribute('data-width-mm') || '10');
                            let eh = parseFloat(el.getAttribute('data-height-mm') || '10');

                            let intersects = (ex < mRight && (ex + ew) > this.marqueeMmX &&
                                              ey < mBottom && (ey + eh) > this.marqueeMmY);
                            if (intersects) {
                                matches.push(idx);
                            }
                        }
                    });

                    this.selectedIndices = matches;
                    this.selectedIndex = matches.length > 0 ? matches[matches.length - 1] : null;
                }
                return;
            }

            if (this.isMultiDragging) {
                this.hasDragged = true;
                let dxMm = (event.clientX - this.dragStartX) * scaleX;
                let dyMm = (event.clientY - this.dragStartY) * scaleY;

                if (this.snapToGrid && this.gridSizeMm > 0) {
                    dxMm = Math.round(dxMm / this.gridSizeMm) * this.gridSizeMm;
                    dyMm = Math.round(dyMm / this.gridSizeMm) * this.gridSizeMm;
                }

                dxMm = Math.round(dxMm * 100) / 100;
                dyMm = Math.round(dyMm * 100) / 100;

                let firstIdx = Object.keys(this.multiDragStartPositions)[0];
                if (firstIdx !== undefined) {
                    let ref = this.multiDragStartPositions[firstIdx];
                    let testX = ref.x + dxMm;
                    let testY = ref.y + dyMm;
                    this.activeGuideV = Math.abs(testX - (this.paperW / 2)) < 1.5 ? (this.paperW / 2) : null;
                    this.activeGuideH = Math.abs(testY - (this.paperH / 2)) < 1.5 ? (this.paperH / 2) : null;
                }

                for (let idx in this.multiDragStartPositions) {
                    let init = this.multiDragStartPositions[idx];
                    let newX = Math.round(Math.max(0, Math.min(this.paperW - 2, init.x + dxMm)) * 100) / 100;
                    let newY = Math.round(Math.max(0, Math.min(this.paperH - 2, init.y + dyMm)) * 100) / 100;

                    let g = document.getElementById('elem_' + idx);
                    if (g) {
                        let rot = g.getAttribute('data-rotation') || '0';
                        g.setAttribute('transform', 'translate(' + newX + ', ' + newY + ') rotate(' + rot + ')');
                        g.setAttribute('data-x-mm', newX);
                        g.setAttribute('data-y-mm', newY);

                        let badge = g.querySelector('.elem-coord-badge');
                        if (badge) {
                            badge.textContent = 'X: ' + newX + 'mm, Y: ' + newY + 'mm';
                        }
                    }
                }
                return;
            }

            if (this.isDragging && this.dragIndex !== null) {
                this.hasDragged = true;
                let dxMm = (event.clientX - this.dragStartX) * scaleX;
                let dyMm = (event.clientY - this.dragStartY) * scaleY;

                let newX = Math.max(0, Math.min(this.paperW - 2, this.startElemX + dxMm));
                let newY = Math.max(0, Math.min(this.paperH - 2, this.startElemY + dyMm));

                let g = document.getElementById('elem_' + this.dragIndex);
                let elemW = g ? parseFloat(g.getAttribute('data-width-mm') || '10') : 10;
                let elemH = g ? parseFloat(g.getAttribute('data-height-mm') || '10') : 10;

                let pageCenterX = this.paperW / 2;
                let pageCenterY = this.paperH / 2;

                // Canva-like Smart Guides: VISUAL SUGGESTIONS ONLY (tolerance ~1.5mm)
                // NEVER hard snap coordinates to nearby objects!
                let guideV = null;
                let guideH = null;

                if (Math.abs(newX + (elemW / 2) - pageCenterX) < 1.5) {
                    guideV = pageCenterX;
                } else if (Math.abs(newX) < 1.5) {
                    guideV = 0;
                } else if (Math.abs(newX + elemW - this.paperW) < 1.5) {
                    guideV = this.paperW;
                }

                if (Math.abs(newY + (elemH / 2) - pageCenterY) < 1.5) {
                    guideH = pageCenterY;
                } else if (Math.abs(newY) < 1.5) {
                    guideH = 0;
                } else if (Math.abs(newY + elemH - this.paperH) < 1.5) {
                    guideH = this.paperH;
                }

                // Check nearby elements for visual alignment guides
                let otherElems = document.querySelectorAll('[id^="elem_"]:not(#elem_' + this.dragIndex + ')');
                otherElems.forEach(other => {
                    let ox = parseFloat(other.getAttribute('data-x-mm') || '-999');
                    let oy = parseFloat(other.getAttribute('data-y-mm') || '-999');
                    let ow = parseFloat(other.getAttribute('data-width-mm') || '0');
                    let oh = parseFloat(other.getAttribute('data-height-mm') || '0');

                    if (guideV === null) {
                        if (Math.abs(newX - ox) < 1.5) guideV = ox;
                        else if (Math.abs(newX + elemW - (ox + ow)) < 1.5) guideV = ox + ow;
                        else if (Math.abs(newX + (elemW / 2) - (ox + (ow / 2))) < 1.5) guideV = ox + (ow / 2);
                    }
                    if (guideH === null) {
                        if (Math.abs(newY - oy) < 1.5) guideH = oy;
                        else if (Math.abs(newY + elemH - (oy + oh)) < 1.5) guideH = oy + oh;
                        else if (Math.abs(newY + (elemH / 2) - (oy + (oh / 2))) < 1.5) guideH = oy + (oh / 2);
                    }
                });

                this.activeGuideV = guideV;
                this.activeGuideH = guideH;

                // Snap to Grid is ONLY applied if grid snap is specifically enabled
                if (this.snapToGrid && this.gridSizeMm > 0) {
                    newX = Math.round(newX / this.gridSizeMm) * this.gridSizeMm;
                    newY = Math.round(newY / this.gridSizeMm) * this.gridSizeMm;
                }

                newX = Math.round(newX * 100) / 100;
                newY = Math.round(newY * 100) / 100;

                this.currentElemX = newX;
                this.currentElemY = newY;

                if (g) {
                    let rot = g.getAttribute('data-rotation') || '0';
                    g.setAttribute('transform', 'translate(' + newX + ', ' + newY + ') rotate(' + rot + ')');
                    g.setAttribute('data-x-mm', newX);
                    g.setAttribute('data-y-mm', newY);

                    let badge = g.querySelector('.elem-coord-badge');
                    if (badge) {
                        let w = g.getAttribute('data-width-mm') || '0';
                        let h = g.getAttribute('data-height-mm') || '0';
                        badge.textContent = 'X: ' + newX + 'mm, Y: ' + newY + 'mm | ' + w + '×' + h + 'mm';
                    }
                }

                let xInput = document.getElementById('prop_input_x');
                let yInput = document.getElementById('prop_input_y');
                if (xInput) xInput.value = newX;
                if (yInput) yInput.value = newY;
            }

            else if (this.isResizing && this.resizeIndex !== null) {
                let dxMm = (event.clientX - this.resizeStartX) * scaleX;
                let dyMm = (event.clientY - this.resizeStartY) * scaleY;
                let h = this.resizeHandle;

                let newX = this.startElemX;
                let newY = this.startElemY;
                let newW = this.startW;
                let newH = this.startH;

                // East / West
                if (h.includes('e')) {
                    newW = Math.max(2, this.startW + dxMm);
                } else if (h.includes('w')) {
                    let maxDx = this.startW - 2;
                    let actualDx = Math.min(maxDx, dxMm);
                    newX = this.startElemX + actualDx;
                    newW = this.startW - actualDx;
                }

                // South / North
                if (h.includes('s')) {
                    newH = Math.max(0.5, this.startH + dyMm);
                } else if (h.includes('n')) {
                    let maxDy = this.startH - 0.5;
                    let actualDy = Math.min(maxDy, dyMm);
                    newY = this.startElemY + actualDy;
                    newH = this.startH - actualDy;
                }

                // Aspect Ratio Rule:
                // Corner handles (nw, ne, se, sw) respect lock aspect ratio if enabled.
                // Middle handles (n, e, s, w) MUST ALWAYS resize freely along their axis!
                let isCorner = ['nw', 'ne', 'se', 'sw'].includes(h);
                if (isCorner && this.lockAspect && this.aspectRatio > 0) {
                    let ar = this.aspectRatio;
                    if (h === 'se' || h === 'sw') {
                        newH = Math.max(0.5, newW / ar);
                    } else if (h === 'ne' || h === 'nw') {
                        newH = Math.max(0.5, newW / ar);
                        newY = this.startElemY + (this.startH - newH);
                    }
                }

                if (this.snapToGrid && this.gridSizeMm > 0) {
                    newW = Math.max(this.gridSizeMm, Math.round(newW / this.gridSizeMm) * this.gridSizeMm);
                    newH = Math.max(0.5, Math.round(newH / this.gridSizeMm) * this.gridSizeMm);
                }

                newX = Math.round(newX * 100) / 100;
                newY = Math.round(newY * 100) / 100;
                newW = Math.round(newW * 100) / 100;
                newH = Math.round(newH * 100) / 100;

                this.currentX = newX;
                this.currentY = newY;
                this.currentW = newW;
                this.currentH = newH;

                let g = document.getElementById('elem_' + this.resizeIndex);
                if (g) {
                    let rot = g.getAttribute('data-rotation') || '0';
                    g.setAttribute('transform', 'translate(' + newX + ', ' + newY + ') rotate(' + rot + ')');
                    g.setAttribute('data-x-mm', newX);
                    g.setAttribute('data-y-mm', newY);
                    g.setAttribute('data-width-mm', newW);
                    g.setAttribute('data-height-mm', newH);

                    let elemType = g.getAttribute('data-element-type');

                    // Visual Font Scaling on Resize for text elements if enabled - STRICTLY NW, NE, SE, SW ONLY
                    if (isCorner && this.scaleFontOnResize && (elemType === 'FIELD' || elemType === 'STATIC_TEXT') && this.startH > 0) {
                        let scaleRatio = newH / this.startH;
                        let newFontSizePt = Math.max(4.0, Math.min(72.0, Math.round((this.startFontSize * scaleRatio) * 10) / 10));
                        g.setAttribute('data-font-size', newFontSizePt);
                        let foDiv = g.querySelector('foreignObject > div');
                        if (foDiv) {
                            foDiv.style.fontSize = (newFontSizePt * (25.4 / 72)) + 'px';
                        }
                        let fsInput = document.getElementById('prop_input_font_size');
                        if (fsInput) fsInput.value = newFontSizePt;
                    }

                    let innerLine = g.querySelector('.elem-line');
                    if (innerLine) {
                        innerLine.setAttribute('x2', newW);
                        innerLine.setAttribute('y1', newH / 2);
                        innerLine.setAttribute('y2', newH / 2);
                    }
                    let innerRect = g.querySelector('.elem-rect');
                    if (innerRect) {
                        innerRect.setAttribute('width', newW);
                        innerRect.setAttribute('height', newH);
                    }
                    let innerImg = g.querySelector('.elem-image');
                    if (innerImg) {
                        innerImg.setAttribute('width', newW);
                        innerImg.setAttribute('height', newH);
                    }
                    let innerFO = g.querySelector('foreignObject');
                    if (innerFO) {
                        innerFO.setAttribute('width', newW);
                        innerFO.setAttribute('height', newH);
                    }

                    let svgTxt = g.querySelector('.elem-rendered-text');
                    if (svgTxt) {
                        let anchor = svgTxt.getAttribute('text-anchor');
                        let tx = 0;
                        if (anchor === 'middle') tx = newW / 2;
                        else if (anchor === 'end') tx = newW;
                        svgTxt.setAttribute('x', tx);
                        let tspans = svgTxt.querySelectorAll('tspan');
                        tspans.forEach(ts => ts.setAttribute('x', tx));

                        if (isCorner && this.scaleFontOnResize && (elemType === 'FIELD' || elemType === 'STATIC_TEXT') && this.startH > 0) {
                            let scaleRatio = newH / this.startH;
                            let newFontSizePt = Math.max(4.0, Math.min(72.0, Math.round((this.startFontSize * scaleRatio) * 10) / 10));
                            let fsPx = newFontSizePt * (25.4 / 72);
                            svgTxt.setAttribute('font-size', fsPx);
                            let foDiv = g.querySelector('foreignObject > div');
                            if (foDiv) foDiv.style.fontSize = fsPx + 'px';
                            let fsInput = document.getElementById('prop_input_font_size');
                            if (fsInput) fsInput.value = newFontSizePt;
                        }
                    }

                    let outline = g.querySelector('.elem-outline');
                    if (outline) {
                        outline.setAttribute('x', 0);
                        outline.setAttribute('y', 0);
                        outline.setAttribute('width', newW);
                        outline.setAttribute('height', newH);
                    }

                    let hNW = g.querySelector('.handle-nw');
                    let hN  = g.querySelector('.handle-n');
                    let hNE = g.querySelector('.handle-ne');
                    let hE  = g.querySelector('.handle-e');
                    let hSE = g.querySelector('.handle-se');
                    let hS  = g.querySelector('.handle-s');
                    let hSW = g.querySelector('.handle-sw');
                    let hW  = g.querySelector('.handle-w');

                    if (hNW) { hNW.setAttribute('x', -1); hNW.setAttribute('y', -1); }
                    if (hN)  { hN.setAttribute('x', (newW / 2) - 1); hN.setAttribute('y', -1); }
                    if (hNE) { hNE.setAttribute('x', newW - 1); hNE.setAttribute('y', -1); }
                    if (hE)  { hE.setAttribute('x', newW - 1); hE.setAttribute('y', (newH / 2) - 1); }
                    if (hSE) { hSE.setAttribute('x', newW - 1); hSE.setAttribute('y', newH - 1); }
                    if (hS)  { hS.setAttribute('x', (newW / 2) - 1); hS.setAttribute('y', newH - 1); }
                    if (hSW) { hSW.setAttribute('x', -1); hSW.setAttribute('y', newH - 1); }
                    if (hW)  { hW.setAttribute('x', -1); hW.setAttribute('y', (newH / 2) - 1); }

                    let badge = g.querySelector('.elem-coord-badge');
                    if (badge) {
                        badge.textContent = 'X: ' + newX + 'mm, Y: ' + newY + 'mm | ' + newW + '×' + newH + 'mm';
                    }
                }

                let xInput = document.getElementById('prop_input_x');
                let yInput = document.getElementById('prop_input_y');
                let wInput = document.getElementById('prop_input_w');
                let hInput = document.getElementById('prop_input_h');
                if (xInput) xInput.value = newX;
                if (yInput) yInput.value = newY;
                if (wInput) wInput.value = newW;
                if (hInput) hInput.value = newH;
            }
        },

        onMouseUp(event) {
            this.activeGuideV = null;
            this.activeGuideH = null;

            if (this.isPanning) {
                this.isPanning = false;
            }

            if (this.isMarquee) {
                this.isMarquee = false;
                this.marqueeMmW = 0;
                this.marqueeMmH = 0;
                if (Array.isArray(this.selectedIndices) && this.selectedIndices.length > 0) {
                    @this.selectElements(this.selectedIndices);
                } else {
                    @this.deselectElement();
                }
            }

            if (this.isMultiDragging) {
                this.isMultiDragging = false;
                let moved = [];
                for (let idx in this.multiDragStartPositions) {
                    let g = document.getElementById('elem_' + idx);
                    if (g) {
                        let finalX = parseFloat(g.getAttribute('data-x-mm') || '0');
                        let finalY = parseFloat(g.getAttribute('data-y-mm') || '0');
                        let init = this.multiDragStartPositions[idx];
                        if (Math.abs(finalX - init.x) > 0.01 || Math.abs(finalY - init.y) > 0.01) {
                            moved.push({ index: parseInt(idx), x_mm: finalX, y_mm: finalY });
                        }
                    }
                }
                this.multiDragStartPositions = {};
                if (moved.length > 0) {
                    @this.handleElementsMoved(moved);
                }
            }

            if (this.isDragging && this.dragIndex !== null) {
                this.isDragging = false;
                let idx = this.dragIndex;
                let finalX = this.currentElemX;
                let finalY = this.currentElemY;
                this.dragIndex = null;
                if (Math.abs(finalX - this.startElemX) > 0.01 || Math.abs(finalY - this.startElemY) > 0.01) {
                    @this.handleElementMoved(idx, finalX, finalY);
                }
            }

            if (this.isResizing && this.resizeIndex !== null) {
                this.isResizing = false;
                let idx = this.resizeIndex;
                let finalX = this.currentX;
                let finalY = this.currentY;
                let finalW = this.currentW;
                let finalH = this.currentH;
                let wasCorner = ['nw', 'ne', 'se', 'sw'].includes(this.resizeHandle);
                this.resizeIndex = null;
                this.resizeHandle = null;
                if (finalX !== this.startElemX || finalY !== this.startElemY || finalW !== this.startW || finalH !== this.startH) {
                    @this.handleElementResized(idx, finalX, finalY, finalW, finalH);

                    let g = document.getElementById('elem_' + idx);
                    if (g && wasCorner && this.scaleFontOnResize) {
                        let scaledFs = parseFloat(g.getAttribute('data-font-size') || '0');
                        if (scaledFs > 0) {
                            @this.updateElementProperty(idx, 'font_size', scaledFs);
                        }
                    }
                }
            }
        },

        updateElementProp(prop, val, explicitIdx = null) {
            let idx = (explicitIdx !== null && explicitIdx !== undefined) ? explicitIdx : this.selectedIndex;
            if (idx === null || idx === undefined) return;
            this.selectedIndex = idx;

            let g = document.getElementById('elem_' + idx);
            if (g) {
                let svgTxt = g.querySelector('.elem-rendered-text');
                let foDiv = g.querySelector('foreignObject > div');
                let w = parseFloat(g.getAttribute('data-width-mm') || '10');

                if (prop === 'font_size') {
                    let pt = parseFloat(val) || 10;
                    let fsPx = pt * (25.4 / 72);
                    if (svgTxt) svgTxt.setAttribute('font-size', fsPx);
                    if (foDiv) foDiv.style.fontSize = fsPx + 'px';
                    g.setAttribute('data-font-size', val);
                } else if (prop === 'font_family') {
                    if (svgTxt) svgTxt.setAttribute('font-family', val + ', sans-serif');
                    if (foDiv) foDiv.style.fontFamily = val + ', sans-serif';
                } else if (prop === 'font_weight') {
                    if (svgTxt) svgTxt.setAttribute('font-weight', val);
                    if (foDiv) foDiv.style.fontWeight = val;
                } else if (prop === 'font_style') {
                    if (svgTxt) svgTxt.setAttribute('font-style', val);
                    if (foDiv) foDiv.style.fontStyle = val;
                } else if (prop === 'text_align') {
                    if (svgTxt) {
                        let anchor = (val === 'center') ? 'middle' : ((val === 'right') ? 'end' : 'start');
                        let tx = (val === 'center') ? (w / 2) : ((val === 'right') ? w : 0);
                        svgTxt.setAttribute('text-anchor', anchor);
                        svgTxt.setAttribute('x', tx);
                        let tspans = svgTxt.querySelectorAll('tspan');
                        tspans.forEach(ts => ts.setAttribute('x', tx));
                    }
                    if (foDiv) foDiv.style.textAlign = val;
                } else if (prop === 'color') {
                    if (svgTxt) svgTxt.setAttribute('fill', val);
                    if (foDiv) foDiv.style.color = val;
                    let line = g.querySelector('.elem-line');
                    if (line) line.setAttribute('stroke', val);
                    let rect = g.querySelector('.elem-rect');
                    if (rect) rect.setAttribute('stroke', val);
                } else if (prop === 'rotation') {
                    let x = g.getAttribute('data-x-mm') || '0';
                    let y = g.getAttribute('data-y-mm') || '0';
                    g.setAttribute('transform', 'translate(' + x + ', ' + y + ') rotate(' + val + ')');
                    g.setAttribute('data-rotation', val);
                } else if (prop === 'static_text' || prop === 'content') {
                    this.updateElementCfg('static_text', val);
                    return;
                }
            }

            @this.updateElementProperty(idx, prop, val);
        },

        updateElementCfg(key, val) {
            if (this.selectedIndex === null) return;
            let idx = this.selectedIndex;

            let g = document.getElementById('elem_' + idx);
            if (g && key === 'static_text') {
                let svgTxt = g.querySelector('.elem-rendered-text');
                if (svgTxt) {
                    let tx = svgTxt.getAttribute('x') || '0';
                    let lines = (val || '').split('\n');
                    let fs = parseFloat(svgTxt.getAttribute('font-size') || '3.528');
                    let dyStep = fs * 1.15;
                    svgTxt.innerHTML = '';
                    lines.forEach((l, lIdx) => {
                        let ts = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
                        ts.setAttribute('x', tx);
                        ts.setAttribute('xml:space', 'preserve');
                        if (lIdx > 0) ts.setAttribute('dy', dyStep);
                        ts.textContent = l;
                        svgTxt.appendChild(ts);
                    });
                }
                let foDiv = g.querySelector('foreignObject > div');
                if (foDiv) foDiv.textContent = val;
            }

            @this.updateElementConfig(idx, key, val);
        },

        handleKeyDown(event) {
            if (this.isInputActive() || this.inlineEditIndex !== null) return;

            let isCtrlOrCmd = event.ctrlKey || event.metaKey;

            if (isCtrlOrCmd && !event.shiftKey && (event.key === 'z' || event.key === 'Z')) {
                @this.undo();
                event.preventDefault();
                return;
            }

            if ((isCtrlOrCmd && event.shiftKey && (event.key === 'z' || event.key === 'Z')) ||
                (isCtrlOrCmd && (event.key === 'y' || event.key === 'Y'))) {
                @this.redo();
                event.preventDefault();
                return;
            }

            if (isCtrlOrCmd && (event.key === 'd' || event.key === 'D')) {
                @this.duplicateSelected();
                event.preventDefault();
                return;
            }

            if (isCtrlOrCmd && (event.key === 'c' || event.key === 'C')) {
                @this.copySelected();
                event.preventDefault();
                return;
            }

            if (isCtrlOrCmd && (event.key === 'x' || event.key === 'X')) {
                @this.cutSelected();
                event.preventDefault();
                return;
            }

            if (isCtrlOrCmd && (event.key === 'v' || event.key === 'V')) {
                @this.paste();
                event.preventDefault();
                return;
            }

            if (isCtrlOrCmd && !event.shiftKey && (event.key === 'g' || event.key === 'G')) {
                @this.groupSelected();
                event.preventDefault();
                return;
            }

            if (isCtrlOrCmd && event.shiftKey && (event.key === 'g' || event.key === 'G')) {
                @this.ungroupSelected();
                event.preventDefault();
                return;
            }

            if (isCtrlOrCmd && (event.key === 'a' || event.key === 'A')) {
                @this.selectAll();
                event.preventDefault();
                return;
            }

            if (event.code === 'Space' && !this.isSpaceHeld) {
                this.isSpaceHeld = true;
                event.preventDefault();
                return;
            }

            if (event.key === 'v' || event.key === 'V') {
                this.setTool('select');
                return;
            }

            // H shortcut toggle behavior: Select -> H -> Hand; Hand -> H -> Select
            if (event.key === 'h' || event.key === 'H') {
                if (this.activeTool === 'hand') {
                    this.setTool('select');
                } else {
                    this.setTool('hand');
                }
                return;
            }

            // F shortcut toggle behavior: Fullscreen toggle (Requirement 33)
            if (!isCtrlOrCmd && !event.altKey && !event.shiftKey && (event.key === 'f' || event.key === 'F')) {
                this.toggleFullScreen();
                event.preventDefault();
                return;
            }

            // CorelDRAW-like Keyboard Alignment Shortcuts (L, R, C, T, B, E)
            if (this.selectedIndex !== null || (Array.isArray(this.selectedIndices) && this.selectedIndices.length > 0)) {
                let k = event.key.toLowerCase();
                if (k === 'l') {
                    @this.alignElements('left');
                    event.preventDefault();
                    return;
                } else if (k === 'r') {
                    @this.alignElements('right');
                    event.preventDefault();
                    return;
                } else if (k === 'c') {
                    @this.alignElements('center');
                    event.preventDefault();
                    return;
                } else if (k === 't') {
                    @this.alignElements('top');
                    event.preventDefault();
                    return;
                } else if (k === 'b') {
                    @this.alignElements('bottom');
                    event.preventDefault();
                    return;
                } else if (k === 'e') {
                    @this.alignElements('middle');
                    event.preventDefault();
                    return;
                }
            }

            if (event.key === 'Escape') {
                this.closeContextMenu();
                this.cancelInlineEditing();
                this.selectedIndex = null;
                this.selectedIndices = [];
                @this.deselectElement();
                return;
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                this.deleteSelectedObject();
                event.preventDefault();
                return;
            }

            if ((this.selectedIndex !== null || (Array.isArray(this.selectedIndices) && this.selectedIndices.length > 0)) && 
                ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
                let step = event.shiftKey ? 0.1 : 0.5;
                let dx = 0, dy = 0;
                if (event.key === 'ArrowLeft') dx = -step;
                if (event.key === 'ArrowRight') dx = step;
                if (event.key === 'ArrowUp') dy = -step;
                if (event.key === 'ArrowDown') dy = step;

                let targetIdx = this.selectedIndex ?? (this.selectedIndices[0] ?? 0);
                @this.nudge(targetIdx, dx, dy);
                event.preventDefault();
            }
        },

        handleKeyUp(event) {
            if (event.code === 'Space') {
                this.isSpaceHeld = false;
                if (this.isPanning && this.activeTool !== 'hand') {
                    this.isPanning = false;
                }
            }
        }
    };
}
</script>
