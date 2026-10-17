<?php

namespace App\Modules\WhatsApp\Domain;

use App\Models\Clinic;
use App\Services\ClinicClock;
use Carbon\Carbon;

class AiBookingAssistant
{
    /**
     * Parse intent from natural language patient input
     * Contract Section 14: AI assists understanding, backend enforces business rules
     */
    public function detectIntent(string $text): string
    {
        $clean = mb_strtolower(trim($text));

        // 1. Critical Medical Emergency Check (Section 37)
        if ($this->detectEmergency($clean)) {
            return 'EMERGENCY';
        }

        // 2. Prompt Injection Resistance (Section 81)
        if ($this->isPromptInjection($clean)) {
            return 'PROMPT_INJECTION';
        }

        // 2b. Numbered Menu shortcuts
        if ($clean === '1') {
            return 'BOOKING';
        }
        if ($clean === '2') {
            return 'HOURS';
        }
        if ($clean === '3') {
            return 'FEES';
        }
        if ($clean === '4') {
            return 'DOCTORS';
        }
        if ($clean === '5') {
            return 'LOCATION';
        }
        if ($clean === '6') {
            return 'SERVICES';
        }
        if ($clean === '7') {
            return 'MENU';
        }

        // 3. Human Handoff Intent
        if (preg_match('/\b(human|reception|receptionist|staff|call me|talk to someone|agent|operator)\b/i', $clean)) {
            return 'HUMAN';
        }

        // 4. Cancellation Intent
        if (preg_match('/\b(cancel|withdraw|drop|abort)\b/i', $clean)) {
            return 'CANCEL';
        }

        // 5. Status / Token Tracking
        if (preg_match('/\b(status|track|my token|check booking|check appointment|check slot|token status|position|ahead)\b/i', $clean)) {
            return 'STATUS';
        }

        // 6. Clinic Operating Hours & Timings
        if (preg_match('/\b(timing|timings|hours|time|schedule|open|closed|kya time hai|subah|shaam|sunday)\b/i', $clean)) {
            return 'HOURS';
        }

        // 7. Location & Directions
        if (preg_match('/\b(where|location|address|map|directions|kahan hai|clinic address|reach|landmark)\b/i', $clean)) {
            return 'LOCATION';
        }

        // 8. Consultation Fees & Revisit Policy
        if (preg_match('/\b(fee|fees|cost|charge|charges|price|rate|kitna lagta hai|kitne rupaye|revisit)\b/i', $clean)) {
            return 'FEES';
        }

        // 9. Doctors & Specializations
        if (preg_match('/\b(doctors|specialist|specialization|qualification|who is doctor|doctor list|physicians)\b/i', $clean)) {
            return 'DOCTORS';
        }

        // 10. Services, Diagnostic Tests & Lab
        if (preg_match('/\b(service|services|diagnostic|diagnostics|test|tests|blood test|lab|investigation|checkup|package)\b/i', $clean)) {
            return 'SERVICES';
        }

        // 11. Policies & Instructions
        if (preg_match('/\b(policy|policies|rules|fasting|documents|what to bring|preparation)\b/i', $clean)) {
            return 'POLICIES';
        }

        // 12. Booking Intent
        if (preg_match('/\b(book|appointment|token|slot|milna hai|dikhana hai|visit|schedule appointment)\b/i', $clean)) {
            return 'BOOKING';
        }

        // 13. Menu / Greeting / General Help
        if (preg_match('/\b(hi|hello|hey|namaste|pranam|start|menu|help|info|shuru)\b/i', $clean)) {
            return 'MENU';
        }

        return 'UNKNOWN';
    }

