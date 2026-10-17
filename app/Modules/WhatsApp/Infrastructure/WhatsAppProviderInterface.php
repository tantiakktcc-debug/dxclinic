<?php

namespace App\Modules\WhatsApp\Infrastructure;

use App\Models\WhatsAppConnection;

interface WhatsAppProviderInterface
{
    /**
     * Verify HMAC-SHA256 signature from incoming webhook (X-Hub-Signature-256)
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signature, string $appSecret): bool;

    /**
     * Send plain text message
     */
    public function sendTextMessage(WhatsAppConnection $connection, string $to, string $text, ?string $messageId = null): array;

    /**
     * Send interactive quick-reply buttons (max 3 buttons per Meta WhatsApp specification)
     */
    public function sendInteractiveButtons(
        WhatsAppConnection $connection,
        string $to,
        string $bodyText,
        array $buttons,
        ?string $header = null,
        ?string $footer = null,
        ?string $messageId = null
    ): array;

    /**
     * Send interactive list message (menus, sessions, dates, doctors)
     */
    public function sendInteractiveList(
        WhatsAppConnection $connection,
        string $to,
        string $bodyText,
        string $buttonLabel,
        array $sections,
        ?string $title = null,
        ?string $messageId = null
    ): array;

    /**
     * Send pre-approved Meta WhatsApp template message
     */
    public function sendTemplate(
        WhatsAppConnection $connection,
        string $to,
        string $templateName,
        string $language = 'en',
        array $parameters = [],
        ?string $messageId = null
    ): array;

    /**
     * Send media document (e.g. Token PDF)
     */
    public function sendDocument(
        WhatsAppConnection $connection,
        string $to,
        string $documentUrl,
        string $filename,
        string $caption = '',
        ?string $messageId = null
    ): array;
}
