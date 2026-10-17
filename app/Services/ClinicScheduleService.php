<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Doctor;
use Carbon\Carbon;

class ClinicScheduleService
{
    /**
     * Get configured sessions for a given date.
     * Hierarchy:
     * 1. Doctor-specific schedule_config (if doctor provided and configured)
     * 2. Clinic-wide schedule_config
     * 3. Clinic default schedule fallback
     */
    public function getSessionsForDate(Clinic $clinic, Carbon|string $date, ?Doctor $doctor = null): array
    {
        $dt = is_string($date) 
            ? Carbon::parse($date, ClinicClock::TIMEZONE) 
            : $date->copy()->setTimezone(ClinicClock::TIMEZONE);
        $dayOfWeek = strtolower($dt->format('l')); // monday, sunday, etc.

        // Hierarchy resolution
        if ($doctor && !empty($doctor->schedule_config)) {
            $schedule = $doctor->schedule_config;
        } else {
            $schedule = $clinic->schedule_config ?? $clinic->defaultSchedule();
        }

        if ($dayOfWeek === 'sunday') {
            $sunStart = $schedule['sunday']['single'][0] ?? $schedule['sunday']['morning'][0] ?? '09:00';
            $sunEnd = $schedule['sunday']['single'][1] ?? $schedule['sunday']['morning'][1] ?? '11:00';
            $sunCutoff = $schedule['sunday']['single']['cutoff'] ?? $schedule['sunday']['morning']['cutoff'] ?? $schedule['sunday']['single'][2] ?? $schedule['sunday']['morning'][2] ?? $sunEnd;
            return [
                'SUNDAY_SINGLE' => [
                    'label' => 'Sunday Session',
                    'start' => $sunStart,
                    'end' => $sunEnd,
                    'booking_cutoff' => $sunCutoff,
                ],
            ];
        }

        $daySchedule = $schedule[$dayOfWeek] ?? [
            'morning' => ['09:00', '11:00'],
            'evening' => ['17:00', '19:00'],
        ];

        $morningStart = $daySchedule['morning'][0] ?? '09:00';
        $morningEnd = $daySchedule['morning'][1] ?? '11:00';
        $morningCutoff = $daySchedule['morning']['cutoff'] ?? $daySchedule['morning'][2] ?? $morningEnd;

        $eveningStart = $daySchedule['evening'][0] ?? '17:00';
        $eveningEnd = $daySchedule['evening'][1] ?? '19:00';
        $eveningCutoff = $daySchedule['evening']['cutoff'] ?? $daySchedule['evening'][2] ?? $eveningEnd;

        return [
            'MORNING' => [
                'label' => 'Morning Session',
                'start' => $morningStart,
                'end' => $morningEnd,
                'booking_cutoff' => $morningCutoff,
            ],
            'EVENING' => [
                'label' => 'Evening Session',
                'start' => $eveningStart,
                'end' => $eveningEnd,
                'booking_cutoff' => $eveningCutoff,
            ],
        ];
    }

    /**
     * Get bookable sessions for a given date (respects today expiry & tomorrow availability)
     */
    public function getBookableSessionsForDate(Clinic $clinic, Carbon|string $date, ?Doctor $doctor = null): array
    {
        if (!ClinicClock::isAllowedDate($date, $clinic->max_advance_booking_days)) {
            return [];
        }

        $allSessions = $this->getSessionsForDate($clinic, $date, $doctor);

        // If booking for a future allowed date (e.g. tomorrow or beyond), all sessions are bookable
        if (!ClinicClock::isToday($date)) {
            return $allSessions;
        }

        // If booking for today, filter out expired sessions: current_time >= booking_cutoff
        $currentTime = ClinicClock::now()->format('H:i:s');
        $bookable = [];

        foreach ($allSessions as $key => $session) {
            $cutoff = $session['booking_cutoff'] ?? $session['end'] ?? '23:59';
            $cutoffTime = strlen($cutoff) === 5 ? $cutoff . ':00' : $cutoff;
            if ($currentTime < $cutoffTime) {
                $bookable[$key] = $session;
            }
        }

        return $bookable;
    }

    /**
     * Validate if the session is valid for the given date (exists in configuration)
     */
    public function isValidSession(Clinic $clinic, Carbon|string $date, string $sessionType, ?Doctor $doctor = null): bool
    {
        $sessions = $this->getSessionsForDate($clinic, $date, $doctor);
        return array_key_exists(strtoupper($sessionType), $sessions);
    }

    /**
     * Check if a specific session is currently bookable for the given date
     */
    public function isSessionBookable(Clinic $clinic, Carbon|string $date, string $sessionType, ?Doctor $doctor = null): bool
    {
        $bookable = $this->getBookableSessionsForDate($clinic, $date, $doctor);
        return array_key_exists(strtoupper($sessionType), $bookable);
    }

    /**
     * Check if a session has already expired for the given date
     */
    public function isSessionExpired(Clinic $clinic, Carbon|string $date, string $sessionType, ?Doctor $doctor = null): bool
    {
        // Future allowed dates are not expired
        if (!ClinicClock::isToday($date)) {
            return !ClinicClock::isAllowedDate($date, $clinic->max_advance_booking_days);
        }

        $sessions = $this->getSessionsForDate($clinic, $date, $doctor);
        $type = strtoupper($sessionType);

        if (!isset($sessions[$type])) {
            return true;
        }

        $cutoff = $sessions[$type]['booking_cutoff'] ?? $sessions[$type]['end'] ?? '00:00';
        $cutoffTime = strlen($cutoff) === 5 ? $cutoff . ':00' : $cutoff;
        $currentTime = ClinicClock::now()->format('H:i:s');
        return $currentTime >= $cutoffTime;
    }

    /**
     * Get current active session or next upcoming session type
     */
    public function getCurrentOrNextSessionType(Clinic $clinic, Carbon|string|null $date = null, ?Doctor $doctor = null): string
    {
        $dt = $date 
            ? (is_string($date) ? Carbon::parse($date, ClinicClock::TIMEZONE) : $date->copy()->setTimezone(ClinicClock::TIMEZONE)) 
            : ClinicClock::now();
        $dayOfWeek = strtolower($dt->format('l'));

        if ($dayOfWeek === 'sunday') {
            return 'SUNDAY_SINGLE';
        }

        $sessions = $this->getSessionsForDate($clinic, $dt, $doctor);
        if (isset($sessions['MORNING'])) {
            $currentTime = ClinicClock::now()->format('H:i');
            $morningEnd = $sessions['MORNING']['end'] ?? '09:00';
            if ($currentTime < $morningEnd) {
                return 'MORNING';
            }
        }

        return 'EVENING';
    }
}
