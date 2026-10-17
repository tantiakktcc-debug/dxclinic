<?php

namespace App\Services;

use App\Models\Clinic;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicSlugService
{
    /**
     * Canonical list of common generic clinic noise words to strip when sensible.
     */
    protected const NOISE_WORDS = [
        'super',
        'superspecialty',
        'superspeciality',
        'specialty',
        'speciality',
        'multispecialty',
        'multispeciality',
        'polyclinic',
        'nursinghome',
        'hospital',
        'healthcare',
        'center',
        'centre',
        'clinic',
    ];

    /**
     * Known city abbreviations for human-friendly collision suffixes.
     */
    protected const CITY_ABBREVIATIONS = [
        'varanasi' => 'vns',
        'lucknow'  => 'lko',
        'delhi'    => 'del',
        'mumbai'   => 'mum',
        'kolkata'  => 'kol',
        'patna'    => 'pat',
        'kanpur'   => 'knp',
        'prayagraj' => 'pry',
        'allahabad' => 'ald',
    ];

    /**
     * Normalize an input string into a canonical, URL-safe, lowercase alphanumeric slug.
     */
    public static function normalize(string $input): string
    {
        // 1. Lowercase and trim
        $clean = strtolower(trim($input));

        // 2. Remove accents and transliterate if needed
        $clean = Str::ascii($clean);

        // 3. Remove all non-alphanumeric characters (keep only a-z and 0-9)
        $clean = preg_replace('/[^a-z0-9]/', '', $clean);

        return $clean ?? '';
    }

    /**
     * Generate the primary base slug from a clinic name, intelligently stripping noise words
     * if the remaining core identifier is sufficiently descriptive (at least 3 characters).
     */
    public static function generateBaseSlug(string $clinicName): string
    {
        $normalizedFull = static::normalize($clinicName);

        // If the name is already concise (<= 6 chars) or matches "demo", preserve it
        if (strlen($normalizedFull) <= 6 || str_starts_with($normalizedFull, 'demo')) {
            return $normalizedFull ?: 'clinic';
        }

        // Tokenize words from raw clinic name
        $words = preg_split('/[\s\-_,\.]+/', strtolower(trim(Str::ascii($clinicName))), -1, PREG_SPLIT_NO_EMPTY);
        $filteredWords = [];

        foreach ($words as $word) {
            $cleanWord = preg_replace('/[^a-z0-9]/', '', $word);
            if (empty($cleanWord)) {
                continue;
            }
            // Strip noise words unless it's the only word
            if (in_array($cleanWord, self::NOISE_WORDS, true)) {
                continue;
            }
            $filteredWords[] = $cleanWord;
        }

        $condensed = implode('', $filteredWords);

        // If filtering stripped everything or made it too short, fall back to the full normalized name
        if (strlen($condensed) < 3) {
            $condensed = $normalizedFull;
        }

        return $condensed ?: 'clinic';
    }

    /**
     * Generate an ordered list of human-readable candidate slugs.
     *
     * Progression:
     * 1. Core clean name (e.g. "sharmacare", "arogya")
     * 2. Core clean name + city abbreviation (e.g. "sharmacarevns")
     * 3. Core clean name + full city name (e.g. "sharmacarevaranasi")
     * 4. Core clean name + "clinic" (e.g. "sharmacareclinic")
     * 5. Core clean name + city abbreviation + "clinic" (e.g. "sharmacarevnsclinic")
     * 6. Sequential numbers (e.g. "sharmacare2", "sharmacare3", ...)
     */
    public static function generateCandidates(string $clinicName, ?string $city = null): array
    {
        $base = static::generateBaseSlug($clinicName);
        $candidates = [$base];

        $cleanCity = $city ? static::normalize($city) : null;
        $cityAbbr = $cleanCity && isset(self::CITY_ABBREVIATIONS[$cleanCity])
            ? self::CITY_ABBREVIATIONS[$cleanCity]
            : null;

        // Candidate 2: City Abbreviation
        if ($cityAbbr && !str_ends_with($base, $cityAbbr)) {
            $candidates[] = $base . $cityAbbr;
        }

        // Candidate 3: Full City Name
        if ($cleanCity && !str_ends_with($base, $cleanCity)) {
            $candidates[] = $base . $cleanCity;
        }

        // Candidate 4: Suffix "clinic" if not present
        if (!str_ends_with($base, 'clinic')) {
            $candidates[] = $base . 'clinic';
        }

        // Candidate 5: City + clinic
        if ($cityAbbr && !str_ends_with($base, 'clinic')) {
            $candidates[] = $base . $cityAbbr . 'clinic';
        }

        // Candidate 6+: Sequential human numbers
        for ($i = 2; $i <= 50; $i++) {
            $candidates[] = $base . $i;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Check if a public slug is currently available in the database.
     */
    public static function isSlugAvailable(string $slug, ?int $excludeClinicId = null): bool
    {
        $normalized = static::normalize($slug);
        if (empty($normalized)) {
            return false;
        }

        $query = Clinic::where('public_slug', $normalized);
        if ($excludeClinicId) {
            $query->where('id', '!=', $excludeClinicId);
        }

        return !$query->exists();
    }

    /**
     * Generate the first available unique public slug from candidates.
     */
    public static function generateUniqueSlug(string $clinicName, ?string $city = null, ?int $excludeClinicId = null): string
    {
        $candidates = static::generateCandidates($clinicName, $city);

        foreach ($candidates as $candidate) {
            if (static::isSlugAvailable($candidate, $excludeClinicId)) {
                return $candidate;
            }
        }

        // Fallback with random alphanumeric suffix if 50 candidates are exhausted
        do {
            $fallback = substr(static::generateBaseSlug($clinicName), 0, 15) . rand(100, 9999);
        } while (!static::isSlugAvailable($fallback, $excludeClinicId));

        return $fallback;
    }

    /**
     * Validate an explicitly requested custom slug or generate a unique one.
     *
     * @throws \InvalidArgumentException if custom slug is invalid or already taken by another clinic
     */
    public static function validateAndAssignSlug(Clinic $clinic, ?string $customSlug = null): string
    {
        if (!empty($customSlug)) {
            $normalized = static::normalize($customSlug);
            if (strlen($normalized) < 3) {
                throw new \InvalidArgumentException('Public slug must be at least 3 alphanumeric characters.');
            }
            if (strlen($normalized) > 80) {
                throw new \InvalidArgumentException('Public slug cannot exceed 80 characters.');
            }

            if (!static::isSlugAvailable($normalized, $clinic->id)) {
                throw new \InvalidArgumentException("Public slug '{$normalized}' is already in use by another clinic.");
            }

            $clinic->public_slug = $normalized;
            return $normalized;
        }

        $slug = static::generateUniqueSlug($clinic->name, $clinic->city, $clinic->id);
        $clinic->public_slug = $slug;
        return $slug;
    }

    /**
     * Save a clinic with concurrency and race-condition collision protection.
     *
     * Retries automatically with the next available candidate if a database UNIQUE constraint
     * violation (SQLSTATE 23000 / Error 1062) occurs.
     *
     * @throws \RuntimeException if retries are exhausted
     */
    public static function saveWithConcurrencyProtection(Clinic $clinic, ?callable $saveCallback = null, int $maxRetries = 5): Clinic
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $attempt++;
            try {
                if (empty($clinic->public_slug)) {
                    $clinic->public_slug = static::generateUniqueSlug($clinic->name, $clinic->city, $clinic->id);
                }

                if ($saveCallback) {
                    $saveCallback($clinic);
                } else {
                    $clinic->save();
                }

                return $clinic;
            } catch (QueryException $e) {
                // MySQL 1062 = Duplicate entry, SQLSTATE 23000
                $isDuplicateSlug = ($e->errorInfo[1] ?? 0) === 1062 && str_contains($e->getMessage(), 'public_slug');

                if ($isDuplicateSlug && $attempt < $maxRetries) {
                    // Regenerate with next candidate and retry
                    $clinic->public_slug = static::generateUniqueSlug($clinic->name, $clinic->city, $clinic->id);
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException("Failed to resolve public slug collision after {$maxRetries} attempts.");
    }

    /**
     * Find a clinic by public slug.
     */
    public static function findByPublicSlug(?string $slug): ?Clinic
    {
        if (empty($slug)) {
            return null;
        }

        $normalized = static::normalize($slug);
        if (empty($normalized)) {
            return null;
        }

        return Clinic::where('public_slug', $normalized)->first();
    }
}
