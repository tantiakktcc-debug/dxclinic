<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BookingType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorBookingTypeAvailability;
use App\Models\FeeMaster;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingTypeService
{
    /**
     * Return all active booking types for a clinic, ordered for display.
     */
    public function getActiveForClinic(int $clinicId): Collection
    {
        return BookingType::forClinic($clinicId)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Return active booking types enabled for a specific doctor in a clinic.
     * Revision 45: When $requireActiveFee is true, strictly requires both:
     * 1. doctor_booking_type_availability.is_enabled = true
     * 2. active fee_masters record for (clinic_id, doctor_id, booking_type_id)
     * Attaches resolved_fee to each returned BookingType model.
     */
    public function getEnabledForDoctor(int $doctorId, int $clinicId, bool $requireActiveFee = true): Collection
    {
        $doc = Doctor::where('id', $doctorId)->where('clinic_id', $clinicId)->first();
        if (!$doc || !$doc->is_active) {
            return new Collection();
        }

        $types = BookingType::forClinic($clinicId)
            ->active()
            ->ordered()
            ->whereHas('doctorAvailabilities', function ($q) use ($doctorId, $clinicId) {
                $q->where('clinic_id', $clinicId)
                  ->where('doctor_id', $doctorId)
                  ->where('is_enabled', true);
            })
            ->get();

        $today = ClinicClock::today()->toDateString();

        $fees = FeeMaster::where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorId)
            ->whereNotNull('booking_type_id')
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $today);
            })
            ->get()
            ->keyBy('booking_type_id');

        $filtered = new Collection();
        foreach ($types as $bt) {
            $fee = $fees->get($bt->id);
            if ($fee !== null) {
                $bt->resolved_fee = (float) $fee->amount;
                $bt->has_configured_fee = true;
                $filtered->push($bt);
            } elseif (!$requireActiveFee) {
                $bt->resolved_fee = null;
                $bt->has_configured_fee = false;
                $filtered->push($bt);
            }
        }

        return $filtered;
    }

    /**
     * Return active booking types that are public-bookable for a clinic (PWA).
     * Revision 45: When doctorId is provided, strictly limits to types mapped to that doctor
     * with an active, effective fee configured for that doctor.
     */
    public function getPublicBookableForClinic(int $clinicId, ?int $doctorId = null, bool $requireActiveFee = true): Collection
    {
        if ($doctorId) {
            $doc = Doctor::where('id', $doctorId)->where('clinic_id', $clinicId)->first();
            if (!$doc || !$doc->is_active) {
                return new Collection();
            }

            $types = BookingType::forClinic($clinicId)
                ->publicBookable()
                ->ordered()
                ->whereHas('doctorAvailabilities', function ($q) use ($doctorId, $clinicId) {
                    $q->where('clinic_id', $clinicId)
                      ->where('doctor_id', $doctorId)
                      ->where('is_enabled', true);
                })
                ->get();

            $today = ClinicClock::today()->toDateString();
            $fees = FeeMaster::where('clinic_id', $clinicId)
                ->where('doctor_id', $doctorId)
                ->whereNotNull('booking_type_id')
                ->where('is_active', true)
                ->where(function ($q) use ($today) {
                    $q->whereNull('effective_from')
                      ->orWhere('effective_from', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $today);
                })
                ->get()
                ->keyBy('booking_type_id');

            $filtered = new Collection();
            foreach ($types as $bt) {
                $fee = $fees->get($bt->id);
                if ($fee !== null) {
                    $bt->resolved_fee = (float) $fee->amount;
                    $bt->has_configured_fee = true;
                    $filtered->push($bt);
                } elseif (!$requireActiveFee) {
                    $bt->resolved_fee = null;
                    $bt->has_configured_fee = false;
                    $filtered->push($bt);
                }
            }

            return $filtered;
        }

        return BookingType::forClinic($clinicId)
            ->publicBookable()
            ->ordered()
            ->get();
    }

    /**
     * Create a new custom booking type for a clinic.
     * System types cannot be created via this method.
     */
    public function createCustomType(array $data, int $clinicId): BookingType
    {
        $this->validateCode($data['code'] ?? '');

        if (in_array(strtoupper(trim($data['code'])), BookingType::SYSTEM_CODES, true)) {
            throw new InvalidArgumentException('Cannot create a system-reserved booking type code.');
        }

        $exists = BookingType::where('clinic_id', $clinicId)
            ->where('code', strtoupper(trim($data['code'])))
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException("Booking type code '{$data['code']}' already exists for this clinic.");
        }

        return DB::transaction(function () use ($data, $clinicId) {
            $bt = BookingType::create([
                'clinic_id'          => $clinicId,
                'code'               => strtoupper(trim($data['code'])),
                'name'               => trim($data['name']),
                'consultation_mode'  => strtoupper(trim($data['consultation_mode'] ?? 'CLINIC_PHYSICAL')),
                'is_system'          => false,
                'is_active'          => true,
                'is_public_bookable' => (bool) ($data['is_public_bookable'] ?? true),
                'display_order'      => (int) ($data['display_order'] ?? 100),
            ]);

            // Seed availability for all existing active doctors — disabled by default
            $doctors = Doctor::where('clinic_id', $clinicId)->where('is_active', true)->pluck('id');
            foreach ($doctors as $doctorId) {
                DoctorBookingTypeAvailability::firstOrCreate(
                    ['clinic_id' => $clinicId, 'doctor_id' => $doctorId, 'booking_type_id' => $bt->id],
                    ['is_enabled' => false]
                );
            }

            return $bt;
        });
    }

    /**
     * Update a custom booking type's display fields.
     */
    public function updateType(BookingType $bt, array $data): BookingType
    {
        if ($bt->is_system) {
            $bt->update([
                'name'               => trim($data['name'] ?? $bt->name),
                'is_public_bookable' => (bool) ($data['is_public_bookable'] ?? $bt->is_public_bookable),
                'is_active'          => (bool) ($data['is_active'] ?? $bt->is_active),
                'display_order'      => (int) ($data['display_order'] ?? $bt->display_order),
            ]);
        } else {
            $bt->update([
                'name'               => trim($data['name'] ?? $bt->name),
                'consultation_mode'  => strtoupper(trim($data['consultation_mode'] ?? $bt->consultation_mode)),
                'is_public_bookable' => (bool) ($data['is_public_bookable'] ?? $bt->is_public_bookable),
                'is_active'          => (bool) ($data['is_active'] ?? $bt->is_active),
                'display_order'      => (int) ($data['display_order'] ?? $bt->display_order),
            ]);
        }

        return $bt->fresh();
    }

    /**
     * Delete a custom booking type.
     */
    public function deleteType(BookingType $bt): void
    {
        if ($bt->is_system) {
            throw new InvalidArgumentException("System booking type '{$bt->name}' cannot be deleted. You may deactivate it instead.");
        }

        if ($bt->appointments()->exists()) {
            throw new InvalidArgumentException("Booking type '{$bt->name}' cannot be deleted because appointments reference it. Deactivate it instead.");
        }

        DB::transaction(function () use ($bt) {
            // Delete associated doctor fees
            FeeMaster::where('clinic_id', $bt->clinic_id)
                ->where('booking_type_id', $bt->id)
                ->delete();

            $bt->doctorAvailabilities()->delete();
            $bt->delete();
        });
    }

    /**
     * Toggle a single doctor's availability for a booking type.
     */
    public function toggleDoctorAvailability(int $clinicId, int $doctorId, int $bookingTypeId, bool $enabled): void
    {
        $doc = Doctor::where('id', $doctorId)->where('clinic_id', $clinicId)->first();
        if (!$doc) {
            throw new InvalidArgumentException("Doctor does not belong to this clinic.");
        }

        $bt = BookingType::where('id', $bookingTypeId)->where('clinic_id', $clinicId)->first();
        if (!$bt) {
            throw new InvalidArgumentException("Booking type does not belong to this clinic.");
        }

        DoctorBookingTypeAvailability::updateOrCreate(
            [
                'clinic_id'       => $clinicId,
                'doctor_id'       => $doctorId,
                'booking_type_id' => $bookingTypeId,
            ],
            ['is_enabled' => $enabled]
        );
    }

    /**
     * Alias for toggleDoctorAvailability.
     */
    public function setDoctorAvailability(int $clinicId, int $doctorId, int $bookingTypeId, bool $enabled): void
    {
        $this->toggleDoctorAvailability($clinicId, $doctorId, $bookingTypeId, $enabled);
    }

    /**
     * Convenience method to create custom booking type with (clinicId, data) signature.
     */
    public function createCustom(int $clinicId, array $data): BookingType
    {
        return $this->createCustomType($data, $clinicId);
    }

    /**
     * Bulk sync a doctor's booking type availabilities from an [id => bool] map.
     */
    public function syncDoctorAvailabilities(int $clinicId, int $doctorId, array $availabilityMap): void
    {
        $doc = Doctor::where('id', $doctorId)->where('clinic_id', $clinicId)->first();
        if (!$doc) {
            throw new InvalidArgumentException("Doctor does not belong to this clinic.");
        }

        DB::transaction(function () use ($clinicId, $doctorId, $availabilityMap) {
            foreach ($availabilityMap as $bookingTypeId => $enabled) {
                $bt = BookingType::where('clinic_id', $clinicId)
                    ->where('id', (int) $bookingTypeId)
                    ->first();
                if (!$bt) {
                    continue;
                }

                DoctorBookingTypeAvailability::updateOrCreate(
                    [
                        'clinic_id'       => $clinicId,
                        'doctor_id'       => $doctorId,
                        'booking_type_id' => (int) $bookingTypeId,
                    ],
                    ['is_enabled' => (bool) $enabled]
                );
            }
        });
    }

    /**
     * Set/update doctor-specific fee for a booking type (Revision 45).
     */
    public function setDoctorFee(
        int $clinicId,
        int $doctorId,
        int $bookingTypeId,
        float $amount,
        bool $isActive = true,
        ?string $effectiveFrom = null,
        ?string $effectiveTo = null
    ): FeeMaster {
        $doc = Doctor::where('id', $doctorId)->where('clinic_id', $clinicId)->first();
        if (!$doc) {
            throw new InvalidArgumentException("Doctor does not belong to this clinic.");
        }

        $bt = BookingType::where('id', $bookingTypeId)->where('clinic_id', $clinicId)->first();
        if (!$bt) {
            throw new InvalidArgumentException("Booking type does not belong to this clinic.");
        }

        if ($amount < 0) {
            throw new InvalidArgumentException("Fee amount cannot be negative.");
        }

        $effectiveFromDate = $effectiveFrom ? \Carbon\Carbon::parse($effectiveFrom)->toDateString() : null;
        $effectiveToDate = $effectiveTo ? \Carbon\Carbon::parse($effectiveTo)->toDateString() : null;

        if ($effectiveFromDate && $effectiveToDate && $effectiveToDate < $effectiveFromDate) {
            throw new InvalidArgumentException("Effective end date cannot precede effective start date.");
        }

        $code = 'BT_' . $bt->code . '_DOC' . $doctorId . ($effectiveFromDate ? '_' . str_replace('-', '', $effectiveFromDate) : '');

        // Enforce non-overlapping date ranges for the same doctor and booking type
        if ($effectiveFromDate) {
            $overlap = FeeMaster::where('clinic_id', $clinicId)
                ->where('doctor_id', $doctorId)
                ->where('booking_type_id', $bookingTypeId)
                ->where('is_active', true)
                ->whereDate('effective_from', '!=', $effectiveFromDate)
                ->where(function ($q) use ($effectiveFromDate, $effectiveToDate) {
                    if ($effectiveToDate) {
                        $q->where(function ($sub) use ($effectiveFromDate, $effectiveToDate) {
                            $sub->whereBetween('effective_from', [$effectiveFromDate, $effectiveToDate])
                                ->orWhereBetween('effective_to', [$effectiveFromDate, $effectiveToDate])
                                ->orWhere(function ($nested) use ($effectiveFromDate, $effectiveToDate) {
                                    $nested->where('effective_from', '<=', $effectiveFromDate)
                                           ->where(function ($end) use ($effectiveToDate) {
                                               $end->whereNull('effective_to')
                                                   ->orWhere('effective_to', '>=', $effectiveToDate);
                                           });
                                });
                        });
                    } else {
                        $q->where(function ($sub) use ($effectiveFromDate) {
                            $sub->whereNull('effective_to')
                                ->orWhere('effective_to', '>=', $effectiveFromDate);
                        });
                    }
                })
                ->first();

            if ($overlap) {
                $overlapFrom = \Carbon\Carbon::parse($overlap->effective_from)->toDateString();
                // Automatically close previous open-ended fee to prevent overlap
                if ($overlapFrom < $effectiveFromDate && $overlap->effective_to === null) {
                    $overlap->update([
                        'effective_to' => \Carbon\Carbon::parse($effectiveFromDate)->subDay()->toDateString()
                    ]);
                } else {
                    throw new InvalidArgumentException("Conflicting overlapping fee version exists for this doctor and booking type.");
                }
            }
        }

        $fee = null;
        if ($effectiveFromDate) {
            $fee = FeeMaster::where('clinic_id', $clinicId)
                ->where('doctor_id', $doctorId)
                ->where('booking_type_id', $bookingTypeId)
                ->whereDate('effective_from', $effectiveFromDate)
                ->first();
        } else {
            $fee = FeeMaster::where('clinic_id', $clinicId)
                ->where('doctor_id', $doctorId)
                ->where('booking_type_id', $bookingTypeId)
                ->latest('effective_from')
                ->first();
            $effectiveFromDate = $fee ? \Carbon\Carbon::parse($fee->effective_from)->toDateString() : '2026-01-01';
        }

        if ($fee) {
            $fee->update([
                'code'           => $code,
                'name'           => $bt->name,
                'amount'         => $amount,
                'is_active'      => $isActive,
                'effective_from' => $effectiveFromDate,
                'effective_to'   => $effectiveToDate,
            ]);
        } else {
            $fee = FeeMaster::create([
                'clinic_id'       => $clinicId,
                'doctor_id'       => $doctorId,
                'booking_type_id' => $bookingTypeId,
                'effective_from'  => $effectiveFromDate ?: '2026-01-01',
                'code'           => $code,
                'name'           => $bt->name,
                'amount'         => $amount,
                'is_active'      => $isActive,
                'effective_to'   => $effectiveToDate,
            ]);
        }

        AuditLog::log('DOCTOR_BOOKING_TYPE_FEE_UPDATED', 'FEE_MASTER', $fee->id, [
            'clinic_id'       => $clinicId,
            'doctor_id'       => $doctorId,
            'booking_type_id' => $bookingTypeId,
            'amount'          => $amount,
            'effective_from'  => $fee->effective_from,
            'effective_to'    => $fee->effective_to,
            'is_active'       => $isActive,
        ]);

        return $fee;
    }

    /**
     * Get comprehensive matrix of all booking types for a doctor,
     * including availability status, configured fee amount, and overall bookability.
     */
    public function getDoctorBookingTypeMatrix(int $clinicId, int $doctorId): array
    {
        $doc = Doctor::where('id', $doctorId)->where('clinic_id', $clinicId)->first();
        if (!$doc) {
            throw new InvalidArgumentException("Doctor does not belong to this clinic.");
        }

        $types = BookingType::forClinic($clinicId)->ordered()->get();
        $availabilities = DoctorBookingTypeAvailability::where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorId)
            ->get()
            ->keyBy('booking_type_id');

        $fees = FeeMaster::where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorId)
            ->whereNotNull('booking_type_id')
            ->where('is_active', true)
            ->get()
            ->keyBy('booking_type_id');

        $matrix = [];
        foreach ($types as $bt) {
            $avail = $availabilities->get($bt->id);
            $isEnabled = $avail?->is_enabled ?? false;

            $fee = $fees->get($bt->id);
            $feeAmount = $fee ? (float) $fee->amount : null;
            $hasFee = ($fee !== null);

            $matrix[] = [
                'booking_type_id'    => $bt->id,
                'code'               => $bt->code,
                'name'               => $bt->name,
                'consultation_mode'  => $bt->consultation_mode,
                'is_system'          => (bool) $bt->is_system,
                'is_active'          => (bool) $bt->is_active,
                'is_public_bookable' => (bool) $bt->is_public_bookable,
                'is_enabled'         => (bool) $isEnabled,
                'fee_amount'         => $feeAmount,
                'is_fee_configured'  => $hasFee,
                'is_bookable'        => ($bt->is_active && $isEnabled && $hasFee),
            ];
        }

        return $matrix;
    }

    /**
     * Seed default booking type availability rows when a new doctor is created.
     * All system types default to enabled and receive initial fee master records.
     */
    public function seedForNewDoctor(int $doctorId, int $clinicId): void
    {
        $doc = Doctor::where('id', $doctorId)->where('clinic_id', $clinicId)->first();
        if (!$doc) {
            return;
        }

        $bookingTypes = BookingType::forClinic($clinicId)->get();
        foreach ($bookingTypes as $bt) {
            DoctorBookingTypeAvailability::firstOrCreate(
                ['clinic_id' => $clinicId, 'doctor_id' => $doctorId, 'booking_type_id' => $bt->id],
                ['is_enabled' => $bt->is_system]
            );

            if ($bt->is_system) {
                $clinicFee = FeeMaster::where('clinic_id', $clinicId)
                    ->whereNull('doctor_id')
                    ->where('is_active', true)
                    ->where(function ($q) use ($bt) {
                        if ($bt->code === 'ONLINE_CONSULTANCY') {
                            $q->where('code', 'CONSULTATION_ONLINE');
                        } elseif ($bt->code === 'EMERGENCY_CONSULTANCY') {
                            $q->where('code', 'CONSULTATION_EMERGENCY');
                        } else {
                            $q->whereIn('code', ['CONSULTATION_PHYSICAL', 'CONSULTATION_REGULAR']);
                        }
                    })
                    ->first();

                $amount = $clinicFee?->amount ?? ($bt->code === 'ONLINE_CONSULTANCY' ? 700.00 : 600.00);

                FeeMaster::firstOrCreate(
                    [
                        'clinic_id'       => $clinicId,
                        'doctor_id'       => $doctorId,
                        'booking_type_id' => $bt->id,
                        'effective_from'  => '2026-01-01',
                    ],
                    [
                        'code'           => 'BT_' . $bt->code . '_DOC' . $doctorId . '_20260101',
                        'name'           => $bt->name,
                        'amount'         => $amount,
                        'is_active'      => true,
                        'effective_from' => '2026-01-01',
                    ]
                );
            }
        }
    }

    /**
     * Resolve a BookingType for a given clinic by code.
     */
    public function resolveByCode(string $code, int $clinicId): ?BookingType
    {
        return BookingType::where('clinic_id', $clinicId)
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    /**
     * Resolve the effective consultation_mode from a BookingType code.
     */
    public function getConsultationMode(string $bookingTypeCode, int $clinicId): string
    {
        $bt = $this->resolveByCode($bookingTypeCode, $clinicId);
        return $bt?->consultation_mode ?? 'CLINIC_PHYSICAL';
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function validateCode(string $code): void
    {
        if (empty(trim($code))) {
            throw new InvalidArgumentException('Booking type code cannot be empty.');
        }

        if (!preg_match('/^[A-Z0-9_]+$/', strtoupper(trim($code)))) {
            throw new InvalidArgumentException('Booking type code must be uppercase letters, digits, and underscores only.');
        }

        if (strlen($code) > 60) {
            throw new InvalidArgumentException('Booking type code must not exceed 60 characters.');
        }
    }
}
