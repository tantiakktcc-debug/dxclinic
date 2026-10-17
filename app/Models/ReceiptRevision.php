<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptRevision extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'receipt_id',
        'revision_number',
        'old_values',
        'new_values',
        'reason',
        'user_id',
        'financial_impact',
        'linked_refund_id',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedRefund(): BelongsTo
    {
        return $this->belongsTo(Refund::class, 'linked_refund_id');
    }
}
