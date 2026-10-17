<div class="h-[calc(100vh-5rem)] flex flex-col bg-slate-100 -m-6"
     x-data="{
         init() {
             this.scrollToBottom();
             Livewire.on('chat-scrolled-to-bottom', () => {
                 this.$nextTick(() => this.scrollToBottom());
             });
         },
         scrollToBottom() {
             const container = document.getElementById('chat-scroll-container');
             if (container) {
                 container.scrollTop = container.scrollHeight;
             }
         }
     }"
     @if($pollingEnabled) wire:poll.5s @endif>

    <!-- Top Alert Notifications -->
    @if (session()->has('message'))
        <div class="bg-emerald-50 border-b border-emerald-200 px-6 py-2 text-xs font-semibold text-emerald-800 flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-rose-50 border-b border-rose-200 px-6 py-2 text-xs font-semibold text-rose-800 flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-500 hover:text-rose-700">&times;</button>
        </div>
    @endif

    <!-- Three-Pane Main Workspace (Stable CSS Grid — never reflows on filter change) -->
    <div class="flex-1 overflow-hidden"
         style="display: grid; grid-template-columns: 320px minmax(520px, 1fr) 360px; grid-template-rows: 1fr;">

        <!-- =============================================================
             LEFT PANE: CONVERSATION LIST & FILTERS (Width: 320px)
             ============================================================= -->
        <aside class="bg-white border-r border-slate-200 flex flex-col overflow-hidden" style="min-width: 0;">
            <!-- Header & Search -->
            <div class="p-4 border-b border-slate-200 bg-slate-50/60 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                            <span>WhatsApp Desk</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                {{ $clinic->name }}
                            </span>
                        </h2>
                        <p class="text-[11px] text-slate-500 font-medium">Conversational OPD Booking &amp; Triage</p>
                    </div>

                    <!-- Connection indicator -->
                    @if($connection && in_array($connection->status, ['CONNECTED', 'ACTIVE']))
                        <span title="WhatsApp Connected via {{ strtoupper($connection->provider ?? 'BAILEYS') }}" class="flex h-3 w-3 relative" aria-label="Connected">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                    @else
                        <span title="WhatsApp Inactive / Disconnected" class="inline-block h-3 w-3 rounded-full bg-amber-400" aria-label="Offline"></span>
                    @endif
                </div>

                <!-- Search Input -->
                <div class="relative">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search phone, name, or text..."
                           class="w-full pl-9 pr-3 py-1.5 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-slate-800 placeholder-slate-400 shadow-2xs">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>

                <!-- Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-[11px] font-semibold no-scrollbar">
                    <button type="button"
                            wire:click="filterByStatus('ALL')"
                            class="px-2.5 py-1 rounded-lg transition whitespace-nowrap {{ $statusFilter === 'ALL' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All
                    </button>
                    <button type="button"
                            wire:click="filterByStatus('NEEDS_HUMAN')"
                            class="px-2.5 py-1 rounded-lg transition whitespace-nowrap {{ $statusFilter === 'NEEDS_HUMAN' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                        Needs Human
                    </button>
                    <button type="button"
                            wire:click="filterByStatus('AI_ACTIVE')"
                            class="px-2.5 py-1 rounded-lg transition whitespace-nowrap {{ $statusFilter === 'AI_ACTIVE' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' }}">
                        AI Active
                    </button>
                    <button type="button"
                            wire:click="filterByStatus('BOOKED')"
                            class="px-2.5 py-1 rounded-lg transition whitespace-nowrap {{ $statusFilter === 'BOOKED' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                        Booked
                    </button>
                    <button type="button"
                            wire:click="filterByStatus('UNREAD')"
                            class="px-2.5 py-1 rounded-lg transition whitespace-nowrap {{ $statusFilter === 'UNREAD' ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' }}">
                        Unread
                    </button>
                    <button type="button"
                            wire:click="filterByStatus('CLOSED')"
                            class="px-2.5 py-1 rounded-lg transition whitespace-nowrap {{ $statusFilter === 'CLOSED' ? 'bg-slate-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Closed
                    </button>
                </div>
            </div>

            <!-- Conversations Scroll List -->
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                @forelse($conversations as $conv)
                    @php
                        $isSelected = ($selectedConversationId === $conv->id);
                        $latestMsg = $conv->messages->last();
                        $unreadCount = $conv->messages->where('direction', 'INBOUND')->where('status', '!=', 'READ')->count();
                        $isNeedsHuman = ($conv->human_handoff_active || $conv->current_state === 'HUMAN_HANDOFF');
                    @endphp
                    <div wire:key="conv-{{ $conv->id }}"
                         wire:click="selectConversation({{ $conv->id }})"
                         class="p-3.5 cursor-pointer transition flex items-start gap-3 hover:bg-slate-50 {{ $isSelected ? 'bg-emerald-50/70 border-l-4 border-emerald-600' : '' }}">

                        <!-- Contact Avatar with Initial -->
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs shrink-0 {{ $isNeedsHuman ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700' }}">
                            {{ substr(preg_replace('/[^0-9]/', '', $conv->wa_id), -2) }}
                        </div>

                        <!-- Conversation Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                <span class="font-bold text-xs text-slate-900 truncate">
                                    +{{ $conv->wa_id }}
                                </span>
                                <span class="text-[10px] text-slate-400 whitespace-nowrap font-medium"
                                      title="{{ $conv->last_patient_message_at ? \App\Services\AppTimestampFormatter::formatDateTimeWithTz($conv->last_patient_message_at) : ($conv->updated_at ? \App\Services\AppTimestampFormatter::formatDateTimeWithTz($conv->updated_at) : '') }}">
                                    {{ $conv->last_patient_message_at ? \App\Services\AppTimestampFormatter::diffForHumans($conv->last_patient_message_at) : ($conv->updated_at ? \App\Services\AppTimestampFormatter::diffForHumans($conv->updated_at) : '') }}
                                </span>
                            </div>

                            <!-- Last Message Preview -->
                            <p class="text-xs text-slate-500 truncate mb-1.5 font-normal">
                                @if($latestMsg)
                                    @if($latestMsg->direction === 'OUTBOUND')
                                        <span class="text-slate-400 font-medium">You: </span>
                                    @endif
                                    {{ $latestMsg->content }}
                                @else
                                    <span class="italic text-slate-400">No messages yet</span>
                                @endif
                            </p>

                            <!-- State Badges -->
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @if($isNeedsHuman)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        Needs Human
                                    </span>
                                @elseif($conv->current_state === 'BOOKED')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Token Booked
                                    </span>
                                @elseif($conv->current_state === 'CLOSED')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">
                                        Closed
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700">
                                        AI: {{ $conv->current_state }}
                                    </span>
                                @endif

                                @if($unreadCount > 0)
                                    <span class="ml-auto inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500 text-white">
                                        {{ $unreadCount }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-400">
                        <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <p class="text-xs font-semibold">No conversations found</p>
                        <p class="text-[11px] text-slate-400 mt-1">When patients send WhatsApp messages, they will appear here in real-time.</p>
                    </div>
                @endforelse
            </div>

            <!-- Left Footer: Pagination -->
            @if($conversations->hasPages())
                <div class="p-2 border-t border-slate-200 text-xs">
                    {{ $conversations->links() }}
                </div>
            @endif
        </aside>

        <!-- =============================================================
             CENTER PANE: CHAT THREAD & COMPOSER (Flex-1)
             ============================================================= -->
        <main class="flex flex-col bg-slate-50 overflow-hidden" style="min-width: 0;">
            @if($selectedConv)
                <!-- Chat Conversation Header -->
                <div class="px-6 py-3 bg-white border-b border-slate-200 flex items-center justify-between shrink-0 shadow-2xs">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-sm shrink-0">
                            WA
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-extrabold text-slate-900 text-sm">
                                    {{ $matchedPatient ? $matchedPatient->name : 'Unregistered Contact' }}
                                </h3>
                                <span class="text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200 font-mono">
                                    Patient: +{{ $selectedConv->wa_id }}
                                </span>
                                @if($matchedPatient)
                                    <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                        ID: {{ $matchedPatient->patient_id }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2.5 mt-1 text-[11px] font-medium text-slate-500 flex-wrap">
                                <span>Clinic: <strong class="text-slate-800">{{ $clinic->name }}</strong></span>
                                <span>&bull;</span>
                                <span>Provider: <strong class="text-emerald-700 font-mono">{{ strtoupper($connection?->provider ?? 'BAILEYS') }}</strong></span>
                                <span>&bull;</span>
                                <span>Business WA: <strong class="text-slate-800 font-mono">+{{ preg_replace('/[^0-9]/', '', $connection?->phone_number ?? (config('dxclinic.whatsapp.dev_test_phone') ?: '919999999999')) }}</strong></span>
                                <span>&bull;</span>
                                <span>State: <strong class="font-mono text-slate-800">{{ $selectedConv->current_state }}</strong></span>
                                @if($selectedConv->human_handoff_active || $selectedConv->current_state === 'HUMAN_HANDOFF')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        Human Handoff Active (AI Paused)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        AI Assistant Active
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- AI Handoff & State Controls -->
                    <div class="flex items-center gap-2">
                        @if($selectedConv->human_handoff_active || $selectedConv->current_state === 'HUMAN_HANDOFF')
                            <button type="button"
                                    wire:click="resumeAi"
                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Resume AI Assistant</span>
                            </button>
                        @else
                            <button type="button"
                                    wire:click="takeOver"
                                    class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-2xs transition flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Take Over from AI</span>
                            </button>
                        @endif

                        @if($selectedConv->current_state === 'CLOSED')
                            <button type="button"
                                    wire:click="reopenConversation"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition">
                                Reopen
                            </button>
                        @else
                            <button type="button"
                                    wire:click="closeConversation"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition">
                                Close
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Chat Scrollable Thread Container -->
                <div id="chat-scroll-container" class="flex-1 overflow-y-auto p-6 space-y-4">
                    @forelse($messages as $msg)
                        @php
                            $isInbound = ($msg->direction === 'INBOUND');
                            $payload = $msg->payload ?? [];
                            $isOperator = (!$isInbound && ($payload['sender_type'] ?? '') === 'OPERATOR');
                        @endphp

                        <div wire:key="msg-{{ $msg->id }}" class="flex flex-col {{ $isInbound ? 'items-start' : 'items-end' }}">
                            <!-- Bubble Container -->
                            <div class="max-w-xl rounded-2xl p-3.5 shadow-2xs text-xs leading-relaxed space-y-1.5 {{ $isInbound ? 'bg-white text-slate-900 border border-slate-200/80 rounded-tl-sm' : ($isOperator ? 'bg-emerald-600 text-white rounded-tr-sm' : 'bg-slate-900 text-slate-100 rounded-tr-sm') }}">
                                
                                <!-- Header Badge within Bubble -->
                                <div class="flex items-center justify-between gap-3 text-[10px] font-semibold opacity-80 border-b pb-1 {{ $isInbound ? 'border-slate-100 text-slate-500' : ($isOperator ? 'border-emerald-500/60 text-emerald-100' : 'border-slate-800 text-slate-400') }}">
                                    <span>
                                        @if($isInbound)
                                            👤 Patient (+{{ $selectedConv->wa_id }})
                                        @elseif($isOperator)
                                            👨‍💼 Operator ({{ $payload['sent_by_name'] ?? 'Staff' }})
                                        @else
                                            🤖 DxClinic AI Booking Assistant
                                        @endif
                                    </span>
                                    <span title="{{ \App\Services\AppTimestampFormatter::formatDateTimeWithTz($msg->created_at) }}">
                                        {{ \App\Services\AppTimestampFormatter::formatTime($msg->created_at) }}
                                    </span>
                                </div>

                                <!-- Body Content -->
                                <div class="whitespace-pre-wrap font-sans text-xs">
                                    {{ $msg->content }}
                                </div>

                                <!-- Footer Delivery Info -->
                                <div class="flex items-center justify-end gap-1.5 text-[10px] opacity-75"
                                     title="Sent: {{ \App\Services\AppTimestampFormatter::formatDateTimeWithTz($msg->sent_at ?? $msg->created_at) }}{{ $msg->delivered_at ? ' | Delivered: ' . \App\Services\AppTimestampFormatter::formatDateTimeWithTz($msg->delivered_at) : '' }}{{ $msg->read_at ? ' | Read: ' . \App\Services\AppTimestampFormatter::formatDateTimeWithTz($msg->read_at) : '' }}">
                                    <span>{{ \App\Services\AppTimestampFormatter::formatShortDate($msg->created_at) }}</span>
                                    @if(!$isInbound)
                                        <span>&bull; {{ \App\Services\AppTimestampFormatter::formatDeliveryStatus($msg->status, $msg->delivered_at, $msg->sent_at, $msg->read_at) }}</span>
                                        @if($msg->status === 'FAILED')
                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-700 border border-rose-200" title="Message delivery failed. Gateway was unreachable.">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                Failed
                                            </span>
                                        @elseif($msg->status === 'PENDING')
                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-700 border border-amber-200" title="Retry queued — will reattempt delivery automatically.">
                                                <svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                Retrying…
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center text-slate-400">
                            <p class="text-xs font-semibold">No message history in this conversation thread.</p>
                        </div>
                    @endforelse
                </div>

                <!-- Chat Message Composer Form -->
                <div class="p-4 bg-white border-t border-slate-200 shrink-0 space-y-2.5">
                    <!-- Quick Canned Replies Bar -->
                    <div class="flex items-center gap-2 overflow-x-auto text-xs pb-1 no-scrollbar">
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider shrink-0 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                            Canned Replies:
                        </span>
                        @foreach($cannedResponses as $cKey => $cText)
                            <button type="button"
                                    wire:click="applyCannedReply('{{ $cKey }}')"
                                    title="{{ $cText }}"
                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-medium rounded-lg whitespace-nowrap transition border border-slate-200/60">
                                {{ ucwords(strtolower(str_replace('_', ' ', $cKey))) }}
                            </button>
                        @endforeach
                    </div>

                    <!-- Input + Send Button -->
                    <form wire:submit="sendManualReply" class="flex items-end gap-2">
                        <div class="flex-1 relative">
                            <textarea wire:model="replyText"
                                      @if($enterToSend)
                                          @keydown.enter.exact.prevent="$wire.sendManualReply()"
                                      @endif
                                      rows="2"
                                      placeholder="Type your manual response to the patient... (Shift+Enter for newline)"
                                      class="w-full p-3 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-slate-900 placeholder-slate-400 resize-none"></textarea>
                        </div>

                        <div class="flex flex-col gap-1 shrink-0">
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="px-5 py-3 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center justify-center gap-1.5 disabled:opacity-50">
                                <span wire:loading.remove>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                </span>
                                <span wire:loading class="inline-block animate-spin">&#9696;</span>
                                <span>Send</span>
                            </button>

                            <label class="flex items-center gap-1 text-[10px] text-slate-500 font-medium cursor-pointer self-center">
                                <input type="checkbox" wire:model.live="enterToSend" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <span>Enter sends</span>
                            </label>
                        </div>
                    </form>
                </div>

            @else
                <!-- No Conversation Selected Screen -->
                <div class="flex-1 flex flex-col items-center justify-center p-12 text-center text-slate-400">
                    <div class="w-16 h-16 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <h3 class="font-bold text-slate-700 text-base">Select a WhatsApp Conversation</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm">
                        Choose a conversation from the left sidebar to view the message history, consult the matched patient record, or send an authoritative manual response.
                    </p>
                </div>
            @endif
        </main>

        <!-- =============================================================
             RIGHT PANE: PATIENT PROFILE & BOOKING DRAWER (Width: 360px)
             ============================================================= -->
        <aside class="bg-white border-l border-slate-200 flex flex-col overflow-y-auto p-4 space-y-4" style="min-width: 0;">
            <div class="border-b border-slate-200 pb-3">
                <h3 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">Patient Context &amp; Appointments</h3>
                <p class="text-[11px] text-slate-500 font-medium">Automatic identification via WhatsApp mobile</p>
            </div>

            @if($matchedPatient)
                <!-- Matched Patient Summary Card -->
                <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                            Verified Patient
                        </span>
                        <span class="font-mono text-xs font-bold text-slate-900">
                            {{ $matchedPatient->patient_id }}
                        </span>
                    </div>

                    <div>
                        <h4 class="font-bold text-sm text-slate-900">{{ $matchedPatient->name }}</h4>
                        <p class="text-xs text-slate-500 font-medium">
                            {{ $matchedPatient->age }} yrs &bull; {{ $matchedPatient->sex }}
                        </p>
                    </div>

                    <div class="text-[11px] text-slate-600 space-y-1 pt-2 border-t border-slate-200/60">
                        <div><strong>Mobile:</strong> {{ $matchedPatient->mobile }}</div>
                        <div><strong>City:</strong> {{ $matchedPatient->city ?? 'N/A' }}</div>
                        @if($matchedPatient->address)
                            <div class="truncate"><strong>Address:</strong> {{ $matchedPatient->address }}</div>
                        @endif
                    </div>
                </div>

                <!-- Recent Appointments / Active Tokens -->
                <div class="space-y-2">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center justify-between">
                        <span>Appointments</span>
                        <span class="text-[10px] text-slate-500 font-normal">({{ $patientAppointments->count() }})</span>
                    </h4>

                    @forelse($patientAppointments as $app)
                        <div class="bg-white border border-slate-200 rounded-xl p-3 text-xs space-y-1.5 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="font-mono font-extrabold text-sm text-blue-700">
                                    Token #{{ str_pad($app->token_number, 3, '0', STR_PAD_LEFT) }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $app->status === 'COMPLETED' ? 'bg-slate-100 text-slate-700' : ($app->status === 'CONFIRMED' || $app->status === 'ARRIVED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $app->status }}
                                </span>
                            </div>

                            <div class="text-[11px] text-slate-600">
                                <div><strong>Dr:</strong> {{ $app->doctor ? $app->doctor->name : 'General Physician' }}</div>
                                <div><strong>Date:</strong> {{ $app->appointment_date ? \App\Services\AppTimestampFormatter::formatDate($app->appointment_date) : 'N/A' }} ({{ $app->session_type }})</div>
                            </div>

                            <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-medium">Source: {{ $app->booking_source }}</span>
                                <a href="{{ route('operator.queue', ['public_slug' => $clinic->public_slug]) }}" class="text-blue-600 hover:text-blue-800 font-bold underline">
                                    Queue &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="bg-slate-50 rounded-xl p-3 text-center text-slate-400 text-xs">
                            No appointment records found for this patient.
                        </div>
                    @endforelse
                </div>

            @elseif($selectedConv)
                <!-- Unmatched Patient Notice -->
                <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-4 text-xs space-y-2.5">
                    <div class="flex items-center gap-2 text-amber-800 font-bold">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <span>Unregistered Contact</span>
                    </div>
                    <p class="text-slate-600 text-[11px] leading-relaxed">
                        The phone number <strong>+{{ $selectedConv->wa_id }}</strong> does not match an existing registered patient profile in this clinic.
                    </p>
                    <a href="{{ route('operator.booking') }}"
                       class="block w-full text-center px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition shadow-2xs text-xs">
                        Open OPD Booking &rarr;
                    </a>
                </div>
            @endif

            <!-- Quick Action Links -->
            <div class="pt-2 border-t border-slate-200 space-y-1.5">
                <a href="{{ route('operator.queue', ['public_slug' => $clinic->public_slug]) }}"
                   class="w-full flex items-center justify-between px-3 py-2 bg-slate-50 hover:bg-slate-100 rounded-xl text-xs font-bold text-slate-700 transition">
                    <span>OPD Arrival Queue</span>
                    <span class="text-slate-400">&rarr;</span>
                </a>
                <a href="{{ route('queue.live', ['public_slug' => $clinic->public_slug]) }}"
                   target="_blank"
                   class="w-full flex items-center justify-between px-3 py-2 bg-slate-50 hover:bg-slate-100 rounded-xl text-xs font-bold text-slate-700 transition">
                    <span>Live Queue Display</span>
                    <span class="text-slate-400">&rarr;</span>
                </a>
                <a href="{{ route('settings.whatsapp') }}"
                   class="w-full flex items-center justify-between px-3 py-2 bg-slate-50 hover:bg-slate-100 rounded-xl text-xs font-bold text-slate-700 transition">
                    <span>WhatsApp Settings</span>
                    <span class="text-slate-400">&rarr;</span>
                </a>
            </div>

            <!-- Medical Safety Disclaimer Box -->
            <div class="mt-auto bg-slate-50 border border-slate-200 rounded-xl p-3 text-[10px] text-slate-500 leading-normal">
                <strong class="text-slate-700 block mb-0.5">Medical Safety Invariant:</strong>
                WhatsApp messaging is restricted to appointment scheduling, queue updates, and administrative inquiries. Medical diagnosis and prescriptions remain strictly in-cabin workflows.
            </div>
        </aside>

    </div>
</div>
