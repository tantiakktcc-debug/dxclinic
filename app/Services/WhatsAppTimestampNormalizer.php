<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * WhatsAppTimestampNormalizer
 *
 * Normalizes timestamps received from WhatsApp / Baileys / Meta providers
 * into canonical application timestamps (Carbon in UTC).
 *
 * CONTRACT & SEMANTICS:
 * - Provider timestamps may arrive as:
 *   - Unix epoch seconds (standard Baileys / protobuf messageTimestamp)
 *   - Unix epoch milliseconds (JS Date.now())
 *   - Long / Protobuf integer representations (with low/high bits)
 *   - ISO 8601 strings (Meta Cloud API / HTTP webhooks)
 *   - Carbon / DateTime instances
 * - Output is ALWAYS a canonical UTC Carbon instance (ready for DB storage).
 * - Explicit unit detection prevents double-conversion bugs.
 * - Does NOT apply manual "+5.5 hours" string math.
 */
class WhatsAppTimestampNormalizer
{
    /**
     * Threshold to differentiate between epoch seconds and epoch milliseconds.
     * 1e11 represents Sat Mar 03 1973 in milliseconds, or year 5138 in seconds.
     * Valid contemporary epoch seconds are ~1.7e9 (year 2024-2026).
     * Valid contemporary epoch milliseconds are ~1.7e12.
     */
    public const MILLISECOND_THRESHOLD = 100_000_000_000; // 1e11

    /**
     * Normalize any provider timestamp into a canonical UTC Carbon instance.
     */
    public static function normalize(mixed $timestamp): ?Carbon
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        // 1. Already a Carbon instance
        if ($timestamp instanceof Carbon) {
            return $timestamp->copy()->setTimezone('UTC');
        }

        // 2. PHP DateTimeInterface
        if ($timestamp instanceof DateTimeInterface) {
            return Carbon::instance($timestamp)->setTimezone('UTC');
        }

        // 3. Protobuf Long object or array with {low, high} bits
        if (is_object($timestamp) && method_exists($timestamp, 'toNumber')) {
            $timestamp = $timestamp->toNumber();
        } elseif (is_object($timestamp) && isset($timestamp->low, $timestamp->high)) {
            $timestamp = self::longToNumber((int) $timestamp->high, (int) $timestamp->low);
        } elseif (is_array($timestamp) && isset($timestamp['low'], $timestamp['high'])) {
            $timestamp = self::longToNumber((int) $timestamp['high'], (int) $timestamp['low']);
        } elseif (is_array($timestamp) && isset($timestamp['seconds'])) {
            $timestamp = (int) $timestamp['seconds'];
        }

        // 4. Numeric or numeric-string (Epoch seconds or milliseconds)
        if (is_numeric($timestamp)) {
            $numericVal = (float) $timestamp;

            if ($numericVal <= 0) {
                return null;
            }

            // Milliseconds detection
            if ($numericVal >= self::MILLISECOND_THRESHOLD) {
                $seconds = (int) floor($numericVal / 1000);
                return Carbon::createFromTimestampUTC($seconds)->setTimezone('UTC');
            }

            // Seconds detection
            return Carbon::createFromTimestampUTC((int) $numericVal)->setTimezone('UTC');
        }

        // 5. String parsing (ISO 8601, RFC 3339, or MySQL DATETIME)
        if (is_string($timestamp)) {
            $trimmed = trim($timestamp);
            if ($trimmed === '') {
                return null;
            }

            try {
                // Carbon parses ISO strings with offsets and converts canonical to UTC
                return Carbon::parse($trimmed)->setTimezone('UTC');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Convert 64-bit Long (high and low 32-bit integers) to number.
     */
    protected static function longToNumber(int $high, int $low): float
    {
        $unsignedLow = ($low < 0) ? ($low + 4294967296) : $low;
        return ($high * 4294967296) + $unsignedLow;
    }
}
