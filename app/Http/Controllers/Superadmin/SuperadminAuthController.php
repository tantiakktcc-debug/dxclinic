<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperadminAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('developer')->check()) {
            return redirect()->route('superadmin.dashboard');
        }

        return view('superadmin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::guard('developer')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('developer')->user();
            if (!$user->is_active) {
                Auth::guard('developer')->logout();
                return back()->withErrors(['email' => 'Account is deactivated.']);
            }

            $user->update(['last_login_at' => now()]);
            $request->session()->regenerate();

            $intended = $request->session()->pull('url.intended');
            if ($intended && str_contains($intended, '/superadmin') && !str_contains($intended, '/superadmin/login')) {
                return redirect()->to($intended);
            }

            return redirect()->route('superadmin.dashboard');
        }

        return back()->withInput($request->only('email'))->withErrors([
            'email' => 'Invalid Superadmin credentials.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('developer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login');
    }
}
