<?php

namespace App\Services;

use App\Models\Appointment;

class DoctorSeenStatusResolver
{
    /**
     * Resolves authoritative Doctor Consultation Seen status for an appointment.
     * "SEEN" means the patient has actually entered the doctor consultation
     * workflow according to the EXISTING authoritative Visit/Consultation lifecycle.
     *
     * Returns: 'SEEN' | 'NOT SEEN'
     */
    public function resolve(Appointment $appointment): string
    {
        // If appointment itself is completed or in consultation
        $appointmentStatus = strtoupper($appointment->status ?? '');
        if (in_array($appointmentStatus, ['COMPLETED', 'IN_PROGRESS', 'IN_CONSULTATION'], true)) {
            return 'SEEN';
        }

        // Check authoritative visit lifecycle
        $visit = $appointment->visit;
        if ($visit) {
            $visitStatus = strtoupper($visit->status ?? '');
            if (in_array($visitStatus, ['IN_PROGRESS', 'IN_CONSULTATION', 'COMPLETED'], true)) {
                return 'SEEN';
            }

            if ($visit->completed_at !== null) {
                return 'SEEN';
            }
        }

        return 'NOT SEEN';
    }
}
