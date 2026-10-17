<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Application\WhatsAppService;
use App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected WhatsAppProviderInterface $provider
    ) {}

    /**
     * Meta Webhook Verification Handshake (Section 9 GET)
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode !== 'subscribe' || empty($token)) {
            Log::warning("WhatsApp webhook verification failed: Invalid mode or missing token.");
            return response('Forbidden', 403);
        }

        // Check global fallback verify token
        $expectedToken = config('dxclinic.whatsapp_verify_token', 'dxclinic_verify_token_secure');

        // Or match against any active clinic webhook_verify_token
        $connection = WhatsAppConnection::where('webhook_verify_token', $token)->first();

        if ($token === $expectedToken || $connection) {
            if ($connection) {
                $connection->update([
                    'webhook_verified_at' => now(),
                    'status' => 'CONNECTED',
                ]);
            }

            Log::info("WhatsApp webhook verified successfully.");
            return response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        Log::warning("WhatsApp webhook verification failed: Token mismatch.");
        return response('Forbidden', 403);
    }

    /**
     * Meta Webhook Inbound Event Ingestion (Section 9 POST)
     * Validates signature, extracts phone_number_id, resolves tenant, and dispatches.
     */
    public function handle(Request $request)
    {
        // 1. Payload size protection (reject payloads exceeding 512 KB)
        $rawPayload = $request->getContent();
        if (strlen($rawPayload) > 524288) {
            Log::warning("WhatsApp webhook rejected: Payload size exceeds 512KB limit (" . strlen($rawPayload) . " bytes).");
            return response()->json(['error' => 'Payload too large'], 413);
        }

        // 2. Validate valid JSON structure
        $payload = json_decode($rawPayload, true);
        if ($payload === null && !empty($rawPayload)) {
            Log::warning("WhatsApp webhook rejected: Malformed JSON payload.");
            return response()->json(['error' => 'Malformed payload'], 400);
        }

        $payload = $payload ?? [];

        // 3. Signature verification
        $signature = $request->header('X-Hub-Signature-256');
        $appSecret = config('dxclinic.whatsapp.app_secret') ?: config('dxclinic.whatsapp_app_secret');

        if (!empty($appSecret)) {
            if (empty($signature) || !$this->provider->verifyWebhookSignature($rawPayload, $signature, $appSecret)) {
                Log::warning("WhatsApp webhook signature verification failed.");
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        } elseif (!empty($signature)) {
            // If signature is provided, verify it against any clinic-level secret if present
            $valid = false;
            $activeConnections = WhatsAppConnection::whereNotNull('access_token_encrypted')->get();
            foreach ($activeConnections as $conn) {
                $clinicSecret = $conn->getSetting('app_secret');
                if ($clinicSecret && $this->provider->verifyWebhookSignature($rawPayload, $signature, $clinicSecret)) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid && !empty($appSecret)) {
                Log::warning("WhatsApp webhook signature verification failed for connection secret.");
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        // 4. Delegate to application service for tenant resolution and idempotent processing
        $result = $this->whatsAppService->handleIncomingWebhook($payload, $rawPayload, $signature);

        return response()->json([
            'status' => 'EVENT_RECEIVED',
            'details' => $result,
        ], 200);
    }
}
