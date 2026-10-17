<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\CashSession;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VisitService
{
    public function __construct(
        protected PatientIdentityService $patientIdentityService,
        protected AppointmentService $appointmentService
    ) {}

    /**
     * Authoritative action: Mark a patient as seen by doctor in consultation workflow.
     * Enforces strict server-side session, clinic, role, and doctor isolation.
     * Idempotent: Never creates duplicate visits, tokens, or appointments.
     */
    public function markSeen(Appointment $appointment, User $user): Visit
    {
        // 1. Clinic isolation: User can only mark seen appointments in own clinic
        if ($user->role !== 'SUPERADMIN' && (int) $appointment->clinic_id !== (int) $user->clinic_id) {
            abort(403, 'Unauthorized cross-clinic appointment action.');
        }

        // 2. Role authorization check
        $allowedRoles = ['ADMIN', 'OPERATOR', 'DOCTOR', 'SUPERADMIN'];
        if (!in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Unauthorized role for marking patient seen.');
        }

        // 3. Doctor role scope: Doctors may only mark their own assigned appointments
        if ($user->role === 'DOCTOR') {
            if (!$user->doctor_id || (int) $appointment->doctor_id !== (int) $user->doctor_id) {
                abort(403, 'Unauthorized: Doctors may only mark their own assigned appointments as seen.');
            }
        }

        // 4. Authoritative consultation fee gate: Fee MUST be PAID before marking patient seen
        $feeStatusResolver = app(FeeStatusResolver::class);
        $feeStatus = $feeStatusResolver->resolve($appointment);
        if ($feeStatus !== 'PAID') {
            abort(422, "Cannot mark patient as seen. Consultation fee status is {$feeStatus}. Consultation fee must be PAID first via Fee & Pad.");
        }

        return DB::transaction(function () use ($appointment, $user, $feeStatusResolver) {
            // Lock appointment row to prevent race conditions
            $appointment = Appointment::where('id', $appointment->id)->lockForUpdate()->first();
            abort_unless($appointment, 404, 'Appointment not found.');

            // Re-verify fee status inside lock to prevent race conditions
            $feeStatus = $feeStatusResolver->resolve($appointment);
            if ($feeStatus !== 'PAID') {
                abort(422, "Cannot mark patient as seen. Consultation fee status is {$feeStatus}. Consultation fee must be PAID first.");
            }

            // Idempotent: If already in consultation or completed, return existing authoritative visit
            $existingVisit = $appointment->visit;
            if ($existingVisit && in_array(strtoupper($existingVisit->status ?? ''), ['IN_PROGRESS', 'IN_CONSULTATION', 'COMPLETED'], true)) {
                return $existingVisit;
            }
            if (in_array(strtoupper($appointment->status ?? ''), ['IN_PROGRESS', 'IN_CONSULTATION', 'COMPLETED'], true)) {
                if ($existingVisit) {
                    return $existingVisit;
                }
            }

            if ($existingVisit) {
                $existingVisit->update([
                    'status' => 'IN_PROGRESS',
                    'started_at' => $existingVisit->started_at ?? Carbon::now(),
                ]);
                $visit = $existingVisit;
            } else {
                // If visit doesn't exist yet, create Encounter Visit directly in consultation
                $consultationMode = $appointment->consultation_mode ?? 'CLINIC_PHYSICAL';
                $visit = Visit::create([
                    'clinic_id' => $appointment->clinic_id,
                    'patient_id' => $appointment->patient_id,
                    'doctor_id' => $appointment->doctor_id,
                    'appointment_id' => $appointment->id,
                    'visit_date' => $appointment->appointment_date,
                    'session_type' => $appointment->session_type,
                    'consultation_mode' => $consultationMode,
                    'visit_type' => 'NEW',
                    'status' => 'IN_PROGRESS',
                    'arrived_at' => Carbon::now(),
                    'started_at' => Carbon::now(),
                ]);
            }

            // Update appointment status to IN_PROGRESS
            $appointment->update(['status' => 'IN_PROGRESS']);

            // Record authoritative clinical audit log
            AuditLog::create([
                'clinic_id' => $appointment->clinic_id,
                'user_id' => $user->id,
                'action' => 'DOCTOR_CONSULTATION_STARTED',
                'entity_type' => Visit::class,
                'entity_id' => $visit->id,
                'new_values' => [
                    'appointment_id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'status' => 'IN_PROGRESS',
                    'action_by_role' => $user->role,
                ],
                'ip_address' => request()?->ip(),
                'reason' => 'Patient marked seen by doctor in consultation workflow',
                'change_category' => 'CLINICAL',
            ]);

            DB::afterCommit(function () use ($appointment) {
                event(\App\Events\QueueUpdated::fromAppointment($appointment, 'IN_PROGRESS'));
            });

            return $visit;
        });
    }

    /**
     * Mark patient arrival and create/resolve Visit idempotently (Section 52, 53, 55)
     * NEVER creates a second appointment or second token.
     */
    public function markArrival(Appointment $appointment, array $patientUpdates = [], ?int $cashSessionId = null): Visit
    {
        return DB::transaction(function () use ($appointment, $patientUpdates, $cashSessionId) {
            // Lock appointment row to prevent race conditions on double-click
            $appointment = Appointment::where('id', $appointment->id)->lockForUpdate()->first();

            // Complete missing patient data if provided (Section 54)
            if (!empty($patientUpdates)) {
                $this->patientIdentityService->completeProfileAtArrival($appointment->patient, $patientUpdates);
            }

            // Check if visit already exists (Idempotent arrival per Section 53)
            $existingVisit = Visit::where('appointment_id', $appointment->id)->first();
            if ($existingVisit) {
                if ($appointment->status === 'BOOKED') {
                    $appointment->update(['status' => 'ARRIVED']);
                }
                return $existingVisit;
            }

            // Resolve active cash session if not provided
            if (!$cashSessionId) {
                $activeSession = CashSession::where('clinic_id', $appointment->clinic_id)
                    ->whereDate('session_date', $appointment->appointment_date)
                    ->where('session_type', $appointment->session_type)
                    ->where('status', 'OPEN')
                    ->first();
                $cashSessionId = $activeSession?->id;
            }

            // Update appointment status to ARRIVED
            $appointment->update(['status' => 'ARRIVED']);

            // Detect OPD Revisit eligibility within 5 days for physical consultations
            $consultationMode = $appointment->consultation_mode ?? 'CLINIC_PHYSICAL';
            $eligibleRevisit = null;
            if ($consultationMode === 'CLINIC_PHYSICAL') {
                $eligibleRevisit = $this->appointmentService->getEligibleRevisitVisit($appointment->patient, $appointment->appointment_date);
            }

            // Create Encounter Visit
            $visit = Visit::create([
                'clinic_id' => $appointment->clinic_id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
                'appointment_id' => $appointment->id,
                'cash_session_id' => $cashSessionId,
                'visit_date' => $appointment->appointment_date,
                'session_type' => $appointment->session_type,
                'consultation_mode' => $consultationMode,
                'visit_type' => $eligibleRevisit ? 'REVISIT' : 'NEW',
                'revisit_parent_visit_id' => $eligibleRevisit?->id,
                'status' => 'REGISTERED',
                'arrived_at' => Carbon::now(),
            ]);

            DB::afterCommit(function () use ($appointment) {
                event(\App\Events\QueueUpdated::fromAppointment($appointment, 'ARRIVED'));
            });

            return $visit;
        });
    }

    /**
     * Start doctor consultation for a visit (Section 42, 45)
     */
    public function startConsultation(Visit $visit): Visit
    {
        return DB::transaction(function () use ($visit) {
            $visit->update([
                'status' => 'IN_PROGRESS',
                'started_at' => $visit->started_at ?? Carbon::now(),
            ]);

            $appointment = $visit->appointment;
            if ($appointment) {
                $appointment->update(['status' => 'IN_PROGRESS']);
                DB::afterCommit(function () use ($appointment) {
                    event(\App\Events\QueueUpdated::fromAppointment($appointment, 'IN_PROGRESS'));
                });
            }

            return $visit;
        });
    }

    /**
     * Complete doctor consultation for a visit (Section 42, 45)
     */
    public function completeConsultation(Visit $visit): Visit
    {
        return DB::transaction(function () use ($visit) {
            $visit->update([
                'status' => 'COMPLETED',
                'completed_at' => Carbon::now(),
            ]);

            $appointment = $visit->appointment;
            if ($appointment) {
                $appointment->update(['status' => 'COMPLETED']);
                DB::afterCommit(function () use ($appointment) {
                    event(\App\Events\QueueUpdated::fromAppointment($appointment, 'COMPLETED'));
                });
            }

            return $visit;
        });
    }
}
