<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperadminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('developer')->check()) {
            if (Auth::guard('web')->check() && $request->routeIs('superadmin.license-history*')) {
                abort(403, 'Unauthorized access to SuperAdmin License History.');
            }
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized. Superadmin access required.'], 403);
            }
            return redirect()->guest(route('superadmin.login'));
        }

        $user = Auth::guard('developer')->user();
        if (!$user->is_active) {
            Auth::guard('developer')->logout();
            return redirect()->route('superadmin.login')->withErrors(['email' => 'Your developer account has been deactivated.']);
        }

        return $next($request);
    }
}
