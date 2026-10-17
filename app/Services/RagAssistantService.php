<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RagAssistantService
{
    protected ?array $indexedKnowledge = null;
    protected AppointmentService $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    /**
     * Load and index knowledge documents from knowledge/ directory
     */
    public function getIndexedKnowledge(): array
    {
        if ($this->indexedKnowledge !== null) {
            return $this->indexedKnowledge;
        }

        $docs = [
            'operator_manual' => [
                'title' => 'Operator User Manual',
                'path' => 'knowledge/manuals/operator_manual.md',
                'audience' => 'OPERATOR',
            ],
            'operator_quick_card' => [
                'title' => 'Operator Quick Card',
                'path' => 'knowledge/manuals/operator_quick_card.md',
                'audience' => 'OPERATOR',
            ],
            'admin_manual' => [
                'title' => 'Administrator Manual',
                'path' => 'knowledge/manuals/admin_manual.md',
                'audience' => 'ADMIN',
            ],
            'admin_quick_card' => [
                'title' => 'Admin Quick Card',
                'path' => 'knowledge/manuals/admin_quick_card.md',
                'audience' => 'ADMIN',
            ],
            'doctor_manual' => [
                'title' => 'Doctor & Physician Manual',
                'path' => 'knowledge/manuals/doctor_manual.md',
                'audience' => 'DOCTOR',
            ],
            'doctor_quick_card' => [
                'title' => 'Doctor Quick Card',
                'path' => 'knowledge/manuals/doctor_quick_card.md',
                'audience' => 'DOCTOR',
            ],
            'faq' => [
                'title' => 'FAQ Knowledge Base',
                'path' => 'knowledge/faq/faq.md',
                'audience' => 'ALL',
            ],
            'workflows' => [
                'title' => 'Clinical & Financial Workflows',
                'path' => 'knowledge/workflows/clinical_financial_workflows.md',
                'audience' => 'ALL',
            ],
            'troubleshooting' => [
                'title' => 'Diagnostic & Troubleshooting Guide',
                'path' => 'knowledge/troubleshooting/troubleshooting.md',
                'audience' => 'ALL',
            ],
            'superadmin_manual' => [
                'title' => 'Superadmin Fleet Manual',
                'path' => 'knowledge/manuals/superadmin_manual.md',
                'audience' => 'ADMIN',
            ],
            'superadmin_quick_card' => [
                'title' => 'Superadmin Quick Card',
                'path' => 'knowledge/manuals/superadmin_quick_card.md',
                'audience' => 'ADMIN',
            ],
            'financial_workflows' => [
                'title' => 'Financial Workflows & Accounting Guide',
                'path' => 'knowledge/workflows/financial_workflows.md',
                'audience' => 'ADMIN',
            ],
        ];

        $chunks = [];

        foreach ($docs as $key => $doc) {
            $fullPath = base_path($doc['path']);
            if (!File::exists($fullPath)) {
                continue;
            }

            $content = File::get($fullPath);
            $sections = preg_split('/(?=\n## |\n### |\n\*\*Q:)/', $content);

            foreach ($sections as $section) {
                $section = trim($section);
                if (empty($section)) {
                    continue;
                }

                // Extract heading
                $heading = '';
                if (preg_match('/^(?:## |### |\*\*Q:\s*)(.+?)(?:\*\*|\n|$)/', $section, $m)) {
                    $heading = trim($m[1]);
                }

                $chunks[] = [
                    'source_key' => $key,
                    'source_title' => $doc['title'],
                    'audience' => $doc['audience'],
                    'heading' => $heading,
                    'content' => $section,
                    'tokens' => $this->tokenize($heading . ' ' . $section),
                ];
            }
        }

        $this->indexedKnowledge = $chunks;
        return $this->indexedKnowledge;
    }

    /**
     * Tokenize string into normalized lowercase keywords
     */
    protected function tokenize(string $text): array
    {
        $text = strtolower(strip_tags($text));
        $words = preg_split('/[^a-z0-9_]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = ['the', 'and', 'for', 'with', 'from', 'this', 'that', 'how', 'what', 'when', 'where', 'why', 'can', 'are', 'is', 'a', 'an', 'to', 'in', 'on', 'of', 'at', 'by', 'dxclinic'];
        return array_values(array_diff($words, $stopWords));
    }

    /**
     * Process message from authenticated operator/admin
     */
    public function processMessage(string $query, User $user, ?Clinic $clinic = null): array
    {
        $queryTrim = trim($query);
        if (empty($queryTrim)) {
            return [
                'type' => 'text',
                'message' => 'Please enter a question or operational request.',
            ];
        }

        $clinic = $clinic ?? Clinic::first();

        // 1. Check Class C Financial/Security Critical Actions (Section 60)
        $classC = $this->checkClassCActions($queryTrim, $clinic);
        if ($classC !== null) {
            return $classC;
        }

        // 2. Check Natural Language Appointment Booking (Class B - Section 56-57)
        if ($this->isBookingIntent($queryTrim)) {
            return $this->parseBookingIntent($queryTrim, $clinic);
        }

        // 3. Check Live Operational Lookups (Class A - Section 59, 86, 87)
        $liveLookup = $this->checkLiveLookups($queryTrim, $clinic);
        if ($liveLookup !== null) {
            return $liveLookup;
        }

        // 4. RAG Knowledge Retrieval (Section 54, 88)
        return $this->retrieveKnowledge($queryTrim, $user);
    }

    /**
     * Guard against Class C financial/security actions directly from chat (Section 60, Revision 30 Section 58)
     */
    protected function checkClassCActions(string $query, ?Clinic $clinic = null): ?array
    {
        $lower = strtolower($query);
        $slug = $clinic?->public_slug ?? Clinic::first()?->public_slug ?? 'default';

        if (preg_match('/\b(record|collect|make|process|receive)\b.*\b(payment|bill)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Financial payments and billing require strict patient and payment method authorization. Please navigate to the Consultation or Diagnostic Billing workflow.',
                'action_label' => 'Open Arrival Queue',
                'action_url' => route('operator.queue', ['public_slug' => $slug]),
            ];
        }

        if (preg_match('/\b(deposit cash|bank deposit|deposit money)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Cash deposits to bank create double-entry journal transfers. Please record cash deposits through the Cash Session Drawer.',
                'action_label' => 'Open Cash Sessions',
                'action_url' => route('cash.sessions'),
            ];
        }

        if (preg_match('/\b(close session|close morning|close evening|close cash)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Closing a cash session requires entering physical cash count and mandatory variance justification if not balanced.',
                'action_label' => 'Open Cash Session Drawer',
                'action_url' => route('cash.sessions'),
            ];
        }

        if (preg_match('/\b(reset|change).*\bpassword\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Password management is protected by security credentials and audit logs. Use the Users & Operators manager or Self Password Change.',
                'action_label' => 'Open User Management',
                'action_url' => route('settings.users'),
            ];
        }

        if (preg_match('/\b(restore database|restore backup)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Database restoration requires administrator authorization, pre-restore backup snapshot, and encryption password verification.',
                'action_label' => 'Open Backup & Restore',
                'action_url' => route('settings.backup'),
            ];
        }

        if (preg_match('/\b(refund|process refund|issue refund|return money|cancel payment)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Financial refunds require authorized approval and double-entry accounting reversal. Please navigate to Consultation or Diagnostic Billing History to process controlled refunds.',
                'action_label' => 'Open Consultation Billing',
                'action_url' => route('operator.consultation'),
            ];
        }

        if (preg_match('/\b(modify bill|edit bill|change bill|supplementary bill)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Settled bills are immutable. Controlled modifications and adjustments must be initiated through the Consultation or Diagnostic Billing workspace with a mandatory reason.',
                'action_label' => 'Open Billing History',
                'action_url' => route('operator.consultation'),
            ];
        }

        if (preg_match('/\b(modify payment|edit payment|change payment|alter payment)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Settled payments are strictly immutable. Any financial adjustments require controlled refund or receipt revision workflows with audited reasons.',
                'action_label' => 'Open Consultation Billing',
                'action_url' => route('operator.consultation'),
            ];
        }

        if (preg_match('/\b(post|create|record|enter)\s+(journal|journal entry|ledger entry)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Double-entry accounting journals are posted automatically by verified financial transactions and cannot be manually created via chat.',
                'action_label' => 'Open Financial Reports',
                'action_url' => route('reports.reconciliation'),
            ];
        }

        if (preg_match('/\b(activate|deactivate|archive)\s+(template|prescription pad)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Prescription pad template activation and archiving require administrator authorization and SVG layout validation in the Template Designer.',
                'action_label' => 'Open Template Designer',
                'action_url' => route('settings.prescription-pad-templates'),
            ];
        }

        if (preg_match('/\b(modify|change|update|save|set|calibrate)\s+(printer profile|printer calibration|printer offset)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'Printer profile calibration values (X/Y offsets, scale) must be configured through the Printer Calibration settings modal.',
                'action_label' => 'Open Printer Calibration',
                'action_url' => route('settings.prescription-pad-templates'),
            ];
        }

        if (preg_match('/\b(connect|disconnect|change|modify)\s+(whatsapp|waba|whatsapp connection|meta connection)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'WhatsApp connection settings, credentials, and tenant onboarding must be configured by an authorized Clinic Administrator in Settings.',
                'action_label' => 'Open WhatsApp Settings',
                'action_url' => route('settings.whatsapp'),
            ];
        }

        if (preg_match('/\b(activate|approve|modify)\s+(whatsapp template|meta template|waba template)\b/', $lower)) {
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_C',
                'message' => 'WhatsApp template approvals are governed by Meta WhatsApp Business Platform policies and cannot be altered via chat.',
                'action_label' => 'Open WhatsApp Settings',
                'action_url' => route('settings.whatsapp'),
            ];
        }

        return null;
    }

    /**
     * Check if query is natural language booking request
     */
    protected function isBookingIntent(string $query): bool
    {
        $lower = strtolower($query);
        return str_starts_with($lower, 'book ') ||
            str_contains($lower, 'book appointment') ||
            str_contains($lower, 'schedule appointment');
    }

    /**
     * Parse natural language booking query (Section 56-57)
     */
    protected function parseBookingIntent(string $query, ?Clinic $clinic): array
    {
        // Extract Patient Name
        $name = null;
        if (preg_match('/\b(?:for|patient)\s+([A-Za-z\s]+?)(?:\s+(?:tomorrow|today|morning|evening|sunday|phone|mobile|dr|\d))/i', $query, $m)) {
            $name = trim($m[1]);
        } elseif (preg_match('/^book\s+(?:appointment\s+)?(?:for\s+)?([A-Za-z\s]+)$/i', $query, $m)) {
            $name = trim($m[1]);
        }

        // Extract Mobile Phone
        $mobile = null;
        if (preg_match('/\b(?:phone|mobile|tel|contact|number)?\s*([6-9]\d{9})\b/', $query, $m)) {
            $mobile = $m[1];
        }

        // Extract Date
        $date = Carbon::today()->format('Y-m-d');
        if (preg_match('/\btomorrow\b/i', $query)) {
            $date = Carbon::tomorrow()->format('Y-m-d');
        } elseif (preg_match('/\b(?:on\s+)?(\d{4}-\d{2}-\d{2})\b/', $query, $m)) {
            $date = $m[1];
        }

        // Extract Session
        $session = 'morning';
        if (preg_match('/\bevening\b/i', $query)) {
            $session = 'evening';
        } elseif (preg_match('/\bsunday\b/i', $query)) {
            $session = 'single';
        }

        // Detect Consultation Mode (Revision 25 Section 91)
        $consultationMode = 'CLINIC_PHYSICAL';
        if (preg_match('/\b(online|virtual|telemedicine|video)\b/i', $query)) {
            $consultationMode = 'ONLINE';
        }

        // Resolve Doctor (Section 41-44)
        $activeDoctors = Doctor::where('is_active', true)->get();
        $selectedDoctor = null;

        if ($activeDoctors->count() === 1) {
            $selectedDoctor = $activeDoctors->first();
        } else {
            // Check if doctor mentioned in text
            foreach ($activeDoctors as $doc) {
                if (stripos($query, $doc->name) !== false) {
                    $selectedDoctor = $doc;
                    break;
                }
            }
            if (!$selectedDoctor && $clinic && $clinic->default_doctor_id) {
                $selectedDoctor = Doctor::find($clinic->default_doctor_id);
            }
            if (!$selectedDoctor) {
                $selectedDoctor = $activeDoctors->first();
            }
        }

        // Look up existing patient if mobile provided
        $existingPatient = null;
        if ($mobile) {
            $existingPatient = Patient::where('mobile', $mobile)->first();
            if ($existingPatient && !$name) {
                $name = $existingPatient->name;
            }
        } elseif ($name) {
            $existingPatient = Patient::where('name', 'LIKE', $name)->first();
            if ($existingPatient && !$mobile) {
                $mobile = $existingPatient->mobile;
            }
        }

        // If missing critical information, ask for it
        if (empty($name)) {
            return [
                'type' => 'text',
                'message' => 'Please provide the patient name to proceed with booking. Example: "Book appointment for Ram Kumar tomorrow evening, phone 9876543210".',
            ];
        }

        if (empty($mobile) && !$existingPatient) {
            return [
                'type' => 'text',
                'message' => "Found patient name '{$name}'. Please provide their 10-digit mobile number to complete the booking proposal.",
            ];
        }

        $modeLabel = match($consultationMode) {
            'ONLINE' => '🌐 Online Consultancy (₹700)',
            'VISITING_CONSULTANCY', 'VISITING' => '🚗 Visiting Consultancy (₹1000)',
            default => '🏥 Clinic Consultancy (₹600)',
        };

        // Generate proposal card for explicit confirmation (Section 57, 91)
        return [
            'type' => 'booking_proposal',
            'proposal' => [
                'patient_name' => $name,
                'patient_phone' => $mobile ?? ($existingPatient?->phone ?? '9876543210'),
                'doctor_id' => $selectedDoctor?->id,
                'doctor_name' => $selectedDoctor ? "{$selectedDoctor->formatted_name} ({$selectedDoctor->specialization})" : 'Default Doctor',
                'appointment_date' => $date,
                'session' => $session,
                'booking_source' => 'CLINIC', // Section 58
                'consultation_mode' => $consultationMode,
            ],
            'message' => "I have prepared the appointment booking proposal ({$modeLabel}). Please review and click Confirm Booking:",
        ];
    }

    /**
     * Check live data lookups (Section 59, 86, 87)
     */
    protected function checkLiveLookups(string $query, ?Clinic $clinic): ?array
    {
        $lower = strtolower($query);

        // Live Queue Window Lookup (Section 123)
        if (str_contains($lower, 'live queue') || str_contains($lower, 'queue window') || str_contains($lower, 'queue monitor')) {
            $slug = $clinic?->public_slug ?? 'democlinic';
            return [
                'type' => 'action_redirect',
                'level' => 'CLASS_A',
                'message' => "The **Live Queue Window** is an authenticated, real-time presentation monitor for the current day's token queue. It runs independently in a separate window, displays booking sources (Reception, PWA, WhatsApp, Telephony) with unified tokens, and updates automatically via WebSockets.",
                'action_label' => 'Open Live Queue Window',
                'action_url' => route('queue.live', ['public_slug' => $slug]),
                'source' => 'Live Queue Architecture',
            ];
        }

        // WhatsApp Automation Lookup (Section 123)
        if (str_contains($lower, 'whatsapp status') || str_contains($lower, 'whatsapp connection') || str_contains($lower, 'whatsapp booking')) {
            $conn = $clinic ? \App\Models\WhatsAppConnection::where('clinic_id', $clinic->id)->first() : null;
            $status = $conn ? $conn->status : 'NOT_CONFIGURED';
            $phone = $conn?->display_phone_number ?: 'Not configured';

            return [
                'type' => 'text',
                'message' => "💬 **WhatsApp Integration Status:** {$status}\n• Display Phone: {$phone}\n• Channel Type: Meta WhatsApp Cloud API\n• Unified Token Series: Active across Reception, PWA, and WhatsApp.\n\nTo configure or adjust settings, visit Settings > WhatsApp Automation.",
                'source' => 'WhatsApp Connection Subsystem',
            ];
        }

        // Today's Cash Collection (Section 15-16, 59)
        if (str_contains($lower, 'cash collection') || str_contains($lower, 'today cash') || str_contains($lower, "today's cash")) {
            $today = Carbon::today()->toDateString();
            $cashCollection = (float) Payment::where('clinic_id', $clinic?->id ?? 1)
                ->where('payment_method', 'CASH')
                ->where('status', 'SETTLED')
                ->where(function ($q) use ($today) {
                    $q->whereDate('settled_at', $today)->orWhereDate('created_at', $today);
                })
                ->sum('amount');

            return [
                'type' => 'text',
                'message' => "💰 **Today's Cash Collection:** ₹ " . number_format($cashCollection, 2) . " (Derived authoritatively from settled CASH payments for " . Carbon::today()->format('d M Y') . ").",
                'source' => 'Live Authoritative Cash Counter',
            ];
        }

        // Today's UPI Collection
        if (str_contains($lower, 'upi collection') || str_contains($lower, 'today upi') || str_contains($lower, "today's upi")) {
            $today = Carbon::today()->toDateString();
            $upiCollection = (float) Payment::where('clinic_id', $clinic?->id ?? 1)
                ->where('payment_method', 'UPI')
                ->where('status', 'SETTLED')
                ->where(function ($q) use ($today) {
                    $q->whereDate('settled_at', $today)->orWhereDate('created_at', $today);
                })
                ->sum('amount');

            return [
                'type' => 'text',
                'message' => "📱 **Today's UPI Collection:** ₹ " . number_format($upiCollection, 2) . " (Settled with Soundbox confirmation for " . Carbon::today()->format('d M Y') . ").",
                'source' => 'Live Bank Ledger Payments',
            ];
        }

        // Default Doctor Query (Section 87)
        if (str_contains($lower, 'default doctor') || str_contains($lower, 'who is the doctor') || str_contains($lower, 'active doctors')) {
            $activeDoctors = Doctor::where('is_active', true)->get();
            $defaultDoc = $clinic && $clinic->defaultDoctor ? $clinic->defaultDoctor : $activeDoctors->first();

            $docList = $activeDoctors->map(fn($d) => "• Dr. {$d->name} ({$d->specialization})")->implode("\n");

            return [
                'type' => 'text',
                'message' => "👨‍⚕️ **Default Booking Doctor:** " . ($defaultDoc ? "Dr. {$defaultDoc->name} ({$defaultDoc->specialization})" : "None configured") . "\n\n**Active Doctors ({$activeDoctors->count()}):**\n{$docList}",
                'source' => 'Clinic & Doctor Configuration',
            ];
        }

        // Patient Lookup (Section 86)
        if (preg_match('/\b(?:find|lookup|search|check)\s+patient\s+([A-Za-z0-9\s]+)/i', $query, $m)) {
            $term = trim($m[1]);
            $patients = Patient::where('name', 'LIKE', "%{$term}%")
                ->orWhere('mobile', 'LIKE', "%{$term}%")
                ->orWhere('patient_id', 'LIKE', "%{$term}%")
                ->limit(3)
                ->get();

            if ($patients->isEmpty()) {
                return [
                    'type' => 'text',
                    'message' => "No patient records found matching '{$term}'.",
                    'source' => 'Patient Registry',
                ];
            }

            $list = $patients->map(function ($p) {
                return "• **{$p->name}** (ID: `{$p->patient_id}`, Phone: {$p->phone}, Age: {$p->age}Y/{$p->sex})";
            })->implode("\n");

            return [
                'type' => 'text',
                'message' => "📋 **Patient Search Results:**\n\n{$list}",
                'source' => 'Patient Registry Service',
            ];
        }

        return null;
    }

    /**
     * Retrieve knowledge from authoritative documents (Section 54, 88)
     */
    protected function retrieveKnowledge(string $query, User $user): array
    {
        $chunks = $this->getIndexedKnowledge();
        $queryTokens = $this->tokenize($query);

        if (empty($queryTokens)) {
            return [
                'type' => 'text',
                'message' => 'I could not find that in the current DxClinic knowledge base.',
            ];
        }

        $bestScore = 0;
        $bestChunk = null;

        foreach ($chunks as $chunk) {
            // Role awareness (Section 54)
            if ($chunk['audience'] === 'ADMIN' && !$user->isAdmin()) {
                continue;
            }

            $score = 0;
            $headingTokens = $this->tokenize($chunk['heading']);

            foreach ($queryTokens as $qt) {
                // Higher weight for matches in heading
                if (in_array($qt, $headingTokens, true)) {
                    $score += 5;
                }
                if (in_array($qt, $chunk['tokens'], true)) {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestChunk = $chunk;
            }
        }

        // Threshold check for strict grounding (Section 88)
        if ($bestScore < 3 || $bestChunk === null) {
            return [
                'type' => 'text',
                'message' => 'I could not find that in the current DxClinic knowledge base.',
            ];
        }

        // Format clean excerpt from best chunk
        $content = $bestChunk['content'];
        // Trim to first 4-5 paragraphs if very long
        $paragraphs = explode("\n\n", $content);
        $excerpt = implode("\n\n", array_slice($paragraphs, 0, 4));

        return [
            'type' => 'text',
            'message' => $excerpt,
            'source' => $bestChunk['source_title'] . (!empty($bestChunk['heading']) ? " &bull; " . $bestChunk['heading'] : ''),
        ];
    }

    /**
     * Public search for knowledge/FAQ suitable for public patients or unauthenticated widgets
     */
    public function searchPublicKnowledge(string $query, ?Clinic $clinic = null): ?array
    {
        $chunks = $this->getIndexedKnowledge();
        $queryTokens = $this->tokenize($query);

        if (empty($queryTokens)) {
            return null;
        }

        $bestScore = 0;
        $bestChunk = null;

        foreach ($chunks as $chunk) {
            // Only allow public-safe knowledge (ALL audience or FAQ)
            if ($chunk['audience'] !== 'ALL' && $chunk['source_key'] !== 'faq') {
                continue;
            }

            $score = 0;
            $headingTokens = $this->tokenize($chunk['heading']);

            foreach ($queryTokens as $qt) {
                if (in_array($qt, $headingTokens, true)) {
                    $score += 5;
                }
                if (in_array($qt, $chunk['tokens'], true)) {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestChunk = $chunk;
            }
        }

        if ($bestScore < 3 || $bestChunk === null) {
            return null;
        }

        // Return clean answer
        $content = $bestChunk['content'];
        $clean = preg_replace('/^\*\*Q:\s*.*?\*\*\s*/s', '', $content);
        $clean = preg_replace('/^###\s*Q\d+:\s*.*?\n/s', '', $clean);
        $clean = preg_replace('/^\*\*Answer:\*\*\s*/', '', $clean);
        $paragraphs = explode("\n\n", trim($clean));
        $excerpt = implode("\n\n", array_slice($paragraphs, 0, 3));

        return [
            'message' => $excerpt,
            'source' => $bestChunk['source_title'] . (!empty($bestChunk['heading']) ? " — " . $bestChunk['heading'] : ''),
        ];
    }
}

