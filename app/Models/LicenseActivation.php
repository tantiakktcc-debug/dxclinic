<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseActivation extends Model
{
    use HasFactory;

    protected $table = 'license_activations';

    public $timestamps = false;

    protected $fillable = [
        'license_id',
        'clinic_id',
        'user_id',
        'activation_code_hash',
        'activated_at',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
