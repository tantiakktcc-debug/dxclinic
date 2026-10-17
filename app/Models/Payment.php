<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'payment_number',
        'cash_session_id',
        'consultation_bill_id',
        'diagnostic_bill_id',
        'payment_method',
        'bank_account_id',
        'amount',
        'status',
        'soundbox_confirmed',
        'reference_number',
        'idempotency_key',
        'settled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'soundbox_confirmed' => 'boolean',
        'settled_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function consultationBill(): BelongsTo
    {
        return $this->belongsTo(ConsultationBill::class);
    }

    public function diagnosticBill(): BelongsTo
    {
        return $this->belongsTo(DiagnosticBill::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function journal(): HasOne
    {
        return $this->hasOne(Journal::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function refunds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Refund::class, 'original_payment_id');
    }

    public function getRefundedAmountAttribute(): float
    {
        return (float) $this->refunds()->where('status', 'PROCESSED')->sum('refund_amount');
    }

    public function getRefundableAmountAttribute(): float
    {
        return max(0.00, (float) $this->amount - $this->refunded_amount);
    }

    public function getRetainedAmountAttribute(): float
    {
        return max(0.00, (float) $this->amount - $this->refunded_amount);
    }


    public function getBillAttribute()
    {
        return $this->consultationBill ?? $this->diagnosticBill;
    }

    public function getPatientAttribute(): ?Patient
    {
        return $this->consultationBill?->patient ?? $this->diagnosticBill?->patient;
    }

    public function getPaymentDateAttribute(): ?\Carbon\Carbon
    {
        return $this->settled_at ?? $this->created_at;
    }
}
