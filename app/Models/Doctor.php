<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'qualification',
        'registration_number',
        'specialization',
        'contact_number',
        'is_active',
        'schedule_config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'schedule_config' => 'array',
    ];

    protected $appends = [
        'formatted_name',
        'display_name',
    ];

    /**
     * Always store canonical clean name without title prefix
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = \App\Services\DoctorNamePresenter::stripTitle($value);
    }

    protected static function booted(): void
    {
        static::creating(function (Doctor $doctor) {
            if ($doctor->clinic_id) {
                $clinic = Clinic::find($doctor->clinic_id);
                if ($clinic && !$clinic->canAddDoctor()) {
                    throw new \InvalidArgumentException("You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat.");
                }
            }
        });

        static::updating(function (Doctor $doctor) {
            if ($doctor->isDirty('is_active') && $doctor->is_active) {
                if ($doctor->clinic_id) {
                    $clinic = Clinic::find($doctor->clinic_id);
                    if ($clinic && !$clinic->canAddDoctor()) {
                        throw new \InvalidArgumentException("You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat.");
                    }
                }
            }
        });

        static::created(function (Doctor $doctor) {
            if ($doctor->clinic_id) {
                app(\App\Services\BookingTypeService::class)->seedForNewDoctor($doctor->id, $doctor->clinic_id);
            }
        });

        static::deleting(function (Doctor $doctor) {
            $doctor->bookingTypeAvailabilities()->delete();
        });
    }

    public function bookingTypeAvailabilities(): HasMany
    {
        return $this->hasMany(DoctorBookingTypeAvailability::class);
    }

    public function bookingPolicies(): HasMany
    {
        return $this->hasMany(DoctorBookingPolicy::class)->orderBy('effective_from');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(DoctorLeave::class)->orderBy('start_date');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function prescriptionPadTemplates(): HasMany
    {
        return $this->hasMany(PrescriptionPadTemplate::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(FeeMaster::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }

    public function consultationTypes(): HasMany
    {
        return $this->hasMany(DoctorConsultationType::class);
    }

    public function enabledConsultationModes(): array
    {
        return $this->consultationTypes()
            ->where('is_enabled', true)
            ->pluck('consultation_mode')
            ->toArray();
    }

    public function activePrescriptionPadTemplate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrescriptionPadTemplate::class)->where('is_active', true)->where('status', 'ACTIVE');
    }

    /**
     * Get authoritative normalized display name with single 'Dr.' title.
     * Prevents 'Dr. Dr.' duplication globally.
     */
    public function getFormattedNameAttribute(): string
    {
        return \App\Services\DoctorNamePresenter::format($this->attributes['name'] ?? $this->name);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->formatted_name;
    }

    public static function formatName(?string $name): string
    {
        return \App\Services\DoctorNamePresenter::format($name);
    }
}
