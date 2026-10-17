<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BookingType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorBookingTypeAvailability;
use App\Models\FeeMaster;
use App\Models\Patient;
use App\Services\BookingTypeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AppointmentService
{
    public function __construct(
        protected TokenAllocationService $tokenAllocationService,
        protected ClinicScheduleService $clinicScheduleService,
        protected BookingTypeService $bookingTypeService,
        protected ?BookingEligibilityService $eligibilityService = null
    ) {
        $this->eligibilityService = $eligibilityService ?? app(BookingEligibilityService::class);
    }

    /**
     * Book an appointment with date-scoped common token (Section 48, 49, 50)
     */
    public function bookAppointment(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $clinicId = (int) ($data['clinic_id'] ?? Clinic::first()?->id);
            $clinic = Clinic::findOrFail($clinicId);

            $patientId = (int) ($data['patient_id'] ?? 0);
            $patient = Patient::findOrFail($patientId);

            if ((int) $patient->clinic_id !== (int) $clinic->id) {
                throw new InvalidArgumentException("Patient does not belong to this clinic.");
            }

            $dateStr = Carbon::parse($data['appointment_date'])->toDateString();
            $sessionType = strtoupper(trim($data['session_type']));
            $bookingSource = strtoupper(trim($data['booking_source']));
            $consultationMode = strtoupper(trim((string) ($data['consultation_mode'] ?? 'CLINIC_PHYSICAL')));

            if (!in_array($bookingSource, ['ONLINE', 'TELEPHONIC', 'CLINIC', 'WHATSAPP'])) {
                throw new InvalidArgumentException("Booking source must be ONLINE, TELEPHONIC, CLINIC, or WHATSAPP.");
            }

            if (!in_array($consultationMode, ['CLINIC_PHYSICAL', 'ONLINE'])) {
                $consultationMode = 'CLINIC_PHYSICAL';
            }

            // Authoritative doctor resolution & locking (Revision 24 Section 41-44, Revision 46)
            $doctorId = null;
            if (!empty($data['doctor_id'])) {
                $candidateDoctor = Doctor::where('clinic_id', $clinic->id)
                    ->where('id', (int) $data['doctor_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                if ($candidateDoctor) {
                    $doctorId = $candidateDoctor->id;
                }
            }

            if (!$doctorId && $clinic->default_doctor_id) {
                $defaultDoc = Doctor::where('clinic_id', $clinic->id)
                    ->where('id', $clinic->default_doctor_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                if ($defaultDoc) {
                    $doctorId = $defaultDoc->id;
                }
            }

            if (!$doctorId) {
                $doctorId = Doctor::where('clinic_id', $clinic->id)->where('is_active', true)->lockForUpdate()->first()?->id
                    ?? Doctor::where('clinic_id', $clinic->id)->lockForUpdate()->first()?->id;
            }

            // Validate doctor belongs strictly to this clinic
            if ($doctorId) {
                $doctorRecord = Doctor::where('id', $doctorId)->where('clinic_id', $clinic->id)->first();
                if (!$doctorRecord || !$doctorRecord->is_active) {
                    throw new InvalidArgumentException("Selected doctor does not belong to this clinic or is inactive.");
                }
            }

            // Strict Authoritative Booking Eligibility Guard (Sections 1, 4, 18, 40, 41)
            $eligibility = $this->eligibilityService->evaluateEligibility(
                $clinic,
                $dateStr,
                $sessionType,
                $doctorId
            );

            if (!$eligibility['eligible']) {
                throw new InvalidArgumentException($eligibility['reason']);
            }

            // ---------------------------------------------------------------
            // Booking Type & Fee Resolution (Revision 45/46 Strict Contract)
            // Clinic -> Doctor -> Mapped Booking Type -> Mapped Fee -> Appointment
            // ---------------------------------------------------------------
            $bookingTypeId = null;
            $bookingTypeName = null;
            $feeAmount = null;

            if (!empty($data['booking_type_code']) || !empty($data['booking_type_id'])) {
                $bt = null;
                if (!empty($data['booking_type_code'])) {
                    $bt = $this->bookingTypeService->resolveByCode(
                        $data['booking_type_code'],
                        $clinic->id
                    );
                } elseif (!empty($data['booking_type_id'])) {
                    $bt = BookingType::where('clinic_id', $clinic->id)
                        ->where('id', (int) $data['booking_type_id'])
                        ->first();
                }

                if (!$bt || !$bt->is_active) {
                    throw new InvalidArgumentException("Booking type is invalid, inactive, or belongs to another clinic.");
                }

                // Verify doctor is mapped and enabled for this booking type
                if ($doctorId) {
                    $avail = DoctorBookingTypeAvailability::where('clinic_id', $clinic->id)
                        ->where('doctor_id', $doctorId)
                        ->where('booking_type_id', $bt->id)
                        ->where('is_enabled', true)
                        ->exists();

                    if (!$avail) {
                        throw new InvalidArgumentException("Booking type '{$bt->name}' is not enabled for the selected doctor.");
                    }

                    // Verify exact active fee is configured for this (clinic, doctor, booking_type)
                    $fee = FeeMaster::getFeeForBookingType($clinic->id, $doctorId, $bt->id, $dateStr);
                    if ($fee === null) {
                        throw new InvalidArgumentException("Fee is not configured for this doctor and consultancy type.");
                    }
                    $feeAmount = (float) $fee;
                }

                $bookingTypeId = $bt->id;
                $bookingTypeName = $bt->name;
                $consultationMode = $bt->consultation_mode;
            } else {
                // Legacy consultation_mode fallback
                $feeAmount = FeeMaster::getFeeForMode($consultationMode, $clinic->id, $doctorId);
            }

            $graceParentId = !empty($data['grace_parent_appointment_id']) ? (int) $data['grace_parent_appointment_id'] : null;

            // Allocate atomic common date-and-session scoped token (Revision 32)
            $tokenData = $this->tokenAllocationService->allocateToken($clinic, $dateStr, $sessionType);

            $secureToken = hash('sha256', Str::random(40) . microtime() . $patient->id . $tokenData['token_number']);

            $appointment = Appointment::create([
                'clinic_id'                   => $clinic->id,
                'patient_id'                  => $patient->id,
                'doctor_id'                   => $doctorId,
                'appointment_date'            => $dateStr,
                'session_type'                => $sessionType,
                'booking_source'              => $bookingSource,
                'consultation_mode'           => $consultationMode,
                'booking_type_id'             => $bookingTypeId,
                'fee_amount'                  => $feeAmount,
                'prescription_template_name'  => null,
                'token_number'                => $tokenData['token_number'],
                'formatted_token'             => $tokenData['formatted_token'],
                'status'                      => 'BOOKED',
                'secure_token'                => $secureToken,
                'grace_parent_appointment_id' => $graceParentId,
            ]);

            // Broadcast QueueUpdated ONLY AFTER DB transaction successfully commits (Section 45)
            DB::afterCommit(function () use ($appointment) {
                event(\App\Events\QueueUpdated::fromAppointment($appointment, 'BOOKED'));
            });

            return $appointment;
        });
    }

    /**
     * Check if a patient is eligible for telephone grace rebooking within 5 calendar days (Section 86)
     */
    public function getEligibleTelephoneGraceAppointment(Patient $patient, Carbon|string $currentDate): ?Appointment
    {
        $today = is_string($currentDate) ? Carbon::parse($currentDate) : $currentDate->copy();
        $graceDays = (int) config('dxclinic.telephone_grace_days', 5);

        return Appointment::where('patient_id', $patient->id)
            ->where('booking_source', 'TELEPHONIC')
            ->where('status', 'ABSENT')
            ->whereDate('appointment_date', '>=', $today->copy()->subDays($graceDays)->toDateString())
            ->whereDate('appointment_date', '<', $today->toDateString())
            ->whereDoesntHave('graceChildren')
            ->latest('appointment_date')
            ->first();
    }

    /**
     * Check if a patient is eligible for free OPD revisit within 5 calendar days (Revision 25 Section 14-16)
     */
    public function getEligibleRevisitVisit(Patient $patient, Carbon|string $currentDate): ?\App\Models\Visit
    {
        $today = is_string($currentDate) ? Carbon::parse($currentDate) : $currentDate->copy();
        $revisitDays = 5;

        // Qualifying completed physical consultation within 5 calendar days
        return \App\Models\Visit::where('patient_id', $patient->id)
            ->where('status', 'COMPLETED')
            ->where(function ($q) {
                $q->where('consultation_mode', 'CLINIC_PHYSICAL')
                  ->orWhereNull('consultation_mode');
            })
            ->whereDate('visit_date', '>=', $today->copy()->subDays($revisitDays)->toDateString())
            ->whereDate('visit_date', '<=', $today->toDateString())
            ->whereDoesntHave('revisitChildVisits')
            ->latest('visit_date')
            ->first();
    }
}
