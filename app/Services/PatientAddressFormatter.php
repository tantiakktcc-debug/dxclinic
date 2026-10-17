<?php

namespace App\Services;

class PatientAddressFormatter
{
    /**
     * Format patient address for display and printing (Section 62, 101)
     * Format: "Address, City" (e.g., "House 25, Lanka, Varanasi")
     * Must never output "City: Varanasi", "City - Varanasi", or "Varanasi City".
     */
    public static function format(?string $address, ?string $city): string
    {
        $cleanAddress = trim((string) $address);
        $cleanCity = trim((string) $city);

        // Remove any trailing commas or redundant whitespace
        $cleanAddress = rtrim($cleanAddress, " ,\t\n\r\0\x0B");
        $cleanCity = trim($cleanCity, " ,\t\n\r\0\x0B");

        if ($cleanAddress !== '' && $cleanCity !== '') {
            // Avoid duplicating city if already ends with city
            if (str_ends_with(strtolower($cleanAddress), strtolower($cleanCity))) {
                return $cleanAddress;
            }
            return "{$cleanAddress}, {$cleanCity}";
        }

        if ($cleanAddress !== '') {
            return $cleanAddress;
        }

        return $cleanCity;
    }
}
