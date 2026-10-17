<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Authoritative AI Tool Execution Layer
 * Contract Sections 29 & 30: AI can call ONLY approved tools;
 * tool executor is the strict tenant security boundary.
 */
class ClinicAiToolExecutor
{
    public function __construct(
        protected Clinic $clinic,
        protected ?ClinicKnowledgeBaseService $knowledgeService = null,
        protected ?ClinicScheduleService $scheduleService = null,
        protected ?AppointmentService $appointmentService = null,
        protected ?PatientIdentityService $patientIdentityService = null,
        protected ?BookingEligibilityService $eligibilityService = null
    ) {
        $this->knowledgeService = $knowledgeService ?? app(ClinicKnowledgeBaseService::class);
        $this->scheduleService = $scheduleService ?? app(ClinicScheduleService::class);
        $this->appointmentService = $appointmentService ?? app(AppointmentService::class);
        $this->patientIdentityService = $patientIdentityService ?? app(PatientIdentityService::class);
        $this->eligibilityService = $eligibilityService ?? app(BookingEligibilityService::class);
    }

    /**
     * Tool 1: searchClinicKnowledge()
     */
    public function searchClinicKnowledge(string $query, ?string $category = null): array
    {
        return $this->knowledgeService->searchKnowledge($this->clinic->id, $query, $category);
    }

    /**
     * Tool 2: getClinicInfo()
     */
    public function getClinicInfo(): array
    {
        return [
            'clinic_id' => $this->clinic->id,
            'name' => $this->clinic->name,
            'tagline' => $this->clinic->tagline ?? 'Multi-Specialty Clinical Center',
            'address' => $this->clinic->address,
            'city' => $this->clinic->city,
            'pincode' => $this->clinic->pincode,
            'phone' => $this->clinic->phone,
            'email' => $this->clinic->email,
            'public_slug' => $this->clinic->public_slug,
        ];
    }

    /**
     * Tool 3: getClinicHours()
     */
    public function getClinicHours(): array
    {
        $schedule = $this->clinic->schedule_config ?? $this->clinic->defaultSchedule();
        return [
            'clinic_id' => $this->clinic->id,
            'schedule' => $schedule,
            'max_advance_days' => $this->clinic->max_advance_booking_days,
            'sunday_enabled' => $this->clinic->sunday_enabled,
        ];
    }

    /**
     * Tool 4: getClinicServices()
     */
    public function getClinicServices(): array
    {
        $res = $this->knowledgeService->searchKnowledge($this->clinic->id, 'services diagnostics', 'SERVICES', 1);
        return [
            'clinic_id' => $this->clinic->id,
            'services_overview' => $res[0]['content'] ?? 'Outpatient consultation and diagnostic testing.',
        ];
    }

    /**
     * Tool 5: getConsultationFees()
     */
    public function getConsultationFees(?int $doctorId = null): array
    {
        $doc = null;
        if ($doctorId) {
            $doc = Doctor::where('clinic_id', $this->clinic->id)->find($doctorId);
        }

        $fee = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->clinic->id, $doc?->id);

