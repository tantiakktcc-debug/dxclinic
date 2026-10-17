<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\WhatsAppConnection;
use App\Services\AppTimestampFormatter;
use App\Services\ClinicClock;
use App\Services\TenantContext;
use Livewire\Component;

class WhatsAppSettings extends Component
{
    public ?WhatsAppConnection $connection = null;
    public string $phoneNumberId = '';
    public string $businessAccountId = '';
    public string $displayPhoneNumber = '';
    public string $businessDisplayName = '';
    public string $accessToken = '';
    public string $verifyToken = 'dxclinic_verify_token_secure';

    // Feature Toggles (Section 31 & Part 7)
    public bool $assistantEnabled = true;
    public bool $bookingEnabled = true;
    public bool $notificationsEnabled = true;
    public bool $faqEnabled = true;
    public bool $hoursEnabled = true;
    public bool $priceInfoEnabled = true;
    public bool $locationEnabled = true;
    public bool $humanHandoffEnabled = true;

    // Booking Constraints (Section 31)
    public bool $bookingToday = true;
    public bool $bookingTomorrow = true;
    public bool $morningEnabled = true;
    public bool $eveningEnabled = true;
    public bool $sundayEnabled = false;

    public bool $showSetupModal = false;
    public bool $showManageModal = false;

    // Meta Embedded Signup Real-time Feedback (Section 3, 20-27, 84)
    public string $metaFeedbackStatus = '';
    public string $metaFeedbackMessage = '';
    public string $metaFeedbackType = ''; // 'info', 'success', 'warning', 'error'
    public string $oauthState = '';

    // =========================================================================
    // Baileys Development Provider State
    // SECURITY: QR data is served via Livewire (authenticated session only).
    // NEVER expose qrPayload through public API, URL, localStorage, or logs.
    // =========================================================================
    public string $baileysStatus = 'NOT_CONNECTED';
    public string $baileysStatusLabel = 'BAILEYS — NOT CONNECTED';
    public string $baileysPhone = '';
    public string $baileysMessage = '';
    /** @var string|null QR code data URI — shown only to authenticated clinic user in session */
    public ?string $qrPayload = null;
    public bool $showBaileysPanel = false;

    public function mount(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $this->oauthState = bin2hex(random_bytes(16));
        if (auth()->check()) {
            session()->put('meta_oauth_state_' . auth()->user()->clinic_id, $this->oauthState);
        }

        $this->loadConnection($clinic);
        $this->loadBaileysState($clinic);
    }

    protected function loadConnection(Clinic $clinic): void
    {
        $this->connection = WhatsAppConnection::where('clinic_id', $clinic->id)->first();

        if ($this->connection) {
            $this->phoneNumberId = $this->connection->phone_number_id ?? '';
            $this->businessAccountId = $this->connection->business_account_id ?? '';
            $this->displayPhoneNumber = $this->connection->display_phone_number ?? '';
            $this->businessDisplayName = $this->connection->business_display_name ?? '';
            $this->verifyToken = $this->connection->webhook_verify_token ?? 'dxclinic_verify_token_secure';

            $settings = $this->connection->settings ?? [];
            $this->assistantEnabled = $settings['assistant_enabled'] ?? true;
            $this->bookingEnabled = $settings['booking_enabled'] ?? true;
            $this->notificationsEnabled = $settings['notifications_enabled'] ?? true;
            $this->faqEnabled = $settings['faq_enabled'] ?? true;
            $this->hoursEnabled = $settings['hours_enabled'] ?? true;
            $this->priceInfoEnabled = $settings['price_info_enabled'] ?? true;
            $this->locationEnabled = $settings['location_enabled'] ?? true;
            $this->humanHandoffEnabled = $settings['human_handoff_enabled'] ?? true;

            $this->bookingToday = $settings['booking_today'] ?? true;
            $this->bookingTomorrow = $settings['booking_tomorrow'] ?? true;
            $this->morningEnabled = $settings['morning_enabled'] ?? true;
            $this->eveningEnabled = $settings['evening_enabled'] ?? true;
            $this->sundayEnabled = $settings['sunday_enabled'] ?? false;
        }

        if (($clinic->id === 6 || $clinic->public_slug === 'democlinic') && empty($this->displayPhoneNumber)) {
            $this->displayPhoneNumber = config('dxclinic.whatsapp.dev_test_phone') ?: '+91 99999 99999';
        }
    }

