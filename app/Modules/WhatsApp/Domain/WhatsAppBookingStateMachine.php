<?php

namespace App\Modules\WhatsApp\Domain;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientChannelIdentity;
use App\Models\WhatsAppConversation;
use App\Services\AppointmentService;
use App\Services\BookingEligibilityService;
use App\Services\ClinicClock;
use App\Services\ClinicScheduleService;
use App\Services\PatientIdentityService;
use App\Services\WhatsAppIdentityNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WhatsAppBookingStateMachine
{
    public function __construct(
        protected AppointmentService $appointmentService,
        protected ClinicScheduleService $clinicScheduleService,
        protected PatientIdentityService $patientIdentityService,
        protected AiBookingAssistant $aiAssistant,
        protected ?\App\Services\ClinicKnowledgeBaseService $knowledgeService = null,
        protected ?BookingEligibilityService $eligibilityService = null
    ) {
        $this->knowledgeService = $knowledgeService ?? app(\App\Services\ClinicKnowledgeBaseService::class);
        $this->eligibilityService = $eligibilityService ?? app(BookingEligibilityService::class);
    }

    /**
     * Helper to process an incoming message from a WhatsApp connection and phone number.
     * Returns the formatted textual reply.
     */
    public function processMessage(\App\Models\WhatsAppConnection $connection, string $fromPhone, string $incomingText): string
    {
        $clinic = $connection->clinic;
        $conversation = WhatsAppConversation::firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'wa_id' => $fromPhone,
            ],
            [
                'whatsapp_connection_id' => $connection->id,
                'current_state' => 'NEW',
                'last_patient_message_at' => now(),
            ]
        );

        $result = $this->process($conversation, $incomingText, $clinic);
        return $result['text'] ?? '';
    }

    /**
     * Process patient message through the deterministic state machine
     * Returns an array defining the response to send: ['text' => ..., 'buttons' => ..., 'options' => ...]
     */
    public function process(WhatsAppConversation $conversation, string $incomingText, Clinic $clinic): array
    {
        $text = trim($incomingText);
        $currentState = $conversation->current_state ?? 'NEW';
        $payload = $conversation->state_payload ?? [];

        // 1. Human Handoff Check (Section 30)
        if ($conversation->human_handoff_active) {
            if (preg_match('/\b(bot|ai|menu|start|restart|return to ai|main menu)\b/i', $text)) {
                $conversation->update([
                    'human_handoff_active' => false,
                    'current_state' => 'MENU',
                    'state_payload' => null,
                ]);
                return $this->renderMainMenu($clinic, "Welcome back! Returning to AI Assistant.");
            }

            // In human handoff mode, AI does not automatically reply with booking logic
            return [
                'type' => 'text',
                'text' => "Your conversation is with our Clinic Receptionist. An operator will reply shortly.\n\nType *MENU* anytime to return to the automated assistant.",
            ];
        }

        $intent = $this->aiAssistant->detectIntent($text);

        // Medical Emergency Guard (Section 37)
        if ($intent === 'EMERGENCY') {
            return [
                'type' => 'text',
                'text' => $this->knowledgeService->checkEmergency($text),
                'buttons' => ['btn_menu' => 'Main Menu'],
            ];
        }

        // Prompt Injection Resistance (Section 81)
        if ($intent === 'PROMPT_INJECTION') {
            return [
                'type' => 'text',
                'text' => "⚠️ Security Notice: Invalid instruction detected. Instructions attempting to bypass clinic boundary or rules are not permitted. You are securely connected with {$clinic->name}.",
                'buttons' => ['btn_menu' => 'Main Menu'],
            ];
        }

        // State Machine Dispatch - STATE FIRST
        switch ($currentState) {
            case 'NEW':
            case 'MENU':
                return $this->handleMenuState($conversation, $text, $intent, $clinic);

            case 'WAITING_DATE':
                return $this->handleWaitingDateState($conversation, $text, $clinic);

            case 'NO_SESSION_AVAILABLE':
                return $this->handleNoSessionAvailableState($conversation, $text, $clinic);

            case 'WAITING_SESSION':
                return $this->handleWaitingSessionState($conversation, $text, $clinic);

            case 'WAITING_DOCTOR':
                return $this->handleWaitingDoctorState($conversation, $text, $clinic);

            case 'CONFIRM_EXISTING':
                return $this->handleConfirmExistingState($conversation, $text, $clinic);

            case 'WAITING_PATIENT':
                return $this->handleWaitingPatientState($conversation, $text, $clinic);

            case 'WAITING_CONFIRMATION':
                return $this->handleWaitingConfirmationState($conversation, $text, $clinic);

            case 'STATUS_LOOKUP':
                return $this->handleStatusLookupState($conversation, $text, $clinic);

            case 'BOOKED':
                if (preg_match('/\b(book|another|new)\b/i', $text)) {
                    $conversation->transitionTo('WAITING_DATE', []);
                    return $this->promptForDate($clinic, "Starting a new appointment booking.");
                }
                // Fall back to menu if no specific action taken in BOOKED
                return $this->handleMenuState($conversation, $text, $intent, $clinic);

            default:
                $conversation->transitionTo('MENU', []);
                return $this->renderMainMenu($clinic);
        }
    }

    protected function handleMenuState(WhatsAppConversation $conversation, string $text, string $intent, Clinic $clinic): array
    {
        if ($text === 'btn_book' || $text === '1' || $intent === 'BOOKING') {
            // Check if patient provided date or session in the same message
            $date = $this->aiAssistant->extractDate($text, $clinic);
            $session = $this->aiAssistant->extractSession($text);

            $payload = [];
            if ($date) {
                $payload['appointment_date'] = $date;
            }
            if ($session) {
                $payload['session_type'] = $session;
            }

            if ($date && $session) {
                return $this->proceedAfterDateAndSession($conversation, $clinic, $payload);
            } elseif ($date) {
                // Not transitioning here, let promptForSession handle transition if empty
                $conversation->transitionTo('WAITING_SESSION', $payload);
                return $this->promptForSession($clinic, $date, null, $conversation);
            } else {
                $conversation->transitionTo('WAITING_DATE', $payload);
                return $this->promptForDate($clinic);
            }
        }

        if ($text === 'btn_status' || $text === '2' || $intent === 'STATUS') {
            $token = $this->aiAssistant->extractToken($text);
            if ($token) {
                return $this->lookupTokenStatus($clinic, $conversation->wa_id, $token);
            }
            $conversation->transitionTo('STATUS_LOOKUP');
            return [
                'type' => 'text',
                'text' => "Please reply with your Token Number or Patient ID to check your appointment status (e.g. *003* or *DXP-00001*).",
            ];
        }

        if ($text === 'btn_info' || $text === '3' || $intent === 'HOURS') {
            return $this->renderClinicHours($clinic);
        }

        if ($text === '4' || $intent === 'FEES') {
            return $this->renderConsultationFees($clinic);
        }

        if ($text === '5' || $intent === 'DOCTORS') {
            return $this->renderDoctorsList($clinic);
        }

        if ($text === '6') {
            $rag = $this->knowledgeService->answerQuery($clinic, 'diagnostic tests');
            return [
                'type' => 'text',
                'text' => $rag['text'] ?? "Diagnostic tests are available. Please contact reception.",
                'buttons' => [ 'btn_book' => 'Book Appointment', 'btn_menu' => 'Main Menu' ],
            ];
        }

        if ($text === '7' || $intent === 'HUMAN') {
            $conversation->update([
                'human_handoff_active' => true,
                'human_handoff_requested_at' => now(),
                'current_state' => 'HUMAN_HANDOFF',
            ]);
            return [
                'type' => 'text',
                'text' => "Connecting you with the Receptionist at {$clinic->name}...\n\nA staff member will assist you shortly. Type *MENU* anytime to return to the automated assistant.",
                'buttons' => ['btn_menu' => 'Return to Menu'],
            ];
        }

        // Check clinic-specific RAG knowledge base for natural language queries
        $rag = $this->knowledgeService->answerQuery($clinic, $text);
        if ($rag['type'] === 'answer') {
            return [
                'type' => 'text',
                'text' => $rag['text'],
                'buttons' => [
                    'btn_book' => 'Book Appointment',
                    'btn_menu' => 'Main Menu',
                ],
            ];
        }

        return $this->renderMainMenu($clinic);
    }

    protected function handleWaitingDateState(WhatsAppConversation $conversation, string $text, Clinic $clinic): array
    {
        if ($text === '0' || preg_match('/\b(back|menu)\b/i', $text)) {
            $conversation->transitionTo('MENU', []);
            return $this->renderMainMenu($clinic);
        }

        $date = null;
        if ($text === 'btn_today' || $text === '1' || preg_match('/\btoday\b/i', $text)) {
            $date = ClinicClock::today()->toDateString();
        } elseif ($text === 'btn_tomorrow' || $text === '2' || preg_match('/\btomorrow\b/i', $text)) {
            $date = ClinicClock::today()->copy()->addDay()->toDateString();
        } else {
            $date = $this->aiAssistant->extractDate($text, $clinic);
        }

        if (!$date || !ClinicClock::isAllowedDate($date, $clinic->max_advance_booking_days)) {
            return [
                'type' => 'text',
                'text' => "⚠️ Appointments can only be booked for *Today* or *Tomorrow*.\n\nPlease choose an available date:",
                'buttons' => [
                    'btn_today' => 'Today (' . ClinicClock::today()->format('d M') . ')',
                    'btn_tomorrow' => 'Tomorrow (' . ClinicClock::today()->copy()->addDay()->format('d M') . ')',
                ],
            ];
        }

        $conversation->transitionTo('WAITING_SESSION', ['appointment_date' => $date]);
        return $this->promptForSession($clinic, $date, null, $conversation);
    }

    protected function handleNoSessionAvailableState(WhatsAppConversation $conversation, string $text, Clinic $clinic): array
    {
        if ($text === '0' || preg_match('/\b(back|menu)\b/i', $text)) {
            $conversation->transitionTo('MENU', []);
            return $this->renderMainMenu($clinic);
        }

        $payload = $conversation->state_payload ?? [];

        if ($text === 'btn_tomorrow' || $text === '1' || preg_match('/\b(tomorrow|yes|check tomorrow|book tomorrow)\b/i', $text)) {
            $tomorrow = ClinicClock::today()->copy()->addDay()->toDateString();
            $payload['appointment_date'] = $tomorrow;
            $conversation->transitionTo('WAITING_SESSION', $payload);
            return $this->promptForSession($clinic, $tomorrow, null, $conversation);
        }

        if ($text === 'btn_date' || $text === '2' || preg_match('/\b(choose another date|another date|date)\b/i', $text)) {
            $conversation->transitionTo('WAITING_DATE', $payload);
            return $this->promptForDate($clinic);
        }

        $date = $payload['appointment_date'] ?? ClinicClock::today()->toDateString();
        $fmtDate = Carbon::parse($date)->format('D, d M');
        return [
            'type' => 'text',
            'text' => "⚠️ Invalid choice.\n\nNo sessions are available for:\n{$fmtDate}.\n\nWhat would you like to do?",
            'buttons' => [
                'btn_tomorrow' => 'Check Tomorrow',
                'btn_date' => 'Choose Another Date',
            ],
        ];
    }

    protected function handleWaitingSessionState(WhatsAppConversation $conversation, string $text, Clinic $clinic): array
    {
        if ($text === '0' || preg_match('/\b(back|menu)\b/i', $text)) {
            $conversation->transitionTo('MENU', []);
            return $this->renderMainMenu($clinic);
        }

        $payload = $conversation->state_payload ?? [];
        $date = $payload['appointment_date'] ?? ClinicClock::today()->toDateString();

        $session = null;
        if ($text === 'btn_morning' || $text === '1' || preg_match('/\bmorning\b/i', $text)) {
            $session = 'MORNING';
        } elseif ($text === 'btn_evening' || $text === '2' || preg_match('/\bevening\b/i', $text)) {
            $session = 'EVENING';
        } elseif ($text === 'btn_sunday' || $text === '3' || preg_match('/\bsunday\b/i', $text)) {
            $session = 'SUNDAY_SINGLE';
        } else {
            $session = $this->aiAssistant->extractSession($text);
        }

        if (!$session || !$this->clinicScheduleService->isValidSession($clinic, $date, $session)) {
            return $this->promptForSession($clinic, $date, "⚠️ Please select a valid session for {$date}:", $conversation);
        }

        // Authoritative eligibility check via BookingEligibilityService (Section 1, 6, 7, 26, 33, 62)
        $eligibility = $this->eligibilityService->evaluateEligibility($clinic, $date, $session);
        if (!$eligibility['eligible']) {
            if ($eligibility['code'] === BookingEligibilityService::CODE_SESSION_CUTOFF_PASSED && $session === 'MORNING') {
                $eveningCheck = $this->eligibilityService->evaluateEligibility($clinic, $date, 'EVENING');
                if ($eveningCheck['eligible']) {
                    return [
                        'type' => 'text',
                        'text' => "Today's morning session is no longer bookable because its 11:00 AM booking cutoff has passed.\n\nToday's evening session is still available. Would you like to book for the Evening session?",
                        'buttons' => [
                            'btn_evening' => 'Evening Session',
                            'btn_tomorrow' => 'Tomorrow',
                        ],
                    ];
                }
            }

            return $this->promptForSession($clinic, $date, "⚠️ {$eligibility['reason']}", $conversation);
        }

        $payload['session_type'] = $session;
        return $this->proceedAfterDateAndSession($conversation, $clinic, $payload);
    }

    protected function proceedAfterDateAndSession(WhatsAppConversation $conversation, Clinic $clinic, array $payload): array
    {
        $date = $payload['appointment_date'];
        $session = $payload['session_type'];

        // Check authoritative doctor availability via BookingEligibilityService (Section 19-24)
        $availableDoctors = $this->eligibilityService->getAvailableDoctors($clinic, $date, $session);

        if (empty($availableDoctors)) {
            return $this->promptForSession($clinic, $date, "⚠️ No doctor is available for the {$session} session on this date. Please choose another session:", $conversation);
        }

        if (count($availableDoctors) > 1) {
            $conversation->transitionTo('WAITING_DOCTOR', $payload);
            $buttons = [];
            $docList = "";
            foreach ($availableDoctors as $doc) {
                $buttons['btn_doc_' . $doc['id']] = $doc['name'];
            }
            return [
                'type' => 'text',
                'text' => "Please choose an available doctor for your consultation at *{$clinic->name}*:\n\nReply with a number or doctor name.",
                'buttons' => $buttons,
            ];
        }

        $payload['doctor_id'] = $availableDoctors[0]['id'];

        return $this->proceedToPatientResolution($conversation, $clinic, $payload);
    }

    protected function handleWaitingDoctorState(WhatsAppConversation $conversation, string $text, Clinic $clinic): array
    {
        if ($text === '0' || preg_match('/\b(back|menu)\b/i', $text)) {
            $conversation->transitionTo('MENU', []);
            return $this->renderMainMenu($clinic);
        }

        $payload = $conversation->state_payload ?? [];
        $doctorId = null;

        if (preg_match('/^btn_doc_(\d+)$/', $text, $matches)) {
            $doctorId = (int) $matches[1];
        } elseif (is_numeric($text)) {
            $availableDoctors = $this->eligibilityService->getAvailableDoctors($clinic, $payload['appointment_date'] ?? ClinicClock::today()->toDateString(), $payload['session_type'] ?? 'MORNING');
            $idx = ((int) $text) - 1;
            if (isset($availableDoctors[$idx])) {
                $doctorId = $availableDoctors[$idx]['id'];
            }
        } else {
            // Find doctor by name in clinic
            $doc = Doctor::where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->where('name', 'LIKE', "%{$text}%")
                ->first();
            if ($doc) {
                $doctorId = $doc->id;
            }
        }

        $activeDoctor = $doctorId ? Doctor::where('clinic_id', $clinic->id)->where('id', $doctorId)->where('is_active', true)->first() : null;
        if (!$activeDoctor) {
            $availableDoctors = $this->eligibilityService->getAvailableDoctors($clinic, $payload['appointment_date'] ?? ClinicClock::today()->toDateString(), $payload['session_type'] ?? 'MORNING');
            $buttons = [];
            foreach ($availableDoctors as $doc) {
                $buttons['btn_doc_' . $doc['id']] = $doc['name'];
            }
            return [
                'type' => 'text',
                'text' => "⚠️ Please select a valid available doctor from the list:",
                'buttons' => $buttons,
            ];
        }

        // Authoritative doctor eligibility check
        $date = $payload['appointment_date'] ?? ClinicClock::today()->toDateString();
        $session = $payload['session_type'] ?? 'MORNING';
        $docEligibility = $this->eligibilityService->evaluateEligibility($clinic, $date, $session, $activeDoctor->id);

        if (!$docEligibility['eligible']) {
            $availableDoctors = $this->eligibilityService->getAvailableDoctors($clinic, $date, $session);
            $buttons = [];
            foreach ($availableDoctors as $doc) {
                $buttons['btn_doc_' . $doc['id']] = $doc['name'];
            }
            return [
                'type' => 'text',
                'text' => "⚠️ {$docEligibility['reason']}\n\nPlease choose another available doctor:",
                'buttons' => $buttons,
            ];
        }

        $payload['doctor_id'] = $activeDoctor->id;
        return $this->proceedToPatientResolution($conversation, $clinic, $payload);
    }

    protected function proceedToPatientResolution(WhatsAppConversation $conversation, Clinic $clinic, array $payload): array
    {
        $waPhone = preg_replace('/[^0-9]/', '', $conversation->wa_id);
        $raw10 = substr($waPhone, -10);

        // Check existing patient identity for this WhatsApp number (Section 11)
        $channelId = PatientChannelIdentity::where('clinic_id', $clinic->id)
            ->where('channel', 'WHATSAPP')
            ->where('external_identity', $waPhone)
            ->first();

        $candidatePatient = null;
        if ($channelId) {
            $candidatePatient = Patient::where('clinic_id', $clinic->id)->find($channelId->patient_id);
        }

        if (!$candidatePatient && strlen($raw10) === 10) {
            $candidatePatient = Patient::where('clinic_id', $clinic->id)
                ->where(function ($q) use ($raw10) {
                    $q->where('mobile', $raw10)
                        ->orWhere('mobile', '+91 ' . $raw10)
                        ->orWhere('mobile', 'LIKE', '%' . $raw10);
                })
                ->orderByDesc('id')
                ->first();
        }

        if ($candidatePatient) {
            $payload['candidate_patient_id'] = $candidatePatient->id;
            $conversation->transitionTo('CONFIRM_EXISTING', $payload);

            return [
                'type' => 'text',
                'text' => "We found an existing patient record:\n\n" .
                    "👤 *{$candidatePatient->name}* ({$candidatePatient->patient_id})\n" .
                    "Age: {$candidatePatient->age} yrs, {$candidatePatient->sex}\n" .
                    "City: {$candidatePatient->city}\n\n" .
                    "Would you like to book for *{$candidatePatient->name}*?",
                'buttons' => [
                    'btn_use_existing' => "Yes, book for {$candidatePatient->name}",
                    'btn_new_patient' => 'No, register new patient',
                ],
            ];
        }

        // New patient registration needed
        $conversation->transitionTo('WAITING_PATIENT', $payload);
        return [
            'type' => 'text',
            'text' => "Please provide patient details in this format:\n\n" .
                "*Full Name, Age, Gender, City*\n\n" .
                "_Example: Rahul Sharma, 32, Male, Varanasi_",
        ];
    }

    protected function handleConfirmExistingState(WhatsAppConversation $conversation, string $text, Clinic $clinic): array
    {
        $payload = $conversation->state_payload ?? [];

        if ($text === 'btn_use_existing' || preg_match('/\b(yes|ha|haan|confirm|y|use)\b/i', $text)) {
            $patientId = (int) ($payload['candidate_patient_id'] ?? 0);
            $patient = Patient::where('clinic_id', $clinic->id)->find($patientId);
            if ($patient) {
                $payload['patient_id'] = $patient->id;

                // Bind/verify channel identity (Section 11)
                $waPhone = WhatsAppIdentityNormalizer::normalize($conversation->wa_id);
                PatientChannelIdentity::updateOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'channel' => 'WHATSAPP',
                        'external_identity' => $waPhone,
                    ],
                    [
                        'patient_id' => $patient->id,
                        'verified_at' => now(),
                    ]
                );

                $conversation->transitionTo('WAITING_CONFIRMATION', $payload);
                return $this->renderBookingSummary($clinic, $patient, $payload);
            }
        }

        if ($text === '0' || preg_match('/\b(back|menu)\b/i', $text)) {
            $conversation->transitionTo('MENU', []);
            return $this->renderMainMenu($clinic);
        }

        if ($text === 'btn_new_patient' || $text === '2' || preg_match('/\b(no|nahin|new|dusra|different)\b/i', $text)) {
            $conversation->transitionTo('WAITING_PATIENT', $payload);
            return [
                'type' => 'text',
                'text' => "Please provide the new patient's details in this format:\n\n" .
                    "*Full Name, Age, Gender, City*\n\n" .
                    "_Example: Amit Verma, 25, Male, Varanasi_",
            ];
        }

        return [
            'type' => 'text',
            'text' => "Please choose an option:",
            'buttons' => [
                'btn_use_existing' => 'Book for Existing Patient',
                'btn_new_patient' => 'Register New Patient',
            ],
        ];
    }

    protected function handleWaitingPatientState(WhatsAppConversation $conversation, string $text, Clinic $clinic): array
    {
        if ($text === '0' || preg_match('/\b(back|menu)\b/i', $text)) {
            $conversation->transitionTo('MENU', []);
            return $this->renderMainMenu($clinic);
        }
        $payload = $conversation->state_payload ?? [];
        $parsed = $this->aiAssistant->extractPatientDetails($text);

        if (!$parsed) {
            return [
                'type' => 'text',
                'text' => "⚠️ Could not understand patient details. Please reply in this format:\n\n" .
                    "*Name, Age, Gender, City*\n" .
                    "_Example: Sita Devi, 45, Female, Varanasi_",
            ];
        }

        $waPhone = WhatsAppIdentityNormalizer::normalize($conversation->wa_id);
        $raw10 = WhatsAppIdentityNormalizer::to10Digit($conversation->wa_id);

        try {
            // Register patient using authoritative PatientIdentityService (Section 11)
            $patient = $this->patientIdentityService->resolvePatient([
                'clinic_id' => $clinic->id,
                'name' => $parsed['name'],
                'age' => $parsed['age'],
                'sex' => $parsed['sex'],
                'city' => $parsed['city'],
                'phone' => $raw10,
                'address' => 'WhatsApp Self-Booking',
            ]);

            // Link WhatsApp channel identity
            PatientChannelIdentity::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'channel' => 'WHATSAPP',
                    'external_identity' => $waPhone,
                ],
                [
                    'patient_id' => $patient->id,
                    'verified_at' => now(),
                ]
            );

            $payload['patient_id'] = $patient->id;
            $conversation->transitionTo('WAITING_CONFIRMATION', $payload);

            return $this->renderBookingSummary($clinic, $patient, $payload);
        } catch (\Throwable $e) {
            return [
                'type' => 'text',
                'text' => "⚠️ Registration failed: " . $e->getMessage() . "\nPlease try again or contact reception.",
            ];
        }
    }

    protected function handleWaitingConfirmationState(WhatsAppConversation $conversation, string $text, Clinic $clinic): array
    {
        $payload = $conversation->state_payload ?? [];

        if ($text === 'btn_confirm' || $text === '1' || preg_match('/\b(confirm|yes|ha|haan|book|ok)\b/i', $text)) {
            // Execute authoritative booking transaction (Section 23)
            return $this->executeBooking($conversation, $clinic, $payload);
        }

        if ($text === '2') {
            $conversation->transitionTo('WAITING_DATE', []);
            return $this->promptForDate($clinic, "Let's change your booking details.");
        }

        if ($text === 'btn_cancel' || $text === '0' || preg_match('/\b(cancel|no|nahin|stop|abort)\b/i', $text)) {
            $conversation->transitionTo('MENU', []);
            return $this->renderMainMenu($clinic, "❌ Booking cancelled. No token was allocated.");
        }

        $patient = Patient::where('clinic_id', $clinic->id)->find($payload['patient_id'] ?? 0);
        if ($patient) {
            return $this->renderBookingSummary($clinic, $patient, $payload);
        }

        $conversation->transitionTo('MENU', []);
        return $this->renderMainMenu($clinic);
    }

    /**
     * Authoritative Booking Transaction (Section 23, 24, 25, 27)
     */
    protected function executeBooking(WhatsAppConversation $conversation, Clinic $clinic, array $payload): array
    {
        $patientId = (int) ($payload['patient_id'] ?? 0);
        $patient = Patient::where('clinic_id', $clinic->id)->findOrFail($patientId);

        $dateStr = $payload['appointment_date'] ?? ClinicClock::today()->toDateString();
        $session = $payload['session_type'] ?? 'MORNING';
        $doctorId = !empty($payload['doctor_id']) ? (int) $payload['doctor_id'] : null;

        try {
            // Strict authoritative booking via AppointmentService
            // This acquires the token sequence lock, allocates next unified token,
            // commits to DB, and triggers QueueUpdated event on DB::afterCommit!
            $appointment = $this->appointmentService->bookAppointment([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctorId,
                'appointment_date' => $dateStr,
                'session_type' => $session,
                'booking_source' => Appointment::SOURCE_WHATSAPP, // Section 3: WHATSAPP
                'consultation_mode' => 'CLINIC_PHYSICAL', // Section 20
            ]);

            // Mark conversation state as BOOKED
            $conversation->transitionTo('BOOKED', [
                'last_appointment_id' => $appointment->id,
                'last_token_number' => $appointment->token_number,
                'last_formatted_token' => $appointment->formatted_token,
            ]);

            // Asynchronously dispatch WhatsApp confirmation message and token PDF (Section 28, 29, 59, 60)
            $this->queueBookingNotification($clinic, $appointment, $conversation->wa_id);

            $docName = $appointment->doctor ? 'Dr. ' . ltrim($appointment->doctor->name, 'Dr. ') : 'General OPD';

            return [
                'type' => 'text',
                'text' => "🎉 *APPOINTMENT CONFIRMED!*\n\n" .
                    "🏥 *Clinic:* {$clinic->name}\n" .
                    "🎫 *Token Number:* *{$appointment->formatted_token}*\n" .
                    "👤 *Patient:* {$patient->name} ({$patient->patient_id})\n" .
                    "👨‍⚕️ *Doctor:* {$docName}\n" .
                    "📅 *Date:* " . Carbon::parse($dateStr)->format('l, d M Y') . "\n" .
                    "⏰ *Session:* {$session}\n" .
                    "📌 *Booking Channel:* WhatsApp\n\n" .
                    "Token #{$appointment->formatted_token} is now live in the clinic queue. Please arrive during your session timings.\n\n" .
                    "Type *STATUS* to check your queue position anytime.",
                'buttons' => [
                    'btn_status' => 'Check Token Status',
                    'btn_menu' => 'Main Menu',
                ],
                'pdf_url' => route('token.pdf', $appointment->secure_token),
            ];
        } catch (\Throwable $e) {
            Log::error("WhatsApp booking failure: " . $e->getMessage());
            return [
                'type' => 'text',
                'text' => "⚠️ Booking could not be completed: " . $e->getMessage() . "\n\nPlease try again or contact reception.",
                'buttons' => ['btn_menu' => 'Return to Menu'],
            ];
        }
    }

    /**
     * Dispatch asynchronous notification job (Section 28, 29)
     * Failure to deliver does NOT roll back the appointment!
     */
    protected function queueBookingNotification(Clinic $clinic, Appointment $appointment, string $recipientPhone): void
    {
        try {
            $delivery = \App\Models\WhatsAppMessageDelivery::create([
                'clinic_id' => $clinic->id,
                'appointment_id' => $appointment->id,
                'recipient_phone' => $recipientPhone,
                'message_type' => 'CONFIRMATION',
                'status' => 'PENDING',
                'payload' => [
                    'text' => "DxClinic Booking Confirmation: Token #{$appointment->formatted_token} for {$appointment->patient->name} on {$appointment->appointment_date->format('d M Y')}.",
                    'token' => $appointment->formatted_token,
                    'patient_name' => $appointment->patient->name,
                ],
            ]);

            \App\Jobs\SendWhatsAppMessageJob::dispatch($delivery->id);
        } catch (\Throwable $e) {
            Log::error("Failed to queue WhatsApp notification: " . $e->getMessage());
        }
    }

    protected function handleStatusLookupState(WhatsAppConversation $conversation, string $text, Clinic $clinic): array
    {
        $token = $this->aiAssistant->extractToken($text) ?: trim($text);
        return $this->lookupTokenStatus($clinic, $conversation->wa_id, $token);
    }

    /**
     * Secure status lookup respecting patient identity boundaries (Section 61)
     */
    protected function lookupTokenStatus(Clinic $clinic, string $waPhone, string $tokenQuery): array
    {
        $cleanPhone = WhatsAppIdentityNormalizer::normalize($waPhone);
        $raw10 = WhatsAppIdentityNormalizer::to10Digit($waPhone);

        // Find appointment for today/tomorrow matching token and patient's authorized identity
        $query = Appointment::where('clinic_id', $clinic->id)
            ->where(function ($sub) use ($tokenQuery) {
                $sub->where('formatted_token', $tokenQuery)
                    ->orWhere('token_number', (int) $tokenQuery)
                    ->orWhereHas('patient', function ($pq) use ($tokenQuery) {
                        $pq->where('patient_id', $tokenQuery)
                            ->orWhere('legacy_alias', $tokenQuery);
                    });
            })
            ->whereDate('appointment_date', '>=', ClinicClock::today()->toDateString())
            ->whereHas('patient', function ($pq) use ($raw10, $cleanPhone, $clinic) {
                $pq->where(function ($mq) use ($raw10) {
                    $mq->where('mobile', $raw10)
                        ->orWhere('mobile', '+91 ' . $raw10)
                        ->orWhere('mobile', 'LIKE', '%' . $raw10);
                })->orWhereHas('channelIdentities', function ($ci) use ($cleanPhone, $clinic) {
                    $ci->where('clinic_id', $clinic->id)
                        ->where('channel', 'WHATSAPP')
                        ->where('external_identity', $cleanPhone);
                });
            })
            ->with(['patient', 'doctor', 'visit'])
            ->latest('appointment_date')
            ->first();

        if (!$query) {
            return [
                'type' => 'text',
                'text' => "🔍 No matching appointment found for token/ID '{$tokenQuery}' associated with your phone number.\n\nPlease check the token number or contact reception.",
                'buttons' => ['btn_menu' => 'Main Menu'],
            ];
        }

        $docName = $query->doctor ? 'Dr. ' . ltrim($query->doctor->name, 'Dr. ') : 'General OPD';
        $statusDesc = match ($query->status) {
            'ARRIVED' => '✅ Marked ARRIVED at clinic. Waiting for doctor consultation.',
            'COMPLETED' => '🎉 Consultation COMPLETED.',
            'CANCELLED' => '❌ CANCELLED.',
            'ABSENT' => '⚠️ Marked ABSENT.',
            default => '⏳ BOOKED. Please reach the clinic during your session.',
        };

        return [
            'type' => 'text',
            'text' => "*APPOINTMENT STATUS*\n\n" .
                "🎫 *Token:* #{$query->formatted_token}\n" .
                "👤 *Patient:* {$query->patient->name}\n" .
                "👨‍⚕️ *Doctor:* {$docName}\n" .
                "📅 *Date:* " . $query->appointment_date->format('d M Y') . " ({$query->session_type})\n" .
                "📊 *Status:* {$statusDesc}\n\n" .
                "Hospital: {$clinic->name}",
            'buttons' => ['btn_menu' => 'Main Menu'],
        ];
    }

    protected function renderBookingSummary(Clinic $clinic, Patient $patient, array $payload): array
    {
        $dateStr = $payload['appointment_date'] ?? ClinicClock::today()->toDateString();
        $session = $payload['session_type'] ?? 'MORNING';
        $doctorId = !empty($payload['doctor_id']) ? (int) $payload['doctor_id'] : null;
        $doctor = $doctorId ? Doctor::find($doctorId) : null;
        $docName = $doctor ? 'Dr. ' . ltrim($doctor->name, 'Dr. ') : 'General OPD';

        $fee = $doctor ? ($doctor->consultation_fee ?? 600) : 600;

        return [
            'type' => 'text',
            'text' => "Please confirm your appointment:\n\n" .
                "Doctor: {$docName}\n" .
                "Date: " . Carbon::parse($dateStr)->format('D, d M') . "\n" .
                "Session: " . ucfirst(strtolower($session)) . "\n" .
                "Fee: ₹{$fee}",
            'buttons' => [
                'btn_confirm' => 'Confirm Booking',
                'btn_cancel' => 'Cancel',
            ],
        ];
    }

    protected function promptForDate(Clinic $clinic, ?string $prefix = null): array
    {
        $today = ClinicClock::today();
        $tomorrow = $today->copy()->addDay();

        $msg = ($prefix ? $prefix . "\n\n" : "") .
            "📅 *Please select appointment date for {$clinic->name}:*";

        return [
            'type' => 'text',
            'text' => $msg,
            'buttons' => [
                'btn_today' => 'Today (' . $today->format('d M') . ')',
                'btn_tomorrow' => 'Tomorrow (' . $tomorrow->format('d M') . ')',
            ],
        ];
    }

    protected function promptForSession(Clinic $clinic, string $date, ?string $prefix = null, ?WhatsAppConversation $conversation = null): array
    {
        $availableSessions = $this->eligibilityService->getAvailableSessions($clinic, $date);
        $buttons = [];
        $listOptions = "";
        $index = 1;

        foreach ($availableSessions as $sess) {
            $key = $sess['session'];
            $label = ucfirst(strtolower($key));
            if (!empty($sess['start']) && !empty($sess['end'])) {
                $label .= " — {$sess['start']} to {$sess['end']}";
            }
            $listOptions .= "{$index}. {$label}\n";
            $buttons['btn_' . strtolower($key)] = mb_substr($label, 0, 20);
            $index++;
        }

        if (empty($buttons)) {
            if ($conversation) {
                $payload = $conversation->state_payload ?? [];
                $payload['appointment_date'] = $date;
                $conversation->transitionTo('NO_SESSION_AVAILABLE', $payload);
            }
            
            $fmtDate = Carbon::parse($date)->format('D, d M');
            $msg = ($prefix ? $prefix . "\n\n" : "") .
                "No sessions are available for:\n{$fmtDate}.\n\nWhat would you like to do?";
            return [
                'type' => 'text',
                'text' => $msg,
                'buttons' => [
                    'btn_tomorrow' => 'Check Tomorrow',
                    'btn_date' => 'Choose Another Date',
                ],
            ];
        }
        
        $listOptions .= "0. Back";

        $dateFormatted = Carbon::parse($date)->format('D, d M Y');
        $msg = ($prefix ? $prefix . "\n\n" : "") .
            "⏰ *Select session for {$dateFormatted}:*\n\n" . $listOptions;

        return [
            'type' => 'text',
            'text' => $msg,
            'buttons' => $buttons,
        ];
    }

    protected function renderMainMenu(Clinic $clinic, ?string $prefix = null): array
    {
        $header = $prefix ? $prefix . "\n\n" : "";
        return [
            'type' => 'text',
            'text' => "{$header}Welcome to *{$clinic->name}*.\n\n" .
                "How can I help you?",
            'buttons' => [
                'btn_book' => 'Book Appointment',
                'btn_status' => 'Check My Token',
                'btn_info' => 'Clinic Timings',
                'btn_fees' => 'Consultation Fees',
                'btn_doctors' => 'Doctors',
                'btn_tests' => 'Diagnostic Tests',
                'btn_staff' => 'Talk to Staff',
            ],
        ];
    }

    protected function renderClinicHours(Clinic $clinic): array
    {
        $schedule = $clinic->schedule_config ?? $clinic->defaultSchedule();
        
        $formatDay = function($dayData) {
            if (!$dayData) return 'Closed';
            $slots = [];
            foreach ($dayData as $sName => $times) {
                if (is_array($times) && count($times) === 2) {
                    $start = Carbon::createFromFormat('H:i', $times[0])->format('g:i A');
                    $end = Carbon::createFromFormat('H:i', $times[1])->format('g:i A');
                    $slots[] = ucfirst($sName) . ": {$start} – {$end}";
                }
            }
            return empty($slots) ? 'Closed' : implode("\n", $slots);
        };

        // Assume Monday-Saturday are identical based on typical config
        $monSat = $formatDay($schedule['monday'] ?? null);
        $sunday = $formatDay($schedule['sunday'] ?? null);

        $text = "Clinic Timings\n\n" .
                "Monday–Saturday\n" .
                "{$monSat}\n\n" .
                "Sunday\n" .
                "{$sunday}";

        return [
            'type' => 'text',
            'text' => $text,
            'buttons' => [
                'btn_book' => 'Book Appointment',
                'btn_menu' => 'Main Menu',
            ],
        ];
    }

    protected function renderClinicLocation(Clinic $clinic): array
    {
        return [
            'type' => 'text',
            'text' => "*Clinic Location:*\n\n" .
                "🏥 *{$clinic->name}*\n" .
                "📍 {$clinic->address}\n" .
                "🏙️ {$clinic->city}, {$clinic->pincode}\n" .
                "📞 Phone: {$clinic->phone}",
            'buttons' => [
                'btn_book' => 'Book Appointment',
                'btn_menu' => 'Main Menu',
            ],
        ];
    }

    protected function renderConsultationFees(Clinic $clinic): array
    {
        $doctors = Doctor::where('clinic_id', $clinic->id)->where('is_active', true)->get();
        $lines = ["Consultation Fee\n"];

        if ($doctors->isEmpty()) {
            $lines[] = "General consultation: Contact reception";
        } else {
            foreach ($doctors as $doc) {
                $fee = \App\Models\FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $clinic->id, $doc->id);
                $feeStr = $fee !== null ? "₹" . number_format($fee, 0) : "Contact reception";
                $name = "Dr. " . ltrim($doc->name, 'Dr. ');
                $lines[] = "{$name} ({$doc->specialization}): {$feeStr}";
            }
        }

        return [
            'type' => 'text',
            'text' => implode("\n", $lines),
            'buttons' => [
                'btn_book' => 'Book Appointment',
                'btn_menu' => 'Main Menu',
            ],
        ];
    }

    protected function renderDoctorsList(Clinic $clinic): array
    {
        $rag = $this->knowledgeService->searchKnowledge($clinic->id, 'doctors', 'DOCTORS', 1);
        $text = !empty($rag) ? $rag[0]['content'] : "General OPD doctors are available during clinic hours.";
        return [
            'type' => 'text',
            'text' => "👨‍⚕️ " . $text,
            'buttons' => [
                'btn_book' => 'Book Appointment',
                'btn_menu' => 'Main Menu',
            ],
        ];
    }
}
