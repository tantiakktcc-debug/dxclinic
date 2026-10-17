<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Infrastructure\BaileysWhatsAppProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BaileysConnectionService
 *
 * Manages the lifecycle of the Baileys development WhatsApp provider connection.
 *
 * AUTHORITATIVE RULES:
 * - clinic_id is ALWAYS sourced from Auth::user()->clinic_id — NEVER from request payload
 * - QR payload is stored in DB temporarily; cleared after successful connection
 * - Connection state is persisted in whatsapp_connections (provider='baileys')
 * - Gateway calls are server-side only; no gateway credentials leak to browser/JS
 * - This service does NOT implement patient, appointment, token, or billing logic
 */
class BaileysConnectionService
{
    // QR expiry in seconds (5 minutes — Baileys default)
    private const QR_EXPIRY_SECONDS = 300;

    public function __construct(
        protected BaileysWhatsAppProvider $gateway
    ) {}

    // =========================================================================
    // Connection Lifecycle
    // =========================================================================

    /**
     * Initiate Baileys connection for the authenticated clinic.
     * Creates/updates a whatsapp_connection record with provider='baileys'.
     * Requests QR from gateway and stores ephemeral QR payload in DB.
     *
     * @param Clinic $clinic Authoritative from Auth::user()->clinic_id
     * @return array ['status' => 'QR_REQUIRED'|'CONNECTED'|'ERROR', 'qr' => ..., 'message' => ...]
     */
    public function initiateConnection(Clinic $clinic): array
    {
        return DB::transaction(function () use ($clinic) {
            $connection = $this->getOrCreateBaileysConnection($clinic);

            // Check if Baileys gateway actually has an active connected session for this clinic
            $gatewayStatusResult = $this->gateway->getConnectionStatus($clinic->id);
            if (($gatewayStatusResult['status'] ?? '') === 'CONNECTED') {
                $connection->update([
                    'provider'             => 'baileys',
                    'status'               => 'CONNECTED',
                    'baileys_phone_number' => $gatewayStatusResult['phone'] ?? $connection->baileys_phone_number,
                    'connected_at'         => $connection->connected_at ?? now(),
                ]);
                return [
                    'status'  => 'CONNECTED',
                    'message' => 'BAILEYS — DEVELOPMENT CONNECTED',
                    'phone'   => $connection->baileys_phone_number,
                ];
            }

            // If gateway already has an active, unexpired QR ready, use it immediately
            if (($gatewayStatusResult['status'] ?? '') === 'QR_REQUIRED' && !empty($gatewayStatusResult['qr'])) {
                $qrData = $gatewayStatusResult['qr'];
                $connection->update([
                    'provider'           => 'baileys',
                    'status'             => 'QR_REQUIRED',
                    'baileys_qr_payload' => encrypt($qrData),
                    'qr_generated_at'    => now(),
                ]);
                return [
                    'status'  => 'QR_REQUIRED',
                    'message' => 'Scan the QR code with WhatsApp to connect.',
                    'qr'      => $qrData,
                ];
            }

            // Mark as CONNECTING with provider 'baileys'
            $connection->update([
                'provider'           => 'baileys',
                'status'             => 'CONNECTING',
                'baileys_qr_payload' => null,
                'qr_generated_at'    => null,
            ]);

            // Request QR from gateway (server-to-server, signed)
            $gatewayResult = $this->gateway->requestQr($clinic->id);

            if (!($gatewayResult['success'] ?? false)) {
                $connection->update(['status' => 'ERROR']);
                $this->audit($clinic, 'BAILEYS_CONNECT_FAILED', [
                    'error' => $gatewayResult['error'] ?? 'GATEWAY_UNREACHABLE',
                ]);
                return [
                    'status'  => 'ERROR',
                    'message' => $gatewayResult['message'] ?? 'Baileys gateway is unreachable.',
                ];
            }

            $gatewayStatus = $gatewayResult['status'] ?? 'QR_REQUIRED';

            if ($gatewayStatus === 'CONNECTED') {
                // Already paired (session restored)
                $connection->update([
                    'status'               => 'CONNECTED',
                    'baileys_phone_number' => $gatewayResult['phone'] ?? null,
                    'display_phone_number' => $gatewayResult['phone'] ?? $connection->display_phone_number,
                    'baileys_qr_payload'   => null,
                    'connected_at'         => now(),
                    'disconnected_at'      => null,
                ]);

                $this->audit($clinic, 'BAILEYS_CONNECTED_SESSION_RESTORED', [
                    'phone' => $gatewayResult['phone'] ?? 'unknown',
                ]);

                return [
                    'status'  => 'CONNECTED',
                    'message' => 'BAILEYS — DEVELOPMENT CONNECTED (Session Restored)',
                    'phone'   => $gatewayResult['phone'] ?? null,
                ];
            }

            // QR required — store ephemeral QR payload (never logged, never returned to browser as plaintext)
            $qrData = $gatewayResult['qr'] ?? null;
            $connection->update([
                'status'            => 'QR_REQUIRED',
                'baileys_qr_payload' => $qrData ? encrypt($qrData) : null,
                'qr_generated_at'   => now(),
            ]);

            $this->audit($clinic, 'BAILEYS_QR_GENERATED', [
                'clinic_id' => $clinic->id,
            ]);

            return [
                'status'  => 'QR_REQUIRED',
                'message' => 'Scan the QR code with WhatsApp to connect.',
                'qr'      => $qrData, // Returned to Livewire for display in authenticated session only
            ];
        });
    }

