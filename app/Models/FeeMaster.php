<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'booking_type_id',
        'code',
        'name',
        'amount',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

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
        return $this->belongsTo(BookingType::class);
    }

    /**
     * Authoritative fee resolution for Revision 45:
     * Strictly resolves fee for (clinic_id, doctor_id, booking_type_id).
     * Returns null if no active fee exists for that exact doctor and booking type.
     * No silent fallback to other doctors, clinics, or arbitrary modes!
     */
    public static function getFeeForBookingType(int $clinicId, int $doctorId, int $bookingTypeId, ?string $asOfDate = null): ?float
    {
        // 1. Verify doctor belongs to clinic
        $doctorExists = Doctor::where('id', $doctorId)->where('clinic_id', $clinicId)->exists();
        if (!$doctorExists) {
            return null;
        }

        // 2. Verify booking type belongs to clinic
        $btExists = BookingType::where('id', $bookingTypeId)->where('clinic_id', $clinicId)->exists();
        if (!$btExists) {
            return null;
        }

        // 3. Verify doctor availability is enabled
        $avail = DoctorBookingTypeAvailability::where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorId)
            ->where('booking_type_id', $bookingTypeId)
            ->where('is_enabled', true)
            ->exists();
        if (!$avail) {
            return null;
        }

        // 4. Resolve active fee for this exact (clinic, doctor, booking_type)
        $date = $asOfDate ?? \App\Services\ClinicClock::today()->toDateString();

        $fee = self::where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorId)
            ->where('booking_type_id', $bookingTypeId)
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($fee) {
            return (float) $fee->amount;
        }

        return null;
    }

    /**
     * Check if an active fee exists for this doctor and booking type.
     */
    public static function hasFeeForBookingType(int $clinicId, int $doctorId, int $bookingTypeId, ?string $asOfDate = null): bool
    {
        return self::getFeeForBookingType($clinicId, $doctorId, $bookingTypeId, $asOfDate) !== null;
    }

    /**
     * Legacy authoritative fee resolution hierarchy (Revision 33/44 fallback):
     * 1. Doctor-specific fee (if doctor_id provided)
     * 2. Clinic-wide default fee (doctor_id IS NULL)
     * 3. Fallback REGULAR consultation fee
     * 4. System default fallback (700 online / 600 physical)
     */
    public static function getFeeForMode(string $mode, ?int $clinicId = null, ?int $doctorId = null): float
    {
        $clinicId = $clinicId ?? Clinic::first()?->id ?? 1;
        $upperMode = strtoupper(trim($mode));

        $isOnline = ($upperMode === 'ONLINE');
        $isVisiting = in_array($upperMode, ['VISITING_CONSULTANCY', 'VISITING'], true);
        $isEmergency = ($upperMode === 'EMERGENCY' || $upperMode === 'CONSULTATION_EMERGENCY' || $upperMode === 'EMERGENCY_CONSULTANCY');

        if ($isOnline) {
            $code = 'CONSULTATION_ONLINE';
            $default = 700.00;
        } elseif ($isVisiting) {
            $code = 'CONSULTATION_VISITING';
            $default = 1000.00;
        } elseif ($isEmergency) {
            $code = 'CONSULTATION_EMERGENCY';
            $default = 600.00;
        } else {
            $code = 'CONSULTATION_PHYSICAL';
            $default = 600.00;
        }

        // Validate doctor belongs strictly to this clinic to prevent cross-tenant fee bleed
        if ($doctorId) {
            $docExists = Doctor::where('id', $doctorId)->where('clinic_id', $clinicId)->exists();
            if (!$docExists) {
                $doctorId = null;
            }
        }

        // 1. Doctor-specific configuration
        if ($doctorId) {
            $docFee = self::where('clinic_id', $clinicId)
                ->where('doctor_id', $doctorId)
                ->where(function ($q) use ($code, $upperMode) {
                    $q->where('code', $code);
                    if (in_array($upperMode, ['VISITING_CONSULTANCY', 'VISITING'], true)) {
                        $q->orWhere('code', 'CONSULTATION_VISITING_CONSULTANCY');
                    }
                })
                ->where('is_active', true)
                ->first();

            if ($docFee) {
                return (float) $docFee->amount;
            }
        }

        // 2. Clinic-wide default configuration
        $clinicFee = self::where('clinic_id', $clinicId)
            ->whereNull('doctor_id')
            ->where(function ($q) use ($code, $upperMode) {
                $q->where('code', $code);
                if (in_array($upperMode, ['VISITING_CONSULTANCY', 'VISITING'], true)) {
                    $q->orWhere('code', 'CONSULTATION_VISITING_CONSULTANCY');
                }
            })
            ->where('is_active', true)
            ->first();

        if ($clinicFee) {
            return (float) $clinicFee->amount;
        }

        // 3. Backward-compatible CONSULTATION_REGULAR fallback for physical
        if (!$isOnline && !$isVisiting && !$isEmergency) {
            if ($doctorId) {
                $docReg = self::where('clinic_id', $clinicId)
                    ->where('doctor_id', $doctorId)
                    ->where('code', 'CONSULTATION_REGULAR')
                    ->where('is_active', true)
                    ->first();
                if ($docReg) {
                    return (float) $docReg->amount;
                }
            }

            $reg = self::where('clinic_id', $clinicId)
                ->whereNull('doctor_id')
                ->where('code', 'CONSULTATION_REGULAR')
                ->where('is_active', true)
                ->first();
            if ($reg) {
                return (float) $reg->amount;
            }
        }

        // 4. System fallback
        return $default;
    }
}
