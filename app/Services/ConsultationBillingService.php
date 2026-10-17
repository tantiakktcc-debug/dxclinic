<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ConsultationBill;
use App\Models\FeeMaster;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConsultationBillingService
{
    /**
     * Create or retrieve Consultation Bill for a visit (Section 57, 72, 73, 86)
     */
    public function createOrGetBill(Visit $visit, float $discount = 0.00, float $waiver = 0.00): ConsultationBill
    {
        return DB::transaction(function () use ($visit, $discount, $waiver) {
            $existing = ConsultationBill::where('visit_id', $visit->id)->first();
            if ($existing) {
                return $existing;
            }

            $appointment = $visit->appointment;
            $mode = $visit->consultation_mode ?: ($appointment?->consultation_mode ?: 'CLINIC_PHYSICAL');

            // Resolve Fee Master rate (Doctor-specific -> Clinic default -> System fallback)
            $doctorId = $visit->doctor_id ?: $appointment?->doctor_id;

            if ($appointment && $appointment->fee_amount !== null) {
                $gross = (float) $appointment->fee_amount;
            } elseif ($appointment && $appointment->booking_type_id && $doctorId) {
                $btFee = FeeMaster::getFeeForBookingType($visit->clinic_id, $doctorId, $appointment->booking_type_id, $appointment->appointment_date?->toDateString());
                $gross = $btFee !== null ? (float) $btFee : FeeMaster::getFeeForMode($mode, $visit->clinic_id, $doctorId);
            } else {
                $gross = FeeMaster::getFeeForMode($mode, $visit->clinic_id, $doctorId);
            }

            $waiverType = null;

            // Check OPD Revisit rule (Revision 25 Section 12-16)
            if ($visit->visit_type === 'REVISIT') {
                $discount = 0.00;
                $waiver = $gross;
                $waiverType = 'OPD_REVISIT';
                $net = 0.00;
                $status = 'WAIVED';
            } elseif (!empty($appointment?->grace_parent_appointment_id)) {
                // Check Telephone Grace rule (Section 86)
                $discount = 0.00;
                $waiver = $gross;
                $waiverType = 'TELEPHONE_GRACE';
                $net = 0.00;
                $status = 'WAIVED';
            } else {
                $net = round($gross - $discount - $waiver, 2);
                $status = $net == 0 ? 'WAIVED' : 'PENDING';
            }

            if (!ConsultationBill::validateInvariants($gross, $discount, $waiver, $net)) {
                throw new InvalidArgumentException("Financial invariant violated: net ({$net}) must equal gross ({$gross}) - discount ({$discount}) - waiver ({$waiver}).");
            }

            $billNumber = 'BILL-C-' . date('Ymd') . '-' . str_pad((string) rand(100, 9999), 4, '0', STR_PAD_LEFT);

            return ConsultationBill::create([
                'clinic_id' => $visit->clinic_id,
                'bill_number' => $billNumber,
                'appointment_id' => $appointment?->id,
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'doctor_id' => $visit->doctor_id,
                'bill_date' => Carbon::now()->toDateString(),
                'consultation_mode' => $mode,
                'waiver_type' => $waiverType,
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'waiver_amount' => $waiver,
                'net_amount' => $net,
                'status' => $status,
            ]);
        });
    }
}
