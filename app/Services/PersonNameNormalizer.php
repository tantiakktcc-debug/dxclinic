<?php

namespace App\Services;

use InvalidArgumentException;

class PersonNameNormalizer
{
    public const ERROR_MESSAGE = 'Name may only contain letters and single spaces. Digits, symbols, and HTML are not permitted. Example: Kundan Kumar or राम कुमार';

    /**
     * Validate and normalize a person's name.
     *
     * Rules:
     * - Unicode letters (\p{L}) and spaces only.
     * - Trim leading and trailing whitespace.
     * - Collapse multiple consecutive internal spaces to a single space.
     * - Reject numbers, symbols, emoji, HTML, script tags.
     *
     * @param string|null $input
     * @return string
     * @throws InvalidArgumentException
     */
    public static function normalize(?string $input): string
    {
        if ($input === null) {
            throw new InvalidArgumentException(self::ERROR_MESSAGE);
        }

        // Strip HTML tags
        $stripped = strip_tags($input);
        if ($stripped !== $input) {
            throw new InvalidArgumentException(self::ERROR_MESSAGE);
        }

        // Trim leading and trailing whitespace
        $trimmed = trim($input);
        if ($trimmed === '' || mb_strlen($trimmed) < 2 || mb_strlen($trimmed) > 100) {
            throw new InvalidArgumentException(self::ERROR_MESSAGE);
        }

        // Collapse multiple whitespace characters into a single space
        $collapsed = preg_replace('/\s+/u', ' ', $trimmed);

        // Check that name consists exclusively of Unicode letters, marks (matras/accents), and spaces
        if (!preg_match('/^[\p{L}\p{M} ]+$/u', $collapsed)) {
            throw new InvalidArgumentException(self::ERROR_MESSAGE);
        }

        return $collapsed;
    }

    /**
     * Check if a name is valid.
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
}
