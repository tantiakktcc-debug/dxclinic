<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Vital;
use App\Services\LabCollectionSlipService;
use App\Services\PatientAddressFormatter;
use App\Services\PdfGeneratorService;
use App\Services\PrescriptionDataMapper;
use App\Services\PrescriptionPdfRenderer;
use App\Services\PrescriptionTemplateResolver;
use Illuminate\Http\Request;

class PdfDownloadController extends Controller
{
    protected PdfGeneratorService $pdfService;

    public function __construct(PdfGeneratorService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Download consultation receipt as real PDF file (Strict A5)
     */
    public function consultationReceiptPdf(int $billId, Request $request)
    {
        $bill = ConsultationBill::with(['clinic', 'patient', 'doctor', 'payment', 'appointment'])
            ->findOrFail($billId);

        $this->authorizeClinicResource($bill);

        $data = [
            'bill' => $bill,
            'clinic' => $bill->clinic,
            'patient' => $bill->patient,
            'payment' => $bill->payment,
            'formattedAddress' => $bill->patient->formatted_address,
            'copy' => $request->query('copy', 'patient'),
        ];

        return $this->pdfService->generateConsultationReceiptPdf($data);
    }

    /**
     * Download doctor pad as real PDF file
     */
    public function doctorPadPdf(int $appointmentId, Request $request)
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
            $mode = $request->query('mode', 'production');
            if ($request->has('proof') && $request->boolean('proof')) {
                $mode = 'proof';
            }
            $printerProfile = $resolver->getDefaultPrinterProfile($appointment->clinic_id);
            $data = PrescriptionDataMapper::mapEncounter($appointment, $visit, $vital);
            $renderer = app(PrescriptionPdfRenderer::class);
            $filename = "doctor-pad-{$appointment->formatted_token}-{$mode}.pdf";

            return $renderer->renderPdf($template, $data, $mode, $printerProfile, $request->has('download'), $filename);
        }

        // Legacy fallback
        $data = [
            'appointment' => $appointment,
            'patient' => $appointment->patient,
            'clinic' => $appointment->clinic,
            'doctor' => $appointment->doctor,
            'visit' => $visit,
            'vital' => $vital,
            'formattedAddress' => $appointment->patient->formatted_address,
        ];

