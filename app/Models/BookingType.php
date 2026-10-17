<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingType extends Model
{
    use HasFactory;

    protected $table = 'booking_types';

    protected $fillable = [
        'clinic_id',
        'code',
        'name',
        'consultation_mode',
        'is_system',
        'is_active',
        'is_public_bookable',
        'display_order',
    ];

    protected $casts = [
        'is_system'          => 'boolean',
        'is_active'          => 'boolean',
        'is_public_bookable' => 'boolean',
        'display_order'      => 'integer',
    ];

    protected $appends = [
        'resolved_fee',
        'has_configured_fee',
    ];

    public function getResolvedFeeAttribute(): ?float
    {
        return array_key_exists('resolved_fee', $this->attributes) ? (float) $this->attributes['resolved_fee'] : null;
    }

    public function getHasConfiguredFeeAttribute(): bool
    {
        return !empty($this->attributes['has_configured_fee']);
    }

    // -----------------------------------------------------------------------
    // Built-in system codes — never allow deletion
    // -----------------------------------------------------------------------
    public const CLINIC_CONSULTANCY   = 'CLINIC_CONSULTANCY';
    public const ONLINE_CONSULTANCY   = 'ONLINE_CONSULTANCY';
    public const EMERGENCY_CONSULTANCY = 'EMERGENCY_CONSULTANCY';

    public const SYSTEM_CODES = [
        self::CLINIC_CONSULTANCY,
        self::ONLINE_CONSULTANCY,
        self::EMERGENCY_CONSULTANCY,
    ];

    // -----------------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------------

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctorAvailabilities(): HasMany
    {
        return $this->hasMany(DoctorBookingTypeAvailability::class, 'booking_type_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'booking_type_id');
    }

    public function feeMasters(): HasMany
    {
        return $this->hasMany(FeeMaster::class, 'booking_type_id');
    }

    /**
     * Get active fee for a specific doctor for this booking type.
     */
    public function getFeeForDoctor(int $doctorId): ?float
    {
        return FeeMaster::getFeeForBookingType($this->clinic_id, $doctorId, $this->id);
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublicBookable($query)
    {
        return $query->where('is_public_bookable', true)->where('is_active', true);
    }

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isSystemType(): bool
    {
        return $this->is_system;
    }

    public function canBeDeleted(): bool
    {
        return !$this->is_system && !$this->appointments()->exists();
    }

    /**
     * The corresponding fee_master code for fee resolution.
     */
    public function getFeeCode(): string
    {
        return match ($this->code) {
            self::ONLINE_CONSULTANCY    => 'CONSULTATION_ONLINE',
            self::EMERGENCY_CONSULTANCY => 'CONSULTATION_EMERGENCY',
            default                     => 'CONSULTATION_PHYSICAL',
        };
    }
}
