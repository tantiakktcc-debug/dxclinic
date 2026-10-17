<?php

namespace App\Modules\WhatsApp\Infrastructure;

use App\Models\WhatsAppConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BaileysWhatsAppProvider
 *
 * Development-only transport provider.
 * Delegates ALL message sending to the local Baileys gateway (127.0.0.1:9090).
 * Never used in production. Never exposes gateway credentials to browser/JS/HTML.
 *
 * Security:
 * - Gateway secret lives exclusively in BAILEYS_GATEWAY_SECRET env var (server-side only)
 * - Each request is signed with HMAC-SHA256
 * - Baileys Gateway listens only on loopback 127.0.0.1:9090.
 *   The gateway can optionally be routed through a secure reverse proxy or tunnel for remote development/WebSocket access.
 * Architecture:
 * - Implements WhatsAppProviderInterface identically to MetaWhatsAppProvider
 * - Domain services (Patient, Appointment, Token, Billing) are NOT implemented here
 * - This class is transport-only
 */
class BaileysWhatsAppProvider implements WhatsAppProviderInterface
{
    private string $gatewayUrl;
    private string $gatewaySecret;
    private int $timeoutSeconds;

    public function __construct()
    {
        $this->gatewayUrl    = config('dxclinic.whatsapp.baileys.gateway_url', 'http://127.0.0.1:9090');
        $this->gatewaySecret = config('dxclinic.whatsapp.baileys.gateway_secret', '');
        $this->timeoutSeconds = (int) config('dxclinic.whatsapp.baileys.timeout', 15);
    }

