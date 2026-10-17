<?php

namespace App\Livewire\Operator;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\PatientIdentityService;
use App\Services\RagAssistantService;
use Carbon\Carbon;
use Livewire\Component;

class OperatorAssistant extends Component
{
    public bool $isOpen = false;
    public string $userMessage = '';
    public array $conversation = [];
    public ?Clinic $clinic = null;
    public array $identity = [];

    public function mount(?\App\Services\AssistantIdentityResolver $resolver = null): void
    {
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');

        $resolver = $resolver ?? app(\App\Services\AssistantIdentityResolver::class);
        $user = auth()->user();
        $this->identity = $resolver->resolve($user, $this->clinic);
        $sessionKey = $this->identity['session_key'] ?? 'default_session';

        // Authoritatively retrieve session conversation & greeting state
        $savedConversation = session()->get("assistant_conv_{$sessionKey}", []);
        $alreadyGreeted = session()->get("assistant_greeted_{$sessionKey}", false);

        if (!empty($savedConversation)) {
            $this->conversation = $savedConversation;
        } elseif (!$alreadyGreeted) {
            $greetingText = $this->identity['greeting_text'] ?? "Hello {$user?->name}! How can I help you?";
            $shortLabel = $this->identity['short_label'] ?? 'DxClinic Operational Assistant';
            $this->conversation[] = [
                'role' => 'assistant',
                'type' => 'text',
                'text' => "{$greetingText}\n\nI am your {$shortLabel}. You can ask me how to perform clinical or accounting workflows, inquire about today's cash collections, or type naturally to book an appointment (e.g. \"Book appointment for Ram Kumar tomorrow evening\").",
                'source' => 'DxClinic System',
                'time' => Carbon::now()->format('H:i'),
            ];
            session()->put("assistant_greeted_{$sessionKey}", true);
            session()->put("assistant_conv_{$sessionKey}", $this->conversation);
        }
    }

    public function toggleChat(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function sendPrompt(string $prompt, RagAssistantService $ragService): void
    {
        $this->userMessage = $prompt;
        $this->sendMessage($ragService);
    }

    public function sendMessage(RagAssistantService $ragService): void
    {
        $query = trim($this->userMessage);
        if (empty($query)) {
            return;
        }

        $user = auth()->user();

        // Append user message
        $this->conversation[] = [
            'role' => 'user',
            'type' => 'text',
            'text' => $query,
            'time' => Carbon::now()->format('H:i'),
        ];

        $this->userMessage = '';

        // Process message through RAG assistant
        $response = $ragService->processMessage($query, $user, $this->clinic);

        $assistantMsg = [
            'role' => 'assistant',
            'type' => $response['type'] ?? 'text',
            'text' => $response['message'] ?? '',
            'source' => $response['source'] ?? null,
            'level' => $response['level'] ?? 'CLASS_A',
            'action_label' => $response['action_label'] ?? null,
            'action_url' => $response['action_url'] ?? null,
            'proposal' => $response['proposal'] ?? null,
            'time' => Carbon::now()->format('H:i'),
        ];

        $this->conversation[] = $assistantMsg;
        $sessionKey = $this->identity['session_key'] ?? null;
        if ($sessionKey) {
            session()->put("assistant_conv_{$sessionKey}", $this->conversation);
        }
    }

    public function confirmBooking(
        int $msgIndex,
        AppointmentService $appointmentService,
        PatientIdentityService $patientService
    ): void {
        if (!isset($this->conversation[$msgIndex]['proposal'])) {
            return;
        }

        $proposal = $this->conversation[$msgIndex]['proposal'];

        try {
            // 1. Resolve or create patient
            $patient = $patientService->resolvePatient([
                'name' => $proposal['patient_name'],
                'phone' => $proposal['patient_phone'],
                'clinic_id' => $this->clinic?->id ?? 1,
            ]);

            $sessionType = strtoupper($proposal['session'] ?? 'MORNING');
            if ($sessionType === 'SINGLE') {
                $sessionType = 'SUNDAY_SINGLE';
            }

            // 2. Book appointment via authoritative service (Section 56, 91)
            $consultationMode = $proposal['consultation_mode'] ?? 'CLINIC_PHYSICAL';
            $appointment = $appointmentService->bookAppointment([
                'clinic_id' => $this->clinic?->id ?? 1,
                'patient_id' => $patient->id,
                'appointment_date' => $proposal['appointment_date'],
                'session_type' => $sessionType,
                'booking_source' => $proposal['booking_source'] ?? 'CLINIC',
                'consultation_mode' => $consultationMode,
                'doctor_id' => $proposal['doctor_id'] ? (int) $proposal['doctor_id'] : null,
            ]);

            // Audit
            AuditLog::log('APPOINTMENT_BOOKED_VIA_RAG_ASSISTANT', 'APPOINTMENT', $appointment->id, [
                'token' => $appointment->formatted_token,
                'patient_id' => $patient->id,
                'consultation_mode' => $consultationMode,
            ]);

            // Invalidate proposal to prevent duplicate submission
            $this->conversation[$msgIndex]['confirmed'] = true;

            // Append success confirmation
            $docName = $appointment->doctor ? $appointment->doctor->formatted_name : 'Default Doctor';
            $modeText = match($consultationMode) {
                'ONLINE' => '🌐 Online Consultancy (₹700)',
                'VISITING_CONSULTANCY', 'VISITING' => '🚗 Visiting Consultancy (₹1000)',
                default => '🏥 Clinic Consultancy (₹600)',
            };
            $this->conversation[] = [
                'role' => 'assistant',
                'type' => 'booking_success',
                'text' => "✅ **Appointment Confirmed!**\n\n• **Token:** #{$appointment->formatted_token}\n• **Patient:** {$patient->name} (ID: `{$patient->patient_id}`)\n• **Doctor:** {$docName}\n• **Consultation Type:** {$modeText}\n• **Date:** " . Carbon::parse($appointment->appointment_date)->format('d M Y') . " ({$appointment->session})\n• **Source:** Clinic Operator",
                'token' => $appointment->formatted_token,
                'queue_url' => route('operator.queue'),
                'time' => Carbon::now()->format('H:i'),
            ];

            $sessionKey = $this->identity['session_key'] ?? null;
            if ($sessionKey) {
                session()->put("assistant_conv_{$sessionKey}", $this->conversation);
            }
        } catch (\Exception $e) {
            $this->conversation[] = [
                'role' => 'assistant',
                'type' => 'text',
                'text' => "❌ Booking failed: " . $e->getMessage(),
                'time' => Carbon::now()->format('H:i'),
            ];
            $sessionKey = $this->identity['session_key'] ?? null;
            if ($sessionKey) {
                session()->put("assistant_conv_{$sessionKey}", $this->conversation);
            }
        }
    }

    public function clearChat(): void
    {
        $sessionKey = $this->identity['session_key'] ?? null;
        if ($sessionKey) {
            session()->forget("assistant_conv_{$sessionKey}");
        }
        $this->conversation = [];
    }

    public function render(?\App\Services\AssistantIdentityResolver $resolver = null)
    {
        if (empty($this->identity)) {
            $resolver = $resolver ?? app(\App\Services\AssistantIdentityResolver::class);
            $this->clinic = $this->clinic ?? \App\Services\TenantContext::clinic();
            $this->identity = $resolver->resolve(auth()->user(), $this->clinic);
        }
        return view('livewire.operator.operator-assistant');
    }
}
