<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticBillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnostic_bill_id',
        'diagnostic_item_id',
        'item_type',
        'item_name',
        'rate',
        'quantity',
        'amount',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'quantity' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function diagnosticBill(): BelongsTo
    {
        return $this->belongsTo(DiagnosticBill::class);
    }

    public function diagnosticItem(): BelongsTo
    {
        return $this->belongsTo(DiagnosticItem::class);
    }

    public function getUnitRateAttribute(): float
    {
        return (float) $this->rate;
    }

    public function getDiscountAmountAttribute(): float
    {
        return 0.00;
    }

    public function getNetAmountAttribute(): float
    {
        return (float) $this->amount;
    }
}
