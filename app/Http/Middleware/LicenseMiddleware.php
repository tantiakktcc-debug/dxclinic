<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LicenseMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Superadmin guard bypasses tenant licensing completely
        if (Auth::guard('developer')->check()) {
            return $next($request);
        }

        // 2. Allow auth and activation routes
        if ($request->routeIs('login', 'logout', 'superadmin.*', 'activation.*', 'force-password-change', 'force-password-change.*')) {
            return $next($request);
        }

        $clinic = TenantContext::clinic();
        if (!$clinic) {
            return $next($request);
        }

        // 2.5 Check Demo Tenant Status
        if ($clinic->isDemo()) {
            if ($clinic->isDemoPaused()) {
                if (Auth::guard('web')->check()) {
                    Auth::guard('web')->logout();
                }
                return response()->view('errors.demo_paused', [
                    'clinic' => $clinic,
                ], 503);
            }
            return $next($request);
        }

        // 3. Check Clinic Activation Status
        if (!$clinic->isActivated()) {
            if (Auth::check() && Auth::user()->isAdmin()) {
                return redirect()->route('activation.show');
            }

            return response()->view('errors.clinic_pending_activation', [
                'clinic' => $clinic,
            ], 403);
        }

        // 4. Check Forced Password Change for First Login
        if (Auth::check() && Auth::user()->must_change_password) {
            if (!$request->routeIs('force-password-change', 'force-password-change.submit')) {
                return redirect()->route('force-password-change');
            }
            return $next($request);
        }

        // 5. Check License Validity
        $license = $clinic->activeLicense;
        if (!$license || !$license->isValid()) {
            $status = $license ? $license->status : 'NO_LICENSE';
            if ($license && $license->isExpired()) {
                $status = 'EXPIRED';
            }

            return response()->view('errors.license_inactive', [
                'clinic' => $clinic,
                'license' => $license,
                'status' => $status,
            ], 403);
        }

        return $next($request);
    }
}
