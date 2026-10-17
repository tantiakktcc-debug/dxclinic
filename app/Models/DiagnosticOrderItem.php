<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosticOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnostic_order_id',
        'diagnostic_item_id',
        'test_name',
        'item_type',
        'quantity',
        'specimen_type',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DiagnosticOrder::class, 'diagnostic_order_id');
    }

    public function diagnosticItem(): BelongsTo
    {
        return $this->belongsTo(DiagnosticItem::class, 'diagnostic_item_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(DiagnosticResult::class, 'diagnostic_order_item_id');
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(DiagnosticSpecimen::class, 'order_item_id');
    }
}
