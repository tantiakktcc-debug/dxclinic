<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\TokenPdfService;
use Illuminate\Http\Request;

class TokenPrintController extends Controller
{
    /**
     * Render clean Token PDF / Print layout for patient (Section 51, 79)
     */
    public function show(string $secureToken, TokenPdfService $pdfService)
    {
        $appointment = Appointment::where('secure_token', $secureToken)
            ->with(['clinic', 'patient', 'doctor'])
            ->firstOrFail();

        $data = $pdfService->getTokenPdfData($appointment);

        return view('print.token-pdf', $data);
    }
}
