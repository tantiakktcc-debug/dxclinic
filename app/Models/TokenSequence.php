<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'token_date',
        'session_type',
        'current_token',
    ];

    protected $casts = [
        'token_date' => 'date',
        'current_token' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
