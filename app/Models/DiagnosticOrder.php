<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiagnosticOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'doctor_id',
        'visit_id',
        'appointment_id',
        'diagnostic_bill_id',
        'order_number',
        'order_date',
        'status',
        'priority',
        'clinical_notes',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'datetime',
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

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function diagnosticBill(): BelongsTo
    {
        return $this->belongsTo(DiagnosticBill::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DiagnosticOrderItem::class);
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(DiagnosticSpecimen::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(DiagnosticResult::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(DiagnosticReport::class)->latestOfMany();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DiagnosticReport::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function isOrdered(): bool
    {
        return $this->status === 'ORDERED';
    }

    public function isSamplePending(): bool
    {
        return $this->status === 'SAMPLE_PENDING';
    }

    public function isCollected(): bool
    {
        return $this->status === 'COLLECTED';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'PROCESSING';
    }

    public function isResultEntered(): bool
    {
        return $this->status === 'RESULT_ENTERED';
    }

    public function isVerified(): bool
    {
        return $this->status === 'VERIFIED';
    }

    public function isReleased(): bool
    {
        return $this->status === 'RELEASED';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function getOrderStatusAttribute(): string
    {
        return $this->status ?? 'ORDERED';
    }

    public function setOrderStatusAttribute($value): void
    {
        $this->attributes['status'] = $value;
    }
}
