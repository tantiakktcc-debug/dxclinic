<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'session_date',
        'session_type',
        'status',
        'opened_by',
        'closed_by',
        'opened_at',
        'closed_at',
        'opening_physical_cash',
        'expected_closing_cash',
        'closing_physical_cash',
        'cash_variance',
        'variance_reason',
    ];

    protected $casts = [
        'session_date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_physical_cash' => 'decimal:2',
        'expected_closing_cash' => 'decimal:2',
        'closing_physical_cash' => 'decimal:2',
        'cash_variance' => 'decimal:2',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function fundTransfers(): HasMany
    {
        return $this->hasMany(FundTransfer::class);
    }

    /**
     * System-derived cash receipts from actual settled CASH payments (Section 13, 16, 24)
     */
    public function getActualCashReceiptsAttribute(): float
    {
        return (float) $this->payments()
            ->where('payment_method', 'CASH')
            ->where('status', 'SETTLED')
            ->sum('amount');
    }

    /**
     * System-derived UPI collection from actual settled UPI payments (Section 13, 21, 28)
     */
    public function getActualUpiCollectionAttribute(): float
    {
        return (float) $this->payments()
            ->where('payment_method', 'UPI')
            ->where('status', 'SETTLED')
            ->sum('amount');
    }

    /**
     * System-derived cash expenses from actual CASH expenses (Section 14, 22, 29)
     */
    public function getActualCashExpensesAttribute(): float
    {
        return (float) $this->expenses()
            ->where('payment_method', 'CASH')
            ->sum('amount');
    }

    /**
     * System-derived cash deposited to bank from actual fund transfers (Section 15, 23, 30)
     */
    public function getActualBankCashDepositAttribute(): float
    {
        return (float) $this->fundTransfers()->sum('amount');
    }

    /**
     * Authoritative calculation:
     * Expected Closing Cash = Opening Physical Cash + Cash Receipts - Cash Expenses - Cash Deposited to Bank
     * (Section 16, 24, 31)
     */
    public function computeExpectedClosingCash(): float
    {
        return round(
            (float) $this->opening_physical_cash
            + $this->actual_cash_receipts
            - $this->actual_cash_expenses
            - $this->actual_bank_cash_deposit,
            2
        );
    }
}
