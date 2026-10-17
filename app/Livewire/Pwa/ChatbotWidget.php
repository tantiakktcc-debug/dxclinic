<?php

namespace App\Livewire\Pwa;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\ClinicScheduleService;
use App\Services\PatientIdentityService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Livewire\Component;

class ChatbotWidget extends Component
{
    public bool $isOpen = false;
    public string $userMessage = '';
    public array $messages = [];

    // State machine steps:
    // IDLE, ASK_BOOKING, COLLECT_NAME_MOBILE, CONFIRM_EXISTING, COLLECT_NEW_PATIENT_DETAILS, SELECT_SESSION, BOOKED
    public string $botState = 'IDLE';

    // Form inputs within chat
    public string $chatName = '';
    public string $chatMobile = '';
    public string $chatAge = '';
    public string $chatSex = 'MALE';
    public string $chatAddress = '';
    public string $chatCity = 'Varanasi';
    public string $chatDate = '';
    public string $chatSession = 'MORNING';

    public ?string $public_slug = null;
    public ?string $clinic_code = null;

    public ?Patient $matchedPatient = null;
    public ?Appointment $generatedAppointment = null;
    public array $sessionOptions = [];

    public function mount(?string $public_slug = null, ?string $clinic_code = null): void
    {
        $this->public_slug = $public_slug;
        $this->clinic_code = $clinic_code;
        $clinic = $this->resolveClinic();
        if ($clinic) {
            TenantContext::setClinic($clinic);
        }

        $this->chatDate = Carbon::today()->toDateString();
        
        // Ensure we have a session ID
        if (!session()->isStarted()) {
            session()->start();
        }

        $clinicName = $clinic ? $clinic->name : 'DxClinic';
        
        // Start conversation via service
        if (!empty($this->chatMobile)) {
             $this->triggerEngine("Menu");
        } else {
             // Ask for phone number first
             $this->messages[] = [
                 'sender' => 'bot',
                 'text' => "Hello! Welcome to {$clinicName}. Please provide your 10-digit mobile number to begin.",
                 'time' => Carbon::now()->format('h:i A'),
             ];
        }
    }

    public function resolveClinic(): ?Clinic
    {
        if ($this->public_slug) {
            $clinic = Clinic::where('public_slug', strtolower(trim($this->public_slug)))->first();
            if ($clinic) return $clinic;
        }
        if ($this->clinic_code) {
            $clinic = Clinic::where('clinic_code', $this->clinic_code)->first();
            if ($clinic) return $clinic;
        }
        return TenantContext::getClinic() ?? Clinic::where('public_slug', 'democlinic')->first();
    }

    public function toggleChat(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function handleOption(string $action): void
    {
        $this->userMessage = $action;
        $this->sendMessage();
    }

    public function sendMessage(): void
    {
        $input = trim($this->userMessage);
        if ($input === '') return;

        $this->messages[] = [
            'sender' => 'user',
            'text' => $input,
            'time' => Carbon::now()->format('h:i A'),
        ];
        $this->userMessage = '';

        if (empty($this->chatMobile)) {
            $digits = preg_replace('/[^0-9]/', '', $input);
            if (strlen($digits) >= 10) {
                $this->chatMobile = substr($digits, -10);
                $this->triggerEngine("Hi");
            } else {
                $this->messages[] = [
                    'sender' => 'bot',
                    'text' => "Please provide a valid 10-digit mobile number.",
                    'time' => Carbon::now()->format('h:i A'),
                ];
            }
            return;
        }

        $this->triggerEngine($input);
    }

    protected function triggerEngine(string $text)
    {
        $clinic = $this->resolveClinic();
        if ($clinic) {
            TenantContext::setClinic($clinic);
        }

        $service = app(\App\Services\PatientAssistantService::class);
        $result = $service->processWebMessage($clinic, $this->chatMobile, $text);

        $options = [];
        if (!empty($result['buttons'])) {
            foreach ($result['buttons'] as $key => $label) {
                $options[] = ['label' => $label, 'action' => $key];
            }
        }

        // Try to parse numeric options from text if present
        if (empty($options) && preg_match_all('/^([0-9]+)\.\s+(.+)$/m', $result['text'] ?? '', $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $options[] = ['label' => trim($match[2]), 'action' => trim($match[1])];
            }
        }

        $msgPayload = [
            'sender' => 'bot',
            'text' => $result['text'] ?? '',
            'time' => Carbon::now()->format('h:i A'),
            'options' => !empty($options) ? $options : null,
        ];
        
        if (isset($result['pdf_url'])) {
            $msgPayload['pdf_url'] = $result['pdf_url'];
        }

        $this->messages[] = $msgPayload;
    }

    public function render()
    {
        return view('livewire.pwa.chatbot-widget');
    }
}
