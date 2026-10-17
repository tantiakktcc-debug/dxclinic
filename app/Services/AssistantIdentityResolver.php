<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;

class AssistantIdentityResolver
{
    public function __construct(
        protected ?ClinicScheduleService $scheduleService = null
    ) {
        $this->scheduleService = $scheduleService ?? app(ClinicScheduleService::class);
    }

    /**
     * Authoritatively resolve identity, titles, greetings, and session key for the current user.
     */
    public function resolve(?User $user, ?Clinic $clinic = null): array
    {
        $clinic = $clinic ?? TenantContext::clinic() ?? $user?->clinic;
        $role = strtoupper((string) ($user?->role ?? 'OPERATOR'));
        $userName = trim((string) ($user?->name ?? 'User'));

        $shortLabel = 'Staff Assistant';
        $fullTitle = 'DxClinic Staff Assistant';
        $personalizedName = $userName;
        $isDoctor = false;

        if ($role === 'ADMIN') {
            $shortLabel = 'Admin Assistant';
            $fullTitle = 'DxClinic Admin Assistant';
        } elseif ($role === 'OPERATOR') {
            $shortLabel = 'Operator Assistant';
            $fullTitle = 'DxClinic Operator Assistant';
        } elseif ($role === 'DOCTOR') {
            $isDoctor = true;
            $shortLabel = 'Doctor Assistant';

            // Resolve authoritative doctor profile
            $doctor = null;
            if ($user?->doctor_id) {
                $doctor = Doctor::find($user->doctor_id);
            }
            if (!$doctor && $user?->id) {
                $doctor = Doctor::where('user_id', $user->id)->first();
            }

            $rawDoctorName = $doctor?->name ?: $userName;
            $cleanName = preg_replace('/^(dr\.?|doctor)\s+/i', '', trim($rawDoctorName));
            $personalizedName = 'Dr. ' . $cleanName;

            $fullTitle = "DxClinic {$personalizedName}'s Assistant";
        } elseif ($role === 'SUPERADMIN') {
            $shortLabel = 'Superadmin Assistant';
            $fullTitle = 'DxClinic Superadmin Assistant';
        } else {
            $roleTitle = ucwords(strtolower(str_replace('_', ' ', $role)));
            $shortLabel = "{$roleTitle} Assistant";
            $fullTitle = "DxClinic {$roleTitle} Assistant";
        }

        $greetingHeadline = "Hello, {$personalizedName}.";
        $greetingText = "Hello, {$personalizedName}. How can I help you?";

        // Operational session resolution
        $businessDate = ClinicClock::today()->toDateString();
        $sessionCode = 'ALL';
        if ($clinic) {
            $sessionCode = $this->scheduleService->getCurrentOrNextSessionType($clinic);
        }

        $clinicId = $clinic?->id ?? 0;
        $userId = $user?->id ?? 0;
        $sessionKey = "clinic_{$clinicId}_user_{$userId}_{$businessDate}_{$sessionCode}";

        return [
            'role' => $role,
            'is_doctor' => $isDoctor,
            'user_name' => $userName,
            'personalized_name' => $personalizedName,
            'short_label' => $shortLabel,
            'full_title' => $fullTitle,
            'greeting_headline' => $greetingHeadline,
            'greeting_text' => $greetingText,
            'business_date' => $businessDate,
            'session_code' => $sessionCode,
            'session_key' => $sessionKey,
        ];
    }
}
