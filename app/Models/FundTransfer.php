<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FundTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'cash_session_id',
        'from_ledger_account_id',
        'to_ledger_account_id',
        'amount',
        'transfer_date',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transfer_date' => 'date',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'from_ledger_account_id');
    }

    public function fromLedgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'from_ledger_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'to_ledger_account_id');
    }

    public function toLedgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'to_ledger_account_id');
    }

    public function journal(): HasOne
    {
        return $this->hasOne(Journal::class, 'reference_id')
            ->where('reference_type', self::class);
    }
}
