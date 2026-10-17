<?php

namespace App\Jobs;

use App\Models\WhatsAppConnection;
use App\Models\WhatsAppMessageDelivery;
use App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public int $deliveryId
    ) {}

    /**
     * Calculate the backoff delay based on the attempt count.
     */
    protected function getBackoffDelay(int $attempt): int
    {
        $delays = [10, 30, 60, 120, 300];
        return $delays[$attempt - 1] ?? 300;
    }

    public function handle(WhatsAppProviderInterface $provider): void
    {
        $delivery = WhatsAppMessageDelivery::find($this->deliveryId);
        if (!$delivery) {
            return;
        }

        if ($delivery->status === 'SENT') {
            return;
        }

        $connection = WhatsAppConnection::where('clinic_id', $delivery->clinic_id)->first();
        if (!$connection || in_array($connection->status, ['DISABLED', 'DISCONNECTED'])) {
            $delivery->update([
                'status' => 'FAILED',
                'last_error' => 'WhatsApp connection is inactive or disabled for clinic ' . $delivery->clinic_id,
            ]);
            return;
        }

        $delivery->update([
            'status' => 'SENDING',
            'attempt_count' => $delivery->attempt_count + 1,
        ]);

        $payload = $delivery->payload ?? [];
        $recipient = $delivery->recipient_phone;
        $type = $delivery->message_type;

        $providerMsgId = $delivery->provider_message_id;

        try {
            $result = null;

            if ($type === 'DOCUMENT' && !empty($payload['document_url'])) {
                $result = $provider->sendDocument(
                    $connection,
                    $recipient,
                    $payload['document_url'],
                    $payload['filename'] ?? 'Token.pdf',
                    $payload['caption'] ?? '',
                    $providerMsgId
                );
            } elseif ($type === 'BUTTONS' && !empty($payload['buttons'])) {
                $result = $provider->sendInteractiveButtons(
                    $connection,
                    $recipient,
                    $payload['body'] ?? $payload['text'] ?? '',
                    $payload['buttons'],
                    $payload['header'] ?? null,
                    $payload['footer'] ?? null,
                    $providerMsgId
                );
            } elseif ($type === 'TEMPLATE' && !empty($payload['template_name'])) {
                $result = $provider->sendTemplate(
                    $connection,
                    $recipient,
                    $payload['template_name'],
                    $payload['language'] ?? 'en',
                    $payload['parameters'] ?? [],
                    $providerMsgId
                );
            } else {
                // Default to text
                $text = $payload['text'] ?? $payload['body'] ?? 'Message from DxClinic';
                $result = $provider->sendTextMessage($connection, $recipient, $text, $providerMsgId);
            }

            if ($result['success'] ?? false) {
                $delivery->update([
                    'status' => 'SENT',
                    'sent_at' => now(),
                    'provider_message_id' => $result['provider_message_id'] ?? $providerMsgId,
                    'last_error' => null,
                ]);

                $connection->update([
                    'last_successful_send_at' => now(),
                ]);
            } else {
                $errorMsg = $result['message'] ?? $result['error'] ?? 'Unknown sending error';
                $isLastAttempt = $delivery->attempt_count >= $delivery->max_attempts;

                $delivery->update([
                    'status' => $isLastAttempt ? 'FAILED' : 'RETRYING',
                    'last_error' => $errorMsg,
                ]);

                if (!$isLastAttempt) {
                    $this->release($this->getBackoffDelay($delivery->attempt_count));
                }
            }
        } catch (\Throwable $e) {
            Log::error("SendWhatsAppMessageJob error for delivery {$delivery->id}: " . $e->getMessage());

            $isLastAttempt = $delivery->attempt_count >= $delivery->max_attempts;
            $delivery->update([
                'status' => $isLastAttempt ? 'FAILED' : 'RETRYING',
                'last_error' => $e->getMessage(),
            ]);

            if (!$isLastAttempt) {
                $this->release($this->getBackoffDelay($delivery->attempt_count));
            }
        }
    }
}
