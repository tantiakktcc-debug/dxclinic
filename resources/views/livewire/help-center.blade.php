<div class="space-y-6">
    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4 no-print">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-teal-100 text-teal-800 border border-teal-200">Revision 49+ Reference</span>
                <span class="text-xs text-slate-400">&bull;</span>
                <span class="text-xs font-semibold text-slate-500">Single-Source Documentation Engine</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-1">Help Center & Official Manuals</h1>
            <p class="text-xs text-slate-500 mt-0.5">Authoritative standard operating procedures, clinical guidelines, WhatsApp Desk architecture, and double-entry accounting manuals</p>
        </div>
        <div class="flex items-center gap-2.5">
            <button type="button" onclick="window.print()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Document</span>
            </button>
            <a href="{{ route('pdf.manual', $activeTab) }}" target="_blank" class="px-3.5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Download Official PDF</span>
            </a>
        </div>
    </div>

    <!-- Manual Catalog Pills -->
    <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-2xs no-print">
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
            @foreach($catalog as $key => $meta)
                <button type="button" 
                    wire:click="setTab('{{ $key }}')" 
                    class="px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === $key ? 'bg-teal-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    <span>{{ $meta['title'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <!-- Search & Quick Info Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-2xs no-print">
        <div class="relative flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Filter this document or search keywords..." class="w-full pl-9 pr-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <div class="flex items-center gap-3 text-xs text-slate-500 px-1">
            <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Role: <strong>{{ $currentMeta['role'] }}</strong></span>
            </span>
            <span>&bull;</span>
            <span>Category: <strong>{{ $currentMeta['category'] }}</strong></span>
        </div>
    </div>

    <!-- Main Documentation Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        <!-- Sticky Table of Contents Sidebar -->
        @if(!empty($toc) && empty($search))
        <div class="hidden lg:block lg:col-span-1 sticky top-6 bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs max-h-[calc(100vh-6rem)] overflow-y-auto no-print">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                <span class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                    Table of Contents
                </span>
                <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-md font-semibold">{{ count($toc) }} items</span>
            </div>
            <nav class="space-y-1">
                @foreach($toc as $item)
                    <a href="#{{ $item['slug'] }}" 
                       class="block text-xs py-1 transition truncate hover:text-teal-600 {{ $item['level'] === 2 ? 'font-bold text-slate-800' : 'pl-3 font-normal text-slate-500 hover:text-slate-800' }}"
                       title="{{ $item['title'] }}">
                        {{ $item['title'] }}
                    </a>
                @endforeach
            </nav>

            <div class="mt-6 pt-4 border-t border-slate-100">
                <div class="text-[11px] font-semibold text-slate-500 mb-2">Canonical Developer</div>
                <a href="https://nexorasolutionsind.co.in/" target="_blank" class="text-xs font-bold text-teal-700 hover:underline flex items-center gap-1">
                    <span>Nexora Solutions</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
                <div class="text-[10px] text-slate-400 mt-0.5">Varanasi, UP, India</div>
            </div>
        </div>
        @endif

        <!-- Document Viewer (Structured PDF Manual Representation) -->
        <div class="{{ (!empty($toc) && empty($search)) ? 'lg:col-span-3' : 'lg:col-span-4' }}">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-10 min-h-[600px] doc-manual-canvas">
                <!-- Document Header (Matching PDF Layout) -->
                <div class="pb-5 mb-6 border-b-[2.5px] border-teal-700">
                    <div class="flex items-center justify-between">
                        <span class="text-xl sm:text-2xl font-black text-teal-700 tracking-wide uppercase">DxClinic Clinical Management System</span>
                        <span class="hidden sm:inline-block px-2.5 py-1 bg-slate-100 text-slate-700 rounded-md text-[11px] font-bold border border-slate-200">ISO/DIS Ready</span>
                    </div>
                    <div class="text-base sm:text-lg font-bold text-slate-900 mt-1">
                        {{ $currentMeta['full_title'] }}
                    </div>
                </div>

                <!-- Document Meta-Box (Strict Match with PDF Meta Box) -->
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-8 text-xs text-slate-600 shadow-2xs">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <span class="font-bold text-slate-900">System:</span> DxClinic Clinical Suite
                        </div>
                        <div>
                            <span class="font-bold text-slate-900">Manual Type:</span> {{ $currentMeta['type'] }}
                        </div>
                        <div>
                            <span class="font-bold text-slate-900">Version:</span> Revision 49+
                        </div>
                        <div>
                            <span class="font-bold text-slate-900">Last Updated:</span> {{ date('F Y') }}
                        </div>
                        <div>
                            <span class="font-bold text-slate-900">Developer:</span> Kundan Kumar (Nexora Solutions)
                        </div>
                        <div>
                            <span class="font-bold text-slate-900">Website:</span> 
                            <a href="https://nexorasolutionsind.co.in/" target="_blank" class="text-teal-700 font-bold hover:underline">
                                https://nexorasolutionsind.co.in/
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Structured Rendered Document Content -->
                <div class="prose prose-slate max-w-none text-xs leading-relaxed doc-content-body">
                    {!! $renderedHtml !!}
                </div>

                <!-- Document Footer (Strict Match with PDF Footer) -->
                <div class="mt-12 pt-4 border-t border-slate-200 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <div>
                        DxClinic Revision 49+ &bull; {{ $currentMeta['full_title'] }} &bull; Developer: Kundan Kumar &bull; 
                        <a href="https://nexorasolutionsind.co.in/" target="_blank" class="text-teal-700 hover:underline font-semibold">https://nexorasolutionsind.co.in/</a>
                    </div>
                    <div class="text-[11px] text-slate-400 font-mono">
                        Nexora Solutions &bull; Varanasi, UP, India
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
