<div>
    <!-- Floating Trigger Button (Bottom Right) -->
    <button type="button" 
            wire:click="toggleChat" 
            id="btn_toggle_rag_assistant" 
            aria-label="{{ $identity['full_title'] ?? 'DxClinic Assistant' }}"
            title="{{ $identity['full_title'] ?? 'DxClinic Assistant' }}"
            class="fixed bottom-5 right-5 z-40 p-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-2xl flex items-center gap-2 border-2 border-white transition transform hover:scale-105 cursor-pointer">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
        <span class="text-xs font-bold hidden sm:inline" id="assistant_floating_label">{{ $identity['short_label'] ?? 'Operator Assistant' }}</span>
    </button>

    <!-- Chat Modal Window -->
    @if($isOpen)
        <div class="fixed bottom-20 right-5 z-50 w-full sm:w-96 max-w-[calc(100vw-40px)] bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col h-[560px] animate-in slide-in-from-bottom-5">
            <!-- Header -->
            <div class="p-3.5 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></div>
                    <div>
                        <div class="text-xs font-bold leading-none" id="assistant_panel_title">{{ $identity['full_title'] ?? 'DxClinic Assistant' }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5" id="assistant_panel_subtitle">{{ !empty($identity['is_doctor']) ? 'Clinical Decision Support & Prescriptions' : (($identity['role'] ?? '') === 'ADMIN' ? 'Administrative Operations & Auditing' : 'Authoritative RAG & Operations') }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" wire:click="clearChat" id="btn_clear_assistant_chat" title="Clear Conversation" class="p-1 text-slate-400 hover:text-white rounded text-xs font-mono">
                        Clear
                    </button>
                    <button type="button" wire:click="toggleChat" id="btn_close_assistant_chat" class="p-1 text-slate-400 hover:text-white rounded text-sm font-bold">
                        &times;
                    </button>
                </div>
            </div>

            <!-- Quick Prompts Bar -->
            <div class="p-2 bg-slate-50 border-b border-slate-100 flex gap-1.5 overflow-x-auto text-[10px]">
                @if(!empty($identity['is_doctor']))
                    <button type="button" wire:click="sendPrompt('Today\'s appointments')" class="px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-200 rounded-full text-slate-700 whitespace-nowrap font-medium transition">
                        📅 Today's Appointments
                    </button>
                    <button type="button" wire:click="sendPrompt('How do I add advice notes?')" class="px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-200 rounded-full text-slate-700 whitespace-nowrap font-medium transition">
                        📝 Prescription Advice
                    </button>
                @else
                    <button type="button" wire:click="sendPrompt('Today\'s cash collection')" class="px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-200 rounded-full text-slate-700 whitespace-nowrap font-medium transition">
                        💰 Cash Collection
                    </button>
                    <button type="button" wire:click="sendPrompt('Who is default doctor?')" class="px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-200 rounded-full text-slate-700 whitespace-nowrap font-medium transition">
                        👨‍⚕️ Default Doctor
                    </button>
                    <button type="button" wire:click="sendPrompt('How do I close morning session?')" class="px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-200 rounded-full text-slate-700 whitespace-nowrap font-medium transition">
                        📖 Close Morning Session
                    </button>
                @endif
            </div>

            <!-- Messages Stream -->
            <div id="assistant_messages_stream" class="flex-1 p-3.5 overflow-y-auto space-y-3 bg-slate-50/50 text-xs">
                @foreach($conversation as $idx => $msg)
                    @if($msg['role'] === 'user')
                        <div class="flex justify-end" wire:key="msg-{{ $idx }}">
                            <div class="bg-blue-600 text-white rounded-2xl rounded-tr-xs px-3.5 py-2 max-w-[85%] shadow-xs leading-relaxed">
                                <div class="whitespace-pre-line text-xs">{{ $msg['text'] }}</div>
                                <div class="text-[9px] text-blue-200 text-right mt-1 font-mono">{{ $msg['time'] }}</div>
                            </div>
                        </div>
                    @else
                        <div class="flex justify-start" wire:key="msg-{{ $idx }}">
                            <div class="bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-tl-xs px-3.5 py-2.5 max-w-[92%] shadow-xs space-y-2">
                                <!-- Message Text -->
                                <div class="prose prose-slate max-w-none text-xs leading-relaxed">
                                    {!! \Illuminate\Support\Str::markdown($msg['text']) !!}
                                </div>

                                <!-- Class B: Booking Proposal Confirmation Card (Section 57) -->
                                @if($msg['type'] === 'booking_proposal' && !empty($msg['proposal']))
                                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl space-y-2 text-xs text-blue-950">
                                        <div class="font-bold text-blue-900 flex items-center justify-between">
                                            <span>Appointment Proposal:</span>
                                            <span class="px-2 py-0.5 rounded text-[10px] bg-blue-200 text-blue-800 font-bold uppercase">Class B Write</span>
                                        </div>
                                        <div class="space-y-1 font-mono text-[11px]">
                                            <div><strong>Patient:</strong> {{ $msg['proposal']['patient_name'] }}</div>
                                            <div><strong>Phone:</strong> {{ $msg['proposal']['patient_phone'] }}</div>
                                            <div><strong>Doctor:</strong> {{ $msg['proposal']['doctor_name'] }}</div>
                                            <div><strong>Date:</strong> {{ $msg['proposal']['appointment_date'] }} ({{ ucfirst($msg['proposal']['session']) }})</div>
                                            <div><strong>Source:</strong> {{ $msg['proposal']['booking_source'] }}</div>
                                        </div>

                                        @if(empty($msg['confirmed']))
                                            <div class="pt-2">
                                                <button type="button" wire:click="confirmBooking({{ $idx }})" id="btn_confirm_rag_booking" class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs transition shadow-xs flex items-center justify-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    <span>Confirm Booking</span>
                                                </button>
                                            </div>
                                        @else
                                            <div class="text-[10px] text-emerald-700 font-bold">✓ Booking Confirmed & Token Allocated</div>
                                        @endif
                                    </div>
                                @endif

                                <!-- Class C: Security / Financial Protection Card (Section 60) -->
                                @if($msg['type'] === 'action_redirect' && !empty($msg['action_url']))
                                    <div class="p-2.5 bg-amber-50 border border-amber-200 rounded-xl space-y-1.5 text-xs text-amber-950">
                                        <div class="flex items-center justify-between font-bold text-amber-900">
                                            <span>Protected Action:</span>
                                            <span class="px-2 py-0.5 rounded text-[10px] bg-amber-200 text-amber-900 font-bold uppercase">Class C Security</span>
                                        </div>
                                        <p class="text-[11px] text-amber-800">Direct write from chat is prohibited to prevent unauthorized accounting changes.</p>
                                        <a href="{{ $msg['action_url'] }}" class="inline-block px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg text-[11px] transition">
                                            {{ $msg['action_label'] ?? 'Open Authorized Workflow' }} &rarr;
                                        </a>
                                    </div>
                                @endif

                                <!-- Booking Success Direct Link -->
                                @if($msg['type'] === 'booking_success' && !empty($msg['queue_url']))
                                    <div class="pt-1">
                                        <a href="{{ $msg['queue_url'] }}" class="inline-block px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-[11px] transition">
                                            View in Arrival Queue &rarr;
                                        </a>
                                    </div>
                                @endif

                                <!-- Source / Citation Info (Section 54) -->
                                @if(!empty($msg['source']))
                                    <div class="text-[10px] text-slate-400 font-medium pt-1 border-t border-slate-100 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                        <span>{!! $msg['source'] !!}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <!-- Input Bar -->
            <form wire:submit.prevent="sendMessage" class="p-2.5 bg-white border-t border-slate-200 flex items-center gap-2">
                <input type="text" wire:model="userMessage" id="rag_user_input" placeholder="Ask a question or book an appointment..." class="flex-1 py-2 px-3 border border-slate-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500 focus:outline-none">
                <button type="submit" id="btn_send_rag_message" class="p-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </form>
        </div>
    @endif
</div>
