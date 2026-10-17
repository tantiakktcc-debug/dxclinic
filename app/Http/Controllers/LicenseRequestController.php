<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\LicenseRequestService;
use Illuminate\Http\Request;

class LicenseRequestController extends Controller
{
    /**
     * Show the public new license request form (SaaS landing page).
     */
    public function showRequestForm()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->where('code', '!=', 'TRIAL')
            ->orderBy('display_order')
            ->get();

        return view('license.request', compact('plans'));
    }

    /**
     * Process the new license request submission.
     * Public input NEVER controls role, privileges, permissions, or clinic_id.
     */
    public function submitRequest(Request $request)
    {
        // Reject spam silently if honeypot is filled
        if ($request->filled('website_url')) {
            return redirect()->route('license.request')->with('success', 'Your request has been submitted.');
        }

        $validated = $request->validate([
            'clinic_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'plan_id' => 'required|exists:subscription_plans,id',
            'wants_trial' => 'nullable|boolean',
            'requested_doctor_count' => 'required|integer|min:1|max:50',
        ]);

        try {
            $licenseRequest = LicenseRequestService::submitInitialRequest($validated);

            return view('license.confirmation', [
                'requestNumber' => $licenseRequest->request_number,
                'requestType' => 'INITIAL',
                'clinicName' => $licenseRequest->clinic_name,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Show the public renewal request form.
     */
    public function showRenewalForm()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->where('code', '!=', 'TRIAL')
            ->orderBy('display_order')
            ->get();

        return view('license.renewal_request', compact('plans'));
    }

    /**
     * Process the renewal request submission.
     * Verifies identity using clinic_code + registered clinic mobile.
     */
    public function submitRenewal(Request $request)
    {
        // Reject spam silently if honeypot is filled
        if ($request->filled('website_url')) {
            return redirect()->route('license.renewal-request')->with('success', 'Your request has been submitted.');
        }

        $validated = $request->validate([
            'clinic_code' => 'required|string|max:20',
            'mobile' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'owner_name' => 'nullable|string|max:255',
            'plan_id' => 'required|exists:subscription_plans,id',
            'requested_doctor_count' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $licenseRequest = LicenseRequestService::submitRenewalRequest($validated);

            return view('license.confirmation', [
                'requestNumber' => $licenseRequest->request_number,
                'requestType' => 'RENEWAL',
                'clinicName' => $licenseRequest->clinic_name,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Public pricing page showing available plans.
     */
    public function pricing()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->where('code', '!=', 'TRIAL')
            ->orderBy('display_order')
            ->get();

        return view('license.pricing', compact('plans'));
    }
}
