<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\ClinicKnowledgeDocument;
use App\Models\Doctor;
use App\Models\FeeMaster;
use Illuminate\Support\Facades\Log;

class ClinicKnowledgeBaseService
{
    public function __construct(
        protected ClinicScheduleService $scheduleService
    ) {}

    /**
     * Ingest and synchronize structured clinic knowledge for a specific clinic.
     * Guaranteed tenant-scoped to $clinic->id.
     */
    public function seedClinicKnowledge(Clinic $clinic): array
    {
        return $this->ingestClinicKnowledge($clinic);
    }

    public function ingestClinicKnowledge(Clinic $clinic): array
    {
        $clinicId = $clinic->id;
        $createdOrUpdated = [];

        // 1. CLINIC_PROFILE & LOCATION
        $profileContent = "Clinic Name: {$clinic->name}\n" .
            "Tagline: " . ($clinic->tagline ?? 'Multi-Specialty Clinical Center') . "\n" .
            "Address: {$clinic->address}\n" .
            "City: {$clinic->city}, {$clinic->pincode}\n" .
            "Phone: {$clinic->phone}\n" .
            "Email: " . ($clinic->email ?? 'N/A') . "\n" .
            "Public Booking URL: " . rtrim((string) config('app.public_booking_url', 'https://dxclinic.example.local/book'), '/') . "/{$clinic->public_slug}\n" .
            "Directions: Located at {$clinic->address} in {$clinic->city}. Accessible by public and private transport.";

        $createdOrUpdated[] = $this->upsertDoc(
            $clinicId,
            'CLINIC_PROFILE',
            "About {$clinic->name} & Location",
            'CLINIC_PROFILE',
            $profileContent,
            ['name', 'address', 'location', 'where', 'phone', 'contact', 'city', 'reach', 'map', 'directions']
        );

        // 2. TIMINGS & SCHEDULE
        $schedule = $clinic->schedule_config ?? $clinic->defaultSchedule();
        $timingLines = ["Authoritative Operating Hours for {$clinic->name}:"];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $dayData = $schedule[$day] ?? null;
            if (!$dayData) {
                $timingLines[] = "• " . ucfirst($day) . ": Closed";
                continue;
            }
            $slots = [];
            foreach ($dayData as $sName => $times) {
                if (is_array($times) && count($times) === 2) {
                    $slots[] = ucfirst($sName) . " ({$times[0]} to {$times[1]})";
                }
            }
            $timingLines[] = "• " . ucfirst($day) . ": " . (empty($slots) ? 'Closed' : implode(', ', $slots));
        }
        $timingLines[] = "\nAdvance Booking: Up to {$clinic->max_advance_booking_days} day(s) in advance (Today and Tomorrow).";
        $timingLines[] = "Sunday Schedule: Single Morning Session (09:00 to 11:00).";

        $createdOrUpdated[] = $this->upsertDoc(
            $clinicId,
            'TIMINGS',
            "Clinic Operating Hours & Schedule",
            'TIMINGS',
            implode("\n", $timingLines),
            ['hours', 'timing', 'timings', 'open', 'closed', 'schedule', 'sunday', 'morning', 'evening', 'time']
        );

        // 3. DOCTORS & SPECIALIZATIONS
        $doctors = Doctor::where('clinic_id', $clinicId)->where('is_active', true)->get();
        $docLines = ["Doctors Available at {$clinic->name}:"];
        if ($doctors->isEmpty()) {
            $docLines[] = "General OPD consultation available during working sessions.";
        } else {
            foreach ($doctors as $doc) {
                $qual = $doc->qualification ? " ({$doc->qualification})" : "";
                $spec = $doc->specialization ? " - {$doc->specialization}" : " - General Medicine";
                $room = $doc->room_number ? " [Room {$doc->room_number}]" : "";
                $docLines[] = "• Dr. " . ltrim($doc->name, 'Dr. ') . "{$qual}{$spec}{$room}";
            }
        }
        $docLines[] = "\nTo book an appointment with any doctor, reply with BOOK.";

