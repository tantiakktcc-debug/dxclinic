<?php

namespace App\Livewire\Operator;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class WhatsAppDesk extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'ALL'; // ALL, UNREAD, NEEDS_HUMAN, AI_ACTIVE, BOOKED, CLOSED
    public ?int $selectedConversationId = null;
    public string $replyText = '';
    public bool $enterToSend = true;
    public bool $pollingEnabled = true;

    // Canned response templates
    public array $cannedResponses = [
        'CONFIRM_ARRIVAL' => 'Namaste! Your token is active. Please arrive at the clinic 15 minutes before your scheduled session.',
        'RUNNING_DELAY' => 'Notice: Today OPD session is experiencing a slight delay of approximately 15 minutes. We appreciate your patience.',
        'DOC_IN_CABIN' => 'Dr. has entered the consultation cabin and patient calls have commenced. Please check the Live Queue display.',
        'BRING_RECORDS' => 'Kindly remember to bring any prior prescription slips, laboratory investigation reports, and your Patient ID.',
        'SESSION_CLOSED' => 'Thank you for contacting us. Today booking sessions are now concluded. Advance bookings for tomorrow will open as scheduled.',
    ];

    public function mount(?int $conversationId = null): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $userRole = strtoupper(auth()->user()->role ?? '');
        abort_if($userRole === 'DOCTOR', 403, 'WhatsApp Operator Desk is restricted to Administrators and Frontdesk Operators.');

        if ($conversationId) {
            $this->selectConversation($conversationId);
        } else {
            // Automatically select most recent conversation if available
            $first = WhatsAppConversation::where('clinic_id', $clinic->id)
                ->orderByDesc('last_patient_message_at')
                ->first();
            if ($first) {
                $this->selectConversation($first->id);
            }
        }
    }

    public function selectConversation(int $id): void
    {
        $clinic = $this->getCurrentClinic();
        $conversation = WhatsAppConversation::where('clinic_id', $clinic->id)->findOrFail($id);
        $this->selectedConversationId = $conversation->id;

        // Mark inbound unread messages as READ
        WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)
            ->where('direction', 'INBOUND')
            ->where('status', '!=', 'READ')
            ->update(['status' => 'READ']);

        $this->dispatch('chat-scrolled-to-bottom');
    }

    public function filterByStatus(string $filter): void
    {
        $this->statusFilter = $filter;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyCannedReply(string $key): void
    {
        if (isset($this->cannedResponses[$key])) {
            $this->replyText = $this->cannedResponses[$key];
        }
    }

    public function sendManualReply(?WhatsAppProviderInterface $provider = null): void
    {
        $provider = $provider ?? app(WhatsAppProviderInterface::class);
        $clinic = $this->getCurrentClinic();
        $user = auth()->user();

        $text = trim($this->replyText);
        if ($text === '') {
            return;
        }

        abort_unless($this->selectedConversationId, 400, 'No conversation selected.');
        $conversation = WhatsAppConversation::where('clinic_id', $clinic->id)
            ->findOrFail($this->selectedConversationId);

        // Fetch clinic active WhatsApp connection
        $connection = $conversation->connection 
            ?? WhatsAppConnection::where('clinic_id', $clinic->id)->where('status', 'CONNECTED')->first()
            ?? WhatsAppConnection::where('clinic_id', $clinic->id)->first();

        if (!$connection) {
            session()->flash('error', 'No WhatsApp connection found for this clinic. Please configure in Settings > WhatsApp.');
            return;
        }

        try {
            // 1. Dispatch through authoritative WhatsApp provider (Baileys or Meta)
            $sendResult = $provider->sendTextMessage($connection, $conversation->wa_id, $text);

            // 2. Persist message record with OPERATOR metadata and honest delivery timestamps
            $isSent = !empty($sendResult['success']);
            $nowUtc = now();
            $message = WhatsAppMessage::create([
                'clinic_id' => $clinic->id,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'OUTBOUND',
                'message_type' => 'text',
                'provider_message_id' => $sendResult['provider_message_id'] ?? null,
                'content' => $text,
                'payload' => [
                    'sender_type' => 'OPERATOR',
                    'sent_by_user_id' => $user->id,
                    'sent_by_name' => $user->name,
                    'sent_by_role' => $user->role,
                    'gateway_result' => $sendResult,
                    'sent_at_utc' => $nowUtc->toIso8601String(),
                ],
                'status' => $isSent ? 'SENT' : 'FAILED',
                'sent_at' => $isSent ? $nowUtc : null,
                'delivered_at' => null, // Delivery receipt will arrive asynchronously
                'read_at' => null,
            ]);

            // 3. Transition conversation to HUMAN_HANDOFF so AI does not send conflicting replies
            $conversation->update([
                'last_outbound_message_at' => now(),
                'human_handoff_active' => true,
                'human_handoff_requested_at' => $conversation->human_handoff_requested_at ?? now(),
                'current_state' => 'HUMAN_HANDOFF',
            ]);

            // 4. Audit Log Entry
            AuditLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'action' => 'WHATSAPP_MANUAL_MESSAGE_SENT',
                'entity_type' => 'WhatsAppMessage',
                'entity_id' => $message->id,
                'new_values' => [
                    'conversation_id' => $conversation->id,
                    'wa_id' => $conversation->wa_id,
                    'content_length' => strlen($text),
                    'provider' => $connection->provider,
                ],
                'ip_address' => request()?->ip(),
                'reason' => 'Operator manual messaging via WhatsApp Desk',
            ]);

            $this->replyText = '';
            $this->dispatch('chat-scrolled-to-bottom');
            session()->flash('message', 'Message sent successfully.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to send WhatsApp message: ' . $e->getMessage());
        }
    }

    public function takeOver(): void
    {
        $clinic = $this->getCurrentClinic();
        abort_unless($this->selectedConversationId, 400, 'No conversation selected.');

        $conversation = WhatsAppConversation::where('clinic_id', $clinic->id)
            ->findOrFail($this->selectedConversationId);

        $conversation->update([
            'human_handoff_active' => true,
            'human_handoff_requested_at' => now(),
            'current_state' => 'HUMAN_HANDOFF',
        ]);

        AuditLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => auth()->id(),
            'action' => 'WHATSAPP_OPERATOR_TAKEOVER',
            'entity_type' => 'WhatsAppConversation',
            'entity_id' => $conversation->id,
            'new_values' => ['human_handoff_active' => true, 'current_state' => 'HUMAN_HANDOFF'],
            'ip_address' => request()?->ip(),
            'reason' => 'Operator paused AI to take manual control',
        ]);

        session()->flash('message', 'AI Assistant paused. You have taken manual control of this conversation.');
    }

    public function resumeAi(): void
    {
        $clinic = $this->getCurrentClinic();
        abort_unless($this->selectedConversationId, 400, 'No conversation selected.');

        $conversation = WhatsAppConversation::where('clinic_id', $clinic->id)
            ->findOrFail($this->selectedConversationId);

        $conversation->update([
            'human_handoff_active' => false,
            'current_state' => 'MENU',
        ]);

        AuditLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => auth()->id(),
            'action' => 'WHATSAPP_AI_RESUMED',
            'entity_type' => 'WhatsAppConversation',
            'entity_id' => $conversation->id,
            'new_values' => ['human_handoff_active' => false, 'current_state' => 'MENU'],
            'ip_address' => request()?->ip(),
            'reason' => 'Operator resumed automated AI booking assistant',
        ]);

        session()->flash('message', 'AI Assistant resumed. Automated menu & booking responses re-enabled.');
    }

    public function closeConversation(): void
    {
        $clinic = $this->getCurrentClinic();
        abort_unless($this->selectedConversationId, 400, 'No conversation selected.');

        $conversation = WhatsAppConversation::where('clinic_id', $clinic->id)
            ->findOrFail($this->selectedConversationId);

        $conversation->update([
            'current_state' => 'CLOSED',
            'human_handoff_active' => false,
        ]);

        session()->flash('message', 'Conversation marked as Closed.');
    }

    public function reopenConversation(): void
    {
        $clinic = $this->getCurrentClinic();
        abort_unless($this->selectedConversationId, 400, 'No conversation selected.');

        $conversation = WhatsAppConversation::where('clinic_id', $clinic->id)
            ->findOrFail($this->selectedConversationId);

        $conversation->update([
            'current_state' => 'MENU',
            'human_handoff_active' => false,
        ]);

        session()->flash('message', 'Conversation reopened.');
    }

    protected function getCurrentClinic(): Clinic
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Tenant context not resolved.');
        return $clinic;
    }

    public function render()
    {
        $clinic = $this->getCurrentClinic();

        // 1. Build conversations query with tenant isolation
        $query = WhatsAppConversation::where('clinic_id', $clinic->id)
            ->with(['connection']);

        // Search by phone or message content
        if ($this->search !== '') {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('wa_id', 'like', "%{$s}%")
                  ->orWhereHas('messages', function ($mq) use ($s) {
                      $mq->where('content', 'like', "%{$s}%");
                  });
            });
        }

        // Status filters
        if ($this->statusFilter === 'UNREAD') {
            $query->whereHas('messages', function ($mq) {
                $mq->where('direction', 'INBOUND')->where('status', '!=', 'READ');
            });
        } elseif ($this->statusFilter === 'NEEDS_HUMAN') {
            $query->where(function ($q) {
                $q->where('human_handoff_active', true)
                  ->orWhere('current_state', 'HUMAN_HANDOFF');
            });
        } elseif ($this->statusFilter === 'AI_ACTIVE') {
            $query->where('human_handoff_active', false)
                  ->where('current_state', '!=', 'CLOSED');
        } elseif ($this->statusFilter === 'BOOKED') {
            $query->where('current_state', 'BOOKED');
        } elseif ($this->statusFilter === 'CLOSED') {
            $query->where('current_state', 'CLOSED');
        }

        $conversations = $query->orderByDesc('last_patient_message_at')
            ->orderByDesc('updated_at')
            ->paginate(30);

        // 2. Load Selected Conversation with messages & patient details
        $selectedConv = null;
        $messages = collect();
        $matchedPatient = null;
        $patientAppointments = collect();

        if ($this->selectedConversationId) {
            $selectedConv = WhatsAppConversation::where('clinic_id', $clinic->id)
                ->with('connection')
                ->find($this->selectedConversationId);

            if ($selectedConv) {
                $messages = WhatsAppMessage::where('whatsapp_conversation_id', $selectedConv->id)
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Find linked patient by phone number
                $rawPhone = preg_replace('/[^0-9]/', '', $selectedConv->wa_id);
                $last10 = substr($rawPhone, -10);

                $matchedPatient = Patient::where('clinic_id', $clinic->id)
                    ->where(function ($q) use ($rawPhone, $last10) {
                        $q->where('mobile', 'like', "%{$last10}%")
                          ->orWhere('mobile', 'like', "%{$rawPhone}%");
                    })
                    ->first();

                if ($matchedPatient) {
                    $patientAppointments = Appointment::where('clinic_id', $clinic->id)
                        ->where('patient_id', $matchedPatient->id)
                        ->with(['doctor', 'consultationBill.payment'])
                        ->orderByDesc('appointment_date')
                        ->orderByDesc('id')
                        ->limit(5)
                        ->get();
                }
            }
        }

        // WhatsApp connection status banner
        $connection = WhatsAppConnection::where('clinic_id', $clinic->id)->first();

        return view('livewire.operator.whatsapp-desk', [
            'clinic' => $clinic,
            'connection' => $connection,
            'conversations' => $conversations,
            'selectedConv' => $selectedConv,
            'messages' => $messages,
            'matchedPatient' => $matchedPatient,
            'patientAppointments' => $patientAppointments,
        ])->layout('components.layouts.app', ['title' => 'WhatsApp Operator Desk — DxClinic']);
    }
}
