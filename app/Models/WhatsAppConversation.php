<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'clinic_id',
        'whatsapp_connection_id',
        'wa_id',
        'current_state',
        'state_payload',
        'human_handoff_active',
        'human_handoff_requested_at',
        'last_patient_message_at',
        'last_outbound_message_at',
    ];

    protected $casts = [
        'state_payload' => 'array',
        'human_handoff_active' => 'boolean',
        'human_handoff_requested_at' => 'datetime',
        'last_patient_message_at' => 'datetime',
        'last_outbound_message_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnection::class, 'whatsapp_connection_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id');
    }

    public function transitionTo(string $newState, ?array $additionalPayload = null): void
    {
        $payload = $this->state_payload ?? [];
        if ($additionalPayload !== null) {
            $payload = array_merge($payload, $additionalPayload);
        }

        $this->update([
            'current_state' => $newState,
            'state_payload' => $payload,
        ]);
    }

    public function clearStatePayload(): void
    {
        $this->update(['state_payload' => null]);
    }
}
