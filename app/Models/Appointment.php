<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasFactory;

    public const SOURCE_CLINIC = 'CLINIC';
    public const SOURCE_ONLINE = 'ONLINE';
    public const SOURCE_TELEPHONIC = 'TELEPHONIC';
    public const SOURCE_WHATSAPP = 'WHATSAPP';

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'doctor_id',
        'appointment_date',
        'session_type',
        'booking_source',
        'consultation_mode',
        'booking_type_id',
        'fee_amount',
        'token_number',
        'formatted_token',
        'status',
        'secure_token',
        'grace_parent_appointment_id',
        'prescription_pad_template_id',
        'prescription_template_name',
        'is_legacy_pad',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'token_number' => 'integer',
        'fee_amount' => 'decimal:2',
        'is_legacy_pad' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class);
    }

    public function consultationBill(): HasOne
    {
        return $this->hasOne(ConsultationBill::class);
    }

    public function graceParent(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'grace_parent_appointment_id');
    }

    public function graceChildren(): HasMany
    {
        return $this->hasMany(Appointment::class, 'grace_parent_appointment_id');
    }

    public function prescriptionPadTemplate(): BelongsTo
    {
        return $this->belongsTo(PrescriptionPadTemplate::class, 'prescription_pad_template_id');
    }

    public function bookingType(): BelongsTo
    {
        return $this->belongsTo(BookingType::class, 'booking_type_id');
    }

    /**
     * Resolve the effective fee code for this appointment.
     * Delegates to the BookingType if set; falls back to consultation_mode mapping.
     */
    public function getEffectiveFeeCode(): string
    {
        if ($this->bookingType) {
            return $this->bookingType->getFeeCode();
        }
        return match (strtoupper((string) $this->consultation_mode)) {
            'ONLINE' => 'CONSULTATION_ONLINE',
            'VISITING_CONSULTANCY', 'VISITING' => 'CONSULTATION_VISITING',
            default => 'CONSULTATION_PHYSICAL',
        };
    }
}
