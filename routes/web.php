<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingQrController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PdfDownloadController;
use App\Http\Controllers\ReceiptPrintController;
use App\Http\Controllers\TokenPrintController;
use App\Livewire\Cash\CashSessionManager;
use App\Livewire\Cash\DailyReportView;
use App\Livewire\ClinicDoctorManager;
use App\Livewire\Dashboard;
use App\Livewire\HelpCenter;
use App\Livewire\Onboarding\ClinicOnboarding;
use App\Livewire\Lab\DiagnosticLabWorklist;
use App\Livewire\Operator\ArrivalQueue;
use App\Livewire\Operator\ConsultationBilling;
use App\Livewire\Operator\DiagnosticBilling;
use App\Livewire\Operator\OperatorBooking;
use App\Livewire\PatientManager;
use App\Livewire\Pwa\PatientBooking;
use App\Livewire\Reports\DiagnosticReportView;
use App\Livewire\Reports\FinancialReconciliationView;
use App\Livewire\Reports\OpdReportView;
use App\Livewire\Settings\BackupManager;
use App\Livewire\Settings\BankAccountManager;
use App\Livewire\Settings\BookingQrManager;
use App\Livewire\Settings\BookingTypeManager;
use App\Livewire\Settings\BrandingManager;
use App\Livewire\Settings\ExpenseCategoryManager;
use App\Livewire\Settings\FeeMasterManager;
use App\Livewire\Settings\PrescriptionPadDesigner;
use App\Livewire\Settings\PrescriptionPadManager;
use App\Livewire\Settings\UserManager;
use App\Livewire\Settings\VitalMasterManager;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\Superadmin\SuperadminAuthController;
use App\Http\Controllers\Superadmin\SuperadminDashboardController;
use App\Http\Controllers\Superadmin\SuperadminSalesController;
use App\Http\Controllers\LicenseRequestController;

/*
|--------------------------------------------------------------------------
| DxClinic Web Routes (Revision 34 Multi-Tenant)
|--------------------------------------------------------------------------
*/

// Public License Request & Renewal Request Pages (Revision 36)
// Rate-limited, no auth required — public-facing SaaS commercial layer
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/license-request', [LicenseRequestController::class, 'showRequestForm'])->name('license.request');
    Route::post('/license-request', [LicenseRequestController::class, 'submitRequest'])->name('license.request.submit');
    Route::get('/license-renewal-request', [LicenseRequestController::class, 'showRenewalForm'])->name('license.renewal-request');
    Route::post('/license-renewal-request', [LicenseRequestController::class, 'submitRenewal'])->name('license.renewal-request.submit');
    Route::get('/pricing', [LicenseRequestController::class, 'pricing'])->name('license.pricing');
});

