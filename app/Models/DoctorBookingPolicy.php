<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class DoctorBookingPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'effective_from',
        'effective_to',
        'max_advance_booking_days',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_from' => 'date:Y-m-d',
        'effective_to' => 'date:Y-m-d',
        'max_advance_booking_days' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (DoctorBookingPolicy $policy) {
            if ($policy->effective_from > $policy->effective_to) {
                throw new InvalidArgumentException("Policy effective_from ({$policy->effective_from}) cannot be after effective_to ({$policy->effective_to}).");
            }

            if ($policy->max_advance_booking_days < 0) {
                throw new InvalidArgumentException("max_advance_booking_days must be non-negative (>= 0).");
            }

            if ($policy->doctor && $policy->clinic_id && (int) $policy->doctor->clinic_id !== (int) $policy->clinic_id) {
                throw new InvalidArgumentException("Doctor does not belong to the specified clinic.");
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDate($query, Carbon|string $date)
    {
        $d = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();
        return $query->whereDate('effective_from', '<=', $d)
            ->whereDate('effective_to', '>=', $d);
    }
}