        return $this->pdfService->generateDoctorPadPdf($data);
    }

    /**
     * Download diagnostic receipt as real PDF file (Strict A5)
     */
    public function diagnosticReceiptPdf(int $billId, Request $request)
    {
        $bill = DiagnosticBill::with(['clinic', 'patient', 'doctor', 'payment', 'items'])
            ->findOrFail($billId);

        $this->authorizeClinicResource($bill);

        $data = [
            'bill' => $bill,
            'clinic' => $bill->clinic,
            'patient' => $bill->patient,
            'payment' => $bill->payment,
            'items' => $bill->items,
            'formattedAddress' => $bill->patient->formatted_address,
            'copy' => $request->query('copy', 'patient'),
        ];

        return $this->pdfService->generateDiagnosticReceiptPdf($data);
    }

    /**
     * Download lab collection slip as real PDF file
     */
    public function labSlipPdf(int $billId, LabCollectionSlipService $slipService)
    {
        $bill = DiagnosticBill::findOrFail($billId);

        $this->authorizeClinicResource($bill);

        $data = $slipService->getSlipData($bill);

        return $this->pdfService->generateLabSlipPdf($data);
    }

    /**
     * Download patient history as real PDF file
     */
    public function patientHistoryPdf(int $patientId)
    {
        $patient = Patient::with(['clinic'])->findOrFail($patientId);

        $this->authorizeClinicResource($patient);

        $visits = Visit::where('patient_id', $patientId)
            ->with(['appointment', 'vital', 'consultationBill.payment', 'diagnosticBills.items', 'doctor'])
            ->orderBy('visit_date', 'desc')
            ->get();

        $diagnosticReports = \App\Models\DiagnosticReport::where('patient_id', $patientId)
            ->where('status', 'RELEASED')
            ->with(['doctor', 'releasedBy', 'order.items.diagnosticItem'])
            ->orderBy('released_at', 'desc')
            ->get();

        $data = [
            'patient' => $patient,
            'clinic' => $patient->clinic,
            'visits' => $visits,
            'diagnosticReports' => $diagnosticReports,
            'formattedAddress' => $patient->formatted_address,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ];

        return $this->pdfService->generatePatientHistoryPdf($data);
    }

    /**
     * Download OPD Consultation Report as real PDF (Section 65, 112, 124)
     */
    public function opdReportPdf(Request $request, \App\Services\ReportService $reportService)
    {
        $clinic = \App\Services\TenantContext::clinic() ?? auth()->user()?->clinic ?? abort(403, 'Tenant context required.');
        $filters = $request->only([
            'period', 'start_date', 'end_date', 'doctor_id', 'session_type',
            'booking_source', 'consultation_mode', 'payment_status'
        ]);

        $report = $reportService->getOpdReport($clinic, $filters);

        $doctorScope = 'All Doctors';
        if (!empty($filters['doctor_id']) && $filters['doctor_id'] !== 'ALL') {
            $doc = \App\Models\Doctor::find($filters['doctor_id']);
            $doctorScope = $doc ? "Dr. {$doc->name}" : 'Selected Doctor';
        }

        $data = [
            'clinic' => $clinic,
            'report' => $report,
            'doctorScope' => $doctorScope,
            'title' => 'OPD Patient Consultation Report',
        ];

        return $this->pdfService->generateOpdReportPdf($data);
    }

    /**
     * Download Diagnostic Laboratory Report as real PDF (Section 65, 112, 125)
     */
    public function diagnosticReportPdf(Request $request, \App\Services\ReportService $reportService)
    {
        $clinic = \App\Services\TenantContext::clinic() ?? auth()->user()?->clinic ?? abort(403, 'Tenant context required.');
        $filters = $request->only([
            'period', 'start_date', 'end_date', 'doctor_id', 'session_type', 'payment_status'
        ]);

        $report = $reportService->getDiagnosticReport($clinic, $filters);

        $doctorScope = 'All Doctors';
        if (!empty($filters['doctor_id']) && $filters['doctor_id'] !== 'ALL') {
            $doc = \App\Models\Doctor::find($filters['doctor_id']);
            $doctorScope = $doc ? "Dr. {$doc->name}" : 'Selected Doctor';
        }

        $data = [
            'clinic' => $clinic,
            'report' => $report,
            'doctorScope' => $doctorScope,
            'title' => 'Diagnostic Laboratory Report',
        ];

        return $this->pdfService->generateDiagnosticReportPdf($data);
    }

    /**
     * Download Clinical Patient Diagnostic Result Report as real PDF (Revision 36)
     */
    public function diagnosticResultReportPdf(int $reportId, Request $request)
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

        $data = [
            'report' => $report,
            'order' => $order,
            'specimen' => $specimen,
            'results' => $results,
            'qrBase64' => $qrBase64,
        ];

        return $this->pdfService->generateDiagnosticResultReportPdf($data, $request->has('download'));
    }

    /**
     * Download Manual or Quick Card as real PDF (Section 26, 87, 88, 126, 127)
     */
    public function manualPdf(string $type)
    {
        $clinic = \App\Models\Clinic::first();

        $manualMap = [
            'operator' => [
                'file' => 'knowledge/manuals/operator_manual.md',
                'title' => 'Operator Complete Desk Operation Manual',
                'type' => 'Full Operator Manual',
            ],
            'operator-quick' => [
                'file' => 'knowledge/manuals/operator_quick_card.md',
                'title' => 'OPERATOR — QUICK OPERATION CARD',
                'type' => '1-Page Quick Reference Card',
            ],
            'doctor' => [
                'file' => 'knowledge/manuals/doctor_manual.md',
                'title' => 'Attending Physician & Doctor Operations Manual',
                'type' => 'Full Doctor Operation Manual',
            ],
            'doctor-quick' => [
                'file' => 'knowledge/manuals/doctor_quick_card.md',
                'title' => 'DOCTOR — QUICK OPERATION CARD',
                'type' => '1-Page Quick Reference Card',
            ],
            'admin' => [
                'file' => 'knowledge/manuals/admin_manual.md',
                'title' => 'Administrator Complete Setup & Controls Manual',
                'type' => 'Full Admin Manual',
            ],
            'admin-quick' => [
                'file' => 'knowledge/manuals/admin_quick_card.md',
                'title' => 'ADMIN — QUICK ADMINISTRATION CARD',
                'type' => '1-Page Quick Reference Card',
            ],
            'whatsapp' => [
                'file' => 'knowledge/manuals/whatsapp_manual.md',
                'title' => 'WhatsApp Operator Desk & AI Booking Manual',
                'type' => 'Standard Operating Procedure & Integration Architecture',
            ],
            'financial-workflows' => [
                'file' => 'knowledge/workflows/financial_workflows.md',
                'title' => 'Financial Workflows & Reconciliation Guide',
                'type' => 'Accounting Architecture Manual',
            ],
            'faq' => [
                'file' => 'knowledge/faq/faq.md',
                'title' => 'Clinical & Administrative FAQ',
                'type' => 'Searchable Knowledge Base',
            ],
            'troubleshooting' => [
                'file' => 'knowledge/troubleshooting/troubleshooting.md',
                'title' => 'System Troubleshooting & Diagnostics Manual',
                'type' => 'System Troubleshooting Guide',
            ],
        ];

        $target = $manualMap[$type] ?? $manualMap['operator'];
        $path = base_path($target['file']);
        $rawMarkdown = \Illuminate\Support\Facades\File::exists($path)
            ? \Illuminate\Support\Facades\File::get($path)
            : "# {$target['title']}\n\nManual content file not found.";

        $renderedHtml = \Illuminate\Support\Str::markdown($rawMarkdown);

        $data = [
            'clinic' => $clinic,
            'manualTitle' => $target['title'],
            'manualType' => $target['type'],
            'renderedContent' => $renderedHtml,
        ];

        return $this->pdfService->generateManualPdf($data);
    }

    /**
     * Public Verification View for Diagnostic Lab Report QR Code (Revision 36)
     */
    public function verifyLabReportPublic(string $reportNumber)
    {
        $report = \App\Models\DiagnosticReport::with(['clinic', 'patient', 'doctor', 'releasedBy'])
            ->where('report_number', $reportNumber)
            ->firstOrFail();

        return view('public.verify-lab-report', [
            'report' => $report,
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