        $createdOrUpdated[] = $this->upsertDoc(
            $clinicId,
            'DOCTORS',
            "Doctor Profiles & Specializations",
            'DOCTORS',
            implode("\n", $docLines),
            ['doctor', 'doctors', 'physician', 'specialist', 'dr', 'who', 'consultant']
        );

        // 4. CONSULTATION FEES & REVISIT POLICY
        $feeLines = ["Consultation Fee Structure at {$clinic->name}:"];
        $defaultPhysicalFee = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $clinicId);
        $feeLines[] = "• Standard Physical OPD Consultation: " . ($defaultPhysicalFee !== null ? "₹" . number_format($defaultPhysicalFee, 2) : "₹600.00");

        if ($doctors->isNotEmpty()) {
            foreach ($doctors as $doc) {
                $docFee = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $clinicId, $doc->id);
                if ($docFee !== null) {
                    $feeLines[] = "• Dr. " . ltrim($doc->name, 'Dr. ') . ": ₹" . number_format($docFee, 2);
                }
            }
        }
        $feeLines[] = "• 5-Day OPD Revisit Policy: Follow-up visits within 5 days of consultation have a ₹0 revisit fee (Gross ₹600 with ₹600 waiver applied).";
        $feeLines[] = "• Payment Methods: Cash and UPI are accepted at reception upon arrival.";

        $createdOrUpdated[] = $this->upsertDoc(
            $clinicId,
            'FEES',
            "Consultation Fees & Revisit Rules",
            'FEES',
            implode("\n", $feeLines),
            ['fee', 'fees', 'cost', 'charge', 'charges', 'price', 'rate', 'revisit', 'followup', 'rupees']
        );

        // 5. SERVICES & DIAGNOSTICS
        $servicesContent = "Healthcare Services Offered at {$clinic->name}:\n" .
            "1. Outpatient (OPD) Consultations: General medicine, routine health assessments, vital monitoring.\n" .
            "2. Diagnostic Lab Services: Routine blood investigations, urine analysis, lipid profile, HbA1c, and metabolic panels.\n" .
            "3. Preventive Health Checkups: Annual health screening and diabetic monitoring packages.\n" .
            "4. Digital Prescriptions: High-precision standardized digital doctor prescriptions.\n" .
            "5. Live Queue Tracking: Real-time mobile token tracking from home.";

        $createdOrUpdated[] = $this->upsertDoc(
            $clinicId,
            'SERVICES',
            "Clinical & Diagnostic Services",
            'SERVICES',
            $servicesContent,
            ['service', 'services', 'diagnostic', 'diagnostics', 'tests', 'blood', 'lab', 'checkup', 'package']
        );

        // 6. CLINIC POLICIES (CANCELLATION & REFUNDS)
        $policiesContent = "Clinic Policies at {$clinic->name}:\n" .
            "• Appointment Cancellation: Patients may cancel before their session starts without any penalty. Cancelled tokens are retired and never reused to maintain queue integrity.\n" .
            "• Punctuality: Patients are requested to arrive at least 15 minutes before their scheduled session ends.\n" .
            "• Unified Token System: All booking channels (Reception, Online PWA, WhatsApp, Telephony) share one authoritative sequential token sequence.\n" .
            "• Payments & Receipts: Official computer-generated receipts are issued for all consultation and diagnostic payments at reception.";

        $createdOrUpdated[] = $this->upsertDoc(
            $clinicId,
            'POLICIES',
            "Clinic Policies, Cancellations & Rules",
            'POLICIES',
            $policiesContent,
            ['policy', 'policies', 'cancel', 'cancellation', 'refund', 'rules', 'arrival', 'late', 'absent']
        );

        // 7. PATIENT INSTRUCTIONS
        $instructionsContent = "Patient Visit Instructions for {$clinic->name}:\n" .
            "1. Documents to Bring: Please carry any previous medical records, past prescriptions, and diagnostic test reports.\n" .
            "2. Fasting Instructions: For fasting blood sugar (FBS) or lipid profile tests, please maintain 10-12 hours of overnight fasting.\n" .
            "3. Reception Check-In: On arrival, show your WhatsApp booking token number at reception to confirm arrival.\n" .
            "4. Live Queue: Monitor your position in the live queue via your mobile token link.";

        $createdOrUpdated[] = $this->upsertDoc(
            $clinicId,
            'PATIENT_INSTRUCTIONS',
            "Patient Preparation & Visit Instructions",
            'PATIENT_INSTRUCTIONS',
            $instructionsContent,
            ['bring', 'instructions', 'preparation', 'fasting', 'documents', 'records', 'checkin', 'what to carry']
        );

        // 8. GENERAL FAQS
        $faqContent = "Frequently Asked Questions for {$clinic->name}:\n\n" .
            "Q: Can I book an appointment for tomorrow?\n" .
            "A: Yes! You can book for Today or Tomorrow by replying with BOOK or 'Tomorrow morning'.\n\n" .
            "Q: How do I know my token number?\n" .
            "A: Once your booking is confirmed, you will receive an instant WhatsApp confirmation with your token number. You can also type STATUS anytime.\n\n" .
            "Q: What if I need to speak to a human receptionist?\n" .
            "A: Type HELP or HUMAN at any time, and you will be connected with our clinic reception staff.";

        $createdOrUpdated[] = $this->upsertDoc(
            $clinicId,
            'FAQ',
            "General Clinic FAQs",
            'FAQ',
            $faqContent,
            ['faq', 'question', 'help', 'human', 'reception', 'token', 'how', 'query']
        );

        return $createdOrUpdated;
    }

    /**
     * Search clinic knowledge with strict multi-tenant isolation.
     */
    public function searchKnowledge(Clinic|int $clinic, string $query, ?string $category = null, int $limit = 3): array
    {
        $clinicId = $clinic instanceof Clinic ? $clinic->id : (int) $clinic;

        // 1. Strict tenant boundary: only documents for this clinic
        $docsQuery = ClinicKnowledgeDocument::forClinic($clinicId)->active();

        if ($category) {
            $docsQuery->inCategory($category);
        }

        $docs = $docsQuery->get();

        if ($docs->isEmpty()) {
            // Auto-ingest if clinic documents have not been initialized yet
            $clinic = Clinic::find($clinicId);
            if ($clinic) {
                $this->ingestClinicKnowledge($clinic);
                $docs = ClinicKnowledgeDocument::forClinic($clinicId)->active()->get();
            }
        }

        $tokens = $this->tokenize($query);
        $scored = [];

        foreach ($docs as $doc) {
            $score = 0.0;
            $docKeyLower = strtolower($doc->document_key);
            $catLower = strtolower($doc->category);
            $titleLower = strtolower($doc->title);
            $contentLower = strtolower($doc->content);
            $keywords = array_map('strtolower', $doc->keywords ?? []);

            // Check exact category or key in query
            if (str_contains(strtolower($query), $catLower) || str_contains(strtolower($query), $docKeyLower)) {
                $score += 15.0;
            }

            foreach ($tokens as $token) {
                if (strlen($token) < 3) continue;

                // Keyword match
                if (in_array($token, $keywords, true)) {
                    $score += 6.0;
                }
                // Title match (whole word)
                if (preg_match('/\b' . preg_quote($token, '/') . '\b/i', $titleLower)) {
                    $score += 5.0;
                }
                // Content match (whole word)
                if (preg_match('/\b' . preg_quote($token, '/') . '\b/i', $contentLower)) {
                    $score += 2.0;
                }
            }

            if ($score > 0) {
                $scored[] = [
                    'document' => $doc,
                    'score' => $score,
                    'category' => $doc->category,
                    'title' => $doc->title,
                    'content' => $doc->content,
                ];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Medical Safety Guard: Check for medical emergency symptoms.
     * Section 37: Assistants must NEVER diagnose or prescribe.
     */
    public function checkEmergency(string $query): ?string
    {
        $clean = strtolower($query);

        $emergencyPatterns = [
            '/\b(chest pain|heart attack|angina|cardiac arrest)\b/i',
            '/\b(cannot breathe|can\'t breathe|shortness of breath|suffocating|choking|difficulty breathing)\b/i',
            '/\b(stroke|facial droop|paralysis|slurred speech|sudden weakness)\b/i',
            '/\b(unconscious|passed out|fainted|loss of consciousness|unresponsive)\b/i',
            '/\b(heavy bleeding|bleeding profusely|hemorrhage|blood vomiting)\b/i',
            '/\b(poison|poisoning|swallowed chemical|overdose)\b/i',
            '/\b(seizure|convulsion|epilepsy fit)\b/i',
            '/\b(suicide|suicidal|kill myself)\b/i',
        ];

        foreach ($emergencyPatterns as $pattern) {
            if (preg_match($pattern, $clean)) {
                return "🚨 *MEDICAL EMERGENCY NOTICE*\n\n" .
                    "This automated assistant provides appointment booking and clinic information only. It *cannot* provide medical diagnoses, treatment, or prescription advice.\n\n" .
                    "⚠️ *If you or someone around you is facing a medical emergency, please immediately:*\n" .
                    "1. Call Emergency Services (112 / 102 / 108 in India)\n" .
                    "2. Go to the nearest Hospital Emergency Room\n" .
                    "3. Do not wait for an OPD appointment.";
            }
        }

        return null;
    }

    /**
     * Answer a natural language question about the clinic, returning the formatted string response.
     */
    public function answerQuestion(Clinic $clinic, string $query): string
    {
        $res = $this->answerQuery($clinic, $query);
        return $res['text'] ?? '';
    }

    /**
     * Generate authoritative RAG response for clinic inquiry.
     */
    public function answerQuery(Clinic $clinic, string $query): array
    {
        // 1. Medical Safety Check
        $emergency = $this->checkEmergency($query);
        if ($emergency !== null) {
            return [
                'type' => 'emergency',
                'text' => $emergency,
                'category' => 'EMERGENCY_ALERT',
                'confidence' => 1.0,
            ];
        }

        // 2. Strict Tenant-Scoped Knowledge Retrieval
        $results = $this->searchKnowledge($clinic->id, $query, null, 2);

        if (empty($results) || $results[0]['score'] < 3.0) {
            return [
                'type' => 'not_found',
                'text' => "I don't have enough specific information in {$clinic->name}'s records to answer that question accurately.\n\n" .
                    "You can:\n" .
                    "• Reply *BOOK* to schedule a consultation with our doctor\n" .
                    "• Reply *HELP* to connect with our receptionist\n" .
                    "• Call the clinic directly at *{$clinic->phone}*",
                'category' => 'FALLBACK',
                'confidence' => 0.0,
            ];
        }

        $top = $results[0];
        $header = "🏥 *{$top['title']}* ({$clinic->name})\n\n";

        return [
            'type' => 'answer',
            'text' => $header . $top['content'] . "\n\n_Reply *BOOK* to schedule an appointment or *MENU* for options._",
            'category' => $top['category'],
            'confidence' => min(1.0, $top['score'] / 20.0),
            'source_title' => $top['title'],
        ];
    }

    /**
     * Upsert a knowledge document with unique clinic_id + document_key constraint.
     */
    protected function upsertDoc(
        int $clinicId,
        string $key,
        string $title,
        string $category,
        string $content,
        array $keywords = []
    ): ClinicKnowledgeDocument {
        return ClinicKnowledgeDocument::updateOrCreate(
            [
                'clinic_id' => $clinicId,
                'document_key' => $key,
            ],
            [
                'title' => $title,
                'category' => $category,
                'content' => $content,
                'keywords' => array_values(array_unique($keywords)),
                'is_active' => true,
            ]
        );
    }

    /**
     * Clean keyword tokenizer for BM25-style term scoring.
     */
    protected function tokenize(string $text): array
    {
        $text = strtolower(strip_tags($text));
        $words = preg_split('/[^a-z0-9_]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = ['the', 'and', 'for', 'with', 'from', 'this', 'that', 'how', 'what', 'when', 'where', 'why', 'can', 'are', 'is', 'a', 'an', 'to', 'in', 'on', 'of', 'at', 'by', 'i', 'my', 'me', 'we', 'us', 'you', 'please', 'do', 'does', 'did', 'have', 'has', 'had', 'be', 'been', 'being', 'or', 'if', 'as', 'it', 'its', 'so', 'no', 'not'];
        return array_values(array_diff($words, $stopWords));
    }
}
