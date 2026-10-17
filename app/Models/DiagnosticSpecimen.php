<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosticSpecimen extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'diagnostic_order_id',
        'order_item_id',
        'specimen_identifier',
        'specimen_type',
        'status',
        'collected_at',
        'collected_by',
        'received_at',
        'processed_at',
        'notes',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'is_recollection',
        'parent_specimen_id',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'is_recollection' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DiagnosticOrder::class, 'diagnostic_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(DiagnosticOrderItem::class, 'order_item_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function parentSpecimen(): BelongsTo
    {
        return $this->belongsTo(DiagnosticSpecimen::class, 'parent_specimen_id');
    }

    public function recollections(): HasMany
    {
        return $this->hasMany(DiagnosticSpecimen::class, 'parent_specimen_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(DiagnosticResult::class, 'diagnostic_specimen_id');
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function isPending(): bool
    {
        return $this->status === 'SAMPLE_PENDING';
    }

    public function isCollected(): bool
    {
        return $this->status === 'COLLECTED';
    }

    public function isReceived(): bool
    {
        return $this->status === 'RECEIVED';
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

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    /**
     * Check if a state transition is permitted.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        $allowedTransitions = [
            'SAMPLE_PENDING' => ['COLLECTED', 'CANCELLED'],
            'COLLECTED' => ['RECEIVED', 'PROCESSING', 'RESULT_ENTERED', 'REJECTED'],
            'RECEIVED' => ['PROCESSING', 'RESULT_ENTERED', 'REJECTED'],
            'PROCESSING' => ['RESULT_ENTERED', 'REJECTED'],
            'RESULT_ENTERED' => ['VERIFIED', 'REJECTED'],
            'VERIFIED' => ['RELEASED'],
            'RELEASED' => [], // Released cannot silently jump back
            'REJECTED' => [], // Terminal; recollection creates a new specimen
            'CANCELLED' => [],
        ];

        return in_array($targetStatus, $allowedTransitions[$this->status] ?? [], true);
    }

    public function getCollectedByUserIdAttribute(): ?int
    {
        return $this->collected_by;
    }
}
