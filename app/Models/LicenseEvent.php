<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseEvent extends Model
{
    use HasFactory;

    protected $table = 'license_events';

    public $timestamps = false;

    protected $fillable = [
        'license_id',
        'event_type',
        'performed_by_type',
        'performed_by_id',
        'old_status',
        'new_status',
        'old_end_date',
        'new_end_date',
        'notes',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'old_end_date' => 'date',
        'new_end_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
