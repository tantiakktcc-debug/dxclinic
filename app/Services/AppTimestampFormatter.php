<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * AppTimestampFormatter
 *
 * Authoritative display formatter for all clinic-facing timestamps.
 * Guarantees that every user-facing timestamp in WhatsApp Desk, Queue,
 * and appointments is rendered strictly in the authoritative application
 * timezone (Asia/Kolkata, UTC+05:30).
 *
 * RULES & INVARIANTS:
 * - NO manual "+5.5 hours" string or integer arithmetic.
 * - ALWAYS timezone-aware via Carbon->setTimezone(ClinicClock::TIMEZONE).
 * - Honest delivery states: never fabricates delivery times if unavailable.
 */
class AppTimestampFormatter
{
    /**
     * Convert any timestamp representation into a Carbon instance in Asia/Kolkata.
     */
    public static function toClinicTime(mixed $timestamp): ?Carbon
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        if ($timestamp instanceof Carbon) {
            return $timestamp->copy()->setTimezone(ClinicClock::TIMEZONE);
        }

        if ($timestamp instanceof DateTimeInterface) {
            return Carbon::instance($timestamp)->setTimezone(ClinicClock::TIMEZONE);
        }

        // Normalize first if raw integer or string
        $normalizedUtc = WhatsAppTimestampNormalizer::normalize($timestamp);
        if ($normalizedUtc) {
            return $normalizedUtc->setTimezone(ClinicClock::TIMEZONE);
        }

        return null;
    }

    /**
     * Format timestamp for clinic display with custom format (default: 'h:i A', e.g. '02:24 PM').
     */
    public static function formatForClinic(mixed $timestamp, string $format = 'h:i A', string $fallback = '--:--'): string
    {
        $clinicTime = self::toClinicTime($timestamp);
        if (!$clinicTime) {
            return $fallback;
        }

        return $clinicTime->format($format);
    }

    /**
     * Format time only: 'h:i A' (e.g. '02:24 PM').
     */
    public static function formatTime(mixed $timestamp, string $fallback = '--:--'): string
    {
        return self::formatForClinic($timestamp, 'h:i A', $fallback);
    }

    /**
     * Format time in 24-hour notation: 'H:i' (e.g. '14:24').
     */
    public static function format24HourTime(mixed $timestamp, string $fallback = '--:--'): string
    {
        return self::formatForClinic($timestamp, 'H:i', $fallback);
    }

    /**
     * Format date only: 'd M Y' (e.g. '06 Oct 2026').
     */
    public static function formatDate(mixed $timestamp, string $fallback = 'N/A'): string
    {
        return self::formatForClinic($timestamp, 'd M Y', $fallback);
    }

    /**
     * Format short date: 'd M' (e.g. '06 Oct').
     */
    public static function formatShortDate(mixed $timestamp, string $fallback = 'N/A'): string
    {
        return self::formatForClinic($timestamp, 'd M', $fallback);
    }

    /**
     * Format date and time: 'd M Y, h:i A' (e.g. '06 Oct 2026, 02:24 PM').
     */
    public static function formatDateTime(mixed $timestamp, string $fallback = 'N/A'): string
    {
        return self::formatForClinic($timestamp, 'd M Y, h:i A', $fallback);
    }

    /**
     * Format date and time with explicit IST indicator: 'd M Y, h:i A IST'.
     */
    public static function formatDateTimeWithTz(mixed $timestamp, string $fallback = 'N/A'): string
    {
        $clinicTime = self::toClinicTime($timestamp);
        if (!$clinicTime) {
            return $fallback;
        }

        return $clinicTime->format('d M Y, h:i A') . ' IST';
    }

    /**
     * Relative difference for humans in clinic context.
     */
    public static function diffForHumans(mixed $timestamp, bool $short = true, string $fallback = ''): string
    {
        $clinicTime = self::toClinicTime($timestamp);
        if (!$clinicTime) {
            return $fallback;
        }

        return $clinicTime->diffForHumans(ClinicClock::now(), $short);
    }

    /**
     * Format message delivery status honestly without fabricating times.
     */
    public static function formatDeliveryStatus(
        ?string $status,
        mixed $deliveredAt = null,
        mixed $sentAt = null,
        mixed $readAt = null
    ): string {
        $upperStatus = strtoupper($status ?? 'SENT');

        if ($upperStatus === 'READ') {
            $formattedRead = $readAt ? self::formatTime($readAt) : null;
            return $formattedRead ? "Read at {$formattedRead}" : 'Read';
        }

        if ($upperStatus === 'DELIVERED') {
            $formattedDelivered = $deliveredAt ? self::formatTime($deliveredAt) : null;
            return $formattedDelivered ? "Delivered at {$formattedDelivered}" : 'Delivered (time unavailable)';
        }

        if ($upperStatus === 'SENT') {
            $formattedSent = $sentAt ? self::formatTime($sentAt) : null;
            return $formattedSent ? "Sent at {$formattedSent}" : 'Sent';
        }

        if ($upperStatus === 'PENDING') {
            return 'Pending';
        }

        if ($upperStatus === 'FAILED') {
            return 'Failed';
        }

        return $upperStatus;
    }
}
