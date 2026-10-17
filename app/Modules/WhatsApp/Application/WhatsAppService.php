<?php

namespace App\Modules\WhatsApp\Application;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\Patient;
use App\Models\PatientChannelIdentity;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Modules\WhatsApp\Domain\WhatsAppBookingStateMachine;
use App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface;
use App\Services\AppointmentService;
use App\Services\ClinicClock;
use App\Services\ClinicScheduleService;
use App\Services\PatientIdentityService;
use App\Services\WhatsAppIdentityNormalizer;
use App\Services\WhatsAppTimestampNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function __construct(
        protected WhatsAppProviderInterface $provider,
        protected WhatsAppBookingStateMachine $stateMachine,
        protected AppointmentService $appointmentService,
        protected ClinicScheduleService $clinicScheduleService,
        protected PatientIdentityService $patientIdentityService
    ) {}

    /**
     * Resolve connection and clinic from phone_number_id (Section 10)
     */
    public function resolveConnectionByPhoneNumberId(string $phoneNumberId): ?WhatsAppConnection
    {
        return WhatsAppConnection::with('clinic')
            ->where('phone_number_id', trim($phoneNumberId))
            ->first();
    }

    /**
     * Ingest and process an incoming Meta Webhook payload (Sections 9, 10, 26, 27)
     */
    public function handleIncomingWebhook(array $payload, ?string $rawPayload = null, ?string $signature = null): array
    {
        // 1. Extract entry and message elements
        $entries = $payload['entry'] ?? [];
        if (empty($entries)) {
            return ['status' => 'ignored', 'reason' => 'No entries in payload'];
        }

        $results = [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $metadata = $value['metadata'] ?? [];
                $phoneNumberId = (string) ($metadata['phone_number_id'] ?? '');

                if (!$phoneNumberId) {
                    continue;
                }

                // Webhook Tenant Resolution (Section 10)
                $connection = $this->resolveConnectionByPhoneNumberId($phoneNumberId);
                if (!$connection || !$connection->clinic) {
                    Log::warning("WhatsApp webhook received for unregistered phone_number_id: {$phoneNumberId}");
                    $results[] = [
                        'status' => 'rejected',
                        'phone_number_id' => $phoneNumberId,
                        'reason' => 'Unregistered WhatsApp connection.',
                    ];
                    continue;
                }

                // Reject disabled or disconnected connections (Phase E, Phase O)
                if (in_array($connection->status, ['DISABLED', 'DISCONNECTED', 'ERROR'])) {
                    Log::warning("WhatsApp webhook received for inactive connection {$phoneNumberId} (status: {$connection->status})");
                    $results[] = [
                        'status' => 'rejected',
                        'phone_number_id' => $phoneNumberId,
                        'reason' => 'WhatsApp connection is disabled or disconnected.',
                    ];
                    continue;
                }

                $clinic = $connection->clinic;

                // Handle inbound patient messages
                $messages = $value['messages'] ?? [];
                foreach ($messages as $msg) {
                    $eventId = $msg['id'] ?? null;
                    if (!$eventId) {
                        $eventId = hash('sha256', json_encode($msg) . $phoneNumberId);
                    }

                    // Webhook Idempotency (Section 26)
                    $existingEvent = WhatsAppWebhookEvent::where('event_id', $eventId)->first();
                    if ($existingEvent && $existingEvent->processed) {
                        Log::info("Idempotent WhatsApp webhook event skipped: {$eventId}");
                        $results[] = [
                            'status' => 'idempotent_skip',
                            'event_id' => $eventId,
                        ];
                        continue;
                    }

                    try {
                        $webhookRecord = WhatsAppWebhookEvent::firstOrCreate(
                            ['event_id' => $eventId],
                            [
                                'phone_number_id' => $phoneNumberId,
                                'clinic_id' => $clinic->id,
                                'event_type' => 'messages',
                                'payload' => $msg,
                                'processed' => false,
                            ]
                        );
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Race condition handling for concurrent webhook delivery
                        $webhookRecord = WhatsAppWebhookEvent::where('event_id', $eventId)->first();
                    }

                    if (!$webhookRecord || $webhookRecord->processed) {
                        Log::info("Idempotent WhatsApp webhook event skipped after race check: {$eventId}");
                        $results[] = [
                            'status' => 'idempotent_skip',
                            'event_id' => $eventId,
                        ];
                        continue;
                    }

                    try {
                        $processResult = $this->processPatientMessage($connection, $clinic, $msg);

                        $webhookRecord->update([
                            'processed' => true,
                            'processed_at' => now(),
                        ]);

                        $connection->update([
                            'last_webhook_at' => now(),
                            'last_message_at' => now(),
                        ]);

                        $results[] = [
                            'status' => 'processed',
                            'event_id' => $eventId,
                            'result' => $processResult,
                        ];
                    } catch (\Throwable $e) {
                        Log::error("Error processing WhatsApp message {$eventId}: " . $e->getMessage());
                        $webhookRecord->update([
                            'error' => $e->getMessage(),
                        ]);

                        $results[] = [
                            'status' => 'error',
                            'event_id' => $eventId,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        return [
            'status' => 'success',
            'processed_count' => count($results),
            'details' => $results,
        ];
    }

    /**
     * Process an individual message from a patient
     */
    protected function processPatientMessage(WhatsAppConnection $connection, Clinic $clinic, array $msg): array
    {
        $waId = WhatsAppIdentityNormalizer::normalize((string) ($msg['from'] ?? ''));
        $type = $msg['type'] ?? 'text';

        // Extract message text or button payload
        $body = '';
        if ($type === 'text') {
            $body = $msg['text']['body'] ?? '';
        } elseif ($type === 'interactive') {
            $interactive = $msg['interactive'] ?? [];
            if (($interactive['type'] ?? '') === 'button_reply') {
                $body = $interactive['button_reply']['id'] ?? '';
            } elseif (($interactive['type'] ?? '') === 'list_reply') {
                $body = $interactive['list_reply']['id'] ?? '';
            }
        } elseif ($type === 'button') {
            $body = $msg['button']['payload'] ?? $msg['button']['text'] ?? '';
        }

        $rawTs = $msg['timestamp'] ?? null;
        $providerTimestamp = $rawTs ? WhatsAppTimestampNormalizer::normalize($rawTs) : null;
        $receivedAt = now();

        // Find or create conversation for this clinic and wa_id
        try {
            $conversation = WhatsAppConversation::firstOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'wa_id' => $waId,
                ],
                [
                    'whatsapp_connection_id' => $connection->id,
                    'current_state' => 'NEW',
                    'last_patient_message_at' => $providerTimestamp ?? $receivedAt,
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $conversation = WhatsAppConversation::where('clinic_id', $clinic->id)->where('wa_id', $waId)->firstOrFail();
        }

        $conversation->update([
            'last_patient_message_at' => $providerTimestamp ?? $receivedAt,
        ]);

        // Record incoming message (Section 12)
        WhatsAppMessage::create([
            'clinic_id' => $clinic->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'INBOUND',
            'message_type' => $type,
            'provider_message_id' => $msg['id'] ?? null,
            'content' => $body,
            'payload' => array_merge($msg, [
                'provider_timestamp' => $providerTimestamp?->toIso8601String(),
                'received_at' => $receivedAt->toIso8601String(),
            ]),
            'status' => 'DELIVERED',
            'sent_at' => $providerTimestamp ?? $receivedAt,
            'delivered_at' => $providerTimestamp ?? $receivedAt,
            'provider_timestamp' => $providerTimestamp,
        ]);

        // Execute state machine
        $response = $this->stateMachine->process($conversation, $body, $clinic);

        // Send reply through robust NotificationService
        $notificationService = app(\App\Services\NotificationService::class);
        $outboundResult = $notificationService->sendWhatsAppNotification(
            $clinic,
            $waId,
            !empty($response['buttons']) ? 'BUTTONS' : 'TEXT',
            $response
        );

        return [
            'wa_id' => $waId,
            'current_state' => $conversation->fresh()->current_state,
            'outbound' => $outboundResult,
            'retry_queued' => $outboundResult['status'] === 'QUEUED',
        ];
    }

    /**
     * Send outbound message via provider adapter
     */
    protected function sendOutboundResponse(WhatsAppConnection $connection, string $to, array $response): array
    {
        $text = $response['text'] ?? '';
        $buttons = $response['buttons'] ?? [];

        if (!empty($buttons)) {
            return $this->provider->sendInteractiveButtons($connection, $to, $text, $buttons);
        }

        return $this->provider->sendTextMessage($connection, $to, $text);
    }

    // =========================================================================
    // APPROVED AI CLINIC-SCOPED TOOLS (Contract Section 15)
    // =========================================================================

    public function getClinicHours(Clinic $clinic): array
    {
        return $clinic->schedule_config ?? $clinic->defaultSchedule();
    }

    public function getClinicLocation(Clinic $clinic): array
    {
        return [
            'name' => $clinic->name,
            'address' => $clinic->address,
            'city' => $clinic->city,
            'pincode' => $clinic->pincode,
            'phone' => $clinic->phone,
        ];
    }

    public function getServices(Clinic $clinic): array
    {
        return [
            'opd_consultation' => true,
            'diagnostic_lab' => true,
            'modes' => ['CLINIC_PHYSICAL'],
        ];
    }

    public function getConsultationFee(Clinic $clinic, ?int $doctorId = null, ?int $bookingTypeId = null): ?float
    {
        if ($bookingTypeId && $doctorId) {
            $fee = FeeMaster::getFeeForBookingType($clinic->id, $doctorId, $bookingTypeId, ClinicClock::today()->toDateString());
            if ($fee !== null) {
                return (float) $fee;
            }
        }

        return FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $clinic->id, $doctorId);
    }

    public function getBookableDates(Clinic $clinic): array
    {
        $today = ClinicClock::today();
        $dates = [$today->toDateString()];
        if (($clinic->max_advance_booking_days ?? 1) >= 1) {
            $dates[] = $today->copy()->addDay()->toDateString();
        }
        return $dates;
    }

    public function getBookableSessions(Clinic $clinic, string $date): array
    {
        return $this->clinicScheduleService->getSessionsForDate($clinic, $date);
    }

    public function getDoctors(Clinic $clinic): array
    {
        return Doctor::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'specialization', 'qualification'])
            ->toArray();
    }

    public function searchPatient(Clinic $clinic, string $phone, ?string $name = null): ?Patient
    {
        if ($name) {
            return $this->patientIdentityService->findExisting($clinic->id, $name, $phone);
        }

        return $this->patientIdentityService->findCandidatesByMobile($clinic->id, $phone)->first();
    }

    public function createPatient(Clinic $clinic, array $patientData): Patient
    {
        $patientData['clinic_id'] = $clinic->id;
        return $this->patientIdentityService->registerPatient($patientData);
    }

    public function createAppointment(Clinic $clinic, array $bookingData): Appointment
    {
        $bookingData['clinic_id'] = $clinic->id;
        $bookingData['booking_source'] = Appointment::SOURCE_WHATSAPP;
        return $this->appointmentService->bookAppointment($bookingData);
    }

    public function getBookingStatus(Clinic $clinic, string $phone, string $tokenOrId): ?Appointment
    {
        $cleanPhone = WhatsAppIdentityNormalizer::normalize($phone);
        $raw10 = WhatsAppIdentityNormalizer::to10Digit($phone);

        return Appointment::where('clinic_id', $clinic->id)
            ->where(function ($sub) use ($tokenOrId) {
                $sub->where('formatted_token', $tokenOrId)
                    ->orWhere('token_number', (int) $tokenOrId)
                    ->orWhereHas('patient', function ($pq) use ($tokenOrId) {
                        $pq->where('patient_id', $tokenOrId);
                    });
            })
            ->whereHas('patient', function ($pq) use ($raw10, $cleanPhone, $clinic) {
                $pq->where('mobile', $raw10)
                    ->orWhere('mobile', '+91 ' . $raw10)
                    ->orWhereHas('channelIdentities', function ($ci) use ($cleanPhone, $clinic) {
                        $ci->where('clinic_id', $clinic->id)
                            ->where('channel', 'WHATSAPP')
                            ->where('external_identity', $cleanPhone);
                    });
            })
            ->with(['patient', 'doctor', 'visit'])
            ->first();
    }

    public function cancelAppointment(Clinic $clinic, string $phone, int $appointmentId): bool
    {
        $appointment = Appointment::where('clinic_id', $clinic->id)->find($appointmentId);
        if (!$appointment || $appointment->status === 'CANCELLED') {
            return false;
        }

        return DB::transaction(function () use ($appointment) {
            $appointment->update(['status' => 'CANCELLED']);

            DB::afterCommit(function () use ($appointment) {
                event(\App\Events\QueueUpdated::fromAppointment($appointment, 'CANCELLED'));
            });

            return true;
        });
    }
}