    /**
     * Detect medical emergency indicators (Section 37)
     */
    public function detectEmergency(string $text): bool
    {
        $patterns = [
            '/\b(chest pain|heart attack|angina|cardiac arrest)\b/i',
            '/\b(cannot breathe|can\'t breathe|shortness of breath|suffocating|choking|difficulty breathing)\b/i',
            '/\b(stroke|facial droop|paralysis|slurred speech|sudden weakness)\b/i',
            '/\b(unconscious|passed out|fainted|loss of consciousness|unresponsive)\b/i',
            '/\b(heavy bleeding|bleeding profusely|hemorrhage|blood vomiting)\b/i',
            '/\b(poison|poisoning|swallowed chemical|overdose)\b/i',
            '/\b(seizure|convulsion|epilepsy fit)\b/i',
            '/\b(suicide|suicidal|kill myself)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect prompt injection attempts (Section 81)
     */
    public function isPromptInjection(string $text): bool
    {
        $patterns = [
            '/\bignore\s+(all\s+)?(previous\s+)?(instructions|rules|prompts|directions)\b/i',
            '/\b(disregard system prompt|system prompt|override rules)\b/i',
            '/\b(switch clinic|change clinic|use clinic \d+|clinic_id\s*=\s*\d+)\b/i',
            '/\b(act as|pretend to be|you are now|jailbreak|developer mode)\b/i',
            '/\b(drop table|select \* from|delete from|exec\s*\(|eval\s*\()\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Safely extract candidate booking date from text
     * Returns YYYY-MM-DD or null
     */
    public function extractDate(string $text, Clinic $clinic): ?string
    {
        $clean = mb_strtolower(trim($text));
        $today = ClinicClock::today();

        if (preg_match('/\b(today|aaj|current date)\b/i', $clean)) {
            return $today->toDateString();
        }

        if (preg_match('/\b(tomorrow|kal|next day)\b/i', $clean)) {
            return $today->copy()->addDay()->toDateString();
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $clean, $matches)) {
            $candidate = $matches[1];
            if (ClinicClock::isAllowedDate($candidate, $clinic->max_advance_booking_days)) {
                return $candidate;
            }
        }

        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $clean, $matches)) {
            try {
                $candidate = sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
                if (ClinicClock::isAllowedDate($candidate, $clinic->max_advance_booking_days)) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                // Ignore parse failures
            }
        }

        return null;
    }

    /**
     * Extract session preference from natural language
     */
    public function extractSession(string $text): ?string
    {
        $clean = mb_strtolower(trim($text));

        if (preg_match('/\b(morning|subah|am|early|morning slot)\b/i', $clean)) {
            return 'MORNING';
        }

        if (preg_match('/\b(evening|shaam|pm|afternoon|dopahar|evening slot|night)\b/i', $clean)) {
            return 'EVENING';
        }

        if (preg_match('/\b(sunday|sunday session|single)\b/i', $clean)) {
            return 'SUNDAY_SINGLE';
        }

        return null;
    }

    /**
     * Extract token number from status queries (e.g., "status 003", "check token 12")
     */
    public function extractToken(string $text): ?string
    {
        if (preg_match('/\b(?:token|number|no\.?|status)?\s*#?([0-9]{1,4})\b/i', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract patient demographic hints (Name, Age, Gender) from text
     * Example: "Rahul Sharma, 28, Male" or "Priya, 34, Female, Varanasi"
     */
    public function extractPatientDetails(string $text): ?array
    {
        $parts = array_map('trim', explode(',', $text));

        if (count($parts) >= 2) {
            $name = $parts[0];
            $age = null;
            $sex = 'MALE';
            $city = 'Varanasi';

            foreach (array_slice($parts, 1) as $part) {
                if (is_numeric($part)) {
                    $age = (int) $part;
                } elseif (preg_match('/^(male|m|purush)$/i', $part)) {
                    $sex = 'MALE';
                } elseif (preg_match('/^(female|f|mahila|aurat)$/i', $part)) {
                    $sex = 'FEMALE';
                } elseif (preg_match('/^(other|o)$/i', $part)) {
                    $sex = 'OTHER';
                } else {
                    $city = $part;
                }
            }

            if (!empty($name) && $age !== null) {
                return [
                    'name' => $name,
                    'age' => $age,
                    'sex' => $sex,
                    'city' => $city,
                ];
            }
        }

        return null;
    }
}
