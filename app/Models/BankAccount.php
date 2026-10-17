<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'bank_name',
        'account_holder',
        'account_number',
        'ifsc',
        'branch',
        'upi_id',
        'ledger_account_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Book Bank Balance derived authoritatively from linked ledger account (Section 19 & 35)
     */
    public function getBookBalanceAttribute(): float
    {
        return $this->ledgerAccount ? $this->ledgerAccount->balance : 0.0;
    }
}
