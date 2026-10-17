<?php

namespace App\Services;

class DoctorNamePresenter
{
    /**
     * Normalize stored name by stripping leading titles ("Dr.", "Dr", "Doctor", "Dr. Dr.", etc.)
     * Canonical stored name: "Rajesh Sharma"
     */
    /**
     * Normalize stored name by stripping leading titles ("Dr.", "Dr", "Doctor", "Dr. Dr.", etc.)
     * Canonical stored name: "Rajesh Sharma"
     */
    public static function stripTitle(mixed $target): string
    {
        if ($target === null) {
            return '';
        }

        if (is_object($target) && isset($target->name)) {
            $target = $target->name;
        }

        if (!is_string($target)) {
            return '';
        }

        $trimmed = trim($target);
        if ($trimmed === '') {
            return '';
        }

        // Recursively or via regex strip any leading Dr./Doctor title variations
        $stripped = preg_replace('/^(?:(?:Dr|Doctor)\.?\s*)+/i', '', $trimmed);

        return trim($stripped ?? '');
    }

    /**
     * Authoritative presenter: Always returns formatted name with exactly single "Dr. " prefix
     * e.g. "Dr. Rajesh Sharma"
     * Idempotent: whether input is "Rajesh Sharma", "Dr. Rajesh Sharma", or "Dr. Dr. Rajesh Sharma",
     * or a Doctor model object, it ALWAYS returns "Dr. Rajesh Sharma".
     */
    public static function format(mixed $target): string
    {
        $clean = self::stripTitle($target);
        if ($clean === '') {
            return '';
        }

        return 'Dr. ' . $clean;
    }
}

