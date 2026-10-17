<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\DiagnosticBillItem;
use App\Models\Doctor;
use App\Models\Payment;
use Carbon\Carbon;

class ReportService
{
    /**
     * Resolve date range from period or explicit dates.
     */
    public function resolveDateRange(string $period = 'daily', ?string $startDate = null, ?string $endDate = null): array
    {
        $today = Carbon::today();

        switch ($period) {
            case 'daily':
                $start = $startDate ? Carbon::parse($startDate)->startOfDay() : $today->copy()->startOfDay();
                $end = $start->copy()->endOfDay();
                break;
            case 'weekly':
                $start = $today->copy()->startOfWeek();
                $end = $today->copy()->endOfWeek();
                break;
            case 'monthly':
                $start = $today->copy()->startOfMonth();
                $end = $today->copy()->endOfMonth();
                break;
            case 'quarterly':
                $start = $today->copy()->firstOfQuarter();
                $end = $today->copy()->lastOfQuarter();
                break;
            case 'half_yearly':
                $month = $today->month;
                if ($month <= 6) {
                    $start = Carbon::create($today->year, 1, 1)->startOfDay();
                    $end = Carbon::create($today->year, 6, 30)->endOfDay();
                } else {
                    $start = Carbon::create($today->year, 7, 1)->startOfDay();
                    $end = Carbon::create($today->year, 12, 31)->endOfDay();
                }
                break;
            case 'financial_year':
                // April 1 to March 31
                $year = $today->month >= 4 ? $today->year : $today->year - 1;
                $start = Carbon::create($year, 4, 1)->startOfDay();
                $end = Carbon::create($year + 1, 3, 31)->endOfDay();
                break;
            case 'custom':
            default:
                $start = $startDate ? Carbon::parse($startDate)->startOfDay() : $today->copy()->startOfDay();
                $end = $endDate ? Carbon::parse($endDate)->endOfDay() : $start->copy()->endOfDay();
                break;
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    /**
     * Get OPD / Session-wise / Day-wise / Doctor-wise Patient Report (Section 54-59, 124)
     * Strictly EXCLUDES vitals.
     * TOTAL COLLECTION calculates strictly from settled consultation payments.
     */
    public function getOpdReport(Clinic $clinic, array $filters = []): array
    {
        $period = $filters['period'] ?? 'daily';
        [$startDate, $endDate] = $this->resolveDateRange($period, $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        $query = Appointment::with(['patient', 'doctor', 'visit.consultationBill.payments'])
            ->where('clinic_id', $clinic->id)
            ->whereBetween('appointment_date', [$startDate, $endDate]);

        // Doctor Filter: All Doctors or Specific Doctor (Section 57, 58, 59)
        if (!empty($filters['doctor_id']) && $filters['doctor_id'] !== 'ALL') {
            $query->where('doctor_id', (int) $filters['doctor_id']);
        }

        // Session Filter: MORNING, EVENING, SUNDAY, or ALL (Section 55, 64)
        if (!empty($filters['session_type']) && $filters['session_type'] !== 'ALL') {
            $query->where('session_type', strtoupper($filters['session_type']));
        }

        // Booking Source Filter: CLINIC, ONLINE, TELEPHONIC, or ALL (Section 53)
        if (!empty($filters['booking_source']) && $filters['booking_source'] !== 'ALL') {
            $query->where('booking_source', strtoupper($filters['booking_source']));
        }

        // Consultation Mode Filter: CLINIC_PHYSICAL, ONLINE, or ALL (Section 53)
        if (!empty($filters['consultation_mode']) && $filters['consultation_mode'] !== 'ALL') {
            $query->where('consultation_mode', strtoupper($filters['consultation_mode']));
        }

        $appointments = $query->orderBy('appointment_date')
            ->orderBy('token_number')
            ->get();

        $rows = [];
        $totalCollection = 0.00;
        $totalBilled = 0.00;
        $totalWaived = 0.00;

        foreach ($appointments as $appt) {
            $patient = $appt->patient;
            $doctor = $appt->doctor;
            $visit = $appt->visit;
            $bill = $visit?->consultationBill;

            // Resolve settled payment amount and payment mode
            $paidFee = 0.00;
            $paymentModes = [];
            $billStatus = 'PENDING';
            $waiverType = $bill?->waiver_type;

            if ($bill) {
                $totalBilled += (float) $bill->gross_amount;
                $totalWaived += (float) $bill->waiver_amount;

                if ($bill->status === 'PAID') {
                    $settledPayments = $bill->payments->where('status', 'SETTLED');
                    foreach ($settledPayments as $p) {
                        $paidFee += (float) $p->amount;
                        $paymentModes[] = $p->payment_method;
                    }
                    $billStatus = 'PAID';
                } elseif ($bill->status === 'WAIVED') {
                    $billStatus = 'WAIVED';
                    $paidFee = 0.00; // Zero fee revisit or telephone grace per Section 16, 17
                }
            }

            // Payment Status Filter
            if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'ALL') {
                $filterStatus = strtoupper($filters['payment_status']);
                if ($billStatus !== $filterStatus) {
                    continue;
                }
            }

            $totalCollection += $paidFee;

            // Formatted Booking Source
            $sourceLabel = match($appt->booking_source) {
                'CLINIC' => 'Clinic',
                'ONLINE' => 'Online',
                'TELEPHONIC' => 'Telephonic',
                default => $appt->booking_source ?? 'Clinic',
            };

            // Formatted Consultation Type (Revision 38)
            $modeLabel = match($appt->consultation_mode) {
                'ONLINE' => 'Online Consultancy',
                'VISITING_CONSULTANCY', 'VISITING' => 'Visiting Consultancy',
                'CLINIC_PHYSICAL' => 'Clinic Consultancy',
                default => 'Clinic Consultancy',
            };

            // Formatted payment mode display
            $paymentModeStr = !empty($paymentModes) ? implode(', ', array_unique($paymentModes)) : ($billStatus === 'WAIVED' ? 'WAIVED (₹0)' : 'UNPAID');

            // Strictly EXCLUDES vitals per Section 54, 56, 110, 124
            $rows[] = [
                'appointment_id' => $appt->id,
                'date' => $appt->appointment_date ? $appt->appointment_date->format('Y-m-d') : '',
                'date_formatted' => $appt->appointment_date ? $appt->appointment_date->format('d M Y') : '',
                'patient_id' => $patient?->patient_id ?? 'N/A',
                'patient_name' => $patient?->name ?? 'Unknown',
                'age' => $patient?->age ?? 0,
                'sex' => $patient?->sex ?? 'MALE',
                'address' => $patient?->address ?? 'N/A',
                'city' => $patient?->city ?? 'Varanasi',
                'mobile' => $patient?->mobile ?? 'N/A',
                'token' => $appt->formatted_token ?? str_pad((string)$appt->token_number, 3, '0', STR_PAD_LEFT),
                'token_raw' => $appt->token_number,
                'session' => $appt->session_type,
                'booking_source' => $sourceLabel,
                'consultation_type' => $modeLabel,
                'paid_fee' => round($paidFee, 2),
                'gross_fee' => $bill ? (float) $bill->gross_amount : 600.00,
                'payment_mode' => $paymentModeStr,
                'doctor' => $doctor ? 'Dr. ' . $doctor->name : 'Unassigned',
                'doctor_id' => $doctor?->id,
                'bill_status' => $billStatus,
                'waiver_type' => $waiverType,
            ];
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period' => $period,
            'rows' => $rows,
            'total_patients' => count($rows),
            'total_collection' => round($totalCollection, 2),
            'total_billed' => round($totalBilled, 2),
            'total_waived' => round($totalWaived, 2),
        ];
    }

    /**
     * Get Diagnostic Session-wise & Day-wise Report (Section 60, 61, 62, 125)
     * TOTAL COLLECTION calculates strictly from settled diagnostic payments.
     */
    public function getDiagnosticReport(Clinic $clinic, array $filters = []): array
    {
        $period = $filters['period'] ?? 'daily';
        [$startDate, $endDate] = $this->resolveDateRange($period, $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        $query = DiagnosticBill::with(['patient', 'doctor', 'items', 'payments', 'visit.appointment'])
            ->where('clinic_id', $clinic->id)
            ->whereBetween('bill_date', [$startDate, $endDate]);

        // Doctor Filter: All Doctors or Specific Doctor
        if (!empty($filters['doctor_id']) && $filters['doctor_id'] !== 'ALL') {
            $query->where('doctor_id', (int) $filters['doctor_id']);
        }

        $bills = $query->orderBy('bill_date')->orderBy('id')->get();

        $rows = [];
        $totalCollection = 0.00;
        $totalBilled = 0.00;

        foreach ($bills as $bill) {
            $patient = $bill->patient;
            $doctor = $bill->doctor;
            $visit = $bill->visit;
            $appt = $visit?->appointment;
            $session = $visit?->session_type ?? ($appt?->session_type ?? 'MORNING');

            // Session Filter
            if (!empty($filters['session_type']) && $filters['session_type'] !== 'ALL') {
                if (strtoupper($session) !== strtoupper($filters['session_type'])) {
                    continue;
                }
            }

            // Payment status & mode
            $paidAmount = 0.00;
            $paymentModes = [];
            $settledPayments = $bill->payments->where('status', 'SETTLED');
            foreach ($settledPayments as $p) {
                $paidAmount += (float) $p->amount;
                $paymentModes[] = $p->payment_method;
            }

            // Status filter
            if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'ALL') {
                $filterStatus = strtoupper($filters['payment_status']);
                if ($bill->status !== $filterStatus) {
                    continue;
                }
            }

            $totalCollection += $paidAmount;
            $totalBilled += (float) $bill->net_amount;

            $itemsSummary = $bill->items->pluck('item_name')->join(', ');
            if (empty($itemsSummary)) {
                $itemsSummary = 'General Diagnostic Tests';
            }

            $paymentModeStr = !empty($paymentModes) ? implode(', ', array_unique($paymentModes)) : ($bill->status === 'PAID' ? 'SETTLED' : 'UNPAID');

            $rows[] = [
                'bill_id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'date' => $bill->bill_date ? $bill->bill_date->format('Y-m-d') : '',
                'date_formatted' => $bill->bill_date ? $bill->bill_date->format('d M Y') : '',
                'patient_id' => $patient?->patient_id ?? 'N/A',
                'patient_name' => $patient?->name ?? 'Unknown',
                'diagnostic_item' => $itemsSummary,
                'amount' => (float) $bill->net_amount,
                'paid_amount' => round($paidAmount, 2),
                'doctor' => $doctor ? 'Dr. ' . $doctor->name : 'Unassigned',
                'doctor_id' => $doctor?->id,
                'session' => $session,
                'payment_status' => $bill->status,
                'payment_mode' => $paymentModeStr,
            ];
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period' => $period,
            'rows' => $rows,
            'total_items' => count($rows),
            'total_billed' => round($totalBilled, 2),
            'total_collection' => round($totalCollection, 2),
        ];
    }
}
