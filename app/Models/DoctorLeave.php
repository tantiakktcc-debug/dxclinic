<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class DoctorLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'start_date',
        'end_date',
        'session_type',
        'reason',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (DoctorLeave $leave) {
            if ($leave->start_date > $leave->end_date) {
                throw new InvalidArgumentException("Leave start_date ({$leave->start_date}) cannot be after end_date ({$leave->end_date}).");
            }

            if ($leave->doctor && $leave->clinic_id && (int) $leave->doctor->clinic_id !== (int) $leave->clinic_id) {
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

    public function scopeForDateAndSession($query, Carbon|string $date, ?string $session = null)
    {
        $d = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();
        $q = $query->where('is_active', true)
            ->whereDate('start_date', '<=', $d)
            ->whereDate('end_date', '>=', $d);

        if ($session) {
            $sess = strtoupper($session);
            $q->where(function ($sub) use ($sess) {
                $sub->whereNull('session_type')
                    ->orWhere('session_type', '')
                    ->orWhere('session_type', $sess);
            });
        }

        return $q;
    }
}
