<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function __construct(
        protected WhatsAppProviderInterface $provider
    ) {}

    /**
     * Resolve active WhatsApp connection for the clinic
     */
    public function resolveConnection(Clinic $clinic): ?WhatsAppConnection
    {
        return WhatsAppConnection::where('clinic_id', $clinic->id)
            ->where('status', 'CONNECTED')
            ->first();
    }

    /**
     * Send plain text WhatsApp message via configured provider
     */
    public function sendTextMessage(Clinic $clinic, string $to, string $text, ?string $messageId = null): array
    {
        $connection = $this->resolveConnection($clinic);
        if (!$connection) {
            return [
                'success' => false,
                'status' => 'DISCONNECTED',
                'error' => "No active WhatsApp connection for clinic {$clinic->name} ({$clinic->id})",
            ];
        }

        $canonicalTo = WhatsAppIdentityNormalizer::normalize($to);
        return $this->provider->sendTextMessage($connection, $canonicalTo, $text, $messageId);
    }

    /**
     * Send document (PDF/file) via configured provider
     */
    public function sendDocument(
        Clinic $clinic,
        string $to,
        string $documentUrl,
        string $filename,
        string $caption = '',
        ?string $messageId = null
    ): array {
        $connection = $this->resolveConnection($clinic);
        if (!$connection) {
            return [
                'success' => false,
                'status' => 'DISCONNECTED',
                'error' => "No active WhatsApp connection for clinic {$clinic->name} ({$clinic->id})",
            ];
        }

        $canonicalTo = WhatsAppIdentityNormalizer::normalize($to);
        return $this->provider->sendDocument($connection, $canonicalTo, $documentUrl, $filename, $caption, $messageId);
    }

    public function sendInteractiveButtons(
        Clinic $clinic,
        string $to,
        string $bodyText,
        array $buttons,
        ?string $header = null,
        ?string $footer = null,
        ?string $messageId = null
    ): array {
        $connection = $this->resolveConnection($clinic);
        if (!$connection) {
            return [
                'success' => false,
                'status' => 'DISCONNECTED',
                'error' => "No active WhatsApp connection for clinic {$clinic->name} ({$clinic->id})",
            ];
        }

        $canonicalTo = WhatsAppIdentityNormalizer::normalize($to);
        return $this->provider->sendInteractiveButtons($connection, $canonicalTo, $bodyText, $buttons, $header, $footer, $messageId);
    }

    public function sendInteractiveList(
        Clinic $clinic,
        string $to,
        string $bodyText,
        string $buttonLabel,
        array $sections,
        ?string $title = null,
        ?string $messageId = null
    ): array {
        $connection = $this->resolveConnection($clinic);
        if (!$connection) {
            return [
                'success' => false,
                'status' => 'DISCONNECTED',
                'error' => "No active WhatsApp connection for clinic {$clinic->name} ({$clinic->id})",
            ];
        }

        $canonicalTo = WhatsAppIdentityNormalizer::normalize($to);
        return $this->provider->sendInteractiveList($connection, $canonicalTo, $bodyText, $buttonLabel, $sections, $title, $messageId);
    }

    public function sendTemplate(
        Clinic $clinic,
        string $to,
        string $templateName,
        string $language = 'en',
        array $parameters = [],
        ?string $messageId = null
    ): array {
        $connection = $this->resolveConnection($clinic);
        if (!$connection) {
            return [
                'success' => false,
                'status' => 'DISCONNECTED',
                'error' => "No active WhatsApp connection for clinic {$clinic->name} ({$clinic->id})",
            ];
        }

        $canonicalTo = WhatsAppIdentityNormalizer::normalize($to);
        return $this->provider->sendTemplate($connection, $canonicalTo, $templateName, $language, $parameters, $messageId);
    }

    /**
     * Get underlying provider instance
     */
    public function getProvider(): WhatsAppProviderInterface
    {
        return $this->provider;
    }
}
