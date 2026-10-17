<div class="fixed bottom-6 right-6 z-50">
    <!-- Chat Trigger Floating Button (Section 44) -->
    @if (!$isOpen)
        <button type="button" wire:click="toggleChat" id="btn_open_chatbot" class="relative group flex items-center gap-2 px-5 py-3.5 bg-gradient-to-r from-teal-600 to-indigo-600 hover:from-teal-700 hover:to-indigo-700 text-white rounded-full shadow-2xl transition-all duration-300 transform hover:scale-105">
            <span class="flex h-3 w-3 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-200 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
            </span>
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
            <span class="text-sm font-bold tracking-wide">Help / Chat</span>
        </button>
    @else
        <!-- Chatbot Window -->
        <div class="w-96 max-w-[calc(100vw-2rem)] h-[550px] max-h-[85vh] bg-white rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden animate-in fade-in slide-in-from-bottom-6 duration-300">
            <!-- Header -->
            <div class="bg-gradient-to-r from-teal-700 to-indigo-900 px-4 py-3.5 text-white flex items-center justify-between">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-full bg-teal-500/30 border border-teal-300/40 flex items-center justify-center font-bold text-xs">
                        Dx
                    </div>
                    <div>
                        <h3 class="text-xs font-bold leading-tight">DxClinic Assistant</h3>
                        <p class="text-[10px] text-teal-200 flex items-center">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5"></span> Online
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="toggleChat" id="btn_close_chatbot" class="text-teal-200 hover:text-white p-1 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Messages Scroll Area -->
            <div class="flex-1 p-4 overflow-y-auto space-y-3 text-xs bg-slate-50/60" id="chat_messages_container">
                @foreach ($messages as $msg)
                    @if ($msg['sender'] === 'bot')
                        <div class="flex flex-col items-start max-w-[90%]">
                            <div class="bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-tl-sm px-3.5 py-2.5 shadow-sm whitespace-pre-line leading-relaxed">
                                {{ $msg['text'] }}
                                @if (!empty($msg['source']))
                                    <div class="mt-2 pt-2 border-t border-slate-100 text-[10px] text-teal-700 font-semibold flex items-center gap-1">
                                        <svg class="w-3 h-3 text-teal-600 inline shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                        <span>Source: {{ $msg['source'] }}</span>
                                    </div>
                                @endif
                            </div>
                            <span class="text-[9px] text-slate-400 mt-1 pl-1">{{ $msg['time'] ?? '' }}</span>

                            <!-- Bot Options / Buttons (Section 45, 46) -->
                            @if (!empty($msg['options']))
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach ($msg['options'] as $opt)
                                        <button type="button" wire:click="handleOption('{{ $opt['action'] }}')" class="px-3 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 rounded-xl font-semibold text-xs shadow-xs transition-colors">
                                            {{ $opt['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif



                            <!-- Token PDF link (Section 51) -->
                            @if (!empty($msg['pdf_url']))
                                <div class="mt-2.5 w-full">
                                    <a href="{{ $msg['pdf_url'] }}" target="_blank" class="w-full inline-flex items-center justify-center px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        Download Token PDF
                                    </a>
                                </div>
                            @endif
                        </div>
                    @else
                        <!-- User message bubble -->
                        <div class="flex flex-col items-end self-end max-w-[85%] ml-auto">
                            <div class="bg-gradient-to-r from-teal-600 to-teal-700 text-white rounded-2xl rounded-tr-sm px-3.5 py-2 shadow-xs">
                                {{ $msg['text'] }}
                            </div>
                            <span class="text-[9px] text-slate-400 mt-1 pr-1">{{ $msg['time'] ?? '' }}</span>
                        </div>
                    @endif
                @endforeach
            </div>

            <!-- Input Box -->
            <form wire:submit.prevent="sendMessage" class="p-2.5 bg-white border-t border-slate-200 flex gap-2">
                <input type="text" wire:model="userMessage" id="chatbot_input_text" placeholder="Type 'Help' or 'Hi'..." class="flex-1 px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                <button type="submit" id="btn_chatbot_send" class="px-3.5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl text-xs flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </form>
        </div>
    @endif
</div>