        return [
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $doc?->id,
            'doctor_name' => $doc ? 'Dr. ' . ltrim($doc->name, 'Dr. ') : 'General OPD',
            'fee_inr' => $fee ?? 600.0,
            'revisit_5_day_waiver' => true,
            'payment_modes' => ['CASH', 'UPI'],
        ];
    }

    /**
     * Tool 6: getDoctors()
     */
    public function getDoctors(): array
    {
        $doctors = Doctor::where('clinic_id', $this->clinic->id)
            ->where('is_active', true)
            ->get(['id', 'clinic_id', 'name', 'qualification', 'specialization', 'room_number', 'is_active']);

        return [
            'clinic_id' => $this->clinic->id,
            'doctors' => $doctors->toArray(),
        ];
    }

    /**
     * Tool 7: getAvailableDates()
     */
    public function getAvailableDates(?int $doctorId = null): array
    {
        $dates = $this->eligibilityService->getAvailableDates($this->clinic, $doctorId);

        return [
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $doctorId,
            'available_dates' => $dates,
        ];
    }

    /**
     * Tool 8: getAvailableSessions()
     */
    public function getAvailableSessions(string $date, ?int $doctorId = null): array
    {
        $sessions = $this->eligibilityService->getAvailableSessions($this->clinic, $date, $doctorId);

        return [
            'clinic_id' => $this->clinic->id,
            'date' => $date,
            'doctor_id' => $doctorId,
            'available_sessions' => $sessions,
        ];
    }

    /**
     * Tool 9: getAvailableDoctors()
     */
    public function getAvailableDoctors(string $date, ?string $session = null): array
    {
        if ($session) {
            $doctors = $this->eligibilityService->getAvailableDoctors($this->clinic, $date, $session);
        } else {
            // Check all sessions for this date and collect distinct available doctors
            $allSessions = $this->scheduleService->getSessionsForDate($this->clinic, $date);
            $doctorsById = [];
            foreach (array_keys($allSessions) as $sessName) {
                $docs = $this->eligibilityService->getAvailableDoctors($this->clinic, $date, $sessName);
                foreach ($docs as $d) {
                    $doctorsById[$d['id']] = $d;
                }
            }
            $doctors = array_values($doctorsById);
        }

        return [
            'clinic_id' => $this->clinic->id,
            'date' => $date,
            'session' => $session,
            'doctors' => $doctors,
        ];
    }

    /**
     * Tool 10: findPatient()
     */
    public function findPatient(string $phoneOrId): ?array
    {
        $clean = preg_replace('/[^0-9]/', '', $phoneOrId);
        $raw10 = substr($clean, -10);

        $patient = Patient::where('clinic_id', $this->clinic->id)
            ->where(function ($q) use ($phoneOrId, $raw10) {
                $q->where('patient_id', $phoneOrId)
                    ->orWhere('id', (int) $phoneOrId);
                if (strlen($raw10) === 10) {
                    $q->orWhere('mobile', $raw10)
                        ->orWhere('mobile', '+91 ' . $raw10)
                        ->orWhere('mobile', 'LIKE', '%' . $raw10);
                }
            })
            ->first();

        if (!$patient) {
            return null;
        }

        return [
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->patient_id,
            'name' => $patient->name,
            'age' => $patient->age,
            'sex' => $patient->sex,
            'city' => $patient->city,
        ];
    }

    /**
     * Tool 11: createPatient()
     */
    public function createPatient(array $data): array
    {
        $data['clinic_id'] = $this->clinic->id; // Enforce clinic boundary
        $patient = $this->patientIdentityService->resolvePatient($data);

        return [
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->patient_id,
            'name' => $patient->name,
            'age' => $patient->age,
            'sex' => $patient->sex,
        ];
    }

    /**
     * Dispatcher to execute an approved AI tool by name with arguments.
     * Enforces strict authorization and tenant security boundary.
     */
    public function execute(string $toolName, array $params = []): array
    {
        $approvedTools = [
            'searchClinicKnowledge', 'getClinicInfo', 'getClinicHours', 'getClinicServices',
            'getConsultationFees', 'getDoctors', 'getAvailableDates', 'getAvailableSessions',
            'getAvailableDoctors', 'findPatient', 'createPatient', 'getPatient',
            'createAppointment', 'getAppointment', 'cancelAppointment', 'getBookingStatus', 'getTokenDetails',
        ];

        if (!in_array($toolName, $approvedTools, true) || !method_exists($this, $toolName)) {
            return [
                'success' => false,
                'error' => "Action '{$toolName}' is not an approved clinic AI tool.",
            ];
        }

        try {
            $reflection = new \ReflectionMethod($this, $toolName);
            $args = [];
            foreach ($reflection->getParameters() as $param) {
                $name = $param->getName();
                $snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
                $aliases = [
                    'phoneOrId' => ['mobile', 'phone', 'patient_id', 'id'],
                    'tokenOrId' => ['token', 'appointment_id', 'id'],
                    'date' => ['appointment_date'],
                    'patientId' => ['patient_id', 'id'],
                    'appointmentId' => ['appointment_id', 'id'],
                ];

                if (array_key_exists($name, $params)) {
                    $args[] = $params[$name];
                } elseif (array_key_exists($snakeName, $params)) {
                    $args[] = $params[$snakeName];
                } elseif (isset($aliases[$name])) {
                    $matchedAlias = false;
                    foreach ($aliases[$name] as $alias) {
                        if (array_key_exists($alias, $params)) {
                            $args[] = $params[$alias];
                            $matchedAlias = true;
                            break;
                        }
                    }
                    if (!$matchedAlias && $param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } elseif (!$matchedAlias) {
                        return [
                            'success' => false,
                            'error' => "Missing required parameter '{$name}' for tool '{$toolName}'.",
                        ];
                    }
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    return [
                        'success' => false,
                        'error' => "Missing required parameter '{$name}' for tool '{$toolName}'.",
                    ];
                }
            }

            $data = $reflection->invokeArgs($this, $args);
            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Tool 12: getPatient()
     */
    public function getPatient(int $patientId): ?array
    {
        $patient = Patient::where('clinic_id', $this->clinic->id)->find($patientId);
        if (!$patient) {
            throw new \InvalidArgumentException("Patient not found in this clinic.");
        }

        return [
            'id' => $patient->id,
            'patient_id' => $patient->patient_id,
            'name' => $patient->name,
            'age' => $patient->age,
            'sex' => $patient->sex,
            'city' => $patient->city,
        ];
    }

    /**
     * Tool 13: createAppointment()
     */
    public function createAppointment(array $params): array
    {
        $params['clinic_id'] = $this->clinic->id; // Enforce clinic boundary
        $params['booking_source'] = Appointment::SOURCE_WHATSAPP;
        $params['consultation_mode'] = 'CLINIC_PHYSICAL';

        $appointment = $this->appointmentService->bookAppointment($params);

        return [
            'appointment_id' => $appointment->id,
            'clinic_id' => $appointment->clinic_id,
            'token_number' => $appointment->token_number,
            'formatted_token' => $appointment->formatted_token,
            'appointment_date' => $appointment->appointment_date->toDateString(),
            'session_type' => $appointment->session_type,
            'status' => $appointment->status,
        ];
    }

    /**
     * Tool 14: getAppointment()
     */
    public function getAppointment(string $tokenOrId): ?array
    {
        $appt = Appointment::where('clinic_id', $this->clinic->id)
            ->where(function ($q) use ($tokenOrId) {
                $q->where('formatted_token', $tokenOrId)
                    ->orWhere('id', (int) $tokenOrId)
                    ->orWhere('token_number', (int) $tokenOrId);
            })
            ->with(['patient', 'doctor'])
            ->first();

        if (!$appt) return null;

        return [
            'id' => $appt->id,
            'clinic_id' => $appt->clinic_id,
            'token' => $appt->formatted_token,
            'patient_name' => $appt->patient?->name,
            'doctor_name' => $appt->doctor ? 'Dr. ' . ltrim($appt->doctor->name, 'Dr. ') : 'General OPD',
            'date' => $appt->appointment_date->toDateString(),
            'session' => $appt->session_type,
            'status' => $appt->status,
        ];
    }

    /**
     * Tool 15: cancelAppointment()
     */
    public function cancelAppointment(int $appointmentId, string $reason): array
    {
        $appt = Appointment::where('clinic_id', $this->clinic->id)->findOrFail($appointmentId);

        $appt->update([
            'status' => 'CANCELLED',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        return [
            'appointment_id' => $appt->id,
            'status' => 'CANCELLED',
            'token_retired' => $appt->formatted_token,
        ];
    }

    /**
     * Tool 16: getBookingStatus()
     */
    public function getBookingStatus(string $tokenOrId): ?array
    {
        return $this->getAppointment($tokenOrId);
    }

    /**
     * Tool 17: getTokenDetails()
     */
    public function getTokenDetails(string $tokenOrId): ?array
    {
        $appt = $this->getAppointment($tokenOrId);
        if (!$appt) return null;

        $date = $appt['date'];
        $aheadCount = Appointment::where('clinic_id', $this->clinic->id)
            ->whereDate('appointment_date', $date)
            ->where('session_type', $appt['session'])
            ->where('token_number', '<', (int) $appt['token'])
            ->whereIn('status', ['BOOKED', 'ARRIVED', 'IN_PROGRESS'])
            ->count();

        return [
            'token' => $appt['token'],
            'status' => $appt['status'],
            'session' => $appt['session'],
            'patients_ahead' => $aheadCount,
        ];
    }
}
