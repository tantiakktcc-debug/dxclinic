<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'journal_number',
        'date',
        'description',
        'reference_type',
        'reference_id',
    ];

    public function clinic(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    protected $casts = [
        'date' => 'date',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Invariant check: Debits must balance Credits (Section 74)
     */
    public function isBalanced(): bool
    {
        $debits = (float) $this->entries()->sum('debit');
        $credits = (float) $this->entries()->sum('credit');

        return abs($debits - $credits) < 0.01;
    }

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->entries()->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->entries()->sum('credit');
    }
}
