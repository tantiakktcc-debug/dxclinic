<?php

namespace App\Services;

use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantContext
{
    protected static ?Clinic $currentClinic = null;
    protected static ?Clinic $superadminSelectedClinic = null;

    public static function setClinic(?Clinic $clinic): void
    {
        static::$currentClinic = $clinic;
    }

    public static function getClinic(): ?Clinic
    {
        return static::clinic();
    }

    /**
     * Authoritative Clinic Resolution
     *
     * Rule: For ADMIN / OPERATOR / DOCTOR (web guard), authenticated_user.clinic_id
     * is the ONLY authoritative source. Request headers, route parameters,
     * and session values MUST NEVER override the authenticated user's clinic.
     */
    public static function clinic(): ?Clinic
    {
        // 1. Authoritative: Authenticated web user
        if (Auth::guard('web')->check() && Auth::guard('web')->user()?->clinic_id) {
            $userClinic = Auth::guard('web')->user()->clinic;
            if ($userClinic) {
                static::$currentClinic = $userClinic;
                return $userClinic;
            }
        }

        // 2. Exception B: Superadmin / Developer guard explicit selection
        if (Auth::guard('developer')->check() && static::$superadminSelectedClinic) {
            return static::$superadminSelectedClinic;
        }

        // 3. Explicitly set clinic (e.g. during public booking resolution or testing)
        if (static::$currentClinic && static::$currentClinic->exists) {
            return static::$currentClinic;
        }

        // 4. Default fallback for CLI / console / seeding
        try {
            return Clinic::where('clinic_code', 'NX0001')->first() ?? Clinic::first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function clinicId(): int
    {
        return (int) (static::clinic()?->id ?? 1);
    }

    public static function clinicCode(): string
    {
        return (string) (static::clinic()?->clinic_code ?? 'NX0001');
    }

    /**
     * Exception A: Public Booking Resolution (Revision 47: Public Slug Canonical)
     * Strictly from URL route parameter public_slug, NEVER fallback to Clinic::first()!
     * Protects strictly against query/header/body cross-tenant overrides.
     */
    public static function resolvePublicClinic(Request $request): ?Clinic
    {
        $slug = $request->route('public_slug') ?? $request->route('clinic');

        // Support Livewire AJAX requests originating from /book/{public_slug} or /b/{public_slug}
        if (empty($slug) && $request->is('livewire/*')) {
            $referer = $request->header('Referer');
            if ($referer) {
                $path = parse_url($referer, PHP_URL_PATH);
                if ($path && preg_match('#/(?:book|b)/([a-zA-Z0-9_\-]+)#i', $path, $matches)) {
                    $slug = $matches[1];
                }
            }

            if (empty($slug)) {
                $components = $request->input('components', []);
                if (is_array($components)) {
                    foreach ($components as $comp) {
                        if (isset($comp['snapshot'])) {
                            $decoded = is_string($comp['snapshot']) ? json_decode($comp['snapshot'], true) : $comp['snapshot'];
                            if (isset($decoded['data']['public_slug'])) {
                                $slug = $decoded['data']['public_slug'];
                                break;
                            }
                            if (isset($decoded['data']['clinic_code'])) {
                                $code = $decoded['data']['clinic_code'];
                                $c = Clinic::where('clinic_code', $code)->first();
                                if ($c) {
                                    $slug = $c->public_slug;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (empty($slug)) {
            return null;
        }

        $slugStr = strtolower(trim((string) $slug));
        $clinic = Clinic::where('public_slug', $slugStr)->first();

        if (!$clinic) {
            return null;
        }

        // Cross-Tenant Tamper Protection (Revision 37 + Revision 47)
        $queryCode = $request->query('clinic_code');
        if ($queryCode && strtolower(trim((string) $queryCode)) !== strtolower($clinic->clinic_code)) {
            abort(403, 'Cross-tenant query parameter override prohibited.');
        }

        $querySlug = $request->query('public_slug');
        if ($querySlug && strtolower(trim((string) $querySlug)) !== strtolower($clinic->public_slug)) {
            abort(403, 'Cross-tenant query parameter override prohibited.');
        }

        $queryId = $request->query('clinic_id');
        if ($queryId && (int) $queryId !== (int) $clinic->id) {
            abort(403, 'Cross-tenant query parameter override prohibited.');
        }

        $headerCode = $request->header('X-Clinic-Code');
        if ($headerCode && strtolower(trim((string) $headerCode)) !== strtolower($clinic->clinic_code)) {
            abort(403, 'Cross-tenant header override prohibited.');
        }

        $headerSlug = $request->header('X-Public-Slug');
        if ($headerSlug && strtolower(trim((string) $headerSlug)) !== strtolower($clinic->public_slug)) {
            abort(403, 'Cross-tenant header override prohibited.');
        }

        $headerId = $request->header('X-Clinic-ID') ?? $request->header('X-Tenant-ID');
        if ($headerId && (int) $headerId !== (int) $clinic->id) {
            abort(403, 'Cross-tenant header override prohibited.');
        }

        if ($request->isMethod('POST') && !$request->is('livewire/*')) {
            $postCode = $request->input('clinic_code');
            if ($postCode && strtolower(trim((string) $postCode)) !== strtolower($clinic->clinic_code)) {
                abort(403, 'Cross-tenant body parameter override prohibited.');
            }

            $postSlug = $request->input('public_slug');
            if ($postSlug && strtolower(trim((string) $postSlug)) !== strtolower($clinic->public_slug)) {
                abort(403, 'Cross-tenant body parameter override prohibited.');
            }

            $postId = $request->input('clinic_id');
            if ($postId && (int) $postId !== (int) $clinic->id) {
                abort(403, 'Cross-tenant body parameter override prohibited.');
            }
        }

        static::$currentClinic = $clinic;
        return $clinic;
    }

    /**
     * Exception B: Superadmin Context Resolution
     */
    public static function resolveSuperadminClinic(Request $request): ?Clinic
    {
        $code = $request->route('clinic_code') ?? $request->route('clinic') ?? $request->input('clinic_code');
        if ($code) {
            $clinic = Clinic::where('clinic_code', strtoupper(trim((string) $code)))->first();
            if ($clinic) {
                static::$superadminSelectedClinic = $clinic;
                return $clinic;
            }
        }

        $id = $request->route('clinic_id') ?? $request->input('clinic_id');
        if ($id && is_numeric($id)) {
            $clinic = Clinic::find($id);
            if ($clinic) {
                static::$superadminSelectedClinic = $clinic;
                return $clinic;
            }
        }

        return static::$superadminSelectedClinic ?? Clinic::first();
    }

    public static function setSuperadminClinic(?Clinic $clinic): void
    {
        static::$superadminSelectedClinic = $clinic;
    }

    public static function reset(): void
    {
        static::$currentClinic = null;
        static::$superadminSelectedClinic = null;
    }
}