// Superadmin / Developer Authentication & Fleet Management
Route::prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', function() { return redirect()->route('superadmin.login'); });
    Route::get('/login', [SuperadminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [SuperadminAuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [SuperadminAuthController::class, 'logout'])->name('logout');

    Route::middleware('superadmin')->group(function () {
        Route::get('/dashboard', [SuperadminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/license-history', [SuperadminDashboardController::class, 'history'])->name('license-history');
        Route::get('/license-history/{licenseRequest}', [SuperadminDashboardController::class, 'historyDetail'])->name('license-history.show');
        Route::get('/licenses/issue', [SuperadminDashboardController::class, 'showIssueForm'])->name('licenses.issue');
        Route::post('/licenses/issue', [SuperadminDashboardController::class, 'issueLicense'])->name('licenses.issue.submit');
        Route::get('/licenses/{license}/reveal', [SuperadminDashboardController::class, 'revealKey'])->name('licenses.reveal-key');
        Route::get('/clinics/{clinic}/renew', [SuperadminDashboardController::class, 'showRenewForm'])->name('clinics.renew-form');
        Route::post('/clinics/{clinic}/renew', [SuperadminDashboardController::class, 'renew'])->name('clinics.renew');
        Route::get('/clinics/{clinic}/history', [SuperadminDashboardController::class, 'clinicHistory'])->name('clinics.history');
        Route::post('/clinics/{clinic}/suspend', [SuperadminDashboardController::class, 'suspend'])->name('clinics.suspend');
        Route::post('/clinics/{clinic}/reactivate', [SuperadminDashboardController::class, 'reactivate'])->name('clinics.reactivate');
        Route::post('/clinics/{clinic}/revoke', [SuperadminDashboardController::class, 'revoke'])->name('clinics.revoke');
        Route::post('/clinics/{clinic}/mark-activated', [SuperadminDashboardController::class, 'markActivated'])->name('clinics.mark-activated');
        Route::post('/clinics/{clinic}/add-doctor-seats', [SuperadminDashboardController::class, 'addDoctorSeats'])->name('clinics.add-doctor-seats');
        Route::get('/clinics/{clinic}/events', [SuperadminDashboardController::class, 'events'])->name('clinics.events');

        // Revision 36: License Request Management
        Route::get('/license-requests', [SuperadminDashboardController::class, 'licenseRequests'])->name('license-requests');
        Route::get('/license-requests/{licenseRequest}', [SuperadminDashboardController::class, 'showLicenseRequest'])->name('license-requests.show');
        Route::post('/license-requests/{licenseRequest}/review', [SuperadminDashboardController::class, 'reviewLicenseRequest'])->name('license-requests.review');
        Route::post('/license-requests/{licenseRequest}/fulfill', [SuperadminDashboardController::class, 'fulfillLicenseRequest'])->name('license-requests.fulfill');
        Route::post('/license-requests/{licenseRequest}/reject', [SuperadminDashboardController::class, 'rejectLicenseRequest'])->name('license-requests.reject');

        // Revision 37: Demo Tenant Management
        Route::post('/demo/pause', [SuperadminDashboardController::class, 'pauseDemo'])->name('demo.pause');
        Route::post('/demo/resume', [SuperadminDashboardController::class, 'resumeDemo'])->name('demo.resume');
        Route::post('/demo/reset', [SuperadminDashboardController::class, 'resetDemoData'])->name('demo.reset');

        // Revision 42: Platform Sales, Invoices, Collections, Expenses & Accounting
        Route::get('/sales', [SuperadminSalesController::class, 'salesDashboard'])->name('sales');
        Route::get('/invoices', [SuperadminSalesController::class, 'invoicesList'])->name('invoices');
        Route::get('/invoices/{invoice}', [SuperadminSalesController::class, 'invoiceDetail'])->name('invoices.show');
        Route::get('/invoices/{invoice}/print', [SuperadminSalesController::class, 'invoicePrint'])->name('invoices.print');
        Route::get('/invoices/{invoice}/pdf', [SuperadminSalesController::class, 'invoicePdf'])->name('invoices.pdf');
        Route::post('/invoices/{invoice}/cancel', [SuperadminSalesController::class, 'cancelInvoice'])->name('invoices.cancel');
        Route::get('/collections', [SuperadminSalesController::class, 'collections'])->name('collections');
        Route::get('/clinics/{clinic}/sales', [SuperadminSalesController::class, 'clinicSales'])->name('clinics.sales');
        Route::get('/expenses', [SuperadminSalesController::class, 'expenses'])->name('expenses');
        Route::post('/expenses', [SuperadminSalesController::class, 'storeExpense'])->name('expenses.store');
    });
});

// Clinic License Activation & Initial Password Change
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/activate-clinic', [ActivationController::class, 'show'])->name('activation.show');
    Route::post('/activate-clinic', [ActivationController::class, 'activate'])->name('activation.submit');
    Route::get('/force-password-change', [PasswordChangeController::class, 'show'])->name('force-password-change');
    Route::post('/force-password-change', [PasswordChangeController::class, 'update'])->name('force-password-change.submit');
});

// Legal & Developer Information Routes (Revision 19 - Section 6-12)
Route::get('/about', [LegalController::class, 'about'])->name('about');
Route::get('/privacy-policy', [LegalController::class, 'privacyPolicy'])->name('privacy.policy');
Route::get('/terms-and-conditions', [LegalController::class, 'termsConditions'])->name('terms.conditions');

// Authentication Routes (Revision 18/19/34)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

// Root Redirect
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Patient Self-Booking PWA & Verification (Revision 47: Public Slug Canonical)
Route::match(['get', 'post'], '/book/{public_slug}', PatientBooking::class)->middleware('tenant')->name('pwa.book');
Route::get('/b/{public_slug}', function (string $public_slug) {
    $clinic = \App\Models\Clinic::where('public_slug', strtolower(trim($public_slug)))->first();
    if (!$clinic) {
        abort(404, 'Clinic not found.');
    }
    return redirect()->route('pwa.book', ['public_slug' => $clinic->public_slug], 302);
})->name('pwa.book.short');
Route::get('/democlinic', function () {
    return redirect()->route('pwa.book', ['public_slug' => 'democlinic'], 302);
})->name('pwa.book.democlinic');
Route::get('/verify-token/{secureToken}', [TokenPrintController::class, 'show'])->name('token.verify');
Route::get('/verify-lab-report/{report_number}', [PdfDownloadController::class, 'verifyLabReportPublic'])->name('lab.report.verify');