    /**
     * Poll gateway for current connection state.
     * Called by the Livewire component to update UI.
     */
    public function pollStatus(Clinic $clinic): array
    {
        $connection = WhatsAppConnection::where('clinic_id', $clinic->id)
            ->where('provider', 'baileys')
            ->first();

        if (!$connection) {
            return ['status' => 'NOT_CONNECTED', 'message' => 'No Baileys connection configured.'];
        }

        // Check QR expiry
        if ($connection->status === 'QR_REQUIRED' && $connection->qr_generated_at) {
            $age = now()->diffInSeconds($connection->qr_generated_at);
            if ($age > self::QR_EXPIRY_SECONDS) {
                $connection->update([
                    'status'            => 'DISCONNECTED',
                    'baileys_qr_payload' => null, // Clear expired QR
                    'qr_generated_at'   => null,
                ]);
                return ['status' => 'QR_EXPIRED', 'message' => 'QR code has expired. Please reconnect.'];
            }
        }

        // Query live gateway status
        $gatewayResult = $this->gateway->getConnectionStatus($clinic->id);

        if (!($gatewayResult['success'] ?? false)) {
            // Gateway unreachable — reflect last known DB state with a DISCONNECTED signal
            if (in_array($connection->status, ['CONNECTED', 'CONNECTING', 'QR_REQUIRED', 'RECONNECTING'])) {
                $connection->update(['status' => 'DISCONNECTED']);
            }
            return [
                'status'  => 'GATEWAY_UNREACHABLE',
                'message' => 'Baileys gateway is not running. Start the gateway to reconnect.',
            ];
        }

        $gatewayStatus = $gatewayResult['status'] ?? 'DISCONNECTED';

        // Sync DB with authoritative gateway state
        $this->syncConnectionState($connection, $gatewayStatus, $gatewayResult);

        $qr = null;
        if ($gatewayStatus === 'QR_REQUIRED' && !empty($connection->fresh()->baileys_qr_payload)) {
            try {
                $qr = decrypt($connection->fresh()->baileys_qr_payload);
            } catch (\Throwable $e) {
                $qr = null;
            }
        }

        return [
            'status'  => $gatewayStatus,
            'message' => $this->statusLabel($gatewayStatus, $gatewayResult),
            'phone'   => $gatewayResult['phone'] ?? $connection->fresh()->baileys_phone_number,
            'qr'      => $qr,
        ];
    }

    /**
     * Disconnect the Baileys connection for the clinic.
     */
    public function disconnect(Clinic $clinic): array
    {
        $connection = WhatsAppConnection::where('clinic_id', $clinic->id)
            ->where('provider', 'baileys')
            ->first();

        if ($connection) {
            $connection->update([
                'status'            => 'DISCONNECTED',
                'disconnected_at'   => now(),
                'baileys_qr_payload' => null,
                'qr_generated_at'   => null,
            ]);
        }

        // Signal gateway to terminate session
        $this->gateway->disconnect($clinic->id);

        $this->audit($clinic, 'BAILEYS_DISCONNECTED', []);

        return ['status' => 'DISCONNECTED', 'message' => 'Baileys development connection disconnected.'];
    }

