<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vital extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'weight',
        'height',
        'bp_systolic',
        'bp_diastolic',
        'pulse',
        'spo2',
        'temperature',
        'fever',
        'respiratory_rate',
        'rbs',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'bp_systolic' => 'integer',
        'bp_diastolic' => 'integer',
        'pulse' => 'integer',
        'spo2' => 'integer',
        'temperature' => 'decimal:1',
        'fever' => 'boolean',
        'respiratory_rate' => 'integer',
        'rbs' => 'decimal:2',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function getBloodSugarAttribute(): ?float
    {
        return $this->rbs !== null ? (float) $this->rbs : null;
    }
}