    /**
     * Verify HMAC-SHA256 signature from Baileys gateway webhook
     * Format: sha256=<hex-encoded-hmac>
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signature, string $appSecret): bool
    {
        if (empty($signature) || empty($appSecret)) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);
        return hash_equals($expected, $signature);
    }

    /**
     * Send a plain text message via Baileys gateway
     */
    public function sendTextMessage(WhatsAppConnection $connection, string $to, string $text, ?string $messageId = null): array
    {
        return $this->gatewayPost('/send/text', [
            'phone'      => $this->normalizePhone($to),
            'text'       => $text,
            'clinic_id'  => $connection->clinic_id,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Send interactive quick-reply buttons via Baileys gateway.
     * Baileys sends these as a formatted text message with numbered options.
     */
    public function sendInteractiveButtons(
        WhatsAppConnection $connection,
        string $to,
        string $bodyText,
        array $buttons,
        ?string $header = null,
        ?string $footer = null,
        ?string $messageId = null
    ): array {
        return $this->gatewayPost('/send/buttons', [
            'phone'      => $this->normalizePhone($to),
            'body'       => $bodyText,
            'buttons'    => array_values($buttons),
            'header'     => $header,
            'footer'     => $footer,
            'clinic_id'  => $connection->clinic_id,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Send interactive list message via Baileys gateway.
     */
    public function sendInteractiveList(
        WhatsAppConnection $connection,
        string $to,
        string $bodyText,
        string $buttonLabel,
        array $sections,
        ?string $title = null,
        ?string $messageId = null
    ): array {
        return $this->gatewayPost('/send/list', [
            'phone'        => $this->normalizePhone($to),
            'body'         => $bodyText,
            'button_label' => $buttonLabel,
            'sections'     => $sections,
            'title'        => $title,
            'clinic_id'    => $connection->clinic_id,
            'message_id'   => $messageId,
        ]);
    }

    /**
     * Send template message via Baileys gateway.
     * Baileys converts to formatted text (no WABA template approval required in dev).
     */
    public function sendTemplate(
        WhatsAppConnection $connection,
        string $to,
        string $templateName,
        string $language = 'en',
        array $parameters = [],
        ?string $messageId = null
    ): array {
        return $this->gatewayPost('/send/template', [
            'phone'         => $this->normalizePhone($to),
            'template_name' => $templateName,
            'language'      => $language,
            'parameters'    => $parameters,
            'clinic_id'     => $connection->clinic_id,
            'message_id'    => $messageId,
        ]);
    }

    /**
     * Send document/file via Baileys gateway.
     */
    public function sendDocument(
        WhatsAppConnection $connection,
        string $to,
        string $documentUrl,
        string $filename,
        string $caption = '',
        ?string $messageId = null
    ): array {
        return $this->gatewayPost('/send/document', [
            'phone'      => $this->normalizePhone($to),
            'url'        => $documentUrl,
            'filename'   => $filename,
            'caption'    => $caption,
            'clinic_id'  => $connection->clinic_id,
            'message_id' => $messageId,
        ]);
    }

    // =========================================================================
    // Baileys-specific gateway control methods
    // These are NOT part of WhatsAppProviderInterface; called by the
    // BaileysConnectionService only.
    // =========================================================================

    /**
     * Request the gateway to start connecting and return QR data
     */
    public function requestQr(int $clinicId): array
    {
        return $this->gatewayPost('/connection/start', [
            'clinic_id' => $clinicId,
        ]);
    }

    /**
     * Query current connection status from gateway
     */
    public function getConnectionStatus(int $clinicId): array
    {
        return $this->gatewayGet('/connection/status', ['clinic_id' => $clinicId]);
    }

    /**
     * Instruct gateway to disconnect
     */
    public function disconnect(int $clinicId): array
    {
        return $this->gatewayPost('/connection/disconnect', [
            'clinic_id' => $clinicId,
        ]);
    }

    // =========================================================================
    // Private: Secure gateway HTTP client
    // =========================================================================

    /**
     * POST to the Baileys gateway with HMAC-SHA256 signing.
     * SECURITY: Gateway URL never reaches the browser. Secret never in logs.
     */
    private function gatewayPost(string $path, array $body): array
    {
        $payload  = json_encode($body);
        $nonce    = Str::random(16);
        $ts       = (string) time();
        $signData = $nonce . '.' . $ts . '.' . $payload;
        $sig      = 'sha256=' . hash_hmac('sha256', $signData, $this->gatewaySecret);

        try {
            $response = Http::withHeaders([
                'X-Gateway-Signature' => $sig,
                'X-Gateway-Nonce'     => $nonce,
                'X-Gateway-Timestamp' => $ts,
                'Content-Type'        => 'application/json',
                'Accept'              => 'application/json',
            ])
            ->timeout($this->timeoutSeconds)
            ->withBody($payload, 'application/json')
            ->post($this->gatewayUrl . $path);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                return array_merge(['success' => true], $data);
            }

            // Do not log gateway secret or full response body in production
            Log::warning('Baileys gateway error', [
                'path'   => $path,
                'status' => $response->status(),
                'error'  => $response->json('error', 'Unknown gateway error'),
            ]);

            return [
                'success' => false,
                'error'   => $response->json('error', 'GATEWAY_ERROR'),
                'message' => $response->json('message', 'Gateway returned HTTP ' . $response->status()),
            ];
        } catch (\Throwable $e) {
            // Log exception without exposing gateway secret
            Log::error('Baileys gateway connection failed', [
                'path'      => $path,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success'        => false,
                'error'          => 'GATEWAY_UNREACHABLE',
                'message'        => 'Baileys gateway is unreachable. Appointment will still be committed.',
                'provider_message_id' => null,
            ];
        }
    }

    /**
     * GET from the Baileys gateway with HMAC-SHA256 signing.
     */
    private function gatewayGet(string $path, array $query = []): array
    {
        $ts       = (string) time();
        $nonce    = Str::random(16);
        $signData = $nonce . '.' . $ts . '.' . json_encode($query);
        $sig      = 'sha256=' . hash_hmac('sha256', $signData, $this->gatewaySecret);

        try {
            $response = Http::withHeaders([
                'X-Gateway-Signature' => $sig,
                'X-Gateway-Nonce'     => $nonce,
                'X-Gateway-Timestamp' => $ts,
                'Accept'              => 'application/json',
            ])
            ->timeout($this->timeoutSeconds)
            ->get($this->gatewayUrl . $path, $query);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                return array_merge(['success' => true], $data);
            }

            return [
                'success' => false,
                'error'   => $response->json('error', 'GATEWAY_ERROR'),
                'status'  => 'ERROR',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'GATEWAY_UNREACHABLE',
                'status'  => 'DISCONNECTED',
            ];
        }
    }

    /**
     * Normalize phone number to E.164-style digits for Baileys
     */
    private function normalizePhone(string $phone): string
    {
        return \App\Services\WhatsAppIdentityNormalizer::normalize($phone);
    }
}
