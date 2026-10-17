<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsultationBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'bill_number',
        'appointment_id',
        'visit_id',
        'patient_id',
        'doctor_id',
        'bill_date',
        'gross_amount',
        'discount_amount',
        'waiver_amount',
        'net_amount',
        'consultation_mode',
        'waiver_type',
        'status',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'gross_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'waiver_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function getRefundedAmountAttribute(): float
    {
        return (float) $this->refunds()->where('status', 'PROCESSED')->sum('refund_amount');
    }

    public function getRetainedAmountAttribute(): float
    {
        $paid = (float) ($this->payment?->amount ?? ($this->status === 'PAID' ? $this->net_amount : 0));
        return max(0.00, $paid - $this->refunded_amount);
    }


    /**
     * Validate financial invariant: net = gross - discount - waiver (Section 73)
     */
    public static function validateInvariants(float $gross, float $discount, float $waiver, float $net): bool
    {
        if ($gross < 0 || $discount < 0 || $waiver < 0 || $net < 0) {
            return false;
        }

        return abs($net - ($gross - $discount - $waiver)) < 0.01;
    }
}
