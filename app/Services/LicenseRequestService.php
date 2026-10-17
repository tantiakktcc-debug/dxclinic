<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\License;
use App\Models\LicenseRequest;
use App\Models\LicenseRequestEvent;
use App\Models\LicenseRequestSequence;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LicenseRequestService
{
    /**
     * Submit a new initial license request from the public form.
     * Public request data NEVER controls role, privileges, permissions, or clinic_id.
     */
    public static function submitInitialRequest(array $data): LicenseRequest
    {
        return DB::transaction(function () use ($data) {
            $plan = null;
            $wantsTrial = (bool) ($data['wants_trial'] ?? false);
            $requestedDoctorCount = max(1, (int) ($data['requested_doctor_count'] ?? 1));

            // Resolve plan and calculate pricing snapshot
            if (!empty($data['plan_id'])) {
                $plan = SubscriptionPlan::where('is_active', true)
                    ->where('code', '!=', 'TRIAL')
                    ->findOrFail($data['plan_id']);
            }

            $includedDoctorCount = $plan ? (int) ($plan->included_doctor_seats ?? 1) : 1;
            $additionalDoctorCount = max(0, $requestedDoctorCount - $includedDoctorCount);
            $doctorRateSnapshot = $plan ? (float) ($plan->additional_doctor_seat_price ?? 2999.00) : 2999.00;
            $baseAmount = $plan ? (float) $plan->price : 0.00;
            $additionalDoctorAmount = $additionalDoctorCount * $doctorRateSnapshot;
            $trialMonths = $wantsTrial ? 1 : 0;
            $trialAmount = 0.00; // Trial is always ₹0
            $discountAmount = (float) ($data['discount_amount'] ?? 0.00);
            $netAmount = max(0.00, $baseAmount + $additionalDoctorAmount + $trialAmount - $discountAmount);

            // Check for duplicate pending requests with same email
            $existingPending = LicenseRequest::where('email', $data['email'])
                ->where('request_type', 'INITIAL')
                ->whereIn('status', ['SUBMITTED', 'UNDER_REVIEW'])
                ->first();

            if ($existingPending) {
                throw new \InvalidArgumentException(
                    "A pending license request already exists for this email address (Request #{$existingPending->request_number}). Please wait for it to be processed."
                );
            }

            $requestNumber = LicenseRequestSequence::nextRequestNumber('LR');

            $request = LicenseRequest::create([
                'request_number' => $requestNumber,
                'request_type' => 'INITIAL',
                'status' => 'SUBMITTED',
                // Contact / Owner
                'clinic_name' => $data['clinic_name'],
                'owner_name' => $data['owner_name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'pincode' => $data['pincode'] ?? null,
                // Plan
                'plan_id' => $plan?->id,
                'wants_trial' => $wantsTrial,
                'requested_doctor_count' => $requestedDoctorCount,
                // Pricing snapshot
                'plan_name_snapshot' => $plan?->name,
                'plan_duration_months' => $plan?->duration_months,
                'trial_months' => $trialMonths,
                'included_doctor_count' => $includedDoctorCount,
                'additional_doctor_count' => $additionalDoctorCount,
                'doctor_rate_snapshot' => $doctorRateSnapshot,
                'base_amount' => $baseAmount,
                'additional_doctor_amount' => $additionalDoctorAmount,
                'trial_amount' => $trialAmount,
                'discount_amount' => $discountAmount,
                'net_amount' => $netAmount,
                // Metadata
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Record event
            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'SUBMITTED',
                'performed_by_type' => 'public',
                'performed_by_id' => null,
                'old_status' => null,
                'new_status' => 'SUBMITTED',
                'notes' => "Initial license request submitted for {$data['clinic_name']}",
                'metadata' => [
                    'plan_name' => $plan?->name,
                    'requested_doctors' => $requestedDoctorCount,
                    'wants_trial' => $wantsTrial,
                    'net_amount' => $netAmount,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $request;
        });
    }

    /**
     * Submit a renewal request from the public form.
     * Verification: Clinic Code + Registered Clinic Mobile (not user phone).
     * Never reveals whether a Clinic ID exists when verification fails.
     */
    public static function submitRenewalRequest(array $data): LicenseRequest
    {
        return DB::transaction(function () use ($data) {
            $clinicCode = strtoupper(trim($data['clinic_code'] ?? ''));
            $mobile = preg_replace('/[^0-9]/', '', $data['mobile'] ?? '');

            // Verify clinic identity using clinic's registered phone (not user phone)
            $clinic = Clinic::where('clinic_code', $clinicCode)
                ->first();

            // Normalize the clinic's stored phone for comparison
            $clinicPhone = $clinic ? preg_replace('/[^0-9]/', '', $clinic->phone ?? '') : '';

            // Verification: clinic must exist AND registered mobile must match
            // Return same generic error for both cases (security)
            if (!$clinic || $clinicPhone !== $mobile || empty($mobile)) {
                throw new \InvalidArgumentException(
                    'Unable to verify clinic identity. Please check your Clinic ID and registered mobile number.'
                );
            }

            // Check clinic has a license
            $latestLicense = $clinic->latestLicense;
            if (!$latestLicense) {
                throw new \InvalidArgumentException(
                    'Unable to verify clinic identity. Please check your Clinic ID and registered mobile number.'
                );
            }

            // Check no duplicate pending renewal for this clinic
            $existingPending = LicenseRequest::where('clinic_id', $clinic->id)
                ->where('request_type', 'RENEWAL')
                ->whereIn('status', ['SUBMITTED', 'UNDER_REVIEW'])
                ->first();

            if ($existingPending) {
                throw new \InvalidArgumentException(
                    "A pending renewal request already exists for this clinic (Request #{$existingPending->request_number}). Please wait for it to be processed."
                );
            }

            // Resolve plan
            $plan = null;
            if (!empty($data['plan_id'])) {
                $plan = SubscriptionPlan::where('is_active', true)
                    ->where('code', '!=', 'TRIAL')
                    ->findOrFail($data['plan_id']);
            }

            // Trial MUST be unavailable on renewal (server-side enforcement)
            $trialMonths = 0;

            // Doctor count for renewal: translate total requested into renewal quantities
            $requestedDoctorCount = max(1, (int) ($data['requested_doctor_count'] ?? 
                ($latestLicense->included_doctor_seats + $latestLicense->additional_doctor_seats)));

            $includedDoctorCount = $plan ? (int) ($plan->included_doctor_seats ?? 1) : 
                (int) ($latestLicense->included_doctor_seats ?? 1);
            $additionalDoctorCount = max(0, $requestedDoctorCount - $includedDoctorCount);
            $doctorRateSnapshot = $plan ? (float) ($plan->additional_doctor_seat_price ?? 2999.00) :
                (float) ($latestLicense->additional_doctor_fee_per_year ?? 2999.00);

            $baseAmount = $plan ? (float) $plan->price : 0.00;
            $additionalDoctorAmount = $additionalDoctorCount * $doctorRateSnapshot;
            $discountAmount = (float) ($data['discount_amount'] ?? 0.00);
            $netAmount = max(0.00, $baseAmount + $additionalDoctorAmount - $discountAmount);

            $requestNumber = LicenseRequestSequence::nextRequestNumber('LR');

            $request = LicenseRequest::create([
                'request_number' => $requestNumber,
                'request_type' => 'RENEWAL',
                'status' => 'SUBMITTED',
                // Contact — use existing clinic data
                'clinic_name' => $clinic->name,
                'owner_name' => $data['owner_name'] ?? $clinic->users()->where('role', 'ADMIN')->first()?->name ?? 'Clinic Owner',
                'mobile' => $clinic->phone,
                'email' => $data['email'] ?? $clinic->email ?? '',
                'address' => $clinic->address,
                'city' => $clinic->city,
                'pincode' => $clinic->pincode ?? null,
                // Renewal reference
                'clinic_id' => $clinic->id,
                'clinic_code' => $clinic->clinic_code,
                // Plan
                'plan_id' => $plan?->id,
                'wants_trial' => false, // NEVER on renewal
                'requested_doctor_count' => $requestedDoctorCount,
                // Pricing snapshot
                'plan_name_snapshot' => $plan?->name,
                'plan_duration_months' => $plan?->duration_months,
                'trial_months' => 0,
                'included_doctor_count' => $includedDoctorCount,
                'additional_doctor_count' => $additionalDoctorCount,
                'doctor_rate_snapshot' => $doctorRateSnapshot,
                'base_amount' => $baseAmount,
                'additional_doctor_amount' => $additionalDoctorAmount,
                'trial_amount' => 0.00,
                'discount_amount' => $discountAmount,
                'net_amount' => $netAmount,
                // Metadata
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'SUBMITTED',
                'performed_by_type' => 'public',
                'performed_by_id' => null,
                'old_status' => null,
                'new_status' => 'SUBMITTED',
                'notes' => "Renewal request submitted for Clinic {$clinic->clinic_code} ({$clinic->name})",
                'metadata' => [
                    'clinic_code' => $clinic->clinic_code,
                    'plan_name' => $plan?->name,
                    'requested_doctors' => $requestedDoctorCount,
                    'net_amount' => $netAmount,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $request;
        });
    }

    /**
     * Optional: Mark a request as under review (audit state, not mandatory).
     */
    public static function markUnderReview(int $requestId, int $adminId): LicenseRequest
    {
        return DB::transaction(function () use ($requestId, $adminId) {
            $request = LicenseRequest::lockForUpdate()->findOrFail($requestId);

            if (!$request->isSubmitted()) {
                throw new \InvalidArgumentException('Only SUBMITTED requests can be marked for review.');
            }

            $oldStatus = $request->status;
            $request->update([
                'status' => 'UNDER_REVIEW',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'UNDER_REVIEW',
                'performed_by_type' => 'developer_user',
                'performed_by_id' => $adminId,
                'old_status' => $oldStatus,
                'new_status' => 'UNDER_REVIEW',
                'notes' => 'Request marked for review by Superadmin',
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * Fulfill an INITIAL license request: confirm payment + generate license atomically.
     * Single Superadmin action — no separate "Approve" step required.
     * Allowed from SUBMITTED or UNDER_REVIEW.
     */
    public static function fulfillInitialRequest(
        int $requestId,
        int $adminId,
        array $paymentData
    ): array {
        return DB::transaction(function () use ($requestId, $adminId, $paymentData) {
            $request = LicenseRequest::lockForUpdate()->findOrFail($requestId);

            if (!$request->canBeFulfilled()) {
                throw new \InvalidArgumentException('This request cannot be fulfilled in its current state.');
            }

            if (!$request->isInitial()) {
                throw new \InvalidArgumentException('This method is only for INITIAL license requests.');
            }

            // Resolve plan
            $plan = $request->plan;
            if (!$plan) {
                throw new \InvalidArgumentException('No subscription plan selected for this request.');
            }

            // Trial MUST be enforced server-side: only for INITIAL, never for renewal
            $trialMonths = $request->wants_trial ? max(0, (int) $request->trial_months) : 0;

            // Doctor counts: derive from the customer's total requested count
            $includedSeats = (int) ($plan->included_doctor_seats ?? 1);
            $additionalSeats = max(0, (int) $request->requested_doctor_count - $includedSeats);
            $doctorFee = (float) ($plan->additional_doctor_seat_price ?? 2999.00);

            // Discount from payment data (Superadmin may adjust)
            $discount = (float) ($paymentData['discount_amount'] ?? $request->discount_amount ?? 0.0);

            // Call the authoritative LicenseService::issueLicense()
            $result = LicenseService::issueLicense(
                clinicData: [
                    'name' => $request->clinic_name,
                    'address' => $request->address ?? null,
                    'city' => $request->city ?? null,
                    'pincode' => $request->pincode ?? null,
                    'phone' => $request->mobile,
                    'email' => $request->email ?? null,
                    'notes' => "Generated from License Request #{$request->request_number}",
                ],
                adminData: [
                    'name' => $request->owner_name,
                    'email' => $request->email,
                    'phone' => $request->mobile,
                ],
                planId: $plan->id,
                discount: $discount,
                issuedBy: $adminId,
                includedDoctorSeats: $includedSeats,
                additionalDoctorSeats: $additionalSeats,
                additionalDoctorFeePerYear: $doctorFee,
                startDate: null,
                trialMonths: $trialMonths,
                operatorData: [
                    'name' => 'Operator',
                    'email' => $request->email,
                    'phone' => $request->mobile,
                ],
                doctorData: [
                    'name' => 'Doctor',
                    'email' => $request->email,
                    'contact_number' => $request->mobile,
                ],
            );

            $oldStatus = $request->status;

            $accountMetadata = [
                'admin' => [
                    'user_id' => $result['admin_user']->id,
                    'role' => 'ADMINISTRATOR',
                    'login_id' => $result['admin_user']->login_id,
                    'email' => $result['admin_user']->email,
                    'name' => $result['admin_user']->name,
                    'status' => $result['admin_user']->status,
                    'created_at' => $result['admin_user']->created_at?->toIso8601String(),
                    'credential_issued' => true,
                ],
                'operator' => $result['operator_user'] ? [
                    'user_id' => $result['operator_user']->id,
                    'role' => 'OPERATOR',
                    'login_id' => $result['operator_user']->login_id,
                    'email' => $result['operator_user']->email,
                    'name' => $result['operator_user']->name,
                    'status' => $result['operator_user']->status,
                    'created_at' => $result['operator_user']->created_at?->toIso8601String(),
                    'credential_issued' => true,
                ] : null,
                'doctor' => $result['doctor_user'] ? [
                    'user_id' => $result['doctor_user']->id,
                    'role' => 'DOCTOR',
                    'login_id' => $result['doctor_user']->login_id,
                    'email' => $result['doctor_user']->email,
                    'name' => $result['doctor_user']->name,
                    'doctor_name' => $result['doctor_record']?->name ?? $result['doctor_user']->name,
                    'qualification' => $result['doctor_record']?->qualification,
                    'registration_number' => $result['doctor_record']?->registration_number,
                    'status' => $result['doctor_user']->status,
                    'created_at' => $result['doctor_user']->created_at?->toIso8601String(),
                    'credential_issued' => true,
                ] : null,
                'activation_code' => 'ISSUED',
            ];

            $snapshotData = [
                'clinic_code' => $result['clinic']->clinic_code,
                'public_slug' => $result['clinic']->public_slug,
                'clinic_name' => $request->clinic_name,
                'owner_name' => $request->owner_name,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'city' => $request->city,
                'pincode' => $request->pincode,
                'address' => $request->address,
                'plan_name' => $request->plan_name_snapshot,
                'plan_duration_months' => $request->plan_duration_months,
                'trial_months' => $request->trial_months,
                'included_doctor_count' => $request->included_doctor_count,
                'additional_doctor_count' => $request->additional_doctor_count,
                'base_amount' => $request->base_amount,
                'additional_doctor_amount' => $request->additional_doctor_amount,
                'discount_amount' => $request->discount_amount,
                'net_amount' => $request->net_amount,
                'license_key_prefix' => $result['license']->license_key_prefix,
                'license_key' => $result['license']->decryptKey(),
                'start_date' => $result['license']->start_date?->toDateString(),
                'end_date' => $result['license']->end_date?->toDateString(),
            ];

            // Update request as FULFILLED
            $request->update([
                'status' => 'FULFILLED',
                'payment_confirmed_by' => $adminId,
                'payment_confirmed_at' => now(),
                'payment_method' => $paymentData['payment_method'] ?? 'CASH',
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
                'fulfilled_license_id' => $result['license']->id,
                'fulfilled_clinic_id' => $result['clinic']->id,
                'admin_notes' => $paymentData['admin_notes'] ?? null,
                'account_issuance_metadata' => $accountMetadata,
                'snapshot_data' => $snapshotData,
                // Also mark as reviewed if coming directly from SUBMITTED
                'reviewed_by' => $request->reviewed_by ?? $adminId,
                'reviewed_at' => $request->reviewed_at ?? now(),
            ]);

            // Synchronize payment details with license and invoice
            $result['license']->update([
                'payment_method' => $paymentData['payment_method'] ?? 'CASH',
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
            ]);

            $invoice = \App\Models\PlatformSalesInvoice::where('source_type', 'INITIAL_LICENSE')
                ->where('source_id', $result['license']->id)
                ->first();

            if ($invoice) {
                $invoice->update([
                    'payment_method' => $paymentData['payment_method'] ?? 'CASH',
                    'payment_reference' => $paymentData['payment_reference'] ?? null,
                    'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
                    'status' => 'PAID',
                ]);
            }

            // Record payment confirmation + fulfillment events in one transaction
            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'PAYMENT_CONFIRMED',
                'performed_by_type' => 'developer_user',
                'performed_by_id' => $adminId,
                'old_status' => $oldStatus,
                'new_status' => 'FULFILLED',
                'notes' => "Payment confirmed: {$paymentData['payment_method']} — ₹{$request->net_amount}",
                'metadata' => [
                    'payment_method' => $paymentData['payment_method'] ?? 'CASH',
                    'payment_reference' => $paymentData['payment_reference'] ?? null,
                    'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'FULFILLED',
                'performed_by_type' => 'developer_user',
                'performed_by_id' => $adminId,
                'old_status' => $oldStatus,
                'new_status' => 'FULFILLED',
                'notes' => "License generated: Clinic {$result['clinic']->clinic_code}, License #{$result['license']->id}",
                'metadata' => [
                    'clinic_code' => $result['clinic']->clinic_code,
                    'clinic_id' => $result['clinic']->id,
                    'license_id' => $result['license']->id,
                    'admin_login_id' => $result['admin_user']->login_id,
                    'operator_login_id' => $result['operator_user']?->login_id,
                    'doctor_login_id' => $result['doctor_user']?->login_id,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $result;
        });
    }

    /**
     * Fulfill a RENEWAL license request: confirm payment + renew license atomically.
     * Single Superadmin action. Allowed from SUBMITTED or UNDER_REVIEW.
     * Reuses existing LicenseService::renewLicense() without modification.
     */
    public static function fulfillRenewalRequest(
        int $requestId,
        int $adminId,
        array $paymentData
    ): License {
        return DB::transaction(function () use ($requestId, $adminId, $paymentData) {
            $request = LicenseRequest::lockForUpdate()->findOrFail($requestId);

            if (!$request->canBeFulfilled()) {
                throw new \InvalidArgumentException('This request cannot be fulfilled in its current state.');
            }

            if (!$request->isRenewal()) {
                throw new \InvalidArgumentException('This method is only for RENEWAL requests.');
            }

            if (!$request->clinic_id) {
                throw new \InvalidArgumentException('Renewal request has no associated clinic.');
            }

            // Trial MUST be unavailable on renewal (server-side enforcement)
            // This is absolute — never stack trial months on a renewal
            $trialMonths = 0;

            // Get the clinic's latest license
            $clinic = Clinic::lockForUpdate()->findOrFail($request->clinic_id);
            $latestLicense = $clinic->latestLicense;

            if (!$latestLicense) {
                throw new \InvalidArgumentException('No license found for this clinic.');
            }

            // Translate requested total doctor count into renewal quantities
            // Revision 35 separates: renew_existing_seats + new_seats
            $existingAdditionalSeats = (int) ($latestLicense->additional_doctor_seats ?? 0);
            $includedSeats = (int) ($request->included_doctor_count ?? 1);
            $requestedTotal = (int) $request->requested_doctor_count;
            $requestedAdditional = max(0, $requestedTotal - $includedSeats);

            // If customer requests fewer additional than existing, renew existing and add 0
            // If customer requests more, renew existing and add difference
            $renewExistingSeats = true;
            $newSeatsPurchased = max(0, $requestedAdditional - $existingAdditionalSeats);

            $plan = $request->plan;
            $discount = (float) ($paymentData['discount_amount'] ?? $request->discount_amount ?? 0.0);

            // Call the authoritative LicenseService::renewLicense()
            $newLicense = LicenseService::renewLicense(
                licenseId: $latestLicense->id,
                durationMonths: $plan ? $plan->duration_months : ($request->plan_duration_months ?? 12),
                amount: null, // Server-authoritative pricing
                performedById: $adminId,
                notes: "Renewed from License Request #{$request->request_number}",
                planId: $plan?->id,
                renewExistingDoctorSeats: $renewExistingSeats,
                newDoctorSeatsCount: $newSeatsPurchased,
                discount: $discount,
                paymentReceived: true,
                paymentMethod: $paymentData['payment_method'] ?? 'CASH',
                paymentDate: $paymentData['payment_date'] ?? now()->toDateString(),
                paymentReference: $paymentData['payment_reference'] ?? null,
                idempotencyKey: "LR-{$request->request_number}-{$request->id}",
            );

            $oldStatus = $request->status;

            $snapshotData = [
                'clinic_code' => $clinic->clinic_code,
                'public_slug' => $clinic->public_slug,
                'clinic_name' => $clinic->name,
                'owner_name' => $request->owner_name ?? $clinic->users()->where('role', 'ADMIN')->first()?->name,
                'mobile' => $request->mobile ?? $clinic->phone,
                'email' => $request->email ?? $clinic->email,
                'city' => $clinic->city,
                'pincode' => $clinic->pincode,
                'address' => $clinic->address,
                'plan_name' => $plan ? $plan->name : $request->plan_name_snapshot,
                'plan_duration_months' => $plan ? $plan->duration_months : $request->plan_duration_months,
                'trial_months' => 0,
                'included_doctor_count' => $includedSeats,
                'additional_doctor_count' => $requestedAdditional,
                'base_amount' => $newLicense->amount,
                'discount_amount' => $discount,
                'net_amount' => $newLicense->net_amount,
                'license_key_prefix' => $newLicense->license_key_prefix,
                'license_key' => $newLicense->decryptKey(),
                'start_date' => $newLicense->start_date?->toDateString(),
                'end_date' => $newLicense->end_date?->toDateString(),
            ];

            $request->update([
                'status' => 'FULFILLED',
                'payment_confirmed_by' => $adminId,
                'payment_confirmed_at' => now(),
                'payment_method' => $paymentData['payment_method'] ?? 'CASH',
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
                'fulfilled_license_id' => $newLicense->id,
                'fulfilled_clinic_id' => $clinic->id,
                'admin_notes' => $paymentData['admin_notes'] ?? null,
                'snapshot_data' => $snapshotData,
                'reviewed_by' => $request->reviewed_by ?? $adminId,
                'reviewed_at' => $request->reviewed_at ?? now(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'PAYMENT_CONFIRMED',
                'performed_by_type' => 'developer_user',
                'performed_by_id' => $adminId,
                'old_status' => $oldStatus,
                'new_status' => 'FULFILLED',
                'notes' => "Payment confirmed: {$paymentData['payment_method']} — Renewal for Clinic {$clinic->clinic_code}",
                'metadata' => [
                    'payment_method' => $paymentData['payment_method'] ?? 'CASH',
                    'payment_reference' => $paymentData['payment_reference'] ?? null,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'FULFILLED',
                'performed_by_type' => 'developer_user',
                'performed_by_id' => $adminId,
                'old_status' => $oldStatus,
                'new_status' => 'FULFILLED',
                'notes' => "License renewed for Clinic {$clinic->clinic_code}. New period: {$newLicense->start_date->format('d-m-Y')} to {$newLicense->end_date->format('d-m-Y')}",
                'metadata' => [
                    'clinic_code' => $clinic->clinic_code,
                    'new_license_id' => $newLicense->id,
                    'new_end_date' => $newLicense->end_date->toDateString(),
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $newLicense;
        });
    }

    /**
     * Reject a license request.
     */
    public static function rejectRequest(int $requestId, int $adminId, ?string $reason = null): LicenseRequest
    {
        return DB::transaction(function () use ($requestId, $adminId, $reason) {
            $request = LicenseRequest::lockForUpdate()->findOrFail($requestId);

            if (!$request->canBeRejected()) {
                throw new \InvalidArgumentException('This request cannot be rejected in its current state.');
            }

            $oldStatus = $request->status;
            $request->update([
                'status' => 'REJECTED',
                'rejection_reason' => $reason,
                'rejected_by' => $adminId,
                'rejected_at' => now(),
                'reviewed_by' => $request->reviewed_by ?? $adminId,
                'reviewed_at' => $request->reviewed_at ?? now(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'REJECTED',
                'performed_by_type' => 'developer_user',
                'performed_by_id' => $adminId,
                'old_status' => $oldStatus,
                'new_status' => 'REJECTED',
                'notes' => $reason ?? 'Rejected by Superadmin',
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * Cancel a license request.
     */
    public static function cancelRequest(
        int $requestId,
        ?int $userId = null,
        string $actorType = 'clinic_user',
        ?string $reason = null
    ): LicenseRequest {
        return DB::transaction(function () use ($requestId, $userId, $actorType, $reason) {
            $request = LicenseRequest::lockForUpdate()->findOrFail($requestId);

            if (!$request->canBeCancelled()) {
                throw new \InvalidArgumentException('This request cannot be cancelled in its current state.');
            }

            $oldStatus = $request->status;
            $request->update([
                'status' => 'CANCELLED',
                'cancellation_reason' => $reason,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'CANCELLED',
                'performed_by_type' => $actorType,
                'performed_by_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => 'CANCELLED',
                'notes' => $reason ?? 'Cancelled by requestor',
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * Submit an additional doctor seat request for an existing clinic.
     * Does NOT immediately activate capacity. Creates a PENDING request for Superadmin.
     */
    public static function submitDoctorSeatAddonRequest(array $data, \App\Models\User $user, Clinic $clinic): LicenseRequest
    {
        return DB::transaction(function () use ($data, $user, $clinic) {
            $existingPending = LicenseRequest::where('clinic_id', $clinic->id)
                ->where('request_type', 'DOCTOR_SEAT_ADDON')
                ->whereIn('status', ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING'])
                ->first();

            if ($existingPending) {
                throw new \InvalidArgumentException(
                    "A pending doctor seat request already exists for this clinic (Request #{$existingPending->request_number}). Please wait for it to be processed."
                );
            }

            $license = $clinic->activeLicense ?? $clinic->latestLicense;
            $seatsCount = max(1, (int) ($data['addon_seats_count'] ?? 1));
            $pricePerSeat = (float) ($license?->plan?->additional_doctor_seat_price ?? 2999.00);
            $totalAmount = $pricePerSeat * $seatsCount;

            $currentCapacity = $clinic->totalDoctorCapacity();
            $resultingCapacity = $currentCapacity + $seatsCount;

            $requestNumber = LicenseRequestSequence::nextRequestNumber('LR');

            $request = LicenseRequest::create([
                'request_number' => $requestNumber,
                'request_type' => 'DOCTOR_SEAT_ADDON',
                'status' => 'PENDING',
                'clinic_id' => $clinic->id,
                'clinic_code' => $clinic->clinic_code,
                'clinic_name' => $clinic->name,
                'owner_name' => $user->name,
                'mobile' => $clinic->phone ?? $user->phone ?? '9876543210',
                'email' => $clinic->email ?? $user->email ?? 'admin@dxclinic.local',
                'address' => $clinic->address,
                'city' => $clinic->city,
                'pincode' => $clinic->pincode ?? null,
                'plan_id' => $license?->plan_id,
                'wants_trial' => false,
                'requested_doctor_count' => $seatsCount,
                'plan_name_snapshot' => $license?->plan?->name ?? 'Standard License',
                'plan_duration_months' => $license?->plan?->duration_months ?? 12,
                'trial_months' => 0,
                'included_doctor_count' => $license?->included_doctor_seats ?? 1,
                'additional_doctor_count' => $seatsCount,
                'doctor_rate_snapshot' => $pricePerSeat,
                'base_amount' => 0.00,
                'additional_doctor_amount' => $totalAmount,
                'trial_amount' => 0.00,
                'discount_amount' => 0.00,
                'net_amount' => $totalAmount,
                'admin_notes' => "Doctor Seat Add-on requested by {$user->name} ({$user->role}) on " . now()->toDateTimeString() . ". Current capacity: {$currentCapacity}, used: {$clinic->activeDoctorCount()}, requested seats: +{$seatsCount}, resulting capacity: {$resultingCapacity}.",
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'SUBMITTED',
                'performed_by_type' => 'clinic_user',
                'performed_by_id' => $user->id,
                'old_status' => null,
                'new_status' => 'PENDING',
                'notes' => "Doctor seat add-on request submitted (+{$seatsCount} seat(s)) for {$clinic->name}",
                'metadata' => [
                    'clinic_id' => $clinic->id,
                    'clinic_code' => $clinic->clinic_code,
                    'requested_seats' => $seatsCount,
                    'current_capacity' => $currentCapacity,
                    'resulting_capacity' => $resultingCapacity,
                    'net_amount' => $totalAmount,
                    'requestor_role' => $user->role,
                ],
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            return $request;
        });
    }

    /**
     * Fulfill a DOCTOR_SEAT_ADDON license request: confirm payment + activate additional doctor seats atomically.
     */
    public static function fulfillDoctorSeatAddonRequest(
        int $requestId,
        int $adminId,
        array $paymentData
    ): array {
        return DB::transaction(function () use ($requestId, $adminId, $paymentData) {
            $request = LicenseRequest::lockForUpdate()->findOrFail($requestId);

            if (!$request->canBeFulfilled()) {
                throw new \InvalidArgumentException('This request cannot be fulfilled in its current state.');
            }

            if (!$request->isDoctorSeatAddon()) {
                throw new \InvalidArgumentException('This method is only for DOCTOR_SEAT_ADDON license requests.');
            }

            $seats = max(1, (int) ($request->additional_doctor_count ?: $request->requested_doctor_count ?: 1));

            $result = LicenseService::addDoctorSeats(
                clinicId: $request->clinic_id,
                seatsCount: $seats,
                paymentMethod: $paymentData['payment_method'] ?? 'UPI',
                paymentReference: $paymentData['payment_reference'] ?? null,
                paymentDate: $paymentData['payment_date'] ?? now()->toDateString(),
                performedById: $adminId,
                notes: $paymentData['admin_notes'] ?? "Fulfilled Doctor Seat Add-on Request #{$request->request_number}",
                customPricePerSeat: (float) ($request->doctor_rate_snapshot ?: 2999.00)
            );

            $oldStatus = $request->status;

            $snapshotData = [
                'clinic_code' => $result['clinic']->clinic_code,
                'public_slug' => $result['clinic']->public_slug,
                'clinic_name' => $result['clinic']->name,
                'owner_name' => $request->owner_name,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'city' => $result['clinic']->city,
                'pincode' => $result['clinic']->pincode,
                'address' => $result['clinic']->address,
                'seats_added' => $result['seats_added'],
                'total_capacity' => $result['total_capacity'],
                'doctor_rate' => $request->doctor_rate_snapshot,
                'net_amount' => $request->net_amount,
            ];

            $request->update([
                'status' => 'FULFILLED',
                'payment_confirmed_by' => $adminId,
                'payment_confirmed_at' => now(),
                'payment_method' => $paymentData['payment_method'] ?? 'UPI',
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
                'fulfilled_clinic_id' => $result['clinic']->id,
                'fulfilled_license_id' => $result['license']?->id,
                'admin_notes' => $paymentData['admin_notes'] ?? null,
                'snapshot_data' => $snapshotData,
                'reviewed_by' => $request->reviewed_by ?? $adminId,
                'reviewed_at' => $request->reviewed_at ?? now(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'PAYMENT_CONFIRMED',
                'performed_by_type' => 'developer_user',
                'performed_by_id' => $adminId,
                'old_status' => $oldStatus,
                'new_status' => 'FULFILLED',
                'notes' => "Payment confirmed: {$paymentData['payment_method']} — ₹{$request->net_amount}",
                'metadata' => [
                    'payment_method' => $paymentData['payment_method'] ?? 'UPI',
                    'payment_reference' => $paymentData['payment_reference'] ?? null,
                    'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
                ],
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            LicenseRequestEvent::create([
                'license_request_id' => $request->id,
                'event_type' => 'FULFILLED',
                'performed_by_type' => 'developer_user',
                'performed_by_id' => $adminId,
                'old_status' => $oldStatus,
                'new_status' => 'FULFILLED',
                'notes' => "Doctor seat add-on fulfilled: Clinic {$result['clinic']->clinic_code}, +{$result['seats_added']} seats, total capacity: {$result['total_capacity']}",
                'metadata' => [
                    'clinic_code' => $result['clinic']->clinic_code,
                    'clinic_id' => $result['clinic']->id,
                    'seats_added' => $result['seats_added'],
                    'total_capacity' => $result['total_capacity'],
                ],
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            return $result;
        });
    }
}
