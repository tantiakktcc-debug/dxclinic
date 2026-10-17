<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivationController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only Clinic Administrators can perform license activation.');
        }

        $clinic = $user->clinic ?? TenantContext::clinic();
        if ($clinic && $clinic->isActivated()) {
            return redirect()->route('dashboard');
        }

        return view('auth.activate_clinic', compact('clinic', 'user'));
    }

    public function activate(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only Clinic Administrators can perform license activation.');
        }

        $clinic = $user->clinic ?? TenantContext::clinic();
        if (!$clinic) {
            abort(400, 'Clinic could not be resolved.');
        }

        $validated = $request->validate([
            'activation_code' => ['required', 'string', 'max:50'],
        ]);

        try {
            LicenseService::activateLicense(
                clinicId: $clinic->id,
                adminUserId: $user->id,
                activationCode: trim($validated['activation_code']),
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );

            return redirect()->route('force-password-change')->with(
                'success',
                'Clinic license successfully activated! Please set your new permanent password.'
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors([
                'activation_code' => $e->getMessage(),
            ]);
        }
    }
}