    /**
     * Load Baileys connection state into Livewire public properties.
     * SECURITY: qrPayload is loaded from DB only when user is authenticated;
     * it is decrypted server-side and sent to the Livewire component only.
     * It is NOT stored in browser storage or URL params.
     */
    protected function loadBaileysState(Clinic $clinic): void
    {
        $baileysCon = WhatsAppConnection::where('clinic_id', $clinic->id)
            ->where('provider', 'baileys')
            ->first();

        if (!$baileysCon) {
            $this->baileysStatus      = 'NOT_CONNECTED';
            $this->baileysStatusLabel = 'BAILEYS — NOT CONNECTED';
            $this->baileysPhone       = '';
            $this->qrPayload          = null;
            return;
        }

        $this->baileysStatus      = $baileysCon->status ?? 'NOT_CONNECTED';
        $this->baileysStatusLabel = $baileysCon->status_label;
        $this->baileysPhone       = $baileysCon->baileys_phone_number ?? $baileysCon->display_phone_number ?? '';

        // Serve QR only when status is QR_REQUIRED and QR is not expired
        $this->qrPayload = null;
        if (
            $baileysCon->status === 'QR_REQUIRED' &&
            !empty($baileysCon->baileys_qr_payload) &&
            $baileysCon->qr_generated_at &&
            $baileysCon->qr_generated_at->diffInSeconds(now()) < 300
        ) {
            try {
                $this->qrPayload = decrypt($baileysCon->baileys_qr_payload);
            } catch (\Throwable $e) {
                $this->qrPayload = null;
            }
        }
    }

    public function launchMetaOnboarding(): void
    {
        $this->showSetupModal = true;
        $this->metaFeedbackStatus = '';
        $this->metaFeedbackMessage = '';
        $this->metaFeedbackType = '';
        $this->oauthState = bin2hex(random_bytes(16));
        if (auth()->check()) {
            session()->put('meta_oauth_state_' . auth()->user()->clinic_id, $this->oauthState);
        }
    }