// Clinic Minimal Lookup for Login Screen (Correction 8)
Route::get('/api/clinic-lookup/{clinic_code}', [\App\Http\Controllers\ClinicLookupController::class, 'lookup'])
    ->middleware('throttle:30,1')
    ->name('api.clinic-lookup');

// Tenant Protected Workflows (Auth + Tenant Context + Valid Active License)
Route::middleware(['auth', 'tenant', 'license'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/subscription', \App\Livewire\Settings\LicenseSubscriptionManager::class)->name('subscription');

    // Tenant-isolated resource endpoints (Correction 1 & 14 negative security testing)
    Route::get('/patients/{patient}', function (\App\Models\Patient $patient) {
        return response()->json(['patient_id' => $patient->patient_id, 'name' => $patient->name]);
    })->name('tenant.patient.show');

    Route::get('/appointments/{appointment}', function (\App\Models\Appointment $appointment) {
        return response()->json(['appointment_id' => $appointment->id, 'token' => $appointment->token_number]);
    })->name('tenant.appointment.show');

    Route::get('/templates/{template}', function (\App\Models\PrescriptionPadTemplate $template) {
        return response()->json(['template_id' => $template->id, 'name' => $template->name]);
    })->name('tenant.template.show');

    Route::get('/fees/{fee}', function (\App\Models\FeeMaster $fee) {
        return response()->json(['fee_id' => $fee->id, 'amount' => $fee->amount]);
    })->name('tenant.fee.show');

    // Operator Desk Workflows (Revision 25 Section 9, 50, Revision 47 Tenant-Scoped Operator Queue)
    Route::get('/clinic/{public_slug}/operator/queue', ArrivalQueue::class)->name('operator.queue');
    Route::get('/operator/whatsapp-desk', \App\Livewire\Operator\WhatsAppDesk::class)->name('operator.whatsapp-desk');
    Route::get('/whatsapp/desk', \App\Livewire\Operator\WhatsAppDesk::class)->name('whatsapp.desk');

    // Live Queue Window (Contract Section 32, 34, 69)
    Route::get('/clinic/{public_slug}/queue/live', \App\Livewire\Queue\LiveQueue::class)->name('queue.live');
    Route::get('/queue/live', \App\Livewire\Queue\LiveQueue::class)->name('queue.live.direct');
    Route::prefix('operator')->name('operator.')->group(function () {
        Route::get('/booking', OperatorBooking::class)->name('booking');
        Route::get('/consultation/{appointmentId?}', ConsultationBilling::class)->name('consultation');
        Route::get('/diagnostics/{appointmentId?}', DiagnosticBilling::class)->name('diagnostics');
    });

    // Patient Management (Revision 23)
    Route::get('/patients', PatientManager::class)->name('patients');

    // Diagnostic Laboratory Workflow (Revision 36)
    Route::get('/lab', DiagnosticLabWorklist::class)->name('lab.worklist');

    // Cash Session & Financial Control (Section 31-47, 71-77)
    Route::prefix('cash')->name('cash.')->group(function () {
        Route::get('/sessions', CashSessionManager::class)->name('sessions');
        Route::get('/daily-report', DailyReportView::class)->name('daily-report');
    });

    // Financial & Clinical Reports Subsystem (Revision 25 Section 54-65)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/opd', OpdReportView::class)->name('opd');
        Route::get('/diagnostics', DiagnosticReportView::class)->name('diagnostics');
        Route::get('/reconciliation', FinancialReconciliationView::class)->name('reconciliation');
        Route::get('/daily-cash', DailyReportView::class)->name('daily-cash');
    });

    // Settings & Administration Subsystem (Revision 24 - Section 5)
    Route::prefix('settings')->name('settings.')->group(function () {
        // 1. Clinic & Doctor
        Route::get('/clinic-doctor', ClinicDoctorManager::class)->name('clinic-doctor');

        // 2. Onboarding
        Route::get('/onboarding', ClinicOnboarding::class)->name('onboarding');

        // 3. Users & Operators
        Route::get('/users', UserManager::class)->name('users');

        // 4. Expense Categories
        Route::get('/expense-categories', ExpenseCategoryManager::class)->name('expense-categories');

        // 5. Fee Master
        Route::get('/fee-master', FeeMasterManager::class)->name('fee-master');

        // 5b. Booking Types (Revision 44)
        Route::get('/booking-types', BookingTypeManager::class)->name('booking-types');

        // 6. Vital Master
        Route::get('/vital-master', VitalMasterManager::class)->name('vital-master');

        // 7. Bank Accounts
        Route::get('/bank-accounts', BankAccountManager::class)->name('bank-accounts');

        // 8. Print & Branding
        Route::get('/branding', BrandingManager::class)->name('branding');

        // Prescription Pad Templates (Revision 27)
        Route::get('/prescription-pad-templates', PrescriptionPadManager::class)->name('prescription-pad-templates');
        Route::get('/prescription-pad-designer/{templateId}', PrescriptionPadDesigner::class)->name('prescription-pad-designer');

        // 9. Online Booking / QR
        Route::get('/booking-qr', BookingQrManager::class)->name('booking-qr');
        Route::get('/booking-qr/download-png/{type}', [BookingQrController::class, 'downloadPng'])->name('booking-qr.download-png');
        Route::get('/booking-qr/download-svg/{type}', [BookingQrController::class, 'downloadSvg'])->name('booking-qr.download-svg');
        Route::get('/booking-qr/poster/{type}', [BookingQrController::class, 'printPoster'])->name('booking-qr.poster');

        // 10. Backup & Restore
        Route::get('/backup', BackupManager::class)->name('backup');

        // 11. Help / Manuals / FAQ
        Route::get('/help/{tab?}', HelpCenter::class)->name('help');

        // 12. License & Subscription (Phase 19)
        Route::get('/subscription', \App\Livewire\Settings\LicenseSubscriptionManager::class)->name('subscription');

        // 13. WhatsApp Automation & Connection (Contract Section 31)
        Route::get('/whatsapp', \App\Livewire\Settings\WhatsAppSettings::class)->name('whatsapp');
    });

    // Protected Printing Routes (Tenant & Resource Isolated)
    Route::prefix('print')->name('print.')->group(function () {
        Route::get('/consultation-receipt/{billId}', [ReceiptPrintController::class, 'consultationReceipt'])->name('consultation.receipt');
        Route::get('/consultation-receipt-dash/{billId}', [ReceiptPrintController::class, 'consultationReceipt'])->name('consultation-receipt');
        Route::get('/doctor-pad/{appointmentId}', [ReceiptPrintController::class, 'doctorPad'])->name('doctor.pad');
        Route::get('/doctor-pad-dash/{appointmentId}', [ReceiptPrintController::class, 'doctorPad'])->name('doctor-pad');
        Route::get('/diagnostic-receipt/{billId}', [ReceiptPrintController::class, 'diagnosticReceipt'])->name('diagnostic.receipt');
        Route::get('/diagnostic-receipt-dash/{billId}', [ReceiptPrintController::class, 'diagnosticReceipt'])->name('diagnostic-receipt');
        Route::get('/lab-collection-slip/{billId}', [ReceiptPrintController::class, 'labCollectionSlip'])->name('lab.slip');
        Route::get('/lab-collection-slip-dash/{billId}', [ReceiptPrintController::class, 'labCollectionSlip'])->name('lab-collection-slip');
        Route::get('/diagnostic-result-report/{reportId}', [ReceiptPrintController::class, 'diagnosticResultReportPrint'])->name('diagnostic-result-report');
    });

    // Protected Real PDF Download Routes (Revision 23/24/25 - barryvdh/laravel-dompdf)
    Route::prefix('pdf')->name('pdf.')->group(function () {
        Route::get('/consultation-receipt/{billId}', [PdfDownloadController::class, 'consultationReceiptPdf'])->name('consultation-receipt');
        Route::get('/doctor-pad/{appointmentId}', [PdfDownloadController::class, 'doctorPadPdf'])->name('doctor-pad');
        Route::get('/diagnostic-receipt/{billId}', [PdfDownloadController::class, 'diagnosticReceiptPdf'])->name('diagnostic-receipt');
        Route::get('/lab-slip/{billId}', [PdfDownloadController::class, 'labSlipPdf'])->name('lab-slip');
        Route::get('/patient-history/{patientId}', [PdfDownloadController::class, 'patientHistoryPdf'])->name('patient-history');
        Route::get('/opd-report', [PdfDownloadController::class, 'opdReportPdf'])->name('opd-report');
        Route::get('/diagnostic-report', [PdfDownloadController::class, 'diagnosticReportPdf'])->name('diagnostic-report');
        Route::get('/diagnostic-result-report/{reportId}', [PdfDownloadController::class, 'diagnosticResultReportPdf'])->name('diagnostic-result-report');
        Route::get('/prescription-template/{templateId}', function (int $templateId, \Illuminate\Http\Request $request) {
            $template = \App\Models\PrescriptionPadTemplate::with(['doctor', 'clinic', 'elements'])->findOrFail($templateId);
            $userClinicId = \App\Services\TenantContext::clinicId() ?? auth()->user()?->clinic_id;
            if (auth()->user()?->role !== 'SUPERADMIN' && (int) $template->clinic_id !== (int) $userClinicId) {
                abort(403, 'Unauthorized access to prescription template of another clinic.');
            }
            $mode = ($request->query('mode') === 'proof' || $request->boolean('proof')) ? 'proof' : 'production';
            $data = \App\Services\PrescriptionDataMapper::getSampleData($template->doctor, $template->clinic);
            $printer = app(\App\Services\PrescriptionTemplateResolver::class)->getDefaultPrinterProfile($template->clinic_id);
            return app(\App\Services\PrescriptionPdfRenderer::class)->renderPdf($template, $data, $mode, $printer, $request->has('download'), "prescription-template-{$template->id}-{$mode}.pdf");
        })->name('prescription-template');
    });

    // Help Direct Alias
    Route::get('/help/{tab?}', HelpCenter::class)->name('help');
});

