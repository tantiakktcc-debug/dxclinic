<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\PrescriptionPadTemplate;
use App\Models\PrinterProfile;
use Illuminate\Auth\Access\AuthorizationException;

class PrescriptionTemplateResolver
{
    /**
     * Resolve the prescription pad template for an encounter/appointment.
     * Enforces strict historical immutability and authorization.
     *
     * Resolution order:
     * A. Historical record contains template/version reference -> resolve EXACT referenced template (even if ARCHIVED)
     * B. Historical record has no template reference and is a legacy record -> use existing LEGACY renderer/fallback (return null)
     * C. New Doctor Pad generation for a current encounter -> use doctor's current ACTIVE template and commit it to the appointment
     * D. If no active template exists for a new record -> use legacy renderer/fallback (mark legacy and return null)
     *
     * @param Appointment $appointment
     * @param int|null $clinicId
     * @return PrescriptionPadTemplate|null
     * @throws AuthorizationException
     */
    public function resolveForAppointment(Appointment $appointment, ?int $clinicId = null): ?PrescriptionPadTemplate
    {
        $clinicId = $clinicId ?? $appointment->clinic_id;

        // A. Historical record contains template reference
        if (!empty($appointment->prescription_pad_template_id)) {
            $template = PrescriptionPadTemplate::with(['elements' => function ($q) {
                $q->where('is_visible', true)->orderBy('sort_order');
            }])->find($appointment->prescription_pad_template_id);

            if ($template) {
                // Strict authorization and cross-tenant/cross-doctor verification
                if ($template->doctor_id !== $appointment->doctor_id) {
                    throw new AuthorizationException("Template doctor mismatch: template belongs to doctor {$template->doctor_id}, appointment belongs to doctor {$appointment->doctor_id}.");
                }

                if ($clinicId && $template->clinic_id !== $clinicId) {
                    throw new AuthorizationException("Template clinic mismatch: cross-clinic access is strictly forbidden.");
                }

                // Historical document retains its exact committed template version (even if ARCHIVED)
                return $template;
            }
        }

        // B. Historical record has no template reference and is a legacy record
        if ($appointment->is_legacy_pad) {
            return null; // Triggers existing legacy Blade renderer
        }

        // C. New Doctor Pad generation for a current encounter
        $activeTemplate = $this->resolveActiveForDoctor($appointment->doctor_id, $clinicId);

        if ($activeTemplate) {
            // Commit exact active template ID onto appointment so subsequent reprints are 100% immutable
            $appointment->update([
                'prescription_pad_template_id' => $activeTemplate->id,
                'prescription_template_name' => $activeTemplate->name,
                'is_legacy_pad' => false,
            ]);

            return $activeTemplate;
        }

        // D. If no active template exists for a new record, mark as legacy and fallback
        $appointment->update([
            'is_legacy_pad' => true,
        ]);

        return null;
    }

    /**
     * Resolve the current ACTIVE prescription pad template for a doctor.
     * Returns null if no active template exists.
     */
    public function resolveActiveForDoctor(Doctor|int $doctor, ?int $clinicId = null): ?PrescriptionPadTemplate
    {
        $doctorId = $doctor instanceof Doctor ? $doctor->id : $doctor;

        $query = PrescriptionPadTemplate::query()
            ->with(['elements' => function ($q) {
                $q->where('is_visible', true)->orderBy('sort_order');
            }])
            ->where('doctor_id', $doctorId)
            ->where('status', 'ACTIVE')
            ->where('is_active', true);

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        return $query->first();
    }

    /**
     * Backward-compatible alias for resolveActiveForDoctor.
     */
    public function resolveForDoctor(Doctor|int $doctor, ?int $clinicId = null): ?PrescriptionPadTemplate
    {
        return $this->resolveActiveForDoctor($doctor, $clinicId);
    }

    /**
     * Check if a doctor has an active template.
     */
    public function hasActiveTemplate(Doctor|int $doctor, ?int $clinicId = null): bool
    {
        return $this->resolveActiveForDoctor($doctor, $clinicId) !== null;
    }

    /**
     * Resolve the default printer profile for a clinic.
     */
    public function getDefaultPrinterProfile(?int $clinicId = null): ?PrinterProfile
    {
        if (!$clinicId) {
            return PrinterProfile::where('is_default', true)->first();
        }

        return PrinterProfile::where('clinic_id', $clinicId)
            ->where('is_default', true)
            ->first();
    }
}