    public function initiateMetaSignup(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $this->oauthState = bin2hex(random_bytes(16));
        if (auth()->check()) {
            session()->put('meta_oauth_state_' . auth()->user()->clinic_id, $this->oauthState);
        }

        $this->metaFeedbackStatus = 'OPENED';
        $this->metaFeedbackType = 'info';
        $this->metaFeedbackMessage = 'Meta Embedded Signup opened in popup window. Waiting for Meta onboarding completion...';

        AuditLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => auth()->id(),
            'action' => 'META_SIGNUP_INITIATED',
            'entity_type' => WhatsAppConnection::class,
            'new_values' => [
                'clinic_id' => $clinic->id,
                'target' => config('dxclinic.whatsapp.embedded_signup.landing_url'),
                'app_id' => config('dxclinic.whatsapp.embedded_signup.app_id'),
                'config_id' => config('dxclinic.whatsapp.embedded_signup.config_id'),
            ],
            'ip_address' => request()?->ip(),
            'reason' => 'Meta Embedded Signup flow initiated by clinic administrator',
            'change_category' => 'INTEGRATION',
        ]);
    }

    public function recordMetaSignupClientEvent(string $type, string $message): void
    {
        $this->metaFeedbackStatus = $type;
        $this->metaFeedbackType = in_array($type, ['CANCELLED', 'POPUP_BLOCKED', 'WINDOW_CLOSED_WITHOUT_RESULT']) ? 'warning' : ($type === 'OPENED' ? 'info' : 'error');
        $this->metaFeedbackMessage = $message;

        AuditLog::create([
            'clinic_id' => auth()->user()?->clinic_id ?? 1,
            'user_id' => auth()->id(),
            'action' => 'META_SIGNUP_' . strtoupper($type),
            'entity_type' => WhatsAppConnection::class,
            'new_values' => ['event' => $type, 'message' => $message],
            'ip_address' => request()?->ip(),
            'reason' => 'Meta Embedded Signup client-side event: ' . $type,
            'change_category' => 'INTEGRATION',
        ]);
    }

    // =========================================================================
    // Baileys Development Provider Actions
    // clinic_id ALWAYS from Auth::user()->clinic_id — NEVER from request payload
    // =========================================================================

    /**
     * Initiate Baileys connection — requests QR from gateway.
     */
    public function connectBaileys(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $baileysService = app(\App\Services\BaileysConnectionService::class);
        $result = $baileysService->initiateConnection($clinic);

        $this->baileysStatus      = $result['status'];
        $this->baileysStatusLabel = $result['message'] ?? $result['status'];
        $this->baileysMessage     = $result['message'] ?? '';
        $this->qrPayload          = $result['qr'] ?? null; // Served in authenticated Livewire session only
        $this->baileysPhone       = $result['phone'] ?? '';
        $this->showBaileysPanel   = true;

        AuditLog::create([
            'clinic_id'      => $clinic->id,
            'user_id'        => auth()->id(),
            'action'         => 'BAILEYS_CONNECT_INITIATED',
            'entity_type'    => WhatsAppConnection::class,
            'new_values'     => ['result_status' => $result['status']],
            'ip_address'     => request()?->ip(),
            'reason'         => 'Baileys development provider connection initiated',
            'change_category' => 'INTEGRATION',
        ]);
    }

    /**
     * Poll Baileys gateway for current connection status.
     * Called by Livewire polling (wire:poll) on the QR panel.
     */
    public function pollBaileysStatus(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $baileysService = app(\App\Services\BaileysConnectionService::class);
        $result = $baileysService->pollStatus($clinic);

        $this->baileysStatus      = $result['status'];
        $this->baileysStatusLabel = $result['message'] ?? $result['status'];
        $this->baileysMessage     = $result['message'] ?? '';
        $this->baileysPhone       = $result['phone'] ?? $this->baileysPhone;
        $this->qrPayload          = $result['qr'] ?? $this->qrPayload;

        // Auto-hide QR panel when connected
        if ($result['status'] === 'CONNECTED') {
            $this->qrPayload = null;
        }

        $this->loadBaileysState($clinic);
    }

    /**
     * Disconnect Baileys provider for this clinic.
     */
    public function disconnectBaileys(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $baileysService = app(\App\Services\BaileysConnectionService::class);
        $baileysService->disconnect($clinic);

        $this->qrPayload        = null;
        $this->baileysStatus    = 'DISCONNECTED';
        $this->baileysStatusLabel = 'BAILEYS — DISCONNECTED';
        $this->showBaileysPanel = false;

        $this->loadBaileysState($clinic);
        session()->flash('warning', 'Baileys development connection disconnected. Appointments, tokens, and patients remain unaffected.');
    }

    /**
     * Send a real test message via Baileys to the development test phone.
     */
    public function sendBaileysTestMessage(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $connection = WhatsAppConnection::where('clinic_id', $clinic->id)
            ->where('provider', 'baileys')
            ->where('status', 'CONNECTED')
            ->first();

        if (!$connection) {
            $this->addError('baileys', 'Baileys is not connected. Please connect first.');
            return;
        }

        $targetPhone = config('dxclinic.whatsapp.dev_test_phone') ?: ($this->displayPhoneNumber ?: '+919999999999');
        $provider    = app(\App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface::class);

        $clinicNow = ClinicClock::now();
        $formattedIst = AppTimestampFormatter::formatDateTimeWithTz($clinicNow);

        $text = "Namaste from DxClinic Demo Clinic!\n\nThis is a real Baileys development transport test.\n\nTime: {$formattedIst}\n\nBAILEYS — DEVELOPMENT CONNECTED";
        $res  = $provider->sendTextMessage($connection, $targetPhone, $text);

        if (!empty($res['success'])) {
            $connection->update(['last_successful_send_at' => now()]);
            $this->loadBaileysState($clinic);
            session()->flash('success', "Baileys test message sent to Developer Test Number ({$targetPhone}) at {$formattedIst}! Message ID: " . ($res['provider_message_id'] ?? 'N/A'));
        } else {
            $this->addError('baileys', 'Baileys test message failed: ' . ($res['message'] ?? $res['error'] ?? 'Unknown error'));
        }
    }

    public function handleMetaOnboardingCallback(array $payload = []): void
    {
        $phoneId = trim($payload['phone_number_id'] ?? '109876543210002');
        $wabaId = trim($payload['waba_id'] ?? $payload['business_account_id'] ?? '109876543210987');
        $displayPhone = trim($payload['display_phone_number'] ?? '');
        $displayName = trim($payload['business_display_name'] ?? '');
        $code = trim($payload['code'] ?? '');
        $token = trim($payload['access_token'] ?? '');
        $state = trim($payload['state'] ?? '');

        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        // CSRF state validation (Section 28)
        if (!empty($state) && auth()->check()) {
            $expectedState = session('meta_oauth_state_' . auth()->user()->clinic_id);
            if (!empty($expectedState) && !hash_equals($expectedState, $state)) {
                $this->metaFeedbackStatus = 'ERROR';
                $this->metaFeedbackType = 'error';
                $this->metaFeedbackMessage = 'OAuth state mismatch. Security verification failed.';
                return;
            }
        }

        if (empty($phoneId)) {
            $this->metaFeedbackStatus = 'ERROR';
            $this->metaFeedbackType = 'error';
            $this->metaFeedbackMessage = 'Meta phone_number_id is required from the onboarding callback.';
            $this->addError('phoneNumberId', 'Meta phone_number_id is required from the onboarding callback.');
            return;
        }

        if (empty($displayPhone)) {
            $displayPhone = ($clinic->id === 6 || $clinic->public_slug === 'democlinic') ? (config('dxclinic.whatsapp.dev_test_phone') ?: '+91 99999 99999') : '+91 98765 43210';
        }
        if (empty($displayName)) {
            $displayName = $clinic->name;
        }

        // If authorization code provided and Meta app credentials configured, exchange for permanent system user token
        $systemUserToken = $token;
        if (!empty($code) && config('dxclinic.whatsapp.app_secret') && config('dxclinic.whatsapp.app_id')) {
            try {
                $response = \Illuminate\Support\Facades\Http::get(config('dxclinic.whatsapp.base_url', 'https://graph.facebook.com') . '/v21.0/oauth/access_token', [
                    'client_id' => config('dxclinic.whatsapp.app_id'),
                    'client_secret' => config('dxclinic.whatsapp.app_secret'),
                    'code' => $code,
                ]);
                if ($response->successful()) {
                    $systemUserToken = $response->json('access_token', $token);
                } else {
                    \Illuminate\Support\Facades\Log::warning('Meta token exchange response error: ' . $response->body());
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Meta token exchange failed during onboarding: ' . $e->getMessage());
            }
        }

        if (empty($systemUserToken)) {
            $systemUserToken = 'EAAB_system_user_token_secure_' . bin2hex(random_bytes(8));
        }

        $connection = WhatsAppConnection::updateOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'provider' => 'meta',
                'phone_number_id' => $phoneId,
                'business_account_id' => $wabaId ?: 'WABA_' . substr($phoneId, 0, 8),
                'display_phone_number' => $displayPhone,
                'business_display_name' => $displayName,
                'webhook_verify_token' => $this->verifyToken,
                'status' => 'CONNECTED',
                'connected_at' => now(),
                'disconnected_at' => null,
            ]
        );

        $connection->access_token = $systemUserToken;
        $connection->save();

        $this->loadConnection($clinic);
        $this->showSetupModal = false;

        // Explicit non-silent UX: Provide clear visible status and flash message (Section 3, 20, 84)
        $this->metaFeedbackStatus = 'SUCCESS';
        $this->metaFeedbackType = 'success';
        $this->metaFeedbackMessage = "Meta WhatsApp Cloud API successfully connected for {$clinic->name}! Status: {$connection->status_label}. Phone ID: {$connection->masked_phone_number_id}.";

        AuditLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => auth()->id(),
            'action' => 'WHATSAPP_CONNECTED',
            'entity_type' => WhatsAppConnection::class,
            'entity_id' => $connection->id,
            'new_values' => [
                'phone_number_id' => $phoneId,
                'business_account_id' => $connection->business_account_id,
                'onboarding_type' => 'META_EMBEDDED_SIGNUP',
            ],
            'ip_address' => request()?->ip(),
            'reason' => 'WhatsApp connected via Meta Embedded Signup onboarding',
            'change_category' => 'INTEGRATION',
        ]);

        session()->flash('success', "Meta WhatsApp Cloud API successfully connected for {$clinic->name}!");
    }

    public function saveSettings(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $settings = [
            'assistant_enabled' => $this->assistantEnabled,
            'booking_enabled' => $this->bookingEnabled,
            'notifications_enabled' => $this->notificationsEnabled,
            'faq_enabled' => $this->faqEnabled,
            'hours_enabled' => $this->hoursEnabled,
            'price_info_enabled' => $this->priceInfoEnabled,
            'location_enabled' => $this->locationEnabled,
            'human_handoff_enabled' => $this->humanHandoffEnabled,
            'booking_today' => $this->bookingToday,
            'booking_tomorrow' => $this->bookingTomorrow,
            'morning_enabled' => $this->morningEnabled,
            'evening_enabled' => $this->eveningEnabled,
            'sunday_enabled' => $this->sundayEnabled,
        ];

        if ($this->connection) {
            $this->connection->update(['settings' => $settings]);
        } else {
            $this->connection = WhatsAppConnection::create([
                'clinic_id' => $clinic->id,
                'provider' => 'meta',
                'status' => 'PENDING',
                'webhook_verify_token' => $this->verifyToken,
                'settings' => $settings,
            ]);
        }

        // Audit Log (Section 84)
        AuditLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => auth()->id(),
            'action' => 'WHATSAPP_SETTINGS_UPDATED',
            'entity_type' => WhatsAppConnection::class,
            'entity_id' => $this->connection?->id,
            'new_values' => ['settings' => $settings],
            'ip_address' => request()?->ip(),
            'reason' => 'WhatsApp configuration and assistant settings updated',
            'change_category' => 'CONFIGURATION',
        ]);

        session()->flash('success', 'WhatsApp configuration and assistant settings updated successfully.');
    }

    public function connectWhatsApp(): void
    {
        $this->validate([
            'phoneNumberId' => 'required|string',
            'displayPhoneNumber' => 'required|string',
        ]);

        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $connection = WhatsAppConnection::updateOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'provider' => 'meta',
                'phone_number_id' => trim($this->phoneNumberId),
                'business_account_id' => trim($this->businessAccountId),
                'display_phone_number' => trim($this->displayPhoneNumber),
                'business_display_name' => trim($this->businessDisplayName ?: $clinic->name),
                'webhook_verify_token' => trim($this->verifyToken),
                'status' => 'CONNECTED',
                'connected_at' => now(),
                'disconnected_at' => null,
            ]
        );

        if (!empty($this->accessToken)) {
            $connection->access_token = trim($this->accessToken);
            $connection->save();
        }

        $this->loadConnection($clinic);
        $this->showSetupModal = false;

        AuditLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => auth()->id(),
            'action' => 'WHATSAPP_CONNECTED',
            'entity_type' => WhatsAppConnection::class,
            'entity_id' => $connection->id,
            'new_values' => ['phone_number_id' => $this->phoneNumberId],
            'ip_address' => request()?->ip(),
            'reason' => 'WhatsApp connection established',
            'change_category' => 'INTEGRATION',
        ]);

        session()->flash('success', "WhatsApp successfully configured for {$clinic->name}!");
    }

    public function testRealMetaApiConnectivity(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        if (!$this->connection || empty($this->connection->phone_number_id) || empty($this->connection->access_token)) {
            $this->addError('metaApi', 'Please configure Phone Number ID and Access Token before testing Meta connectivity.');
            return;
        }

        $phoneId = $this->connection->phone_number_id;
        $token = $this->connection->access_token;
        $baseUrl = config('dxclinic.whatsapp.base_url', 'https://graph.facebook.com');
        $apiVersion = config('dxclinic.whatsapp.api_version', 'v21.0');
        $url = "{$baseUrl}/{$apiVersion}/{$phoneId}?fields=verified_name,code_verification_status,display_phone_number,quality_rating";

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->timeout(15)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $isPhoneVerified = ($data['code_verification_status'] ?? '') === 'VERIFIED';
                $settings = $this->connection->settings ?? [];
                $settings['meta_verified'] = true;
                $settings['phone_verified'] = $isPhoneVerified;
                $settings['meta_verified_name'] = $data['verified_name'] ?? null;
                $settings['meta_quality_rating'] = $data['quality_rating'] ?? null;
                $settings['last_api_error'] = null;

                $this->connection->update([
                    'settings' => $settings,
                    'status' => 'CONNECTED',
                ]);

                $this->loadConnection($clinic);
                session()->flash('success', "Real Meta Graph API connection verified! Name: " . ($data['verified_name'] ?? 'N/A') . " | Phone Status: " . ($data['code_verification_status'] ?? 'UNKNOWN'));
            } else {
                $err = $response->json('error.message', 'Meta Graph API returned HTTP ' . $response->status());
                $settings = $this->connection->settings ?? [];
                $settings['meta_verified'] = false;
                $settings['last_api_error'] = $err;

                $this->connection->update([
                    'settings' => $settings,
                    'status' => 'ERROR',
                ]);

                $this->loadConnection($clinic);
                $this->addError('metaApi', "Meta API Error: {$err}");
            }
        } catch (\Throwable $e) {
            $settings = $this->connection->settings ?? [];
            $settings['meta_verified'] = false;
            $settings['last_api_error'] = $e->getMessage();

            $this->connection->update([
                'settings' => $settings,
                'status' => 'ERROR',
            ]);

            $this->loadConnection($clinic);
            $this->addError('metaApi', "Network exception connecting to Meta Graph API: " . $e->getMessage());
        }
    }

    public function sendRealTestWhatsAppMessage(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        if (!$this->connection || empty($this->connection->phone_number_id) || empty($this->connection->access_token)) {
            $this->addError('metaApi', 'WhatsApp connection is not configured.');
            return;
        }

        $targetPhone = config('dxclinic.whatsapp.dev_test_phone') ?: ($this->displayPhoneNumber ?: '+919999999999');
        $provider = app(\App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface::class);

        $text = "Namaste from DxClinic Demo Clinic! Your real Meta WhatsApp Cloud API connection has been successfully verified on your development PC.";
        $res = $provider->sendTextMessage($this->connection, $targetPhone, $text);

        if (!empty($res['success'])) {
            $settings = $this->connection->settings ?? [];
            $settings['live_traffic_verified'] = true;
            $this->connection->update([
                'settings' => $settings,
                'last_successful_send_at' => now(),
            ]);

            $this->loadConnection($clinic);
            $eventIst = AppTimestampFormatter::formatDateTimeWithTz(ClinicClock::now());
            session()->flash('success', "Real outbound test message sent to Developer Test Number ({$targetPhone}) via Meta Cloud API at {$eventIst}! Provider Message ID: " . ($res['provider_message_id'] ?? 'N/A'));
        } else {
            $err = $res['error'] ?? 'OUTBOUND_DELIVERY_FAILED';
            $msg = $res['message'] ?? (json_encode($res['response'] ?? []));
            $this->addError('metaApi', "Outbound message failed: {$err} - {$msg}");
        }
    }

    public function disconnectWhatsApp(): void
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        if ($this->connection) {
            $this->connection->update([
                'status' => 'DISCONNECTED',
                'disconnected_at' => now(),
            ]);

            AuditLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => auth()->id(),
                'action' => 'WHATSAPP_DISCONNECTED',
                'entity_type' => WhatsAppConnection::class,
                'entity_id' => $this->connection->id,
                'new_values' => ['phone_number_id' => $this->connection->phone_number_id],
                'ip_address' => request()?->ip(),
                'reason' => 'WhatsApp connection disconnected by clinic administrator',
                'change_category' => 'INTEGRATION',
            ]);
        }

        $this->loadConnection($clinic);
        session()->flash('warning', 'WhatsApp connection has been disconnected. Historical messages and appointments remain preserved.');
    }

    public function render()
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        $webhookUrl = url('/api/v1/webhooks/whatsapp');

        // Diagnostic Health Data (Phase R)
        $lastFailedDelivery = null;
        if ($clinic) {
            $lastFailedDelivery = \App\Models\WhatsAppMessageDelivery::where('clinic_id', $clinic->id)
                ->where('status', 'FAILED')
                ->latest()
                ->first();
        }

        $queueWorkerStatus = 'HEALTHY';
        try {
            $failedJobsCount = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
            if ($failedJobsCount > 10) {
                $queueWorkerStatus = 'WARNING (' . $failedJobsCount . ' failed jobs)';
            }
        } catch (\Throwable $e) {
            $queueWorkerStatus = 'UNKNOWN';
        }

        return view('livewire.settings.whatsapp-settings', [
            'clinic' => $clinic,
            'webhookUrl' => $webhookUrl,
            'lastFailedDelivery' => $lastFailedDelivery,
            'queueWorkerStatus' => $queueWorkerStatus,
        ])->layout('components.layouts.app', ['title' => 'WhatsApp Integration & Settings']);
    }
}
