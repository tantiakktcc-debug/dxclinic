<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseRequestEvent extends Model
{
    public $timestamps = false;

    protected $table = 'license_request_events';

    protected $fillable = [
        'license_request_id',
        'event_type',
        'performed_by_type',
        'performed_by_id',
        'old_status',
        'new_status',
        'notes',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function licenseRequest(): BelongsTo
    {
        return $this->belongsTo(LicenseRequest::class);
    }
}
