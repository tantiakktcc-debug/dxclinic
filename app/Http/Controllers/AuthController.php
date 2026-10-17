<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('developer')->check()) {
            return redirect()->route('superadmin.dashboard');
        }

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $defaultClinic = Clinic::where('clinic_code', 'NX0001')->first() ?? Clinic::first();

        return view('auth.login', compact('defaultClinic'));
    }

    public function login(Request $request): RedirectResponse
    {
        $loginInput = trim((string) ($request->input('login_id') ?? $request->input('email') ?? ''));
        $password = (string) $request->input('password', '');
        $clinicCode = strtoupper(trim((string) ($request->input('clinic_code') ?? 'NX0001')));

        if ($loginInput === '' || $password === '') {
            throw ValidationException::withMessages([
                'login_id' => 'Please provide your User ID and password.',
            ]);
        }

        // 1. Resolve Clinic
        $clinic = Clinic::where('clinic_code', $clinicCode)->first();

        // 2. Resolve User by Login ID or Email within Clinic
        $user = null;
        $isEmail = filter_var($loginInput, FILTER_VALIDATE_EMAIL);

        if ($isEmail) {
            $query = User::where('email', $loginInput);
            if ($clinic) {
                $query->where('clinic_id', $clinic->id);
            }
            $user = $query->first();

            // Fallback if not found with clinic_id
            if (!$user) {
                $user = User::where('email', $loginInput)->first();
            }
        } else {
            // Login ID (e.g. ADM0001)
            $upperLoginId = strtoupper($loginInput);
            $query = User::where('login_id', $upperLoginId);
            if ($clinic) {
                $query->where('clinic_id', $clinic->id);
            }
            $user = $query->first();

            // Fallback across clinics if clinic code wasn't explicit
            if (!$user) {
                $user = User::where('login_id', $upperLoginId)->first();
            }
        }

        // 3. Verify user and password with timing-attack safe comparison
        if (!$user || !Hash::check($password, $user->password)) {
            $msg = 'Invalid clinic ID, user ID, or password.';
            throw ValidationException::withMessages([
                'login_id' => $msg,
                'email' => $msg,
            ]);
        }

        if (!$user->isActive()) {
            $msg = 'Your staff account is currently inactive. Contact clinic administrator.';
            throw ValidationException::withMessages([
                'login_id' => $msg,
                'email' => $msg,
            ]);
        }

        // 4. Log in the user
        Auth::login($user, $request->boolean('remember'));
        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        $userClinic = $user->clinic ?? Clinic::find($user->clinic_id) ?? $clinic ?? Clinic::first();
        if ($userClinic) {
            TenantContext::setClinic($userClinic);
        }

        // 4.5 Check Demo Clinic Status
        if ($userClinic && $userClinic->isDemoPaused()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'login_id' => 'The DxClinic demonstration environment is temporarily unavailable. Please try again later.',
            ]);
        }

        // 5. Check Clinic Activation Status
        if ($userClinic && !$userClinic->isActivated()) {
            if ($user->isAdmin()) {
                return redirect()->route('activation.show');
            }

            Auth::logout();
            throw ValidationException::withMessages([
                'login_id' => 'This clinic account is pending first-time activation. Please contact the administrator.',
            ]);
        }

        // 6. Check Forced Password Change
        if ($user->must_change_password) {
            return redirect()->route('force-password-change');
        }

        // 7. Check Clinic Onboarding
        if ($userClinic && !$userClinic->is_onboarded) {
            return redirect()->route('onboarding');
        }

        $intended = $request->session()->pull('url.intended');
        if ($intended && !str_contains($intended, '/superadmin')) {
            return redirect()->to($intended);
        }

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        TenantContext::reset();

        return redirect()->route('login')->with('status', 'You have been successfully logged out.');
    }
}
