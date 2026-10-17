<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\LicenseRenewal;
use App\Models\LicenseRequest;
use App\Models\PlatformSalesInvoice;
use App\Models\SubscriptionPlan;
use App\Services\DemoService;
use App\Services\LicenseRequestService;
use App\Services\LicenseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SuperadminDashboardController extends Controller
{
    /**
     * Superadmin Fleet Dashboard & Search (Phase 1)
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $statusFilter = strtoupper(trim((string) $request->input('status', 'ALL')));

        $query = Clinic::query()->with(['activeLicense.plan', 'latestLicense.plan', 'users', 'doctors']);

        if ($q !== '') {
            $normalizedPhone = preg_replace('/[^0-9]/', '', $q);

            $query->where(function ($sub) use ($q, $normalizedPhone) {
                $sub->where('clinic_code', 'LIKE', "%{$q}%")
                    ->orWhere('name', 'LIKE', "%{$q}%")
                    ->orWhere('city', 'LIKE', "%{$q}%")
                    ->orWhere('phone', 'LIKE', "%{$q}%")
                    ->orWhereHas('users', function ($uq) use ($q, $normalizedPhone) {
                        $uq->where('name', 'LIKE', "%{$q}%")
                            ->orWhere('login_id', 'LIKE', "%{$q}%");
                        if (!empty($normalizedPhone)) {
                            $uq->orWhere('phone', 'LIKE', "%{$normalizedPhone}%");
                        }
                    })
                    ->orWhereHas('licenses', function ($lq) use ($q) {
                        $lq->where('license_key_prefix', 'LIKE', "%{$q}%");
                    });

                if (!empty($normalizedPhone)) {
                    $sub->orWhere('phone', 'LIKE', "%{$normalizedPhone}%");
                }
            });
        }

        if ($statusFilter !== 'ALL' && $statusFilter !== '') {
            if ($statusFilter === 'PENDING_ACTIVATION') {
                $query->where('activation_status', 'PENDING_ACTIVATION');
            } elseif ($statusFilter === 'ACTIVATED') {
                $query->where('activation_status', 'ACTIVATED');
            } else {
                $query->whereHas('licenses', function ($lq) use ($statusFilter) {
                    $lq->where('status', $statusFilter);
                });
            }
        }

        $clinics = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        $metrics = [
            'total_clinics' => Clinic::count(),
            'active_licenses' => License::where('status', 'ACTIVE')->count(),
            'expired_licenses' => License::where('status', 'EXPIRED')->count(),
            'suspended_licenses' => License::where('status', 'SUSPENDED')->count(),
            'pending_activations' => Clinic::where('activation_status', 'PENDING_ACTIVATION')->count(),
            'total_revenue' => (float) License::whereIn('status', ['ACTIVE', 'EXPIRED', 'SUSPENDED'])->sum('net_amount'),
        ];

        $plans = SubscriptionPlan::where('is_active', true)->where('code', '!=', 'TRIAL')->orderBy('display_order')->get();
        $demoClinic = Clinic::where('clinic_code', 'democlinic')->orWhere('is_demo', true)->first();

        return view('superadmin.dashboard', compact('clinics', 'metrics', 'q', 'statusFilter', 'plans', 'demoClinic'));
    }

    /**
     * Dedicated Superadmin License Request & Issuance History Page
     * Authoritative historical audit log of all license requests, issuances, renewals, and add-ons.
     */
    public function history(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $statusFilter = strtoupper(trim((string) $request->input('status', 'ALL')));
        $typeFilter = strtoupper(trim((string) $request->input('type', 'ALL')));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        $query = LicenseRequest::with([
            'plan',
            'clinic',
            'fulfilledClinic',
            'fulfilledLicense.plan',
            'reviewedBy',
            'paymentConfirmedBy',
            'rejectedBy',
            'cancelledBy'
        ])->latest('id');

        if ($q !== '') {
            $normalizedPhone = preg_replace('/[^0-9]/', '', $q);
            $hashedKey = hash('sha256', strtoupper($q));

            $query->where(function ($sub) use ($q, $normalizedPhone, $hashedKey) {
                $sub->where('request_number', 'LIKE', "%{$q}%")
                    ->orWhere('clinic_name', 'LIKE', "%{$q}%")
                    ->orWhere('owner_name', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('clinic_code', 'LIKE', "%{$q}%");

                if (!empty($normalizedPhone)) {
                    $sub->orWhere('mobile', 'LIKE', "%{$normalizedPhone}%");
                }

                $sub->orWhereHas('fulfilledClinic', function ($c) use ($q, $normalizedPhone) {
                    $c->where('clinic_code', 'LIKE', "%{$q}%")
                      ->orWhere('public_slug', 'LIKE', "%{$q}%")
                      ->orWhere('name', 'LIKE', "%{$q}%");
                    if (!empty($normalizedPhone)) {
                        $c->orWhere('phone', 'LIKE', "%{$normalizedPhone}%");
                    }
                });

                $sub->orWhereHas('clinic', function ($c) use ($q, $normalizedPhone) {
                    $c->where('clinic_code', 'LIKE', "%{$q}%")
                      ->orWhere('public_slug', 'LIKE', "%{$q}%")
                      ->orWhere('name', 'LIKE', "%{$q}%");
                    if (!empty($normalizedPhone)) {
                        $c->orWhere('phone', 'LIKE', "%{$normalizedPhone}%");
                    }
                });

                $sub->orWhereHas('fulfilledLicense', function ($l) use ($q, $hashedKey) {
                    $l->where('license_key_prefix', 'LIKE', "%{$q}%")
                      ->orWhere('license_key_hash', $hashedKey);
                });
            });
        }

        if ($statusFilter !== 'ALL' && $statusFilter !== '') {
            if ($statusFilter === 'PENDING') {
                $query->whereIn('status', ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING']);
            } else {
                $query->where('status', $statusFilter);
            }
        }

        if ($typeFilter !== 'ALL' && $typeFilter !== '') {
            $query->where('request_type', $typeFilter);
        }

        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $requests = $query->paginate(20)->withQueryString();

        $metrics = [
            'total' => LicenseRequest::count(),
            'fulfilled' => LicenseRequest::where('status', 'FULFILLED')->count(),
            'pending' => LicenseRequest::whereIn('status', ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING'])->count(),
            'rejected' => LicenseRequest::where('status', 'REJECTED')->count(),
            'cancelled' => LicenseRequest::where('status', 'CANCELLED')->count(),
        ];

        return view('superadmin.license_history', compact('requests', 'metrics', 'q', 'statusFilter', 'typeFilter', 'dateFrom', 'dateTo'));
    }

    /**
     * Dedicated Immutable Audit Detail View for a Historical License Request
     */
    public function historyDetail(LicenseRequest $licenseRequest)
    {
        $licenseRequest->load([
            'plan',
            'clinic',
            'fulfilledClinic.users.doctor',
            'fulfilledClinic.doctors',
            'fulfilledLicense.plan',
            'events',
            'reviewedBy',
            'paymentConfirmedBy',
            'rejectedBy',
            'cancelledBy'
        ]);

        $accountMetadata = $licenseRequest->account_issuance_metadata;
        if (empty($accountMetadata) && $licenseRequest->isFulfilled() && $licenseRequest->fulfilledClinic) {
            $clinic = $licenseRequest->fulfilledClinic;
            $adminUser = $clinic->users()->where('role', 'ADMIN')->orderBy('id')->first();
            $operatorUser = $clinic->users()->where('role', 'OPERATOR')->orderBy('id')->first();
            $doctorUser = $clinic->users()->where('role', 'DOCTOR')->orderBy('id')->first();
            $doctorRecord = $clinic->doctors()->first();

            $accountMetadata = [
                'admin' => [
                    'user_id' => $adminUser?->id,
                    'role' => 'ADMINISTRATOR',
                    'login_id' => $adminUser?->login_id ?? 'ADM0001',
                    'email' => $adminUser?->email ?? $licenseRequest->email,
                    'name' => $adminUser?->name ?? $licenseRequest->owner_name,
                    'status' => $adminUser?->status ?? 'ACTIVE',
                    'created_at' => $adminUser?->created_at?->toIso8601String(),
                    'credential_issued' => true,
                ],
                'operator' => $operatorUser ? [
                    'user_id' => $operatorUser->id,
                    'role' => 'OPERATOR',
                    'login_id' => $operatorUser->login_id ?? 'OPR0001',
                    'email' => $operatorUser->email ?? $licenseRequest->email,
                    'name' => $operatorUser->name ?? 'Operator',
                    'status' => $operatorUser->status ?? 'ACTIVE',
                    'created_at' => $operatorUser->created_at?->toIso8601String(),
                    'credential_issued' => true,
                ] : null,
                'doctor' => $doctorUser ? [
                    'user_id' => $doctorUser->id,
                    'role' => 'DOCTOR',
                    'login_id' => $doctorUser->login_id ?? 'DOC0001',
                    'email' => $doctorUser->email ?? $licenseRequest->email,
                    'name' => $doctorUser->name ?? 'Doctor',
                    'doctor_name' => $doctorRecord?->name ?? $doctorUser->name ?? 'Doctor',
                    'qualification' => $doctorRecord?->qualification ?? 'MBBS',
                    'registration_number' => $doctorRecord?->registration_number ?? 'REG-DOC',
                    'status' => $doctorUser->status ?? 'ACTIVE',
                    'created_at' => $doctorUser->created_at?->toIso8601String(),
                    'credential_issued' => true,
                ] : null,
                'activation_code' => 'ISSUED',
            ];

            // Safely persist to make future audit views completely immutable
            $licenseRequest->update(['account_issuance_metadata' => $accountMetadata]);
        }

        $invoice = null;
        if ($licenseRequest->fulfilled_license_id) {
            $invoice = PlatformSalesInvoice::where('license_id', $licenseRequest->fulfilled_license_id)
                ->orWhere('source_id', $licenseRequest->fulfilled_license_id)
                ->first();
        }

        return view('superadmin.license_history_detail', compact('licenseRequest', 'accountMetadata', 'invoice'));
    }

    /**
     * Show New Clinic Issue Form
     */
    public function showIssueForm()
    {
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('display_order')->get();
        return view('superadmin.issue_license', compact('plans'));
    }

    /**
     * Process New Clinic Issue
     */
    public function issueLicense(Request $request)
    {
        $validated = $request->validate([
            'clinic_name' => 'required|string|max:255',
            'clinic_address' => 'nullable|string|max:500',
            'clinic_city' => 'nullable|string|max:100',
            'clinic_phone' => 'nullable|string|max:20',
            'clinic_email' => 'nullable|email|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_phone' => 'nullable|string|max:20',
            'plan_id' => 'required|exists:subscription_plans,id',
            'included_doctor_seats' => 'nullable|integer|min:1|max:50',
            'additional_doctor_seats' => 'nullable|integer|min:0|max:50',
            'additional_doctor_fee_per_year' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'max_advance_booking_days' => 'nullable|integer|min:1|max:30',
            'max_patients_per_mobile' => 'nullable|integer|min:1|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
        $includedSeats = !empty($validated['included_doctor_seats']) ? (int) $validated['included_doctor_seats'] : ($plan->included_doctor_seats ?? 1);
        $additionalSeats = (int) ($validated['additional_doctor_seats'] ?? 0);
        $annualSeatFee = !empty($validated['additional_doctor_fee_per_year']) ? (float) $validated['additional_doctor_fee_per_year'] : (float) ($plan->additional_doctor_seat_price ?? 2999.00);

        $result = LicenseService::issueLicense(
            clinicData: [
                'name' => $validated['clinic_name'],
                'address' => $validated['clinic_address'] ?? null,
                'city' => $validated['clinic_city'] ?? null,
                'phone' => $validated['clinic_phone'] ?? null,
                'email' => $validated['clinic_email'] ?? null,
                'max_advance_booking_days' => $validated['max_advance_booking_days'] ?? 1,
                'max_patients_per_mobile' => $validated['max_patients_per_mobile'] ?? 4,
                'notes' => $validated['notes'] ?? null,
            ],
            adminData: [
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'phone' => $validated['admin_phone'] ?? null,
            ],
            planId: (int) $validated['plan_id'],
            discount: (float) ($validated['discount_amount'] ?? 0.0),
            issuedBy: Auth::guard('developer')->id(),
            includedDoctorSeats: $includedSeats,
            additionalDoctorSeats: $additionalSeats,
            additionalDoctorFeePerYear: $annualSeatFee,
            startDate: $validated['start_date'] ?? null
        );

        return view('superadmin.credential_package', [
            'package' => $result,
        ])->with('success', 'License issued successfully! Please copy or print the onboarding credential package.');
    }

    /**
     * Show Dedicated Paid License Renewal Screen (Phase 2, 3, 4, 6, 13, 14)
     */
    public function showRenewForm(Clinic $clinic)
    {
        $license = $clinic->latestLicense;
        if (!$license) {
            return redirect()->route('superadmin.dashboard')->withErrors(['error' => "No license found for Clinic {$clinic->clinic_code}."]);
        }

        // Phase 3: Only Quarterly, Half-Yearly, Yearly (Trial is NOT a renewal option)
        $plans = SubscriptionPlan::where('is_active', true)
            ->where('code', '!=', 'TRIAL')
            ->orderBy('display_order')
            ->get();

        $owner = $clinic->users()->where('role', 'ADMIN')->first();
        $isRevoked = in_array(strtoupper($license->status), ['REVOKED', 'CANCELLED']);
        $idempotencyKey = (string) Str::uuid();

        return view('superadmin.renew_license', compact('clinic', 'license', 'plans', 'owner', 'isRevoked', 'idempotencyKey'));
    }

    /**
     * Execute Paid License Renewal (Phase 15, 16, 17, 18)
     */
    public function renew(Request $request, Clinic $clinic)
    {
        $license = $clinic->latestLicense;
        if (!$license) {
            return back()->withErrors(['error' => 'No license found for this clinic.']);
        }

        // Phase 12: Revoked / Cancelled Protection
        if (in_array(strtoupper($license->status), ['REVOKED', 'CANCELLED'])) {
            return back()->withErrors([
                'error' => 'This license cannot be renewed through the normal renewal workflow. Please use the appropriate Superadmin reinstatement process.',
            ]);
        }

        // Check if coming from dedicated renewal form or quick dashboard modal
        if ($request->has('plan_id')) {
            $validated = $request->validate([
                'plan_id' => 'required|exists:subscription_plans,id',
                'payment_received' => 'required|in:1,true,yes,YES,on',
                'payment_method' => 'required|string|max:50',
                'payment_date' => 'nullable|date',
                'payment_reference' => 'nullable|string|max:100',
                'renew_existing_doctor_seats' => 'nullable|boolean',
                'new_doctor_seats_count' => 'nullable|integer|min:0|max:50',
                'discount_amount' => 'nullable|numeric|min:0',
                'idempotency_key' => 'nullable|string|max:64',
                'notes' => 'nullable|string|max:500',
            ]);

            $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
            $renewExisting = $request->has('renew_existing_doctor_seats') ? (bool) $validated['renew_existing_doctor_seats'] : true;
            $newSeats = (int) ($validated['new_doctor_seats_count'] ?? 0);
            $discount = (float) ($validated['discount_amount'] ?? 0.0);
            $paymentMethod = $validated['payment_method'];
            $paymentDate = $validated['payment_date'] ?? now()->toDateString();
            $paymentRef = $validated['payment_reference'] ?? null;
            $idempotencyKey = $validated['idempotency_key'] ?? null;
            $notes = $validated['notes'] ?? null;
            $durationMonths = $plan->duration_months;
            $amount = null; // Calculated server-authoritatively
        } else {
            // Quick Dashboard Modal Fallback
            $validated = $request->validate([
                'duration_months' => 'required|integer|min:1|max:120',
                'amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:500',
                'payment_method' => 'nullable|string|max:50',
                'payment_reference' => 'nullable|string|max:100',
            ]);

            $durationMonths = (int) $validated['duration_months'];
            $plan = SubscriptionPlan::where('duration_months', $durationMonths)->where('code', '!=', 'TRIAL')->first() ?? $license->plan;
            $amount = (float) $validated['amount'];
            $renewExisting = true;
            $newSeats = 0;
            $discount = 0.0;
            $paymentMethod = $validated['payment_method'] ?? 'CASH';
            $paymentDate = now()->toDateString();
            $paymentRef = $validated['payment_reference'] ?? 'Quick Renewal';
            $idempotencyKey = (string) Str::uuid();
            $notes = $validated['notes'] ?? null;
        }

        try {
            $newLicense = LicenseService::renewLicense(
                licenseId: $license->id,
                durationMonths: $durationMonths,
                amount: $amount,
                performedById: Auth::guard('developer')->id(),
                notes: $notes,
                planId: $plan?->id,
                renewExistingDoctorSeats: $renewExisting,
                newDoctorSeatsCount: $newSeats,
                discount: $discount,
                paymentReceived: true,
                paymentMethod: $paymentMethod,
                paymentDate: $paymentDate,
                paymentReference: $paymentRef,
                idempotencyKey: $idempotencyKey
            );

            return redirect()->route('superadmin.dashboard')->with(
                'success',
                "License successfully renewed for Clinic {$clinic->clinic_code} ({$plan->name}). Subscription is now ACTIVE until " . $newLicense->end_date->format('d-m-Y') . "."
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Clinic Full Subscription History View (Phase 21)
     */
    public function clinicHistory(Clinic $clinic)
    {
        $licenses = $clinic->licenses()->with(['plan', 'issuedBy'])->orderBy('id', 'desc')->get();
        $renewals = $clinic->licenseRenewals()->with(['plan', 'renewedBy'])->orderBy('id', 'desc')->get();

        return view('superadmin.clinic_history', compact('clinic', 'licenses', 'renewals'));
    }

    public function revealKey(Request $request, License $license)
    {
        $key = $license->decryptKey();
        if (!$key) {
            return response()->json(['error' => 'Unable to decrypt license key.'], 404);
        }

        return response()->json([
            'license_key' => $key,
            'clinic_code' => $license->clinic?->clinic_code,
            'clinic_name' => $license->clinic?->name,
            'status' => $license->status,
        ]);
    }

    public function suspend(Request $request, Clinic $clinic)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $license = $clinic->activeLicense ?? $clinic->latestLicense;
        if (!$license) {
            return back()->withErrors(['error' => 'No active license found to suspend.']);
        }

        LicenseService::suspendLicense(
            licenseId: $license->id,
            reason: $validated['reason'] ?? 'Suspended by Superadmin',
            performedById: Auth::guard('developer')->id()
        );

        return back()->with('success', "Clinic {$clinic->clinic_code} has been suspended.");
    }

    public function reactivate(Request $request, Clinic $clinic)
    {
        $license = $clinic->latestLicense;
        if (!$license) {
            return back()->withErrors(['error' => 'No license found to reactivate.']);
        }

        LicenseService::reactivateLicense(
            licenseId: $license->id,
            performedById: Auth::guard('developer')->id()
        );

        return back()->with('success', "Clinic {$clinic->clinic_code} license reactivated.");
    }

    public function revoke(Request $request, Clinic $clinic)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $license = $clinic->latestLicense;
        if (!$license) {
            return back()->withErrors(['error' => 'No license found to revoke.']);
        }

        LicenseService::revokeLicense(
            licenseId: $license->id,
            reason: $validated['reason'] ?? 'Revoked by Superadmin',
            performedById: Auth::guard('developer')->id()
        );

        return back()->with('success', "Clinic {$clinic->clinic_code} license has been permanently revoked.");
    }

    public function markActivated(Request $request, Clinic $clinic)
    {
        $clinic->update([
            'activation_status' => 'ACTIVATED',
            'activated_at' => now(),
            'activated_by_user_id' => Auth::guard('developer')->id(),
        ]);

        return back()->with('success', "Clinic {$clinic->clinic_code} marked as activated.");
    }

    public function events(Request $request, Clinic $clinic)
    {
        $license = $clinic->latestLicense;
        $events = $license ? $license->events : collect();

        return response()->json([
            'clinic' => [
                'clinic_code' => $clinic->clinic_code,
                'name' => $clinic->name,
            ],
            'events' => $events,
        ]);
    }

    public function addDoctorSeats(Request $request, Clinic $clinic)
    {
        $validated = $request->validate([
            'seats_count' => 'required|integer|min:1|max:50',
            'payment_method' => 'nullable|string|in:CASH,UPI,BANK_TRANSFER,CHEQUE,OTHER',
            'payment_reference' => 'nullable|string|max:100',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        try {
            $result = LicenseService::addDoctorSeats(
                clinicId: $clinic->id,
                seatsCount: (int) $validated['seats_count'],
                paymentMethod: $validated['payment_method'] ?? 'UPI',
                paymentReference: $validated['payment_reference'] ?? null,
                paymentDate: $validated['payment_date'] ?? null,
                performedById: Auth::guard('developer')->id(),
                notes: $validated['notes'] ?? null,
                idempotencyKey: $validated['idempotency_key'] ?? null
            );

            return back()->with('success', "Added {$result['seats_added']} doctor seat(s) successfully (Total capacity: {$result['total_capacity']}). Amount: ₹" . number_format($result['amount_charged'], 2) . ". Valid until {$result['valid_until']}. License Key remains unchanged.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ── Revision 37: Demo Tenant Management ──

    public function pauseDemo(Request $request)
    {
        try {
            $clinic = DemoService::pauseDemo();
            return back()->with('success', 'Demo Clinic has been PAUSED. Staff logins and public PWA booking are temporarily blocked.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function resumeDemo(Request $request)
    {
        try {
            $clinic = DemoService::resumeDemo();
            return back()->with('success', 'Demo Clinic has been RESUMED. Staff logins and public PWA booking are now ACTIVE.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function resetDemoData(Request $request)
    {
        try {
            $clinic = DemoService::resetDemoData();
            return back()->with('success', 'Demo Clinic transactional dataset has been atomically reset to standard deterministic data. All other clinics remain untouched.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ── Revision 36: License Request Management ──

    /**
     * License Requests Queue (Superadmin)
     */
    public function licenseRequests(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $statusFilter = strtoupper(trim((string) $request->input('status', 'ALL')));
        $typeFilter = strtoupper(trim((string) $request->input('type', 'ALL')));

        $query = LicenseRequest::with(['plan', 'clinic', 'reviewedBy', 'fulfilledClinic', 'fulfilledLicense'])
            ->latest('id');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('request_number', 'LIKE', "%{$q}%")
                    ->orWhere('clinic_name', 'LIKE', "%{$q}%")
                    ->orWhere('owner_name', 'LIKE', "%{$q}%")
                    ->orWhere('mobile', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('clinic_code', 'LIKE', "%{$q}%");
            });
        }

        if ($statusFilter !== 'ALL' && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        if ($typeFilter !== 'ALL' && $typeFilter !== '') {
            $query->where('request_type', $typeFilter);
        }

        $requests = $query->paginate(20)->withQueryString();

        $metrics = [
            'total' => LicenseRequest::count(),
            'pending' => LicenseRequest::pending()->count(),
            'fulfilled' => LicenseRequest::where('status', 'FULFILLED')->count(),
            'rejected' => LicenseRequest::where('status', 'REJECTED')->count(),
        ];

        return view('superadmin.license_requests', compact('requests', 'metrics', 'q', 'statusFilter', 'typeFilter'));
    }

    /**
     * License Request Detail + Workflow Actions
     */
    public function showLicenseRequest(LicenseRequest $licenseRequest)
    {
        $licenseRequest->load(['plan', 'clinic', 'events', 'reviewedBy', 'fulfilledClinic', 'fulfilledLicense.plan']);
        $plans = SubscriptionPlan::where('is_active', true)->where('code', '!=', 'TRIAL')->orderBy('display_order')->get();

        return view('superadmin.license_request_detail', compact('licenseRequest', 'plans'));
    }

    /**
     * Optional: Mark request as under review
     */
    public function reviewLicenseRequest(Request $request, LicenseRequest $licenseRequest)
    {
        try {
            LicenseRequestService::markUnderReview($licenseRequest->id, Auth::guard('developer')->id());
            return back()->with('success', "Request {$licenseRequest->request_number} marked for review.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Confirm Payment & Generate/Renew License — single atomic action.
     * Works from SUBMITTED or UNDER_REVIEW.
     */
    public function fulfillLicenseRequest(Request $request, LicenseRequest $licenseRequest)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string|max:50',
            'payment_reference' => 'nullable|string|max:100',
            'payment_date' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'admin_notes' => 'nullable|string|max:1000',
            'payment_confirmed' => 'required|accepted',
        ]);

        $paymentData = [
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
            'discount_amount' => (float) ($validated['discount_amount'] ?? 0.0),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ];

        try {
            if ($licenseRequest->isInitial()) {
                $result = LicenseRequestService::fulfillInitialRequest(
                    $licenseRequest->id,
                    Auth::guard('developer')->id(),
                    $paymentData
                );

                // Show the one-time credential package
                return view('superadmin.credential_package', [
                    'package' => $result,
                    'fromRequest' => $licenseRequest->fresh(),
                ])->with('success', 'License generated successfully from request.');

            } elseif ($licenseRequest->isDoctorSeatAddon()) {
                $result = LicenseRequestService::fulfillDoctorSeatAddonRequest(
                    $licenseRequest->id,
                    Auth::guard('developer')->id(),
                    $paymentData
                );

                return redirect()->route('superadmin.license-requests')
                    ->with('success', "Doctor seat add-on fulfilled for Clinic {$licenseRequest->clinic_name} (+{$result['seats_added']} seats). Total capacity: {$result['total_capacity']}.");

            } else {
                $newLicense = LicenseRequestService::fulfillRenewalRequest(
                    $licenseRequest->id,
                    Auth::guard('developer')->id(),
                    $paymentData
                );

                return redirect()->route('superadmin.license-requests')
                    ->with('success', "License renewed for Clinic {$licenseRequest->clinic_code}. Active until {$newLicense->end_date->format('d-m-Y')}.");
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reject a license request.
     */
    public function rejectLicenseRequest(Request $request, LicenseRequest $licenseRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        try {
            LicenseRequestService::rejectRequest(
                $licenseRequest->id,
                Auth::guard('developer')->id(),
                $validated['rejection_reason'] ?? null
            );

            return redirect()->route('superadmin.license-requests')
                ->with('success', "Request {$licenseRequest->request_number} rejected.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
