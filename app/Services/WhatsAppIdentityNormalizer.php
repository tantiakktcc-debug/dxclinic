<?php

namespace App\Services;

class WhatsAppIdentityNormalizer
{
    /**
     * Standard development/business WhatsApp fallback line for local transport
     */
    public const DEFAULT_BUSINESS_NUMBER = '919999999999';

    /**
     * Normalize an input into a canonical digits-only international WhatsApp identity.
     *
     * Examples:
     * - "+91 9876543210"         => "919876543210"
     * - "919876543210@s.whatsapp.net" => "919876543210"
     * - "9876543210"             => "919876543210" (Indian 10-digit default)
     * - "919876543210:1@s.whatsapp.net" => "919876543210"
     */
    public static function normalize(?string $input): string
    {
        if ($input === null) {
            return '';
        }

        $val = trim($input);

        // Strip JID domain if present (@s.whatsapp.net, @c.us, @lid, etc.)
        if (str_contains($val, '@')) {
            $parts = explode('@', $val, 2);
            $val = $parts[0];
        }

        // Strip device suffix (e.g. :0, :12)
        $val = preg_replace('/:\d+$/', '', $val);

        // Extract digits only
        $digits = preg_replace('/[^0-9]/', '', $val);

        // Standard Indian mobile number normalization (10 digits => prepend country code 91)
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        // If 11 digits starting with 0 (e.g. 09876543210) => convert to 91...
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '91' . substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Convert to standard WhatsApp individual JID (@s.whatsapp.net)
     */
    public static function toJid(?string $input): string
    {
        $normalized = self::normalize($input);
        if ($normalized === '') {
            return '';
        }

        return $normalized . '@s.whatsapp.net';
    }

    /**
     * Extract standard 10-digit mobile number for local patient directory lookup.
     */
    public static function to10Digit(?string $input): string
    {
        $normalized = self::normalize($input);
        return substr($normalized, -10);
    }

    /**
     * Format into user-friendly international display format (e.g. +91 9876543210).
     */
    public static function toDisplay(?string $input): string
    {
        $normalized = self::normalize($input);
        if ($normalized === '') {
            return '';
        }

        if (strlen($normalized) === 12 && str_starts_with($normalized, '91')) {
            return '+91 ' . substr($normalized, 2, 5) . ' ' . substr($normalized, 7);
        }

        return '+' . $normalized;
    }

    /**
     * Check if a given number is the connected clinic/business transport identity.
     */
    public static function isBusinessNumber(?string $input, ?string $configuredBusiness = null): bool
    {
        $normalized = self::normalize($input);
        if ($normalized === '') {
            return false;
        }

        $business = self::normalize($configuredBusiness ?? config('dxclinic.whatsapp.dev_test_phone', self::DEFAULT_BUSINESS_NUMBER));

        return $normalized === $business;
    }

    /**
     * Check if a given number is a customer/patient sender.
     */
    public static function isPatientNumber(?string $input, ?string $configuredBusiness = null): bool
    {
        return !self::isBusinessNumber($input, $configuredBusiness);
    }
}