    /**
     * Called by the Baileys gateway webhook when connection state changes.
     * SECURITY: clinic_id resolved from gateway-signed payload, NOT from request params.
     */
    public function handleGatewayStateChange(int $clinicId, string $state, array $payload): void
    {
        $clinic = Clinic::find($clinicId);
        if (!$clinic) {
            Log::warning("Baileys state change for non-existent clinic: {$clinicId}");
            return;
        }

        $connection = $this->getOrCreateBaileysConnection($clinic);
        $this->syncConnectionState($connection, $state, $payload);

        // When QR refreshes from gateway, update stored QR (encrypted)
        if ($state === 'QR_REQUIRED' && !empty($payload['qr'])) {
            $connection->update([
                'baileys_qr_payload' => encrypt($payload['qr']),
                'qr_generated_at'   => now(),
            ]);
        }

        // When connected, clear QR and record phone number
        if ($state === 'CONNECTED') {
            $connection->update([
                'baileys_qr_payload'   => null,
                'qr_generated_at'      => null,
                'baileys_phone_number' => $payload['phone'] ?? $connection->baileys_phone_number,
                'display_phone_number' => $payload['phone'] ?? $connection->display_phone_number,
                'connected_at'         => now(),
                'disconnected_at'      => null,
            ]);

            $this->audit(
                $connection->clinic,
                'BAILEYS_CONNECTED',
                ['phone' => $payload['phone'] ?? 'unknown']
            );
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function getOrCreateBaileysConnection(Clinic $clinic): WhatsAppConnection
    {
        return WhatsAppConnection::firstOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'provider'       => 'baileys',
                'status'         => 'PENDING',
                'connection_type' => 'BAILEYS_DEV',
                'ownership_type' => 'CLINIC_OWNED',
                'webhook_verify_token' => config('dxclinic.whatsapp.baileys.webhook_verify_token',
                    'baileys_dev_verify_' . hash('sha256', $clinic->id . config('app.key'))),
            ]
        );
    }

    private function syncConnectionState(WhatsAppConnection $connection, string $state, array $payload): void
    {
        $allowed = ['PENDING', 'CONNECTING', 'QR_REQUIRED', 'CONNECTED', 'DISCONNECTED', 'RECONNECTING', 'ERROR'];
        if (!in_array($state, $allowed)) {
            $state = 'ERROR';
        }

        $updates = ['status' => $state];

        if ($state === 'CONNECTED') {
            $updates['connected_at']    = $connection->connected_at ?? now();
            $updates['disconnected_at'] = null;
        } elseif (in_array($state, ['DISCONNECTED', 'ERROR'])) {
            $updates['disconnected_at'] = $connection->disconnected_at ?? now();
        }

        $connection->update($updates);
    }

    private function statusLabel(string $state, array $payload): string
    {
        return match ($state) {
            'CONNECTED'    => 'BAILEYS — DEVELOPMENT CONNECTED',
            'QR_REQUIRED'  => 'QR Code Ready — Scan with WhatsApp',
            'CONNECTING'   => 'Connecting to WhatsApp...',
            'RECONNECTING' => 'Reconnecting...',
            'DISCONNECTED' => 'Disconnected',
            'ERROR'        => 'Connection Error: ' . ($payload['message'] ?? 'Unknown error'),
            default        => strtoupper($state),
        };
    }

    private function audit(Clinic $clinic, string $action, array $data): void
    {
        try {
            AuditLog::create([
                'clinic_id'      => $clinic->id,
                'user_id'        => Auth::id(),
                'action'         => $action,
                'entity_type'    => WhatsAppConnection::class,
                'new_values'     => $data,
                'ip_address'     => request()?->ip(),
                'reason'         => "Baileys development provider: {$action}",
                'change_category' => 'INTEGRATION',
            ]);
        } catch (\Throwable $e) {
            Log::error("Audit log failed for {$action}: " . $e->getMessage());
        }
    }
}
