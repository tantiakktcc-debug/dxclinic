<?php

namespace App\Modules\WhatsApp\Infrastructure;

use App\Models\WhatsAppConnection;
use Illuminate\Support\Str;

class MockWhatsAppProvider implements WhatsAppProviderInterface
{
    public array $sentMessages = [];
    public bool $simulateFailure = false;

    public function verifyWebhookSignature(string $rawPayload, ?string $signature, string $appSecret): bool
    {
        if (empty($signature) || empty($appSecret)) {
            return false;
        }

        $expectedHash = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);
        return hash_equals($expectedHash, $signature);
    }

    public function sendTextMessage(WhatsAppConnection $connection, string $to, string $text, ?string $messageId = null): array
    {
        return $this->record([
            'type' => 'text',
            'to' => $to,
            'text' => $text,
            'clinic_id' => $connection->clinic_id,
            'message_id' => $messageId,
        ]);
    }

    public function sendInteractiveButtons(
        WhatsAppConnection $connection,
        string $to,
        string $bodyText,
        array $buttons,
        ?string $header = null,
        ?string $footer = null,
        ?string $messageId = null
    ): array {
        return $this->record([
            'type' => 'buttons',
            'to' => $to,
            'body' => $bodyText,
            'buttons' => $buttons,
            'header' => $header,
            'footer' => $footer,
            'clinic_id' => $connection->clinic_id,
            'message_id' => $messageId,
        ]);
    }

    public function sendInteractiveList(
        WhatsAppConnection $connection,
        string $to,
        string $bodyText,
        string $buttonLabel,
        array $sections,
        ?string $title = null,
        ?string $messageId = null
    ): array {
        return $this->record([
            'type' => 'list',
            'to' => $to,
            'body' => $bodyText,
            'button_label' => $buttonLabel,
            'sections' => $sections,
            'title' => $title,
            'clinic_id' => $connection->clinic_id,
            'message_id' => $messageId,
        ]);
    }

    public function sendTemplate(
        WhatsAppConnection $connection,
        string $to,
        string $templateName,
        string $language = 'en',
        array $parameters = [],
        ?string $messageId = null
    ): array {
        return $this->record([
            'type' => 'template',
            'to' => $to,
            'template' => $templateName,
            'language' => $language,
            'parameters' => $parameters,
            'clinic_id' => $connection->clinic_id,
            'message_id' => $messageId,
        ]);
    }

    public function sendDocument(
        WhatsAppConnection $connection,
        string $to,
        string $documentUrl,
        string $filename,
        string $caption = '',
        ?string $messageId = null
    ): array {
        return $this->record([
            'type' => 'document',
            'to' => $to,
            'url' => $documentUrl,
            'filename' => $filename,
            'caption' => $caption,
            'clinic_id' => $connection->clinic_id,
            'message_id' => $messageId,
        ]);
    }

    protected function record(array $data): array
    {
        if ($this->simulateFailure) {
            return [
                'success' => false,
                'error' => 'SIMULATED_FAILURE',
                'message' => 'Simulated WhatsApp delivery failure.',
            ];
        }

        $msgId = 'wamid.' . Str::random(24);
        $data['provider_message_id'] = $msgId;
        $data['sent_at'] = now()->toIso8601String();
        $this->sentMessages[] = $data;

        return [
            'success' => true,
            'provider_message_id' => $msgId,
            'response' => ['messages' => [['id' => $msgId]]],
        ];
    }

    public function getLastMessage(): ?array
    {
        return end($this->sentMessages) ?: null;
    }

    public function reset(): void
    {
        $this->sentMessages = [];
        $this->simulateFailure = false;
    }
}
