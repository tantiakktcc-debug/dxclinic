<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class QueueViewService
{
    public function __construct(
        protected ?FeeStatusResolver $feeStatusResolver = null,
        protected ?DoctorSeenStatusResolver $doctorSeenStatusResolver = null
    ) {
        $this->feeStatusResolver = $feeStatusResolver ?? app(FeeStatusResolver::class);
        $this->doctorSeenStatusResolver = $doctorSeenStatusResolver ?? app(DoctorSeenStatusResolver::class);
    }

    /**
     * Get authoritative queue rows and live metrics for a clinic and date
     */
    public function getQueueData(
        Clinic $clinic,
        ?string $date = null,
        string $sessionFilter = 'ALL',
        ?int $doctorFilter = null,
        ?User $user = null
    ): array {
        $user = $user ?? Auth::user();
        $dateStr = $date ? Carbon::parse($date)->toDateString() : ClinicClock::today()->toDateString();

        $query = Appointment::with([
            'patient:id,clinic_id,patient_id,legacy_alias,name,city,mobile',
            'doctor:id,clinic_id,name,specialization',
            'visit:id,appointment_id,status,arrived_at,completed_at',
            'visit.consultationBill.payments',
            'visit.consultationBill.refunds',
            'consultationBill.payments',
            'consultationBill.refunds',
        ])
            ->where('clinic_id', $clinic->id)
            ->whereDate('appointment_date', $dateStr)
            ->orderBy('token_number', 'asc');

        // Apply Doctor-role scope: A doctor can only see their own appointments by default (Section 33, 41, 64)
        if ($user && $user->role === 'DOCTOR') {
            $scopedDocId = $user->doctor_id;
            if ($scopedDocId) {
                $query->where('doctor_id', $scopedDocId);
            }
        } elseif ($doctorFilter) {
            // Admin/Operator explicit filter
            $query->where('doctor_id', $doctorFilter);
        }

        // Apply Session Filter (Section 40)
        $cleanSession = strtoupper(trim($sessionFilter));
        if ($cleanSession !== 'ALL' && in_array($cleanSession, ['MORNING', 'EVENING', 'SUNDAY_SINGLE'])) {
            $query->where('session_type', $cleanSession);
        }

        $appointments = $query->get();

        // Calculate authoritative metrics (Section 43)
        $metrics = $this->calculateMetrics($appointments);

        // Map presentation data (Section 37, 42)
        $items = $appointments->map(function (Appointment $app) {
            $computedStatus = $this->computeDisplayStatus($app);

            return [
                'id' => $app->id,
                'token_number' => $app->token_number,
                'formatted_token' => $app->formatted_token,
                'patient_name' => $app->patient?->name ?? 'Unknown Patient',
                'patient_id' => $app->patient?->patient_id ?? '',
                'city' => $app->patient?->city ?? '',
                'doctor_name' => $app->doctor ? 'Dr. ' . ltrim($app->doctor->name, 'Dr. ') : 'General OPD',
                'doctor_id' => $app->doctor_id,
                'session' => $app->session_type,
                'booking_source' => $app->booking_source,
                'fee_status' => $this->feeStatusResolver->resolve($app),
                'doctor_seen_status' => $this->doctorSeenStatusResolver->resolve($app),
                'appointment_status' => $app->status,
                'visit_status' => $app->visit?->status,
                'computed_status' => $computedStatus,
                'has_arrived' => in_array($computedStatus, ['ARRIVED', 'WAITING', 'IN_PROGRESS', 'COMPLETED']),
                'created_at' => $app->created_at?->toIso8601String(),
            ];
        });

        return [
            'clinic_id' => $clinic->id,
            'clinic_name' => $clinic->name,
            'clinic_slug' => $clinic->public_slug ?? $clinic->clinic_code,
            'date' => $dateStr,
            'session_filter' => $sessionFilter,
            'doctor_filter' => $doctorFilter,
            'metrics' => $metrics,
            'items' => $items,
            'total_count' => $items->count(),
            'fetched_at' => now()->format('H:i:s'),
            'timestamp' => now()->timestamp,
        ];
    }

    /**
     * Compute clean operational display status without mutating database state (Section 42)
     */
    public function computeDisplayStatus(Appointment $appointment): string
    {
        if ($appointment->status === 'CANCELLED') {
            return 'CANCELLED';
        }

        if ($appointment->status === 'ABSENT') {
            return 'ABSENT';
        }

        if ($appointment->visit) {
            $visitStatus = strtoupper($appointment->visit->status ?? '');
            if ($visitStatus === 'COMPLETED') {
                return 'COMPLETED';
            }
            if ($visitStatus === 'IN_PROGRESS' || $visitStatus === 'IN_CONSULTATION') {
                return 'IN_PROGRESS';
            }
            if ($visitStatus === 'REGISTERED' || $visitStatus === 'ARRIVED') {
                return 'ARRIVED';
            }
        }

        if ($appointment->status === 'ARRIVED') {
            return 'ARRIVED';
        }

        if ($appointment->status === 'COMPLETED') {
            return 'COMPLETED';
        }

        return 'BOOKED';
    }

    /**
     * Derive authoritative metrics from appointments collection
     */
    protected function calculateMetrics(Collection $appointments): array
    {
        $total = $appointments->count();
        $waiting = 0;
        $arrived = 0;
        $inConsultation = 0;
        $completed = 0;
        $cancelled = 0;
        $absent = 0;

        foreach ($appointments as $app) {
            $status = $this->computeDisplayStatus($app);

            switch ($status) {
                case 'BOOKED':
                    $waiting++;
                    break;
                case 'ARRIVED':
                    $arrived++;
                    $waiting++;
                    break;
                case 'IN_PROGRESS':
                    $inConsultation++;
                    break;
                case 'COMPLETED':
                    $completed++;
                    break;
                case 'CANCELLED':
                    $cancelled++;
                    break;
                case 'ABSENT':
                    $absent++;
                    break;
            }
        }

        return [
            'total' => $total,
            'waiting' => $waiting,
            'arrived' => $arrived,
            'in_consultation' => $inConsultation,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'absent' => $absent,
        ];
    }
}
