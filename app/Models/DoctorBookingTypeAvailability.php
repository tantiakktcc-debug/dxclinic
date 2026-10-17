<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorBookingTypeAvailability extends Model
{
    use HasFactory;

    protected $table = 'doctor_booking_type_availability';

    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'booking_type_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    // -----------------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------------

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function bookingType(): BelongsTo
    {
        return $this->belongsTo(BookingType::class, 'booking_type_id');
    }
}
