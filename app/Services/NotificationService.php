<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageDelivery;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        protected WhatsAppChannel $whatsAppChannel
    ) {}

    /**
     * Send an outbound notification via WhatsAppChannel with delivery persistence and idempotency tracking.
     */
    public function sendWhatsAppNotification(
        Clinic $clinic,
        string $to,
        string $type,
        array $payload,
        ?int $appointmentId = null,
        ?string $idempotencyKey = null
    ): array {
        $canonicalTo = WhatsAppIdentityNormalizer::normalize($to);

        // Check if an existing delivery was already completed with this idempotency key
        if ($idempotencyKey) {
            $existing = WhatsAppMessageDelivery::where('clinic_id', $clinic->id)
                ->where('recipient_phone', $canonicalTo)
                ->where('message_type', $type)
                ->whereJsonContains('payload->idempotency_key', $idempotencyKey)
                ->whereIn('status', ['SENT', 'SENDING'])
                ->first();

            if ($existing) {
                Log::info("Idempotent notification skipped: {$idempotencyKey}");
                return [
                    'success' => true,
                    'status' => $existing->status,
                    'delivery_id' => $existing->id,
                    'provider_message_id' => $existing->provider_message_id,
                    'idempotent' => true,
                ];
            }
        }

        if ($idempotencyKey) {
            $payload['idempotency_key'] = $idempotencyKey;
        }

        $providerMsgId = 'dxc_msg_' . $clinic->id . '_' . uniqid();

        // Create delivery log record
        $delivery = WhatsAppMessageDelivery::create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointmentId,
            'recipient_phone' => $canonicalTo,
            'message_type' => $type,
            'status' => 'PENDING',
            'attempt_count' => 1,
            'max_attempts' => 3,
            'payload' => $payload,
            'provider_message_id' => $providerMsgId,
        ]);

        try {
            $delivery->update(['status' => 'SENDING']);

            $result = match ($type) {
                'DOCUMENT' => $this->whatsAppChannel->sendDocument(
                    $clinic,
                    $canonicalTo,
                    $payload['document_url'] ?? '',
                    $payload['filename'] ?? 'Token.pdf',
                    $payload['caption'] ?? '',
                    $providerMsgId
                ),
                'BUTTONS' => $this->whatsAppChannel->sendInteractiveButtons(
                    $clinic,
                    $canonicalTo,
                    $payload['body'] ?? $payload['text'] ?? '',
                    $payload['buttons'] ?? [],
                    $payload['header'] ?? null,
                    $payload['footer'] ?? null,
                    $providerMsgId
                ),
                'LIST' => $this->whatsAppChannel->sendInteractiveList(
                    $clinic,
                    $canonicalTo,
                    $payload['body'] ?? $payload['text'] ?? '',
                    $payload['button_label'] ?? 'Options',
                    $payload['sections'] ?? [],
                    $payload['title'] ?? null,
                    $providerMsgId
                ),
                'TEMPLATE' => $this->whatsAppChannel->sendTemplate(
                    $clinic,
                    $canonicalTo,
                    $payload['template_name'] ?? '',
                    $payload['language'] ?? 'en',
                    $payload['parameters'] ?? [],
                    $providerMsgId
                ),
                default => $this->whatsAppChannel->sendTextMessage(
                    $clinic,
                    $canonicalTo,
                    $payload['text'] ?? $payload['body'] ?? '',
                    $providerMsgId
                ),
            };

            $isSent = !empty($result['success']);
            $providerMsgId = $result['provider_message_id'] ?? $providerMsgId;
            $nowUtc = now();

            if ($isSent) {
                $delivery->update([
                    'status' => 'SENT',
                    'sent_at' => $nowUtc,
                    'provider_message_id' => $providerMsgId,
                    'last_error' => null,
                ]);
            } else {
                $delivery->update([
                    'status' => 'RETRYING',
                    'last_error' => $result['error'] ?? 'Provider send failed',
                ]);
                \App\Jobs\SendWhatsAppMessageJob::dispatch($delivery->id)
                    ->delay(now()->addSeconds(10));
                
                Log::info('Notification queued for retry due to provider failure', ['delivery_id' => $delivery->id]);
            }

            // If a conversation exists for this wa_id, record outbound WhatsAppMessage
            $conversation = WhatsAppConversation::where('clinic_id', $clinic->id)
                ->where('wa_id', $canonicalTo)
                ->first();

            if ($conversation) {
                WhatsAppMessage::create([
                    'clinic_id' => $clinic->id,
                    'whatsapp_conversation_id' => $conversation->id,
                    'direction' => 'OUTBOUND',
                    'message_type' => $type === 'DOCUMENT' ? 'document' : 'text',
                    'provider_message_id' => $providerMsgId,
                    'content' => $payload['text'] ?? ($payload['caption'] ?? "Document: " . ($payload['filename'] ?? 'Token.pdf')),
                    'payload' => array_merge($payload, [
                        'delivery_id' => $delivery->id,
                        'gateway_result' => $result,
                        'retry_queued' => !$isSent,
                    ]),
                    'status' => $isSent ? 'SENT' : 'PENDING',
                    'sent_at' => $isSent ? $nowUtc : null,
                ]);

                $conversation->update(['last_outbound_message_at' => $nowUtc]);
            }

            return array_merge($result, [
                'delivery_id' => $delivery->id,
                'status' => $isSent ? 'SENT' : 'RETRYING',
            ]);

        } catch (\Throwable $e) {
            Log::error("NotificationService delivery error: " . $e->getMessage(), [
                'delivery_id' => $delivery->id,
                'type' => $type,
                'clinic_id' => $clinic->id,
            ]);

            $delivery->update([
                'status' => 'RETRYING',
                'last_error' => $e->getMessage(),
            ]);
            
            \App\Jobs\SendWhatsAppMessageJob::dispatch($delivery->id)
                ->delay(now()->addSeconds(10));

            return [
                'success' => false,
                'status' => 'RETRYING',
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Controlled retry for a failed notification delivery.
     * Does NOT allocate a new token or touch appointment state!
     */
    public function retryDelivery(int $deliveryId): array
    {
        $delivery = WhatsAppMessageDelivery::with(['clinic'])->findOrFail($deliveryId);

        if ($delivery->status === 'SENT') {
            return ['success' => true, 'status' => 'ALREADY_SENT'];
        }

        if ($delivery->attempt_count >= $delivery->max_attempts) {
            return ['success' => false, 'status' => 'MAX_ATTEMPTS_EXCEEDED', 'error' => 'Maximum retry attempts reached'];
        }

        $delivery->increment('attempt_count');
        $delivery->update(['status' => 'SENDING']);

        $payload = $delivery->payload ?? [];
        $type = $delivery->message_type;

        $providerMsgId = $delivery->provider_message_id;

        $result = match ($type) {
            'DOCUMENT' => $this->whatsAppChannel->sendDocument(
                $delivery->clinic,
                $delivery->recipient_phone,
                $payload['document_url'] ?? '',
                $payload['filename'] ?? 'Token.pdf',
                $payload['caption'] ?? '',
                $providerMsgId
            ),
            'BUTTONS' => $this->whatsAppChannel->sendInteractiveButtons(
                $delivery->clinic,
                $delivery->recipient_phone,
                $payload['body'] ?? $payload['text'] ?? '',
                $payload['buttons'] ?? [],
                $payload['header'] ?? null,
                $payload['footer'] ?? null,
                $providerMsgId
            ),
            'LIST' => $this->whatsAppChannel->sendInteractiveList(
                $delivery->clinic,
                $delivery->recipient_phone,
                $payload['body'] ?? $payload['text'] ?? '',
                $payload['button_label'] ?? 'Options',
                $payload['sections'] ?? [],
                $payload['title'] ?? null,
                $providerMsgId
            ),
            'TEMPLATE' => $this->whatsAppChannel->sendTemplate(
                $delivery->clinic,
                $delivery->recipient_phone,
                $payload['template_name'] ?? '',
                $payload['language'] ?? 'en',
                $payload['parameters'] ?? [],
                $providerMsgId
            ),
            default => $this->whatsAppChannel->sendTextMessage(
                $delivery->clinic,
                $delivery->recipient_phone,
                $payload['text'] ?? $payload['body'] ?? '',
                $providerMsgId
            ),
        };

        $isSent = !empty($result['success']);
        $delivery->update([
            'status' => $isSent ? 'SENT' : 'FAILED',
            'sent_at' => $isSent ? now() : null,
            'provider_message_id' => $result['provider_message_id'] ?? $delivery->provider_message_id,
            'last_error' => $isSent ? null : ($result['error'] ?? 'Retry failed'),
        ]);

        return array_merge($result, [
            'delivery_id' => $delivery->id,
            'status' => $isSent ? 'SENT' : 'FAILED',
        ]);
    }
}
