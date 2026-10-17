<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'reason',
        'change_category',
        'financial_impact',
        'linked_refund_id',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedRefund(): BelongsTo
    {
        return $this->belongsTo(Refund::class, 'linked_refund_id');
    }

    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $newValues = null,
        ?array $oldValues = null,
        ?string $reason = null,
        ?string $changeCategory = null,
        ?string $financialImpact = null,
        ?int $linkedRefundId = null
    ): self {
        return self::create([
            'clinic_id' => 1,
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'new_values' => $newValues,
            'old_values' => $oldValues,
            'ip_address' => request()?->ip(),
            'reason' => $reason,
            'change_category' => $changeCategory,
            'financial_impact' => $financialImpact,
            'linked_refund_id' => $linkedRefundId,
        ]);
    }
}
