<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosticResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'diagnostic_order_id',
        'diagnostic_order_item_id',
        'diagnostic_specimen_id',
        'diagnostic_item_id',
        'patient_id',
        'parameter_name',
        'result_type',
        'result_value',
        'numeric_value',
        'unit',
        'reference_range',
        'abnormal_flag',
        'remarks',
        'status',
        'version',
        'is_corrected',
        'correction_reason',
        'previous_result_id',
        'entered_at',
        'entered_by',
        'verified_at',
        'verified_by',
        'released_at',
        'released_by',
        'corrected_at',
        'corrected_by',
    ];

    protected $casts = [
        'numeric_value' => 'decimal:4',
        'version' => 'integer',
        'is_corrected' => 'boolean',
        'entered_at' => 'datetime',
        'verified_at' => 'datetime',
        'released_at' => 'datetime',
        'corrected_at' => 'datetime',
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
        return $this->belongsTo(DiagnosticOrderItem::class, 'diagnostic_order_item_id');
    }

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(DiagnosticSpecimen::class, 'diagnostic_specimen_id');
    }

    public function diagnosticItem(): BelongsTo
    {
        return $this->belongsTo(DiagnosticItem::class, 'diagnostic_item_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function previousResult(): BelongsTo
    {
        return $this->belongsTo(DiagnosticResult::class, 'previous_result_id');
    }

    public function subsequentResults(): HasMany
    {
        return $this->hasMany(DiagnosticResult::class, 'previous_result_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'CORRECTED');
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function isVerified(): bool
    {
        return in_array($this->status, ['VERIFIED', 'RELEASED'], true);
    }

    public function isReleased(): bool
    {
        return $this->status === 'RELEASED';
    }

    public function isAbnormal(): bool
    {
        return in_array($this->abnormal_flag, ['HIGH', 'LOW', 'ABNORMAL', 'CRITICAL'], true);
    }

    public function getOrderItemIdAttribute(): int
    {
        return (int) $this->diagnostic_order_item_id;
    }

    public function getVerifiedByUserIdAttribute(): ?int
    {
        return $this->verified_by;
    }

    public function getIsAbnormalAttribute(): bool
    {
        return $this->isAbnormal();
    }
}
