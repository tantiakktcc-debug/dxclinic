<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageDelivery extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_message_deliveries';

    protected $fillable = [
        'clinic_id',
        'appointment_id',
        'recipient_phone',
        'message_type',
        'status',
        'attempt_count',
        'max_attempts',
        'last_error',
        'provider_message_id',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'max_attempts' => 'integer',
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
