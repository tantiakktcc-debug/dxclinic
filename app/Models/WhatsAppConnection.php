<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class WhatsAppConnection extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_connections';


    protected $fillable = [
        'clinic_id',
        'provider',
        'provider_account_id',
        'business_account_id',
        'phone_number_id',
        'display_phone_number',
        'business_display_name',
        'status',
        'connection_type',
        'ownership_type',
        'access_token_encrypted',
        'token_expires_at',
        'webhook_verified_at',
        'webhook_verify_token',
        'settings',
        'connected_at',
        'disconnected_at',
        'last_webhook_at',
        'last_message_at',
        'last_successful_send_at',
        // Baileys-specific (development provider only)
        'baileys_qr_payload',
        'baileys_phone_number',
        'qr_generated_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'token_expires_at' => 'datetime',
        'webhook_verified_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'last_webhook_at' => 'datetime',
        'last_message_at' => 'datetime',
        'last_successful_send_at' => 'datetime',
        'qr_generated_at' => 'datetime',
    ];

    /**
     * Fields that must NEVER be serialized into API responses
     */
    protected $hidden = [
        'access_token_encrypted',
        'access_token',
        'baileys_qr_payload', // Ephemeral; served only through Livewire authenticated session
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class);
    }

    /**
     * Store access token securely encrypted
     */
    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token_encrypted'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Retrieve decrypted access token
     */
    public function getAccessTokenAttribute(): ?string
    {
        if (empty($this->attributes['access_token_encrypted'])) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['access_token_encrypted']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isConnected(): bool
    {
        return $this->status === 'CONNECTED';
    }

    public function isBaileysProvider(): bool
    {
        return strtolower($this->provider ?? '') === 'baileys';
    }

    public function isMetaProvider(): bool
    {
        return strtolower($this->provider ?? 'meta') === 'meta';
    }

    /**
     * Live verified only applies to Meta Cloud API.
     */
    public function isLiveVerified(): bool
    {
        if ($this->isBaileysProvider()) {
            return false; // Baileys is dev-only; no "live verified" concept
        }

        return $this->status === 'CONNECTED'
            && (bool) $this->getSetting('meta_verified', false)
            && (bool) $this->getSetting('phone_verified', false)
            && (bool) $this->getSetting('live_traffic_verified', false);
    }

    /**
     * Provider-aware status label.
     *
     * Baileys statuses: NOT_CONNECTED, CONNECTING, QR_REQUIRED, CONNECTED, DISCONNECTED, RECONNECTING, ERROR
     * Meta statuses:    NOT CONNECTED, CONFIGURED — LIVE META VERIFICATION PENDING, META CONNECTED — LIVE VERIFIED
     *
     * IMPORTANT: Never label a Baileys connection as 'META CONNECTED'.
     */
    public function getStatusLabelAttribute(): string
    {
        // Baileys development provider labels
        if ($this->isBaileysProvider()) {
            return match ($this->status) {
                'CONNECTED'    => 'BAILEYS — DEVELOPMENT CONNECTED',
                'QR_REQUIRED'  => 'BAILEYS — SCAN QR TO CONNECT',
                'CONNECTING'   => 'BAILEYS — CONNECTING...',
                'RECONNECTING' => 'BAILEYS — RECONNECTING...',
                'DISCONNECTED' => 'BAILEYS — DISCONNECTED',
                'ERROR'        => 'BAILEYS — CONNECTION ERROR',
                default        => 'BAILEYS — NOT CONNECTED',
            };
        }

        // Meta Cloud API provider labels (unchanged)
        if ($this->status === 'DISCONNECTED') {
            return 'DISCONNECTED';
        }
        if ($this->status === 'ERROR') {
            return 'ERROR — META CONNECTION FAILED';
        }
        if ($this->isLiveVerified()) {
            return 'META CONNECTED — LIVE VERIFIED';
        }
        if ($this->status === 'CONNECTED' || !empty($this->phone_number_id)) {
            return 'CONFIGURED — LIVE META VERIFICATION PENDING';
        }
        return 'NOT CONNECTED';
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? $default;
    }

    /**
     * Masked display phone for staff UI (Section 6, Phase D)
     */
    public function getMaskedPhoneAttribute(): string
    {
        $phone = $this->display_phone_number ?: '';
        if (strlen($phone) <= 4) {
            return '••••';
        }
        return substr($phone, 0, 4) . ' ••• ' . substr($phone, -4);
    }

    /**
     * Masked Business Account ID
     */
    public function getMaskedBusinessAccountIdAttribute(): string
    {
        $id = $this->business_account_id ?: '';
        if (strlen($id) <= 6) {
            return '••••';
        }
        return substr($id, 0, 4) . '••••' . substr($id, -4);
    }

    /**
     * Masked Phone Number ID
     */
    public function getMaskedPhoneNumberIdAttribute(): string
    {
        $id = $this->phone_number_id ?: '';
        if (strlen($id) <= 6) {
            return '••••';
        }
        return substr($id, 0, 4) . '••••' . substr($id, -4);
    }
}
