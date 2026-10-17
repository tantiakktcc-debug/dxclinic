<?php

namespace App\Services;

use Carbon\Carbon;

class ClinicClock
{
    public const TIMEZONE = 'Asia/Kolkata';

    /**
     * Get clinic authoritative timezone name
     */
    public static function timezone(): string
    {
        return self::TIMEZONE;
    }

    /**
     * Get current clinic time in Asia/Kolkata timezone
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * Get today's date in Asia/Kolkata timezone
     */
    public static function today(): Carbon
    {
        return Carbon::today(self::TIMEZONE);
    }

    /**
     * Get tomorrow's date in Asia/Kolkata timezone
     */
    public static function tomorrow(): Carbon
    {
        return Carbon::tomorrow(self::TIMEZONE);
    }

    /**
     * Set test clock in clinic timezone for tests
     */
    public static function setTestNow(Carbon|string|null $testNow = null): void
    {
        if ($testNow === null) {
            Carbon::setTestNow();
        } elseif (is_string($testNow)) {
            Carbon::setTestNow(Carbon::parse($testNow, self::TIMEZONE));
        } else {
            Carbon::setTestNow($testNow->copy()->setTimezone(self::TIMEZONE));
        }
    }

    /**
     * Validate if date is allowed based on clinic's max_advance_booking_days setting (default 1 = Today or Tomorrow)
     */
    public static function isAllowedDate(Carbon|string $date, ?int $maxAdvanceDays = null): bool
    {
        $d = is_string($date) 
            ? Carbon::parse($date, self::TIMEZONE)->toDateString() 
            : $date->copy()->setTimezone(self::TIMEZONE)->toDateString();

        $maxDays = $maxAdvanceDays ?? TenantContext::clinic()?->max_advance_booking_days ?? 1;

        $today = self::today()->toDateString();
        $maxDate = self::today()->addDays($maxDays)->toDateString();

        return $d >= $today && $d <= $maxDate;
    }

    /**
     * Check if a given date string is today in clinic timezone
     */
    public static function isToday(Carbon|string $date): bool
    {
        $d = is_string($date) 
            ? Carbon::parse($date, self::TIMEZONE)->toDateString() 
            : $date->copy()->setTimezone(self::TIMEZONE)->toDateString();

        return $d === self::today()->toDateString();
    }

    /**
     * Check if a given date string is tomorrow in clinic timezone
     */
    public static function isTomorrow(Carbon|string $date): bool
    {
        $d = is_string($date) 
            ? Carbon::parse($date, self::TIMEZONE)->toDateString() 
            : $date->copy()->setTimezone(self::TIMEZONE)->toDateString();

        return $d === self::tomorrow()->toDateString();
    }
}
