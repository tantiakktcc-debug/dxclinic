<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\Visit;
use App\Models\Vital;
use App\Services\LabCollectionSlipService;
use App\Services\PatientAddressFormatter;
use App\Services\PrescriptionDataMapper;
use App\Services\PrescriptionPdfRenderer;
use App\Services\PrescriptionTemplateResolver;
use Illuminate\Http\Request;

class ReceiptPrintController extends Controller
{
    /**
     * Print Consultation Receipt with Patient Copy & Clinic Copy (A5 Only - Section 30-33)
     */
    /**
     * Print Consultation Receipt with Patient Copy & Clinic Copy (A5 Only - Section 30-33)
     */
    public function consultationReceipt(int $billId, ?Request $request = null)
    {
        $bill = ConsultationBill::with(['clinic', 'patient', 'doctor', 'payment', 'appointment'])
            ->findOrFail($billId);

        $this->authorizeClinicResource($bill);

        $copy = $request ? $request->query('copy', 'patient') : 'patient';

        return view('print.consultation-receipt', [
            'bill' => $bill,
            'clinic' => $bill->clinic,
            'patient' => $bill->patient,
            'payment' => $bill->payment,
            'formattedAddress' => $bill->patient->formatted_address,
            'copy' => $copy,
        ]);
    }

    /**
     * Print Doctor Pad Header overlay for pre-printed physical pad (Section 61, 62, 101)
     * Format: Name, Age/Sex, Address, City (never 'City: Varanasi'), Date, Token, Vitals.
     */
    public function doctorPad(int $appointmentId, ?Request $request = null)
    {
        $appointment = Appointment::with(['clinic', 'patient', 'doctor', 'visit.vital'])
            ->findOrFail($appointmentId);

        $this->authorizeClinicResource($appointment);

        $visit = $appointment->visit ?? Visit::where('appointment_id', $appointment->id)->latest()->first();
        $vital = $visit?->vital ?? ($visit ? Vital::where('visit_id', $visit->id)->latest()->first() : null);
        if (!$vital) {
            $vital = Vital::whereHas('visit', fn($q) => $q->where('appointment_id', $appointment->id))->latest()->first();
        }

        $resolver = app(PrescriptionTemplateResolver::class);
        $template = $resolver->resolveForAppointment($appointment, $appointment->clinic_id);

        if ($template) {
            $mode = ($request && $request->query('mode') === 'proof') ? 'proof' : 'production';
            $printerProfile = $resolver->getDefaultPrinterProfile($appointment->clinic_id);
            $data = PrescriptionDataMapper::mapEncounter($appointment, $visit, $vital);
            $renderer = app(PrescriptionPdfRenderer::class);
            $viewData = $renderer->prepareViewData($template, $data, $mode, $printerProfile);

            return view('print.prescription-pad-template', $viewData);
        }

        // Legacy fallback
        return view('print.doctor-pad', [
            'appointment' => $appointment,
            'patient' => $appointment->patient,
            'clinic' => $appointment->clinic,
            'doctor' => $appointment->doctor,
            'visit' => $visit,
            'vital' => $vital,
            // Strictly formatted as Address, City (Section 62, 101)
            'formattedAddress' => $appointment->patient->formatted_address,
        ]);
    }

    /**
     * Print Diagnostic Bill Receipt (A5 Only - Section 30-33)
     */
    public function diagnosticReceipt(int $billId, ?Request $request = null)
    {
        $bill = DiagnosticBill::with(['clinic', 'patient', 'doctor', 'payment', 'items'])
            ->findOrFail($billId);

        $this->authorizeClinicResource($bill);

        $copy = $request ? $request->query('copy', 'patient') : 'patient';

        return view('print.diagnostic-receipt', [
            'bill' => $bill,
            'clinic' => $bill->clinic,
            'patient' => $bill->patient,
            'payment' => $bill->payment,
            'items' => $bill->items,
            'formattedAddress' => $bill->patient->formatted_address,
            'copy' => $copy,
        ]);
    }

    /**
     * Print Lab Collection Slip (Section 70, 79)
     * Operational requisition slip without test results.
     */
    public function labCollectionSlip(int $billId, LabCollectionSlipService $slipService)
    {
        $bill = DiagnosticBill::findOrFail($billId);

        $this->authorizeClinicResource($bill);

        $data = $slipService->getSlipData($bill);

        return view('print.lab-collection-slip', $data);
    }

    /**
     * Print Diagnostic Result Report (HTML / Browser Print - Revision 36)
     */
    public function diagnosticResultReportPrint(int $reportId)
    {
        $report = \App\Models\DiagnosticReport::with([
            'clinic',
            'patient',
            'doctor',
            'order.items.diagnosticItem',
            'order.specimens',
            'finalizedBy',
            'releasedBy',
        ])->findOrFail($reportId);

        $this->authorizeClinicResource($report);

        $order = $report->order;
        $specimen = $order->specimens()->whereNotIn('status', ['REJECTED', 'CANCELLED'])->latest()->first();
        $results = $order->results()->where('status', '!=', 'CORRECTED')->get();

        $qrData = url("/verify-lab-report/{$report->report_number}");
        $qrPng = app(\App\Services\QrCodeService::class)->generatePng($qrData, 5, 2);
        $qrBase64 = 'data:image/png;base64,' . base64_encode($qrPng);

        return view('print.diagnostic-result-report-pdf', [
            'report' => $report,
            'order' => $order,
            'specimen' => $specimen,
            'results' => $results,
            'qrBase64' => $qrBase64,
        ]);
    }

    /**
     * Authorize that the resource belongs to the current user's clinic.
     */
    protected function authorizeClinicResource($resource): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }
        if ($user->role === 'SUPERADMIN') {
            return;
        }
        $clinicId = \App\Services\TenantContext::clinicId() ?? $user->clinic_id;
        if (!$clinicId || (int) $resource->clinic_id !== (int) $clinicId) {
            abort(403, 'Unauthorized access to resource belonging to another clinic.');
        }
    }
}
