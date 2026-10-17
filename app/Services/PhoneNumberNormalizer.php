<?php

namespace App\Services;

use InvalidArgumentException;

class PhoneNumberNormalizer
{
    public const ERROR_MESSAGE = 'Enter exactly 10 digits or use +91 followed by 10 digits. Example: +91 9876543210';

    /**
     * Validate and normalize a phone number into canonical "+91 9876543210" format.
     *
     * Accepted Formats:
     * A. 9876543210 (10 digits)
     * B. +919876543210 (country code without space)
     * C. +91 9876543210 (canonical)
     * D. 09876543210 (11 digits with leading 0)
     *
     * @param string|null $input
     * @return string
     * @throws InvalidArgumentException
     */
    public static function normalize(?string $input): string
    {
        if ($input === null || trim($input) === '') {
            throw new InvalidArgumentException(self::ERROR_MESSAGE);
        }

        $raw = trim($input);

        // Disallow hyphens, letters or invalid characters
        if (preg_match('/[a-zA-Z\-@#$%^&*()_+=\[\]{}|\\:;"\'<>,.?\/]/', str_replace(['+91', '+'], '', $raw))) {
            throw new InvalidArgumentException(self::ERROR_MESSAGE);
        }

        // Format C & B: Starts with +91
        if (str_starts_with($raw, '+91')) {
            $remainder = substr($raw, 3);
            // Remainder must be exactly 10 digits, optionally preceded by a single space
            if (str_starts_with($remainder, ' ')) {
                $remainder = substr($remainder, 1);
            }
            if (!preg_match('/^\d{10}$/', $remainder)) {
                throw new InvalidArgumentException(self::ERROR_MESSAGE);
            }
            return '+91 ' . $remainder;
        }

        // Must not start with other prefixes like +
        if (str_starts_with($raw, '+')) {
            throw new InvalidArgumentException(self::ERROR_MESSAGE);
        }

        // Format D: Starts with leading 0 followed by 10 digits
        if (str_starts_with($raw, '0')) {
            $remainder = substr($raw, 1);
            if (!preg_match('/^\d{10}$/', $remainder)) {
                throw new InvalidArgumentException(self::ERROR_MESSAGE);
            }
            return '+91 ' . $remainder;
        }

        // Format A: Exactly 10 digits
        if (preg_match('/^\d{10}$/', $raw)) {
            return '+91 ' . $raw;
        }

        throw new InvalidArgumentException(self::ERROR_MESSAGE);
    }

    /**
     * Check if a phone number matches any of the accepted formats.
     */
    public static function isValid(?string $input): bool
    {
        try {
            self::normalize($input);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Extract raw 10 digits from any valid format or return raw if 10 digits.
     */
    public static function toRaw10(?string $input): ?string
    {
        try {
            $normalized = self::normalize($input);
            return substr($normalized, 4); // Strip "+91 "
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
