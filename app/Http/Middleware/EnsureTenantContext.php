<?php

namespace App\Http\Middleware;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\FeeMaster;
use App\Models\Patient;
use App\Models\PrescriptionPadTemplate;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    /**
     * Handle an incoming request with strict tenant context security.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Exception B: Superadmin / Developer Guard
        if (Auth::guard('developer')->check()) {
            $clinic = TenantContext::resolveSuperadminClinic($request);
            View::share('currentClinic', $clinic);
            return $next($request);
        }

        // Authoritative Rule: Authenticated Tenant User (ADMIN / OPERATOR / DOCTOR)
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if ($user->role === 'SUPERADMIN') {
                $authoritativeClinic = TenantContext::resolveSuperadminClinic($request) ?? $user->clinic ?? Clinic::where('clinic_code', 'NX0001')->first() ?? Clinic::first();
            } else {
                $authoritativeClinic = $user->clinic ?? ($user->clinic_id ? Clinic::find($user->clinic_id) : null);
                if (!$authoritativeClinic && empty($user->clinic_id)) {
                    $authoritativeClinic = Clinic::where('clinic_code', 'NX0001')->first() ?? Clinic::first();
                }
            }

            if (!$authoritativeClinic) {
                abort(403, 'User is not assigned to any clinic.');
            }

            // Correction 1 Security Checks:
            // Check 1: X-Clinic-Code header
            // If sent and does not match the user's clinic, reject with 403 or ignore
            $headerClinicCode = $request->header('X-Clinic-Code');
            if ($headerClinicCode && strtoupper(trim($headerClinicCode)) !== strtoupper($authoritativeClinic->clinic_code)) {
                abort(403, 'Cross-tenant access prohibited via X-Clinic-Code header.');
            }

            // Check 2: X-Clinic-ID header
            // If sent, ensure tenant context strictly remains the authenticated user's clinic
            // TenantContext::clinic() authoritatively returns $authoritativeClinic

            // Check 3: Route-level cross-clinic identifier attempt
            $routeClinicCode = $request->route('clinic_code');
            if ($routeClinicCode && is_string($routeClinicCode) && strtoupper(trim($routeClinicCode)) !== strtoupper($authoritativeClinic->clinic_code)) {
                abort(403, 'Cross-tenant route access prohibited.');
            }

            $routePublicSlug = $request->route('public_slug');
            if ($routePublicSlug && is_string($routePublicSlug)) {
                $routeClinic = Clinic::where('public_slug', strtolower(trim($routePublicSlug)))->first();
                if (!$routeClinic) {
                    // Slug doesn't resolve to any clinic — 404 prevents leaking tenant existence
                    abort(404, 'Clinic not found.');
                }
                if ($user->role !== 'SUPERADMIN') {
                    if ((int) $routeClinic->id !== (int) $authoritativeClinic->id) {
                        abort(403, 'Cross-tenant route access prohibited.');
                    }
                }
            }

            // Register URL defaults so route('operator.queue') automatically injects public_slug
            if (!empty($authoritativeClinic->public_slug)) {
                \Illuminate\Support\Facades\URL::defaults(['public_slug' => $authoritativeClinic->public_slug]);
            }

            // Check 4: Cross-Tenant Resource Protection (IDOR protection on patients, appointments, templates, fees, bills)
            if ($user->role !== 'SUPERADMIN') {
                $this->validateResourceOwnership($request, (int) $authoritativeClinic->id);
            }

            // Check Demo Pause State
            if ($authoritativeClinic->isDemoPaused()) {
                Auth::guard('web')->logout();
                return response()->view('errors.demo_paused', [], 503);
            }

            // Always enforce authoritative clinic
            TenantContext::setClinic($authoritativeClinic);
            View::share('currentClinic', $authoritativeClinic);

            return $next($request);
        }

        // Exception A: Public Booking (/book/{public_slug} or /b/{public_slug})
        $clinic = TenantContext::resolvePublicClinic($request);
        if (!$clinic) {
            abort(404, 'Clinic not found.');
        }

        if ($clinic->isDemoPaused()) {
            return response()->view('errors.demo_paused', [
                'clinic' => $clinic,
            ], 503);
        }

        TenantContext::setClinic($clinic);
        View::share('currentClinic', $clinic);

        return $next($request);
    }

    /**
     * Validate that any model passed via route parameters belongs to the authenticated user's clinic.
     */
    protected function validateResourceOwnership(Request $request, int $userClinicId): void
    {
        // 1. Patient check (/patients/{patient} or /pdf/patient-history/{patientId})
        $patient = $request->route('patient') ?? $request->route('patientId');
        if ($patient) {
            $patientModel = $patient instanceof Patient ? $patient : Patient::find($patient);
            if ($patientModel && (int) $patientModel->clinic_id !== $userClinicId) {
                abort(403, 'Cross-tenant access denied. Patient belongs to another clinic.');
            }
        }

        // 2. Appointment check (/appointments/{appointment} or {appointmentId})
        $appointment = $request->route('appointment') ?? $request->route('appointmentId');
        if ($appointment) {
            $appointmentModel = $appointment instanceof Appointment ? $appointment : Appointment::find($appointment);
            if ($appointmentModel && (int) $appointmentModel->clinic_id !== $userClinicId) {
                abort(403, 'Cross-tenant access denied. Appointment belongs to another clinic.');
            }
        }

        // 3. Template check (/templates/{template} or {templateId})
        $template = $request->route('template') ?? $request->route('templateId');
        if ($template) {
            $templateModel = $template instanceof PrescriptionPadTemplate ? $template : PrescriptionPadTemplate::find($template);
            if ($templateModel && (int) $templateModel->clinic_id !== $userClinicId) {
                abort(403, 'Cross-tenant access denied. Prescription template belongs to another clinic.');
            }
        }

        // 4. Fee check (/fees/{fee} or {feeId})
        $fee = $request->route('fee') ?? $request->route('feeId');
        if ($fee) {
            $feeModel = $fee instanceof FeeMaster ? $fee : FeeMaster::find($fee);
            if ($feeModel && (int) $feeModel->clinic_id !== $userClinicId) {
                abort(403, 'Cross-tenant access denied. Fee structure belongs to another clinic.');
            }
        }

        // 5. Bill check ({billId}) in consultation/diagnostic receipts or lab collection slips
        $billId = $request->route('billId');
        if ($billId) {
            $conBill = \App\Models\ConsultationBill::find($billId);
            if ($conBill && (int) $conBill->clinic_id !== $userClinicId) {
                abort(403, 'Cross-tenant access denied. Consultation bill belongs to another clinic.');
            }
            $diagBill = \App\Models\DiagnosticBill::find($billId);
            if ($diagBill && (int) $diagBill->clinic_id !== $userClinicId) {
                abort(403, 'Cross-tenant access denied. Diagnostic bill belongs to another clinic.');
            }
        }
    }
}
