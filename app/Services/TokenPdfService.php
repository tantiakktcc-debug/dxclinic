<?php

namespace App\Services;

use App\Models\Appointment;

class TokenPdfService
{
    /**
     * Prepare data payload for Token PDF (Section 51, 79)
     */
    public function getTokenPdfData(Appointment $appointment): array
    {
        $appointment->loadMissing(['clinic', 'patient', 'doctor']);

        return [
            'clinic_name' => $appointment->clinic->name,
            'clinic_phone' => $appointment->clinic->phone,
            'clinic_address' => $appointment->clinic->address,
            'clinic_city' => $appointment->clinic->city,
            'patient_id' => $appointment->patient->patient_id,
            'patient_name' => $appointment->patient->name,
            'age_sex' => "{$appointment->patient->age} Y / {$appointment->patient->sex}",
            'mobile' => $appointment->patient->mobile,
            'address' => $appointment->patient->formatted_address,
            'appointment_date' => $appointment->appointment_date->format('d/m/Y'),
            'session_type' => $appointment->session_type,
            'token_number' => $appointment->token_number,
            'formatted_token' => $appointment->formatted_token,
            'booking_source' => $appointment->booking_source,
            'payment_status' => 'Pay at Clinic / Unpaid',
            'secure_token' => $appointment->secure_token,
            'verification_url' => url("/verify-token/{$appointment->secure_token}"),
        ];
    }
}
