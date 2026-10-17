<?php

namespace App\Http\Controllers;

use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PasswordChangeController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->must_change_password) {
            return redirect()->route('dashboard');
        }

        $clinic = $user->clinic ?? TenantContext::clinic();

        return view('auth.force_password_change', compact('user', 'clinic'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = Hash::make($validated['password']);
        $user->must_change_password = false;
        $user->save();

        $clinic = $user->clinic ?? TenantContext::clinic();

        if ($clinic && !$clinic->is_onboarded) {
            return redirect()->route('onboarding')->with(
                'success',
                'Password updated successfully! Complete the clinic onboarding setup.'
            );
        }

        return redirect()->route('dashboard')->with(
            'success',
            'Password updated successfully. Welcome to DxClinic!'
        );
    }
}
