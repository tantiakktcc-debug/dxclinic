<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\ClinicDoctorSeatAddon;
use App\Models\ClinicSequence;
use App\Models\Doctor;
use App\Models\DoctorConsultationType;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseEvent;
use App\Models\LicenseRenewal;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PlatformInvoiceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LicenseService
{
    /**
     * Generate secure, random License Key (e.g. NXCL-XXXX-XXXX-XXXX-XXXX)
     */
    public static function generateLicenseKey(string $prefix = 'NXCL'): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $blocks = [];
        for ($b = 0; $b < 4; $b++) {
            $block = '';
            for ($i = 0; $i < 4; $i++) {
                $block .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $blocks[] = $block;
        }

        return $prefix . '-' . implode('-', $blocks);
    }

    /**
     * Generate secure, one-time Activation Code (e.g. NXA-8F7K-92QM)
     */
    public static function generateActivationCode(string $prefix = 'NXA'): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $blocks = [];
        for ($b = 0; $b < 2; $b++) {
            $block = '';
            for ($i = 0; $i < 4; $i++) {
                $block .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $blocks[] = $block;
        }

        return $prefix . '-' . implode('-', $blocks);
    }

    /**
     * Issue new license and provision new Clinic + initial Admin User
     * Returns the one-time Clinic Onboarding Credential Package.
     */
    public static function issueLicense(
        array $clinicData,
        array $adminData,
        int $planId,
        float $discount = 0.0,
        ?int $issuedBy = null,
        int $includedDoctorSeats = 1,
        int $additionalDoctorSeats = 0,
        float $additionalDoctorFeePerYear = 2999.00,
        ?string $startDate = null,
        // ── Revision 36 backward-compatible extensions ──
        int $trialMonths = 0,
        ?array $operatorData = null,
        ?array $doctorData = null
    ): array {
        return DB::transaction(function () use ($clinicData, $adminData, $planId, $discount, $issuedBy, $includedDoctorSeats, $additionalDoctorSeats, $additionalDoctorFeePerYear, $startDate, $trialMonths, $operatorData, $doctorData) {
            $plan = SubscriptionPlan::findOrFail($planId);
            $clinicCode = ClinicSequence::nextClinicCode('NX');

            // 1. Create Clinic (with optional pincode — Revision 36)
            $clinic = Clinic::create([
                'clinic_code' => $clinicCode,
                'name' => $clinicData['name'],
                'address' => $clinicData['address'] ?? 'Clinic Campus',
                'city' => $clinicData['city'] ?? 'Varanasi',
                'pincode' => $clinicData['pincode'] ?? null,
                'phone' => $clinicData['phone'] ?? '9876543210',
                'email' => $clinicData['email'] ?? null,
                'max_advance_booking_days' => $clinicData['max_advance_booking_days'] ?? 1,
                'max_patients_per_mobile' => $clinicData['max_patients_per_mobile'] ?? 4,
                'activation_status' => 'PENDING_ACTIVATION',
                'onboarding_status' => 'NOT_STARTED',
                'is_onboarded' => false,
            ]);

            // 2. Generate Admin User credentials
            $tempAdminPassword = $adminData['password'] ?? Str::password(10, true, true, false, false);
            $adminLoginId = User::nextLoginId($clinic->id, 'ADMIN');

            $adminUser = User::create([
                'clinic_id' => $clinic->id,
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'phone' => $adminData['phone'] ?? null,
                'login_id' => $adminLoginId,
                'role' => 'ADMIN',
                'password' => Hash::make($tempAdminPassword),
                'must_change_password' => true,
                'status' => 'ACTIVE',
            ]);

            // 2a. Optionally create Operator User (Revision 36)
            $operatorUser = null;
            $tempOperatorPassword = null;
            if ($operatorData !== null) {
                $tempOperatorPassword = $operatorData['password'] ?? Str::password(10, true, true, false, false);
                $operatorLoginId = User::nextLoginId($clinic->id, 'OPERATOR');

                $operatorUser = User::create([
                    'clinic_id' => $clinic->id,
                    'name' => $operatorData['name'] ?? 'Operator',
                    'email' => $operatorData['email'] ?? $adminData['email'],
                    'phone' => $operatorData['phone'] ?? null,
                    'login_id' => $operatorLoginId,
                    'role' => 'OPERATOR',
                    'password' => Hash::make($tempOperatorPassword),
                    'must_change_password' => true,
                    'status' => 'ACTIVE',
                ]);
            }

            // 2b. Optionally create Doctor record + Doctor User (Revision 36)
            $doctorRecord = null;
            $doctorUser = null;
            $tempDoctorPassword = null;
            if ($doctorData !== null) {
                // Create Doctor record first (capacity is set by the license below)
                // We must create the license before the doctor to satisfy canAddDoctor() guard,
                // so we defer doctor creation to after license creation (see step 3b below).
            }

            // 3. Generate License Key & Activation Code
            $rawLicenseKey = self::generateLicenseKey('NXCL');
            $rawActivationCode = self::generateActivationCode('NXA');

            $start = $startDate ? Carbon::parse($startDate) : Carbon::today();
            // Revision 36: Trial months stack on top of paid plan duration
            $effectiveDurationMonths = $plan->duration_months + max(0, $trialMonths);
            $endDate = (clone $start)->addMonths($effectiveDurationMonths);
            
            $basePrice = (float) $plan->price;
            $additionalDoctorAmount = (float) ($additionalDoctorSeats * $additionalDoctorFeePerYear);
            $subtotal = $basePrice + $additionalDoctorAmount;
            $netAmount = max(0.00, $subtotal - $discount);

            $license = License::create([
                'clinic_id' => $clinic->id,
                'plan_id' => $plan->id,
                'license_key_hash' => hash('sha256', strtoupper($rawLicenseKey)),
                'license_key_encrypted' => Crypt::encryptString($rawLicenseKey),
                'license_key_prefix' => 'NXCL',
                'activation_code_hash' => hash('sha256', strtoupper($rawActivationCode)),
                'start_date' => $start->toDateString(),
                'end_date' => $endDate->toDateString(),
                'amount' => $basePrice,
                'discount_amount' => $discount,
                'net_amount' => $netAmount,
                'included_doctor_seats' => $includedDoctorSeats,
                'additional_doctor_seats' => $additionalDoctorSeats,
                'additional_doctor_fee_per_year' => $additionalDoctorFeePerYear,
                'additional_doctor_amount' => $additionalDoctorAmount,
                'status' => $plan->code === 'TRIAL' ? 'TRIAL' : 'ACTIVE',
                'issued_by' => $issuedBy,
                'issued_at' => now(),
                'notes' => $clinicData['notes'] ?? 'License issued by Superadmin',
            ]);

            // 3b. Now create Doctor record + Doctor User (license exists, capacity is set)
            if ($doctorData !== null) {
                $doctorRecord = Doctor::create([
                    'clinic_id' => $clinic->id,
                    'name' => $doctorData['name'] ?? 'Doctor',
                    'qualification' => $doctorData['qualification'] ?? 'MBBS',
                    'registration_number' => $doctorData['registration_number'] ?? ('REG-' . strtoupper(Str::random(6))),
                    'specialization' => $doctorData['specialization'] ?? 'General Medicine',
                    'contact_number' => $doctorData['contact_number'] ?? null,
                    'is_active' => true,
                ]);

                // Set as clinic default doctor
                $clinic->update(['default_doctor_id' => $doctorRecord->id]);

                $tempDoctorPassword = $doctorData['password'] ?? Str::password(10, true, true, false, false);
                $doctorLoginId = User::nextLoginId($clinic->id, 'DOCTOR');

                $doctorUser = User::create([
                    'clinic_id' => $clinic->id,
                    'name' => $doctorData['name'] ?? 'Doctor',
                    'email' => $doctorData['email'] ?? $adminData['email'],
                    'phone' => $doctorData['contact_number'] ?? null,
                    'login_id' => $doctorLoginId,
                    'role' => 'DOCTOR',
                    'doctor_id' => $doctorRecord->id,
                    'password' => Hash::make($tempDoctorPassword),
                    'must_change_password' => true,
                    'status' => 'ACTIVE',
                ]);
            }

            // 4. Record License Event
            $trialNote = $trialMonths > 0 ? " (includes {$trialMonths}-month free trial)" : '';
            LicenseEvent::create([
                'license_id' => $license->id,
                'event_type' => 'ISSUED',
                'performed_by_type' => $issuedBy ? 'developer_user' : 'system',
                'performed_by_id' => $issuedBy,
                'old_status' => null,
                'new_status' => $license->status,
                'old_end_date' => null,
                'new_end_date' => $license->end_date->toDateString(),
                'notes' => "New {$plan->name} issued for clinic {$clinicCode} (Doctor Seats: " . ($includedDoctorSeats + $additionalDoctorSeats) . "){$trialNote}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);

            // Revision 42: Create authoritative commercial invoice
            PlatformInvoiceService::createForInitialLicense($license, $issuedBy);

            return [
                'clinic' => $clinic,
                'admin_user' => $adminUser,
                'license' => $license,
                'raw_license_key' => $rawLicenseKey,
                'raw_activation_code' => $rawActivationCode,
                'raw_temp_password' => $tempAdminPassword,
                // ── Revision 36 extensions ──
                'operator_user' => $operatorUser,
                'raw_operator_password' => $tempOperatorPassword,
                'doctor_user' => $doctorUser,
                'doctor_record' => $doctorRecord,
                'raw_doctor_password' => $tempDoctorPassword,
                'trial_months' => $trialMonths,
                'effective_duration_months' => $effectiveDurationMonths,
            ];
        });
    }

    /**
     * Atomic First-Time Clinic Activation
     * Tied strictly to initial Admin User!
     */
    public static function activateLicense(
        int $clinicId,
        int $adminUserId,
        string $activationCode,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        return DB::transaction(function () use ($clinicId, $adminUserId, $activationCode, $ip, $userAgent) {
            $clinic = Clinic::lockForUpdate()->findOrFail($clinicId);
            $user = User::findOrFail($adminUserId);

            // Verify user is Admin of this clinic
            if (!$user->isAdmin()) {
                throw new \InvalidArgumentException('Only the Clinic Administrator can activate the clinic license.');
            }
            if ((int) $user->clinic_id !== (int) $clinic->id) {
                throw new \InvalidArgumentException('User does not belong to this clinic.');
            }

            // Verify clinic status is PENDING_ACTIVATION
            if ($clinic->isActivated()) {
                throw new \InvalidArgumentException('This clinic is already activated.');
            }

            // Find current active/trial license
            $license = License::where('clinic_id', $clinic->id)
                ->whereIn('status', ['TRIAL', 'ACTIVE'])
                ->lockForUpdate()
                ->latest()
                ->first();

            if (!$license) {
                throw new \InvalidArgumentException('No valid license found for this clinic.');
            }

            // Verify activation code hash
            $inputHash = hash('sha256', strtoupper(trim($activationCode)));
            if (empty($license->activation_code_hash) || !hash_equals($license->activation_code_hash, $inputHash)) {
                throw new \InvalidArgumentException('Invalid activation code. Please check your credentials package.');
            }

            // In ONE atomic transaction:
            // 1. Consume activation code
            $savedHash = $license->activation_code_hash;
            $license->activation_code_hash = null;
            $license->activated_at = now();
            $license->save();

            // 2. Mark clinic ACTIVATED and onboarding IN_PROGRESS
            $clinic->activation_status = 'ACTIVATED';
            $clinic->onboarding_status = 'IN_PROGRESS';
            $clinic->activated_at = now();
            $clinic->activated_by_user_id = $user->id;
            $clinic->save();

            // 3. Record activation audit
            $activationRecord = LicenseActivation::create([
                'license_id' => $license->id,
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'activation_code_hash' => $savedHash,
                'activated_at' => now(),
                'ip_address' => $ip ?? request()->ip(),
                'user_agent' => $userAgent ?? request()->userAgent(),
                'created_at' => now(),
            ]);

            // 4. Record license event
            LicenseEvent::create([
                'license_id' => $license->id,
                'event_type' => 'ACTIVATED',
                'performed_by_type' => 'user',
                'performed_by_id' => $user->id,
                'old_status' => $license->status,
                'new_status' => $license->status,
                'old_end_date' => $license->end_date->toDateString(),
                'new_end_date' => $license->end_date->toDateString(),
                'notes' => "License activated by Admin {$user->login_id} ({$user->name})",
                'ip_address' => $ip ?? request()->ip(),
                'created_at' => now(),
            ]);

            // 5. Ensure admin must change password
            $user->must_change_password = true;
            $user->save();

            return [
                'success' => true,
                'clinic' => $clinic,
                'license' => $license,
                'activation' => $activationRecord,
            ];
        });
    }

    /**
     * Renew an existing license by creating a NEW immutable license period (Correction 3)
     * Preserves previous license periods for historical auditing.
     * Never requires activation code again (Correction 9 & 14).
     */
    /**
     * Superadmin One-Click Paid License Renewal (Revision 35)
     * Single atomic transaction creating an immutable renewal period and payment record.
     * Retains permanent clinic license key, never requires activation code or onboarding.
     */
    public static function renewLicense(
        int $licenseId,
        int $durationMonths = 12,
        ?float $amount = null,
        ?int $performedById = null,
        ?string $notes = null,
        ?int $planId = null,
        bool $renewExistingDoctorSeats = true,
        int $newDoctorSeatsCount = 0,
        float $discount = 0.0,
        bool $paymentReceived = true,
        string $paymentMethod = 'CASH',
        ?string $paymentDate = null,
        ?string $paymentReference = null,
        ?string $idempotencyKey = null
    ): License {
        return DB::transaction(function () use (
            $licenseId,
            $durationMonths,
            $amount,
            $performedById,
            $notes,
            $planId,
            $renewExistingDoctorSeats,
            $newDoctorSeatsCount,
            $discount,
            $paymentReceived,
            $paymentMethod,
            $paymentDate,
            $paymentReference,
            $idempotencyKey
        ) {
            // 1. Idempotency Check (Phase 16)
            if (!empty($idempotencyKey)) {
                $existingRenewal = LicenseRenewal::where('idempotency_key', $idempotencyKey)->first();
                if ($existingRenewal && $existingRenewal->license) {
                    return $existingRenewal->license;
                }
            }

            // 2. Lock current license and clinic
            $oldLicense = License::lockForUpdate()->findOrFail($licenseId);
            $clinic = Clinic::lockForUpdate()->findOrFail($oldLicense->clinic_id);

            // 3. Phase 12 — Revoked / Cancelled Protection
            if (in_array(strtoupper($oldLicense->status), ['REVOKED', 'CANCELLED'])) {
                throw new \InvalidArgumentException('This license cannot be renewed through the normal renewal workflow. Please use the appropriate Superadmin reinstatement process.');
            }

            // 4. Resolve Subscription Plan
            if ($planId) {
                $plan = SubscriptionPlan::findOrFail($planId);
            } elseif ($durationMonths) {
                $plan = SubscriptionPlan::where('duration_months', $durationMonths)->where('code', '!=', 'TRIAL')->first()
                    ?? $oldLicense->plan;
            } else {
                $plan = $oldLicense->plan;
            }

            if (!$plan || strtoupper($plan->code) === 'TRIAL') {
                throw new \InvalidArgumentException('Trial is not a valid renewal option.');
            }

            $effectiveMonths = $durationMonths ?: $plan->duration_months;

            // 5. Date Calculations (Phase 9 & 10)
            $today = Carbon::today('Asia/Kolkata');
            $paymentDateObj = $paymentDate ? Carbon::parse($paymentDate, 'Asia/Kolkata') : $today;

            // If current license is still ACTIVE and end_date >= payment date, renewal starts after existing period
            if ($oldLicense->status === 'ACTIVE' && $oldLicense->end_date && Carbon::parse($oldLicense->end_date, 'Asia/Kolkata') >= $paymentDateObj) {
                $newStartDate = Carbon::parse($oldLicense->end_date, 'Asia/Kolkata')->addDay();
            } else {
                // Expired or Suspended: renewal starts on payment date
                $newStartDate = clone $paymentDateObj;
            }
            $newEndDate = (clone $newStartDate)->addMonths($effectiveMonths)->subDay();

            // 6. Doctor Seats & Pricing Calculation (Phase 5, 6, 7, 8)
            $includedSeats = (int) ($plan->included_doctor_seats ?? 1);
            $baseAmount = $amount !== null ? (float) $amount : (float) $plan->price;
            $annualDoctorFee = (float) ($plan->additional_doctor_seat_price ?? 2999.00);
            $existingAdditionalSeats = (int) ($oldLicense->additional_doctor_seats ?? 0);

            if ($effectiveMonths >= 12) {
                // Revision 37: Reconcile with active mid-term add-ons to prevent double billing
                $activeAddonSeats = (int) $clinic->activeDoctorSeatAddons()
                    ->whereDate('valid_until', '>=', $newStartDate)
                    ->sum('seats_count');

                $billableExistingSeats = max(0, $existingAdditionalSeats - $activeAddonSeats);

                $existingDoctorSeatsRenewed = $renewExistingDoctorSeats ? $billableExistingSeats : 0;
                $existingDoctorSeatAmount = $existingDoctorSeatsRenewed * $annualDoctorFee;
                $newDoctorSeatsPurchased = max(0, (int) $newDoctorSeatsCount);
                $newDoctorSeatAmount = $newDoctorSeatsPurchased * $annualDoctorFee;
                $totalDoctorSeatAmount = $existingDoctorSeatAmount + $newDoctorSeatAmount;
                $totalAdditionalSeats = $existingDoctorSeatsRenewed + $newDoctorSeatsPurchased + $activeAddonSeats;
            } else {
                // For non-yearly, retain seats without re-billing extra seats unless specified
                $existingDoctorSeatsRenewed = $existingAdditionalSeats;
                $existingDoctorSeatAmount = 0.00;
                $newDoctorSeatsPurchased = max(0, (int) $newDoctorSeatsCount);
                $newDoctorSeatAmount = 0.00;
                $totalDoctorSeatAmount = 0.00;
                $totalAdditionalSeats = $existingAdditionalSeats + $newDoctorSeatsPurchased;
            }

            $subtotal = $baseAmount + $totalDoctorSeatAmount;
            $discount = max(0.00, (float) $discount);
            $netAmount = max(0.00, $subtotal - $discount);

            // 7. Retain Permanent Clinic License Key (Phase 17)
            $keyHash = $oldLicense->license_key_hash ?: hash('sha256', strtoupper(self::generateLicenseKey('NXCL')));
            $keyEncrypted = $oldLicense->license_key_encrypted ?: Crypt::encryptString(self::generateLicenseKey('NXCL'));
            $keyPrefix = $oldLicense->license_key_prefix ?: 'NXCL';

            // 8. Renewal Number
            $maxRenewal = (int) (LicenseRenewal::where('clinic_id', $clinic->id)->max('renewal_number') ?? 0);
            $renewalNumber = $maxRenewal + 1;

            // 9. Update Old License status to RENEWED
            $oldStatus = $oldLicense->status;
            $oldEndDate = $oldLicense->end_date;
            $oldLicense->status = 'RENEWED';
            $oldLicense->save();

            // 10. Create Immutable Renewal License Period
            $newLicense = License::create([
                'clinic_id' => $clinic->id,
                'previous_license_id' => $oldLicense->id,
                'renewal_number' => $renewalNumber,
                'is_renewal' => true,
                'plan_id' => $plan->id,
                'license_key_hash' => $keyHash,
                'license_key_encrypted' => $keyEncrypted,
                'license_key_prefix' => $keyPrefix,
                'activation_code_hash' => null, // Renewals NEVER require activation code (Phase 17)
                'start_date' => $newStartDate->toDateString(),
                'end_date' => $newEndDate->toDateString(),
                'amount' => $baseAmount,
                'discount_amount' => $discount,
                'net_amount' => $netAmount,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'payment_date' => $paymentDateObj->toDateString(),
                'included_doctor_seats' => $includedSeats,
                'additional_doctor_seats' => $totalAdditionalSeats,
                'additional_doctor_fee_per_year' => $annualDoctorFee,
                'additional_doctor_amount' => $totalDoctorSeatAmount,
                'status' => 'ACTIVE',
                'issued_by' => $performedById,
                'issued_at' => now(),
                'activated_at' => now(),
                'notes' => $notes ?? "License renewed (#{$renewalNumber}) for {$effectiveMonths} months with {$plan->name}.",
            ]);

            // 11. Create Immutable LicenseRenewal Record (Phase 13, 14, 15, 21)
            $renewal = LicenseRenewal::create([
                'clinic_id' => $clinic->id,
                'license_id' => $newLicense->id,
                'previous_license_id' => $oldLicense->id,
                'renewal_number' => $renewalNumber,
                'plan_id' => $plan->id,
                'previous_end_date' => $oldEndDate ? Carbon::parse($oldEndDate)->toDateString() : null,
                'new_start_date' => $newStartDate->toDateString(),
                'new_end_date' => $newEndDate->toDateString(),
                'duration_months' => $effectiveMonths,
                'base_amount' => $baseAmount,
                'renew_existing_doctor_seats' => (bool) $renewExistingDoctorSeats,
                'existing_doctor_seats_renewed' => $existingDoctorSeatsRenewed,
                'existing_doctor_seat_amount' => $existingDoctorSeatAmount,
                'new_doctor_seats_purchased' => $newDoctorSeatsPurchased,
                'new_doctor_seat_amount' => $newDoctorSeatAmount,
                'total_doctor_seat_amount' => $totalDoctorSeatAmount,
                'discount_amount' => $discount,
                'net_amount' => $netAmount,
                'payment_received' => (bool) $paymentReceived,
                'payment_method' => $paymentMethod,
                'payment_date' => $paymentDateObj->toDateString(),
                'payment_reference' => $paymentReference,
                'idempotency_key' => $idempotencyKey,
                'renewed_by' => $performedById,
                'notes' => $notes,
            ]);

            // Revision 42: Create authoritative commercial invoice for renewal
            PlatformInvoiceService::createForRenewal($renewal, $performedById);

            // 12. Audit Events
            LicenseEvent::create([
                'license_id' => $oldLicense->id,
                'event_type' => 'SUPERSEDED_BY_RENEWAL',
                'performed_by_type' => $performedById ? 'developer_user' : 'system',
                'performed_by_id' => $performedById,
                'old_status' => $oldStatus,
                'new_status' => 'RENEWED',
                'old_end_date' => $oldEndDate ? Carbon::parse($oldEndDate)->toDateString() : null,
                'new_end_date' => $oldEndDate ? Carbon::parse($oldEndDate)->toDateString() : null,
                'notes' => "Superseded by renewal license #{$newLicense->id} (#{$renewalNumber})",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);

            LicenseEvent::create([
                'license_id' => $newLicense->id,
                'event_type' => 'RENEWED',
                'performed_by_type' => $performedById ? 'developer_user' : 'system',
                'performed_by_id' => $performedById,
                'old_status' => $oldStatus,
                'new_status' => 'ACTIVE',
                'old_end_date' => $oldEndDate ? Carbon::parse($oldEndDate)->toDateString() : null,
                'new_end_date' => $newEndDate->toDateString(),
                'notes' => "License renewed (#{$renewalNumber}) with {$plan->name}. Net amount: ₹{$netAmount}, Method: {$paymentMethod}. Doctor seats: {$includedSeats} included + {$totalAdditionalSeats} additional.",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);

            return $newLicense;
        });
    }

    /**
     * Revision 37: Mid-Term Additional Doctor Seat Purchase
     *
     * A clinic with an ACTIVE license may purchase an additional doctor during
     * their current license period.
     * - This is NOT a license renewal.
     * - Permanent License Key remains UNCHANGED.
     * - No new base License record is created.
     * - Additional doctor billing is SEPARATE and YEARLY ONLY.
     * - Entitlement created in clinic_doctor_seat_addons with 1-year validity.
     * - Demo clinic is strictly blocked from purchasing additional doctor seats.
     */
    public static function addDoctorSeats(
        int $clinicId,
        int $seatsCount = 1,
        string $paymentMethod = 'CASH',
        ?string $paymentReference = null,
        ?string $paymentDate = null,
        ?int $performedById = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
        ?float $customPricePerSeat = null,
        ?bool $proRata = null
    ): array {
        return DB::transaction(function () use (
            $clinicId,
            $seatsCount,
            $paymentMethod,
            $paymentReference,
            $paymentDate,
            $performedById,
            $notes,
            $idempotencyKey,
            $customPricePerSeat
        ) {
            $clinic = Clinic::lockForUpdate()->findOrFail($clinicId);

            // Hard rule: DEMOCLINIC doctor capacity is strictly 2
            if ($clinic->isDemo()) {
                throw new \InvalidArgumentException('Demo Clinic doctor capacity is strictly limited to 2 doctors. Additional doctor seats cannot be purchased.');
            }

            // Idempotency check
            if (!empty($idempotencyKey)) {
                $existingAddon = ClinicDoctorSeatAddon::where('idempotency_key', $idempotencyKey)->first();
                if ($existingAddon) {
                    return [
                        'clinic' => $clinic,
                        'license' => $existingAddon->license,
                        'addon' => $existingAddon,
                        'seats_added' => $existingAddon->seats_count,
                        'amount_charged' => (float) $existingAddon->price,
                        'valid_from' => $existingAddon->valid_from->toDateString(),
                        'valid_until' => $existingAddon->valid_until->toDateString(),
                        'total_capacity' => $clinic->totalDoctorCapacity(),
                    ];
                }
            }

            $license = License::where('clinic_id', $clinic->id)
                ->whereIn('status', ['TRIAL', 'ACTIVE'])
                ->lockForUpdate()
                ->latest()
                ->first();

            if (!$license || !$license->isValid()) {
                throw new \InvalidArgumentException('Clinic must have an active license to purchase additional doctor seats.');
            }

            $seats = max(1, (int) $seatsCount);
            $annualRate = $customPricePerSeat !== null ? (float) $customPricePerSeat : (float) ($license->additional_doctor_fee_per_year ?: 2999.00);
            $totalPrice = round($annualRate * $seats, 2);

            $payDate = $paymentDate ? Carbon::parse($paymentDate, 'Asia/Kolkata') : Carbon::today('Asia/Kolkata');
            $validFrom = clone $payDate;
            $validUntil = (clone $validFrom)->addYear()->subDay(); // Exactly one year minus one day

            $addon = ClinicDoctorSeatAddon::create([
                'clinic_id' => $clinic->id,
                'license_id' => $license->id,
                'seats_count' => $seats,
                'purchase_date' => $payDate->toDateString(),
                'valid_from' => $validFrom->toDateString(),
                'valid_until' => $validUntil->toDateString(),
                'price' => $totalPrice,
                'currency' => 'INR',
                'payment_method' => $paymentMethod ?: 'CASH',
                'payment_reference' => $paymentReference,
                'payment_date' => $payDate->toDateString(),
                'status' => 'ACTIVE',
                'created_by_developer_user_id' => $performedById,
                'notes' => $notes ?? 'Purchased via Superadmin',
                'idempotency_key' => $idempotencyKey,
            ]);

            // Sync additional_doctor_seats on active license for full entitlement consistency
            $license->increment('additional_doctor_seats', $seats);

            // Revision 42: Create authoritative commercial invoice for doctor seat add-on
            PlatformInvoiceService::createForSeatAddon($addon, $performedById);

            // Record audit event — existing license and license key remain unchanged
            LicenseEvent::create([
                'license_id' => $license->id,
                'event_type' => 'DOCTOR_SEATS_PURCHASED',
                'performed_by_type' => $performedById ? 'developer_user' : 'system',
                'performed_by_id' => $performedById,
                'old_status' => $license->status,
                'new_status' => $license->status,
                'old_end_date' => $license->end_date->toDateString(),
                'new_end_date' => $license->end_date->toDateString(),
                'notes' => "Purchased {$seats} additional doctor seat(s) (Add-On #{$addon->id}). Valid {$validFrom->format('d-m-Y')} to {$validUntil->format('d-m-Y')}. Paid ₹{$totalPrice} via {$paymentMethod}. License Key unchanged.",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);

            return [
                'clinic' => $clinic,
                'license' => $license,
                'addon' => $addon,
                'seats_added' => $seats,
                'amount_charged' => $totalPrice,
                'valid_from' => $validFrom->toDateString(),
                'valid_until' => $validUntil->toDateString(),
                'total_capacity' => $clinic->fresh()->totalDoctorCapacity(),
            ];
        });
    }

    /**
     * Suspend a clinic's license
     */
    public static function suspendLicense(int $licenseId, ?string $reason = null, ?int $performedById = null): License
    {
        return DB::transaction(function () use ($licenseId, $reason, $performedById) {
            $license = License::lockForUpdate()->findOrFail($licenseId);
            $oldStatus = $license->status;

            $license->status = 'SUSPENDED';
            $license->suspended_at = now();
            $license->save();

            LicenseEvent::create([
                'license_id' => $license->id,
                'event_type' => 'SUSPENDED',
                'performed_by_type' => $performedById ? 'developer_user' : 'system',
                'performed_by_id' => $performedById,
                'old_status' => $oldStatus,
                'new_status' => 'SUSPENDED',
                'old_end_date' => $license->end_date ? $license->end_date->toDateString() : null,
                'new_end_date' => $license->end_date ? $license->end_date->toDateString() : null,
                'notes' => $reason ?? 'Suspended by Superadmin',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);

            return $license;
        });
    }

    /**
     * Reactivate a suspended license
     */
    public static function reactivateLicense(int $licenseId, ?int $performedById = null): License
    {
        return DB::transaction(function () use ($licenseId, $performedById) {
            $license = License::lockForUpdate()->findOrFail($licenseId);
            $oldStatus = $license->status;

            $license->status = 'ACTIVE';
            $license->suspended_at = null;
            $license->save();

            LicenseEvent::create([
                'license_id' => $license->id,
                'event_type' => 'REACTIVATED',
                'performed_by_type' => $performedById ? 'developer_user' : 'system',
                'performed_by_id' => $performedById,
                'old_status' => $oldStatus,
                'new_status' => 'ACTIVE',
                'old_end_date' => $license->end_date ? $license->end_date->toDateString() : null,
                'new_end_date' => $license->end_date ? $license->end_date->toDateString() : null,
                'notes' => 'Reactivated by Superadmin',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);

            return $license;
        });
    }

    /**
     * Revoke a clinic license permanently
     */
    public static function revokeLicense(int $licenseId, ?string $reason = null, ?int $performedById = null): License
    {
        return DB::transaction(function () use ($licenseId, $reason, $performedById) {
            $license = License::lockForUpdate()->findOrFail($licenseId);
            $oldStatus = $license->status;

            $license->status = 'REVOKED';
            $license->revoked_at = now();
            $license->save();

            LicenseEvent::create([
                'license_id' => $license->id,
                'event_type' => 'REVOKED',
                'performed_by_type' => $performedById ? 'developer_user' : 'system',
                'performed_by_id' => $performedById,
                'old_status' => $oldStatus,
                'new_status' => 'REVOKED',
                'old_end_date' => $license->end_date ? $license->end_date->toDateString() : null,
                'new_end_date' => $license->end_date ? $license->end_date->toDateString() : null,
                'notes' => $reason ?? 'Revoked by Superadmin',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);

            return $license;
        });
    }
}
