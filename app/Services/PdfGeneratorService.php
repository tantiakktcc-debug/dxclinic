<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfGeneratorService
{
    /**
     * Generate and stream a PDF from an HTML view.
     * Returns either an inline display or a download response.
     */
    public function generateFromView(
        string $viewName,
        array $data,
        string $filename,
        string|array $paperSize = 'a4',
        string $orientation = 'portrait',
        bool $download = false
    ): Response {
        $pdf = Pdf::loadView($viewName, $data)
            ->setPaper($paperSize, $orientation);

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Generate token PDF for a patient
     */
    public function generateTokenPdf(array $data): Response
    {
        if (isset($data['appointment']) && empty($data['formatted_token'])) {
            $tokenData = app(TokenPdfService::class)->getTokenPdfData($data['appointment']);
            $data = array_merge($tokenData, $data);
        }

        $tokenNumber = $data['formatted_token'] ?? ($data['appointment']->formatted_token ?? 'token');

        return $this->generateFromView(
            'print.token-pdf',
            $data,
            "token-{$tokenNumber}.pdf",
            'a5',
            'portrait'
        );
    }

    /**
     * Generate consultation receipt PDF (Strict A5 - Section 30)
     */
    public function generateConsultationReceiptPdf(array $data): Response
    {
        return $this->generateFromView(
            'print.consultation-receipt',
            $data,
            "consultation-receipt-{$data['bill']->bill_number}.pdf",
            'a5',
            'portrait'
        );
    }

    /**
     * Generate diagnostic receipt PDF (Strict A5 - Section 30)
     */
    public function generateDiagnosticReceiptPdf(array $data): Response
    {
        return $this->generateFromView(
            'print.diagnostic-receipt',
            $data,
            "diagnostic-receipt-{$data['bill']->bill_number}.pdf",
            'a5',
            'portrait'
        );
    }

    /**
     * Generate doctor pad PDF
     */
    public function generateDoctorPadPdf(array $data): Response
    {
        $data['isPdf'] = true;

        return $this->generateFromView(
            'print.doctor-pad',
            $data,
            "doctor-pad-{$data['appointment']->formatted_token}.pdf",
            [0, 0, 594, 840.312], // Authoritative 8.25in x 11.671in dimensions
            'portrait'
        );
    }

    /**
     * Generate lab collection slip PDF
     */
    public function generateLabSlipPdf(array $data): Response
    {
        $billNumber = $data['bill_number'] ?? ($data['bill']->bill_number ?? 'slip');

        return $this->generateFromView(
            'print.lab-collection-slip',
            $data,
            "lab-slip-{$billNumber}.pdf",
            'a4',
            'portrait'
        );
    }

    /**
     * Generate patient history PDF
     */
    public function generatePatientHistoryPdf(array $data): Response
    {
        return $this->generateFromView(
            'print.patient-history',
            $data,
            "patient-history-{$data['patient']->patient_id}.pdf",
            'a4',
            'portrait'
        );
    }

    /**
     * Generate OPD Consultation Report PDF (A4 Landscape)
     */
    public function generateOpdReportPdf(array $data): Response
    {
        $filename = "opd-report-" . date('Ymd-His') . ".pdf";
        return $this->generateFromView(
            'print.opd-report-pdf',
            $data,
            $filename,
            'a4',
            'landscape'
        );
    }

    /**
     * Generate Diagnostic Laboratory Report PDF (A4 Portrait)
     */
    public function generateDiagnosticReportPdf(array $data): Response
    {
        $filename = "diagnostic-report-" . date('Ymd-His') . ".pdf";
        return $this->generateFromView(
            'print.diagnostic-report-pdf',
            $data,
            $filename,
            'a4',
            'portrait'
        );
    }

    /**
     * Generate Clinical Patient Diagnostic Result Report PDF (A4 Portrait - Revision 36)
     */
    public function generateDiagnosticResultReportPdf(array $data, bool $download = false): Response
    {
        $report = $data['report'];
        $filename = "lab-report-{$report->report_number}.pdf";
        return $this->generateFromView(
            'print.diagnostic-result-report-pdf',
            $data,
            $filename,
            'a4',
            'portrait',
            $download
        );
    }

    /**
     * Generate User Manual PDF (A4 Portrait)
     */
    public function generateManualPdf(array $data): Response
    {
        $slug = \Illuminate\Support\Str::slug($data['manualTitle'] ?? 'manual');
        return $this->generateFromView(
            'print.manual-pdf',
            $data,
            "dxclinic-{$slug}.pdf",
            'a4',
            'portrait'
        );
    }
}
