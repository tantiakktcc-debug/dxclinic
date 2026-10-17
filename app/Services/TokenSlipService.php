<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TokenSlipService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected TokenPdfService $tokenPdfService
    ) {}

    /**
     * Generate canonical token slip presentation data.
     */
    public function generateTokenSlipPdfData(Appointment $appointment): array
    {
        return $this->tokenPdfService->getTokenPdfData($appointment);
    }

    /**
     * Generate temporary signed document URL valid for 15 minutes.
     * The document is NOT permanently public; expires automatically.
     */
    public function generatePrivateDocumentUrl(Appointment $appointment): string
    {
        return URL::temporarySignedRoute(
            'token.slip.private-download',
            now()->addMinutes(15),
            ['secureToken' => $appointment->secure_token]
        );
    }

    /**
     * Authorize sending token slip based on actor role and clinic/doctor scope.
     *
     * Rules:
     * - Superadmin / Clinic Admin: own clinic only
     * - Operator: own clinic only
     * - Doctor: own clinic AND must be the attending doctor for this appointment
     */
    public function assertAuthorized(Appointment $appointment, ?User $user, Clinic $clinic): void
    {
        if ((int) $appointment->clinic_id !== (int) $clinic->id) {
            throw new HttpException(403, 'Cross-clinic token slip access is forbidden.', null, [], 403);
        }

        if ($user && ($user->role === 'DOCTOR' || $user->doctor_id)) {
            $doctorId = $user->doctor_id;
            if ($doctorId && (int) $appointment->doctor_id !== (int) $doctorId) {
                throw new HttpException(403, 'Doctors can only send token slips for their own assigned appointments.', null, [], 403);
            }
        }
    }

    /**
     * Send Token Slip to patient via WhatsApp using NotificationService.
     * Enforces idempotency key: TOKEN_SLIP:{appointment_id}:{recipient}:{version}
     */
    public function sendTokenSlipViaWhatsApp(
        Appointment $appointment,
        ?string $recipientPhone = null,
        bool $forceResend = false,
        ?string $senderRole = 'OPERATOR',
        ?int $senderUserId = null
    ): array {
        $appointment->loadMissing(['clinic', 'patient', 'doctor']);
        $clinic = $appointment->clinic;

        // Resolve recipient: defaults to patient's verified mobile
        $targetPhone = $recipientPhone ?: ($appointment->patient->mobile ?? '');
        $canonicalPhone = WhatsAppIdentityNormalizer::normalize($targetPhone);

        if (empty($canonicalPhone) || strlen($canonicalPhone) < 10) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'error' => 'WhatsApp number verification required. Valid patient phone number is missing.',
            ];
        }

        $documentUrl = $this->generatePrivateDocumentUrl($appointment);
        $filename = "TokenSlip-{$appointment->formatted_token}.pdf";
        $caption = "Appointment Token Slip: #{$appointment->formatted_token} for {$appointment->patient->name} on " . $appointment->appointment_date->format('d M Y') . " ({$appointment->session_type} Session).";

        $version = $forceResend ? 'v_' . time() : 'v1';
        $idempotencyKey = "TOKEN_SLIP:{$appointment->id}:{$canonicalPhone}:{$version}";

        $payload = [
            'document_url' => $documentUrl,
            'filename' => $filename,
            'caption' => $caption,
            'token_number' => $appointment->formatted_token,
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'sender_role' => $senderRole,
            'sender_user_id' => $senderUserId,
        ];

        return $this->notificationService->sendWhatsAppNotification(
            $clinic,
            $canonicalPhone,
            'DOCUMENT',
            $payload,
            $appointment->id,
            $idempotencyKey
        );
    }
}
