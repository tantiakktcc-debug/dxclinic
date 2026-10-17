<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorBookingPolicy;
use App\Models\DoctorLeave;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingEligibilityService
{
    public const CODE_PASS = 'ELIGIBLE';
    public const CODE_PAST_DATE = 'PAST_DATE_NOT_ALLOWED';
    public const CODE_CLINIC_WINDOW_CLOSED = 'CLINIC_BOOKING_WINDOW_CLOSED';
    public const CODE_DOCTOR_WINDOW_CLOSED = 'DOCTOR_BOOKING_WINDOW_CLOSED';
    public const CODE_CLINIC_CLOSED = 'CLINIC_CLOSED';
    public const CODE_SESSION_NOT_AVAILABLE = 'SESSION_NOT_AVAILABLE';
    public const CODE_SESSION_CUTOFF_PASSED = 'SESSION_CUTOFF_PASSED';
    public const CODE_NO_DOCTOR_AVAILABLE = 'NO_DOCTOR_AVAILABLE';
    public const CODE_DOCTOR_NOT_AVAILABLE = 'DOCTOR_NOT_AVAILABLE';
    public const CODE_DOCTOR_INACTIVE = 'DOCTOR_NOT_AVAILABLE';

    public function __construct(
        protected ?ClinicScheduleService $scheduleService = null
    ) {
        $this->scheduleService = $scheduleService ?? app(ClinicScheduleService::class);
    }

    /**
     * Authoritative Server-Side IST Booking Eligibility Evaluation
     *
     * @param Clinic $clinic
     * @param Carbon|string $date
     * @param string $session
     * @param int|Doctor|null $doctor
     * @return array{eligible: bool, code: string, reason: string, doctor_id: ?int, date: string, session: string}
     */
    public function evaluateEligibility(
        Clinic $clinic,
        Carbon|string $date,
        string $session,
        int|Doctor|null $doctor = null
    ): array {
        $today = ClinicClock::today();
        $currentTime = ClinicClock::now()->format('H:i:s');
        $sessionType = strtoupper(trim($session));

        $targetDate = is_string($date)
            ? Carbon::parse($date, ClinicClock::TIMEZONE)->startOfDay()
            : $date->copy()->setTimezone(ClinicClock::TIMEZONE)->startOfDay();
        $dateStr = $targetDate->toDateString();

        if ($targetDate->isSunday() && in_array($sessionType, ['MORNING', 'SUNDAY', 'SINGLE'])) {
            $sessionType = 'SUNDAY_SINGLE';
        }

        // 1. Validate past date
        if ($targetDate->lt($today)) {
            $reason = ($clinic->max_advance_booking_days ?? 1) == 1
                ? "Cannot book appointments for past dates. Appointments may only be booked for today or tomorrow."
                : "Cannot book appointments for past dates.";
            return [
                'eligible' => false,
                'code' => self::CODE_PAST_DATE,
                'reason' => $reason,
                'doctor_id' => null,
                'date' => $dateStr,
                'session' => $sessionType,
            ];
        }

        // 2. Resolve Doctor (if provided)
        $doc = null;
        if ($doctor instanceof Doctor) {
            $doc = $doctor;
        } elseif (!empty($doctor)) {
            $doc = Doctor::where('clinic_id', $clinic->id)->find((int) $doctor);
            if (!$doc) {
                return [
                    'eligible' => false,
                    'code' => self::CODE_DOCTOR_NOT_AVAILABLE,
                    'reason' => 'Selected doctor does not belong to this clinic or does not exist.',
                    'doctor_id' => null,
                    'date' => $dateStr,
                    'session' => $sessionType,
                ];
            }
        }

        // 3. Clinic Advance Booking Window check
        $clinicMaxDays = (int) ($clinic->max_advance_booking_days ?? 1);
        $clinicMaxDate = $today->copy()->addDays($clinicMaxDays);

        if ($targetDate->gt($clinicMaxDate)) {
            $reason = match ($clinicMaxDays) {
                0 => "This date is outside the clinic's advance booking window. Appointments may only be booked for today only.",
                1 => "This date is outside the clinic's advance booking window. Appointments may only be booked for today or tomorrow.",
                default => "This date is outside the clinic's advance booking window.",
            };
            return [
                'eligible' => false,
                'code' => self::CODE_CLINIC_WINDOW_CLOSED,
                'reason' => $reason,
                'doctor_id' => $doc?->id,
                'date' => $dateStr,
                'session' => $sessionType,
            ];
        }

        // 4. Doctor Advance Booking Window & Policy Precedence
        if ($doc) {
            $effectiveMaxDays = $this->resolveEffectiveAdvanceDays($clinic, $doc, $targetDate);
            $doctorMaxDate = $today->copy()->addDays($effectiveMaxDays);

            if ($targetDate->gt($doctorMaxDate)) {
                return [
                    'eligible' => false,
                    'code' => self::CODE_DOCTOR_WINDOW_CLOSED,
                    'reason' => "This date is outside the selected doctor's advance booking window.",
                    'doctor_id' => $doc->id,
                    'date' => $dateStr,
                    'session' => $sessionType,
                ];
            }
        }

        // 5. Clinic Schedule & Session check
        $sessions = $this->scheduleService->getSessionsForDate($clinic, $targetDate, $doc);
        if (empty($sessions)) {
            return [
                'eligible' => false,
                'code' => self::CODE_CLINIC_CLOSED,
                'reason' => 'The clinic is closed on the selected date.',
                'doctor_id' => $doc?->id,
                'date' => $dateStr,
                'session' => $sessionType,
            ];
        }

        if (!isset($sessions[$sessionType])) {
            return [
                'eligible' => false,
                'code' => self::CODE_SESSION_NOT_AVAILABLE,
                'reason' => "Invalid session '{$sessionType}' for date {$dateStr}. The requested session is not available on this date.",
                'doctor_id' => $doc?->id,
                'date' => $dateStr,
                'session' => $sessionType,
            ];
        }

        $sessionConfig = $sessions[$sessionType];
        $cutoff = $sessionConfig['booking_cutoff'] ?? $sessionConfig['end'] ?? '23:59';
        $cutoffTime = strlen($cutoff) === 5 ? $cutoff . ':00' : $cutoff;

        // 6. Same-Day Session Cutoff check (at final booking moment)
        if ($targetDate->eq($today) && $currentTime >= $cutoffTime) {
            $sessionLabel = strtolower($sessionType);
            if ($sessionType === 'MORNING') {
                $cutoffDisplay = Carbon::parse($cutoff)->format('g:i A');
                $reason = "Morning booking for today closed at {$cutoffDisplay}.";
            } elseif ($sessionType === 'EVENING') {
                $reason = "Today's evening booking is closed because the booking cutoff time has passed.";
            } else {
                $reason = "This clinic session has already ended. Booking cutoff time has passed for {$sessionLabel} session.";
            }

            return [
                'eligible' => false,
                'code' => self::CODE_SESSION_CUTOFF_PASSED,
                'reason' => $reason,
                'doctor_id' => $doc?->id,
                'date' => $dateStr,
                'session' => $sessionType,
            ];
        }

        // 7. Doctor Availability & Leave check
        if ($doc) {
            $docAvail = $this->isDoctorAvailable($doc, $targetDate, $sessionType, $clinic);
            if (!$docAvail['available']) {
                return [
                    'eligible' => false,
                    'code' => $docAvail['code'],
                    'reason' => $docAvail['reason'],
                    'doctor_id' => $doc->id,
                    'date' => $dateStr,
                    'session' => $sessionType,
                ];
            }
        } else {
            // No specific doctor requested: ensure AT LEAST ONE eligible doctor is available
            $availableDoctors = $this->getAvailableDoctors($clinic, $targetDate, $sessionType);
            if (empty($availableDoctors)) {
                return [
                    'eligible' => false,
                    'code' => self::CODE_NO_DOCTOR_AVAILABLE,
                    'reason' => 'No doctor is available for the selected session.',
                    'doctor_id' => null,
                    'date' => $dateStr,
                    'session' => $sessionType,
                ];
            }
        }

        // Passed all checks!
        return [
            'eligible' => true,
            'code' => self::CODE_PASS,
            'reason' => 'Eligible for booking',
            'doctor_id' => $doc?->id,
            'date' => $dateStr,
            'session' => $sessionType,
        ];
    }

    /**
     * Resolve effective advance booking days for a doctor using precedence:
     * effective = MIN(clinic maximum, doctor maximum)
     */
    public function resolveEffectiveAdvanceDays(Clinic $clinic, Doctor $doctor, Carbon|string $targetDate): int
    {
        $clinicMax = (int) ($clinic->max_advance_booking_days ?? 1);
        $d = is_string($targetDate) ? Carbon::parse($targetDate)->toDateString() : $targetDate->toDateString();
        $todayStr = ClinicClock::today()->toDateString();

        // First check for an active policy governing current booking context (today)
        $policy = DoctorBookingPolicy::where('clinic_id', $clinic->id)
            ->where('doctor_id', $doctor->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $todayStr)
            ->whereDate('effective_to', '>=', $todayStr)
            ->orderBy('id', 'desc')
            ->first();

        // If no policy covers today, fallback to policy covering target date
        if (!$policy) {
            $policy = DoctorBookingPolicy::where('clinic_id', $clinic->id)
                ->where('doctor_id', $doctor->id)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $d)
                ->whereDate('effective_to', '>=', $d)
                ->orderBy('id', 'desc')
                ->first();
        }

        if ($policy) {
            $docMax = (int) $policy->max_advance_booking_days;
            return min($clinicMax, $docMax);
        }

        return $clinicMax;
    }

    /**
     * Check if a specific doctor is available on date and session
     *
     * @return array{available: bool, code: string, reason: string}
     */
    public function isDoctorAvailable(Doctor $doctor, Carbon|string $date, string $session, ?Clinic $clinic = null): array
    {
        $c = $clinic ?? $doctor->clinic;
        if (!$c || (int) $doctor->clinic_id !== (int) $c->id) {
            return [
                'available' => false,
                'code' => self::CODE_DOCTOR_NOT_AVAILABLE,
                'reason' => 'Doctor does not belong to this clinic.',
            ];
        }

        if (!$doctor->is_active) {
            return [
                'available' => false,
                'code' => self::CODE_DOCTOR_INACTIVE,
                'reason' => 'The selected doctor is currently inactive.',
            ];
        }

        $targetDate = is_string($date)
            ? Carbon::parse($date, ClinicClock::TIMEZONE)->startOfDay()
            : $date->copy()->setTimezone(ClinicClock::TIMEZONE)->startOfDay();
        $dateStr = $targetDate->toDateString();
        $sessionType = strtoupper(trim($session));
        $sessionLabel = strtolower($sessionType);

        // Check Doctor Leave / Unavailability
        $onLeave = DoctorLeave::where('clinic_id', $c->id)
            ->where('doctor_id', $doctor->id)
            ->forDateAndSession($dateStr, $sessionType)
            ->first();

        if ($onLeave) {
            $reason = !empty($onLeave->reason)
                ? "The selected doctor is on leave ({$onLeave->reason})."
                : "The selected doctor is not available for the {$sessionLabel} session on this date.";
            return [
                'available' => false,
                'code' => self::CODE_DOCTOR_NOT_AVAILABLE,
                'reason' => $reason,
            ];
        }

        // Check Doctor Schedule Config (if custom schedule exists)
        if (!empty($doctor->schedule_config)) {
            $dayOfWeek = strtolower($targetDate->format('l'));
            $docConf = $doctor->schedule_config;

            if ($dayOfWeek === 'sunday') {
                if (empty($docConf['sunday']['single'])) {
                    return [
                        'available' => false,
                        'code' => self::CODE_DOCTOR_NOT_AVAILABLE,
                        'reason' => "The selected doctor is not available on Sunday.",
                    ];
                }
            } else {
                $sessKey = strtolower($sessionType);
                if (empty($docConf[$dayOfWeek][$sessKey])) {
                    return [
                        'available' => false,
                        'code' => self::CODE_DOCTOR_NOT_AVAILABLE,
                        'reason' => "The selected doctor is not available for the {$sessionLabel} session on this date.",
                    ];
                }
            }
        }

        return [
            'available' => true,
            'code' => self::CODE_PASS,
            'reason' => 'Doctor is available',
        ];
    }

    /**
     * Get bookable dates for a clinic (and optionally a specific doctor)
     */
    public function getAvailableDates(Clinic $clinic, ?int $doctorId = null): array
    {
        $today = ClinicClock::today();
        $doc = $doctorId ? Doctor::where('clinic_id', $clinic->id)->find($doctorId) : null;
        $maxDays = (int) ($clinic->max_advance_booking_days ?? 1);

        if ($doc) {
            $docMax = $this->resolveEffectiveAdvanceDays($clinic, $doc, $today);
            $maxDays = min($maxDays, $docMax);
        }

        $availableDates = [];
        for ($i = 0; $i <= $maxDays; $i++) {
            $target = $today->copy()->addDays($i);
            $sessions = $this->getAvailableSessions($clinic, $target, $doc?->id);
            if (!empty($sessions)) {
                $availableDates[] = [
                    'date' => $target->toDateString(),
                    'label' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $target->format('D, d M')),
                    'day_of_week' => strtolower($target->format('l')),
                    'available_sessions_count' => count($sessions),
                ];
            }
        }

        return $availableDates;
    }

    /**
     * Get bookable sessions for a given date (respects IST cutoffs, clinic schedule, doctor policies & availability)
     */
    public function getAvailableSessions(Clinic $clinic, Carbon|string $date, ?int $doctorId = null): array
    {
        $doc = $doctorId ? Doctor::where('clinic_id', $clinic->id)->find($doctorId) : null;
        $allSessions = $this->scheduleService->getSessionsForDate($clinic, $date, $doc);
        $validSessions = [];

        foreach ($allSessions as $sessionType => $conf) {
            $eligibility = $this->evaluateEligibility($clinic, $date, $sessionType, $doc);
            if ($eligibility['eligible']) {
                $validSessions[] = [
                    'session' => $sessionType,
                    'label' => $conf['label'] ?? $sessionType,
                    'start' => $conf['start'] ?? null,
                    'end' => $conf['end'] ?? null,
                    'booking_cutoff' => $conf['booking_cutoff'] ?? $conf['end'] ?? null,
                ];
            }
        }

        return $validSessions;
    }

    /**
     * Get available doctors for a given date and session
     */
    public function getAvailableDoctors(Clinic $clinic, Carbon|string $date, string $session): array
    {
        $sessionType = strtoupper(trim($session));
        $doctors = Doctor::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->get();

        $available = [];
        foreach ($doctors as $doc) {
            $eligibility = $this->evaluateEligibility($clinic, $date, $sessionType, $doc);
            if ($eligibility['eligible']) {
                $available[] = [
                    'id' => $doc->id,
                    'name' => 'Dr. ' . ltrim($doc->name, 'Dr. '),
                    'specialization' => $doc->specialization ?? 'General Physician',
                    'qualification' => $doc->qualification ?? '',
                    'room' => $doc->room_number ?? null,
                ];
            }
        }

        return $available;
    }
}
