<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosticItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'type',
        'code',
        'name',
        'description',
        'rate',
        'result_type',
        'specimen_type',
        'unit',
        'reference_range',
        'reference_text',
        'decimal_precision',
        'critical_low',
        'critical_high',
        'is_active',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'decimal_precision' => 'integer',
        'critical_low' => 'decimal:2',
        'critical_high' => 'decimal:2',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(DiagnosticBillItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(DiagnosticOrderItem::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(DiagnosticResult::class);
    }

    /**
     * Deterministic Frequent / Quick Add scope based purely on historical usage and recency (Section 66)
     */
    public function scopeFrequent(Builder $query, int $limit = 10): Builder
    {
        return $query->where('is_active', true)
            ->orderByDesc('usage_count')
            ->orderByDesc('last_used_at')
            ->orderBy('name')
            ->limit($limit);
    }
}
