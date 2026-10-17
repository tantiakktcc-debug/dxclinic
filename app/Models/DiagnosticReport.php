<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'diagnostic_order_id',
        'patient_id',
        'doctor_id',
        'report_number',
        'status',
        'finalized_at',
        'finalized_by',
        'released_at',
        'released_by',
        'report_notes',
        'verification_hash',
    ];

    protected $casts = [
        'finalized_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DiagnosticOrder::class, 'diagnostic_order_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function isFinal(): bool
    {
        return $this->status === 'FINAL';
    }

    public function isVerified(): bool
    {
        return $this->status === 'VERIFIED';
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isReleased(): bool
    {
        return in_array($this->status, ['RELEASED', 'FINAL'], true);
    }

    public function getReleasedByUserIdAttribute(): ?int
    {
        return $this->released_by;
    }
}