// Clinic Onboarding Setup Wizard
Route::get('/onboarding', ClinicOnboarding::class)->name('onboarding');

// Public Token Printing & PDF (cryptographic token capability URL)
Route::get('/token-pdf/{secureToken}', [TokenPrintController::class, 'show'])->name('token.pdf');
Route::get('/print/token/{secureToken}', [TokenPrintController::class, 'show'])->name('print.token');

// Private Temporary Signed Token Slip Download (Revision 50 - 15-minute expiration)
Route::get('/token-slip-document/{secureToken}', function (string $secureToken, \Illuminate\Http\Request $request) {
    if (!$request->hasValidSignature()) {
        abort(403, 'Invalid or expired token slip access link.');
    }

    $appointment = \App\Models\Appointment::where('secure_token', $secureToken)
        ->with(['clinic', 'patient', 'doctor'])
        ->firstOrFail();

    $data = app(\App\Services\TokenPdfService::class)->getTokenPdfData($appointment);

    return app(\App\Services\PdfGeneratorService::class)->generateTokenPdf($data);
})->name('token.slip.private-download');

// Public Documentation Manuals PDF
Route::get('/pdf/manual/{type}', [PdfDownloadController::class, 'manualPdf'])->name('pdf.manual');

// Professional HTML User Manuals & FAQ Portal (Revision 39)
Route::prefix('manuals')->name('manuals.')->group(function () {
    Route::get('/', function () {
        $path = public_path('manuals/index.html');
        return file_exists($path)
            ? response(file_get_contents($path), 200, ['Content-Type' => 'text/html'])
            : abort(404, 'Manuals hub not found.');
    })->name('hub');

    Route::get('/{slug}', function (string $slug) {
        $cleanSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($slug));
        
        // Match exact or suffixed file
        $candidates = [
            public_path("manuals/{$cleanSlug}.html"),
            public_path("manuals/{$cleanSlug}_manual.html"),
            public_path("manuals/{$cleanSlug}_quick_card.html"),
        ];

        foreach ($candidates as $filePath) {
            if (file_exists($filePath)) {
                return response(file_get_contents($filePath), 200, ['Content-Type' => 'text/html']);
            }
        }

        return redirect()->route('manuals.hub');
    })->name('view');
});

// Development-only Mobile Quick Access Aid (Revision 21 - Section 4)
if (app()->environment('local', 'development')) {
    Route::get('/mobile-test', function () {
        return view('dev.mobile-test');
    })->name('dev.mobile-test');
}
