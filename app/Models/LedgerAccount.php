<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LedgerAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'subtype',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function bankAccount(): HasOne
    {
        return $this->hasOne(BankAccount::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Compute current book balance from journal entries.
     * For ASSET and EXPENSE accounts: normal balance is Debit (Debits - Credits).
     * For LIABILITY, EQUITY, and INCOME accounts: normal balance is Credit (Credits - Debits).
     */
    public function getBalanceAttribute(): float
    {
        $debits = (float) $this->journalEntries()->sum('debit');
        $credits = (float) $this->journalEntries()->sum('credit');

        if (in_array($this->type, ['ASSET', 'EXPENSE'])) {
            return $debits - $credits;
        }

        return $credits - $debits;
    }
}
