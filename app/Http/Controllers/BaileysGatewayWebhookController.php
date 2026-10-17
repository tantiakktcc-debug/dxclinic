<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppConnection;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageDelivery;
use App\Modules\WhatsApp\Application\WhatsAppService;
use App\Services\BaileysConnectionService;
use App\Services\WhatsAppIdentityNormalizer;
use App\Services\WhatsAppTimestampNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * BaileysGatewayWebhookController
 *
 * Receives inbound webhook events FROM the Baileys Node.js gateway.
 * This endpoint is internal — registered only in api.php.
 *
 * SECURITY CONTRACT:
 * - Every request from the gateway is validated against BAILEYS_WEBHOOK_SECRET
 *   using HMAC-SHA256 (X-Gateway-Signature header)
 * - clinic_id is resolved from the gateway-signed payload — NEVER from query string
 * - This controller does NOT expose any connection secrets or credentials to callers
 * - Should be network-restricted to 127.0.0.1 in production (via middleware or Nginx/IIS)
 *
 * Message flow:
 * Baileys gateway → POST /api/internal/baileys/webhook → this controller
 *               → WhatsAppService::handleBaileysMessage (resolves clinic, runs state machine)
 *               → Appointment committed via AppointmentService
 *               → BaileysProvider::sendTextMessage (confirmation back via gateway)
 */
class BaileysGatewayWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected BaileysConnectionService $baileysConnectionService
    ) {}

    /**
     * Handle inbound event from Baileys gateway.
     *
     * Expected payload structure:
     * {
     *   "event": "message" | "connection_state" | "qr",
     *   "clinic_id": 6,
     *   "data": { ... event-specific data ... }
     * }
     */
    public function handle(Request $request): JsonResponse
    {
        Log::info('BAILEYS_WEBHOOK_RECEIVED', ['url' => $request->fullUrl(), 'method' => $request->method()]);

        // 1. Reject oversized payloads
        $rawPayload = $request->getContent();
        if (strlen($rawPayload) > 524288) {
            Log::warning('Baileys gateway webhook rejected: payload too large');
            return response()->json(['error' => 'Payload too large'], 413);
        }

        // 2. Validate HMAC-SHA256 signature
        $signature = $request->header('X-Gateway-Signature');
        $nonce     = $request->header('X-Gateway-Nonce', '');
        $timestamp = $request->header('X-Gateway-Timestamp', '');
        $secret    = config('dxclinic.whatsapp.baileys.webhook_secret', '');

        if (empty($secret)) {
            Log::error('Baileys webhook secret is not configured (BAILEYS_WEBHOOK_SECRET)');
            return response()->json(['error' => 'Gateway authentication not configured'], 500);
        }

        if (!$this->verifyGatewaySignature($rawPayload, $signature, $nonce, $timestamp, $secret)) {
            Log::warning('Baileys gateway webhook: invalid signature', [
                'ip'        => $request->ip(),
                'nonce'     => $nonce,
                'timestamp' => $timestamp,
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 3. Reject stale requests (replay attack prevention — 5-minute window)
        if (!empty($timestamp)) {
            $age = abs(time() - (int) $timestamp);
            if ($age > 300) {
                Log::warning('Baileys gateway webhook: stale timestamp, possible replay attack');
                return response()->json(['error' => 'Request expired'], 401);
            }
        }

        Log::info('BAILEYS_WEBHOOK_VALIDATED');

        // 4. Parse payload
        $payload = json_decode($rawPayload, true);
        if ($payload === null) {
            return response()->json(['error' => 'Malformed JSON'], 400);
        }

        $event    = $payload['event'] ?? 'unknown';
        $clinicId = (int) ($payload['clinic_id'] ?? 0);

        if (!$clinicId) {
            Log::warning('Baileys gateway webhook: missing clinic_id in payload');
            return response()->json(['error' => 'Missing clinic_id'], 400);
        }

        // 5. Dispatch by event type
        try {
            $result = match ($event) {
                'message'          => $this->handleMessage($clinicId, $payload['data'] ?? []),
                'message_status'   => $this->handleMessageStatus($clinicId, $payload['data'] ?? []),
                'connection_state' => $this->handleConnectionState($clinicId, $payload['data'] ?? []),
                'qr'               => $this->handleQr($clinicId, $payload['data'] ?? []),
                default            => ['status' => 'ignored', 'event' => $event],
            };

            return response()->json(['status' => 'ok', 'result' => $result]);

        } catch (\Throwable $e) {
            Log::error('Baileys gateway webhook processing error', [
                'event'     => $event,
                'clinic_id' => $clinicId,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['status' => 'error', 'message' => 'Processing failed'], 500);
        }
    }

    // =========================================================================
    // Event Handlers
    // =========================================================================

    /**
     * Handle inbound WhatsApp message from patient.
     * Transforms Baileys message format into the same Meta-compatible format
     * that WhatsAppService::handleIncomingWebhook expects.
     */
    private function handleMessage(int $clinicId, array $data): array
    {
        Log::info('BAILEYS_MESSAGE_EXTRACTED', ['clinic_id' => $clinicId, 'msg_id' => $data['id'] ?? null, 'remote_jid' => $data['remote_jid'] ?? null]);

        // Resolve connection for this clinic (must be Baileys provider)
        $connection = WhatsAppConnection::where('clinic_id', $clinicId)
            ->where('provider', 'baileys')
            ->first();

        if (!$connection || !$connection->isConnected()) {
            Log::warning("Baileys message for clinic {$clinicId}: no active connection");
            return ['status' => 'rejected', 'reason' => 'No active Baileys connection'];
        }

        // Normalize Baileys message to WhatsApp webhook format
        // This allows WhatsAppService (state machine, patient identity, booking) to work unchanged
        $waId       = WhatsAppIdentityNormalizer::normalize($data['from'] ?? '');
        if (empty($waId) || strlen($waId) < 10) {
            Log::info("Baileys message ignored: invalid sender waId", ['from' => $data['from'] ?? null]);
            return ['status' => 'ignored', 'reason' => 'Invalid sender identity'];
        }

        $messageId  = $data['id'] ?? ('baileys_' . hash('sha256', json_encode($data) . $clinicId));
        $type       = $data['type'] ?? 'text';
        $body       = $data['body'] ?? $data['text'] ?? '';
        $timestamp  = $data['timestamp'] ?? null;

        $msgItem = [
            'id'   => $messageId,
            'from' => $waId,
            'type' => $type,
            'text' => ['body' => $body],
        ];

        if ($timestamp !== null) {
            $msgItem['timestamp'] = (string) $timestamp;
        }

        // Build Meta-compatible webhook payload for state machine
        $metaCompatiblePayload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => $connection->display_phone_number ?? WhatsAppIdentityNormalizer::DEFAULT_BUSINESS_NUMBER,
                            'phone_number_id' => $connection->phone_number_id ?? ('baileys_' . $clinicId),
                        ],
                        'contacts' => !empty($data['push_name']) ? [[
                            'wa_id' => $waId,
                            'profile' => ['name' => $data['push_name']],
                        ]] : [],
                        'messages' => [$msgItem],
                    ],
                ]],
            ]],
        ];

        // Ensure connection has a phone_number_id for routing
        if (empty($connection->phone_number_id)) {
            $connection->update([
                'phone_number_id' => 'baileys_' . $clinicId,
            ]);
        }

        return $this->whatsAppService->handleIncomingWebhook($metaCompatiblePayload);
    }

    /**
     * Handle delivery status or read receipt updates from Baileys.
     * Never fabricates delivery times; normalizes provider timestamps strictly once.
     */
    private function handleMessageStatus(int $clinicId, array $data): array
    {
        $messageId = $data['id'] ?? null;
        if (!$messageId) {
            return ['status' => 'ignored', 'reason' => 'Missing message id'];
        }

        $rawStatus = strtoupper($data['status'] ?? 'SENT');
        $timestamp = $data['timestamp'] ?? null;
        $normalizedTime = $timestamp ? WhatsAppTimestampNormalizer::normalize($timestamp) : null;

        $msg = WhatsAppMessage::where('clinic_id', $clinicId)
            ->where('provider_message_id', $messageId)
            ->first();

        if ($msg) {
            $updateData = ['status' => $rawStatus];

            if ($rawStatus === 'DELIVERED') {
                $updateData['delivered_at'] = $normalizedTime;
            } elseif ($rawStatus === 'READ') {
                $updateData['read_at'] = $normalizedTime;
                if (!$msg->delivered_at && $normalizedTime) {
                    $updateData['delivered_at'] = $normalizedTime;
                }
            } elseif ($rawStatus === 'SENT' && !$msg->sent_at) {
                $updateData['sent_at'] = $normalizedTime ?? now();
            }

            $msg->update($updateData);

            Log::info("WhatsApp message {$messageId} status updated to {$rawStatus}", [
                'clinic_id' => $clinicId,
                'status' => $rawStatus,
                'normalized_time' => $normalizedTime?->toIso8601String(),
            ]);
        }

        // Also update asynchronous delivery record if present
        $delivery = WhatsAppMessageDelivery::where('clinic_id', $clinicId)
            ->where('provider_message_id', $messageId)
            ->first();

        if ($delivery && in_array($rawStatus, ['SENT', 'DELIVERED', 'FAILED'])) {
            $delivery->update([
                'status' => ($rawStatus === 'DELIVERED' || $rawStatus === 'SENT') ? 'SENT' : 'FAILED',
            ]);
        }

        return [
            'status' => 'status_recorded',
            'message_id' => $messageId,
            'delivery_status' => $rawStatus,
            'time' => $normalizedTime?->toIso8601String(),
        ];
    }

    /**
     * Handle connection state change event from Baileys gateway.
     */
    private function handleConnectionState(int $clinicId, array $data): array
    {
        $state = strtoupper($data['state'] ?? 'DISCONNECTED');
        $this->baileysConnectionService->handleGatewayStateChange($clinicId, $state, $data);

        return ['status' => 'synced', 'state' => $state];
    }

    /**
     * Handle QR code refresh event from Baileys gateway.
     */
    private function handleQr(int $clinicId, array $data): array
    {
        $this->baileysConnectionService->handleGatewayStateChange(
            $clinicId,
            'QR_REQUIRED',
            array_merge($data, ['state' => 'QR_REQUIRED'])
        );

        return ['status' => 'qr_stored'];
    }

    // =========================================================================
    // Signature Verification
    // =========================================================================

    /**
     * Verify HMAC-SHA256 signature from Baileys gateway.
     * Format: sha256=<hmac(nonce.timestamp.rawPayload, secret)>
     */
    private function verifyGatewaySignature(
        string $rawPayload,
        ?string $signature,
        string $nonce,
        string $timestamp,
        string $secret
    ): bool {
        if (empty($signature) || empty($nonce) || empty($timestamp)) {
            return false;
        }

        $signData = $nonce . '.' . $timestamp . '.' . $rawPayload;
        $expected = 'sha256=' . hash_hmac('sha256', $signData, $secret);

        return hash_equals($expected, $signature);
    }
}
