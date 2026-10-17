<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DeveloperUser;
use App\Models\Doctor;
use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\LicenseRenewal;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LicenseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Revision35LicenseRenewalFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected DeveloperUser $superadmin;
    protected SubscriptionPlan $trialPlan;
    protected SubscriptionPlan $quarterlyPlan;
    protected SubscriptionPlan $halfYearlyPlan;
    protected SubscriptionPlan $yearlyPlan;
    protected Clinic $clinic1;
    protected User $adminUser1;
    protected License $activeLicense1;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure plans exist
        $this->trialPlan = SubscriptionPlan::firstOrCreate(['code' => 'TRIAL'], [
            'name' => 'Trial Plan',
            'duration_months' => 1,
            'price' => 0.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->quarterlyPlan = SubscriptionPlan::firstOrCreate(['code' => 'QUARTERLY'], [
            'name' => 'Quarterly Plan',
            'duration_months' => 3,
            'price' => 4499.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 2,
        ]);

        $this->halfYearlyPlan = SubscriptionPlan::firstOrCreate(['code' => 'HALF_YEARLY'], [
            'name' => 'Half-Yearly Plan',
            'duration_months' => 6,
            'price' => 7999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 3,
        ]);

        $this->yearlyPlan = SubscriptionPlan::firstOrCreate(['code' => 'YEARLY'], [
            'name' => 'Yearly Plan',
            'duration_months' => 12,
            'price' => 14999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 4,
        ]);

        // Create Superadmin (Developer User)
        $this->superadmin = DeveloperUser::create([
            'name' => 'Superadmin Rev35',
            'email' => 'superadmin_rev35@dxclinic.local',
            'password' => Hash::make('SuperAdmin@Rev35!'),
            'is_active' => true,
        ]);

        // Create Clinic 1 (NX0001)
        $this->clinic1 = Clinic::create([
            'clinic_code' => 'NX0001',
            'name' => 'Apex Healthcare',
            'address' => '123 Medical Enclave',
            'city' => 'Varanasi',
            'phone' => '9876543001',
            'activation_status' => 'ACTIVATED',
            'onboarding_status' => 'COMPLETED',
            'is_onboarded' => true,
        ]);

        $this->adminUser1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Apex Admin',
            'email' => 'admin@apexhealthcare.com',
            'phone' => '9876543001',
            'login_id' => 'ADMIN01',
            'role' => 'ADMIN',
            'password' => Hash::make('Secret123!'),
            'must_change_password' => false,
            'status' => 'ACTIVE',
        ]);

        // Clear any auto-generated license from Clinic model hook
        License::where('clinic_id', $this->clinic1->id)->delete();

        // Active yearly license ending in 30 days
        $key1 = 'NXCL-APEX-1111-2222-3333';
        $this->activeLicense1 = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $key1),
            'license_key_encrypted' => Crypt::encryptString($key1),
            'license_key_prefix' => 'NXCL',
            'start_date' => Carbon::today('Asia/Kolkata')->subMonths(11)->toDateString(),
            'end_date' => Carbon::today('Asia/Kolkata')->addDays(30)->toDateString(),
            'amount' => 14999.00,
            'discount_amount' => 0.00,
            'net_amount' => 14999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seats' => 2,
            'additional_doctor_fee_per_year' => 2999.00,
            'additional_doctor_amount' => 5998.00,
            'status' => 'ACTIVE',
            'issued_by' => $this->superadmin->id,
            'issued_at' => now(),
            'activated_at' => now(),
        ]);
    }

    /**
     * TEST A: Search by Clinic ID (case-insensitive)
     */
    public function test_a_search_by_clinic_id(): void
    {
        $response = $this->actingAs($this->superadmin, 'developer')
            ->get(route('superadmin.dashboard', ['q' => 'nx0001']));

        $response->assertStatus(200);
        $response->assertSee('NX0001');
        $response->assertSee('Apex Healthcare');
    }

    /**
     * TEST B: Search by Clinic Name (partial match)
     */
    public function test_b_search_by_name(): void
    {
        $response = $this->actingAs($this->superadmin, 'developer')
            ->get(route('superadmin.dashboard', ['q' => 'Apex Health']));

        $response->assertStatus(200);
        $response->assertSee('NX0001');
    }

    /**
     * TEST C: Search by Clinic Mobile (normalized digits)
     */
    public function test_c_search_by_mobile(): void
    {
        $response = $this->actingAs($this->superadmin, 'developer')
            ->get(route('superadmin.dashboard', ['q' => '9876543001']));

        $response->assertStatus(200);
        $response->assertSee('NX0001');
    }

    /**
     * TEST D: Quarterly Renewal (3 Months)
     */
    public function test_d_quarterly_renewal(): void
    {
        $oldLicense = $this->activeLicense1;

        $newLicense = LicenseService::renewLicense(
            licenseId: $oldLicense->id,
            durationMonths: 3,
            amount: 4499.00,
            performedById: $this->superadmin->id,
            planId: $this->quarterlyPlan->id,
            paymentReceived: true,
            paymentMethod: 'UPI',
            paymentReference: 'UPI-QUARTER-1'
        );

        $this->assertEquals('ACTIVE', $newLicense->status);
        $this->assertEquals(4499.00, $newLicense->amount);
        $this->assertEquals('RENEWED', $oldLicense->fresh()->status);
        $this->assertEquals($this->quarterlyPlan->id, $newLicense->plan_id);
    }

    /**
     * TEST E: Half-Yearly Renewal (6 Months)
     */
    public function test_e_half_yearly_renewal(): void
    {
        $oldLicense = $this->activeLicense1;

        $newLicense = LicenseService::renewLicense(
            licenseId: $oldLicense->id,
            durationMonths: 6,
            amount: 7999.00,
            performedById: $this->superadmin->id,
            planId: $this->halfYearlyPlan->id,
            paymentReceived: true,
            paymentMethod: 'BANK_TRANSFER',
            paymentReference: 'NEFT-HALF-1'
        );

        $this->assertEquals('ACTIVE', $newLicense->status);
        $this->assertEquals(7999.00, $newLicense->amount);
        $this->assertEquals($this->halfYearlyPlan->id, $newLicense->plan_id);
    }

    /**
     * TEST F: Yearly Renewal (12 Months)
     */
    public function test_f_yearly_renewal(): void
    {
        $oldLicense = $this->activeLicense1;

        $newLicense = LicenseService::renewLicense(
            licenseId: $oldLicense->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            renewExistingDoctorSeats: true,
            newDoctorSeatsCount: 0,
            paymentReceived: true,
            paymentMethod: 'CASH',
            paymentReference: 'CASH-REC-101'
        );

        $this->assertEquals('ACTIVE', $newLicense->status);
        $this->assertEquals(14999.00, $newLicense->amount);
        $this->assertEquals(5998.00, $newLicense->additional_doctor_amount); // 2 existing seats renewed
        $this->assertEquals(20997.00, $newLicense->net_amount);
    }

    /**
     * TEST G: Active License Extension starts after existing end date (Phase 9)
     */
    public function test_g_active_license_extension_starts_after_existing_end_date(): void
    {
        $oldLicense = $this->activeLicense1;
        $expectedStartDate = Carbon::parse($oldLicense->end_date, 'Asia/Kolkata')->addDay()->toDateString();
        $expectedEndDate = Carbon::parse($expectedStartDate, 'Asia/Kolkata')->addMonths(12)->subDay()->toDateString();

        $newLicense = LicenseService::renewLicense(
            licenseId: $oldLicense->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id
        );

        $this->assertEquals($expectedStartDate, $newLicense->start_date->toDateString());
        $this->assertEquals($expectedEndDate, $newLicense->end_date->toDateString());
        // Verify no loss of already-paid subscription time
        $this->assertTrue($newLicense->isValid());
    }

    /**
     * TEST H: Expired License Renewal starts on payment date (Phase 10)
     */
    public function test_h_expired_license_renewal_starts_on_payment_date(): void
    {
        // Expire clinic license
        $expiredDate = Carbon::today('Asia/Kolkata')->subDays(5);
        $this->activeLicense1->update([
            'status' => 'EXPIRED',
            'end_date' => $expiredDate->toDateString(),
        ]);

        $paymentDate = Carbon::today('Asia/Kolkata')->toDateString();
        $expectedEndDate = Carbon::parse($paymentDate, 'Asia/Kolkata')->addMonths(12)->subDay()->toDateString();

        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            paymentDate: $paymentDate
        );

        $this->assertEquals($paymentDate, $newLicense->start_date->toDateString());
        $this->assertEquals($expectedEndDate, $newLicense->end_date->toDateString());
        $this->assertEquals('ACTIVE', $newLicense->status);
    }

    /**
     * TEST I: Suspended License Renewal atomically reactivates clinic (Phase 11)
     */
    public function test_i_suspended_license_renewal_reactivates_clinic(): void
    {
        $this->activeLicense1->update([
            'status' => 'SUSPENDED',
            'suspended_at' => now(),
        ]);

        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id
        );

        $this->assertEquals('ACTIVE', $newLicense->status);
        $this->assertEquals('RENEWED', $this->activeLicense1->fresh()->status);
        $this->assertTrue($this->clinic1->fresh()->hasValidLicense());
    }

    /**
     * TEST J: Revoked / Cancelled License Protection (Phase 12)
     */
    public function test_j_revoked_license_cannot_be_normally_renewed(): void
    {
        $this->activeLicense1->update([
            'status' => 'REVOKED',
            'revoked_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This license cannot be renewed through the normal renewal workflow. Please use the appropriate Superadmin reinstatement process.');

        LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id
        );
    }

    /**
     * TEST K: Renewal requires NO activation code (Phase 17)
     */
    public function test_k_renewal_requires_no_activation_code(): void
    {
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id
        );

        $this->assertNull($newLicense->activation_code_hash);
        $this->assertEquals('ACTIVATED', $this->clinic1->fresh()->activation_status);
    }

    /**
     * TEST L: Renewal requires NO re-onboarding (Phase 17)
     */
    public function test_l_renewal_requires_no_onboarding(): void
    {
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id
        );

        $this->assertEquals('COMPLETED', $this->clinic1->fresh()->onboarding_status);
        $this->assertTrue($this->clinic1->fresh()->isOnboarded());
    }

    /**
     * TEST M: Correct Base Amount calculation
     */
    public function test_m_correct_base_amount_from_plan(): void
    {
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            renewExistingDoctorSeats: false,
            newDoctorSeatsCount: 0
        );

        $this->assertEquals(14999.00, $newLicense->amount);
        $this->assertEquals(14999.00, $newLicense->net_amount);
    }

    /**
     * TEST N: Discount Calculation (Phase 5 & 8)
     */
    public function test_n_discount_calculation(): void
    {
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            renewExistingDoctorSeats: false,
            newDoctorSeatsCount: 0,
            discount: 1999.00
        );

        $this->assertEquals(14999.00, $newLicense->amount);
        $this->assertEquals(1999.00, $newLicense->discount_amount);
        $this->assertEquals(13000.00, $newLicense->net_amount);
    }

    /**
     * TEST O: Existing Additional Doctor Seat Renewal (Phase 6 & 8)
     */
    public function test_o_existing_additional_doctor_seat_renewal(): void
    {
        // Clinic 1 has 2 existing additional seats (annual fee 2,999)
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            renewExistingDoctorSeats: true,
            newDoctorSeatsCount: 0
        );

        $this->assertEquals(2, $newLicense->additional_doctor_seats);
        $this->assertEquals(5998.00, $newLicense->additional_doctor_amount); // 2 * 2999
        $this->assertEquals(20997.00, $newLicense->net_amount); // 14999 + 5998
    }

    /**
     * TEST P: New Additional Doctor Seat Purchase (Phase 6 & 8)
     */
    public function test_p_new_additional_doctor_seat_purchase(): void
    {
        // Renew 2 existing seats + purchase 1 new seat = 3 total additional seats
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            renewExistingDoctorSeats: true,
            newDoctorSeatsCount: 1
        );

        $this->assertEquals(3, $newLicense->additional_doctor_seats);
        $this->assertEquals(8997.00, $newLicense->additional_doctor_amount); // 3 * 2999
        $this->assertEquals(23996.00, $newLicense->net_amount); // 14999 + 8997
    }

    /**
     * TEST Q: No Accidental Duplicate Doctor Billing (Phase 7)
     */
    public function test_q_no_accidental_duplicate_doctor_billing(): void
    {
        // Clinic has 2 paid seats. When renewing existing seats only, new seats count must remain 0
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            renewExistingDoctorSeats: true,
            newDoctorSeatsCount: 0
        );

        // Additional seats must be exactly 2, not 4
        $this->assertEquals(2, $newLicense->additional_doctor_seats);
        $this->assertEquals(3, $newLicense->totalDoctorCapacity()); // 1 included + 2 additional
    }

    /**
     * TEST R: Capacity Calculation (Phase 23)
     */
    public function test_r_capacity_calculation(): void
    {
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            renewExistingDoctorSeats: true,
            newDoctorSeatsCount: 2
        );

        // 1 included + 2 renewed + 2 new = 5 total capacity
        $this->assertEquals(5, $this->clinic1->fresh()->totalDoctorCapacity());
        $this->assertEquals(4, $newLicense->additional_doctor_seats);
    }

    /**
     * TEST S: Doctor creation blocked above capacity (Phase 23)
     */
    public function test_s_doctor_creation_blocked_above_capacity(): void
    {
        // Clinic has capacity 3 (1 included + 2 additional). Create 3 active doctors
        Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Alpha',
            'specialization' => 'Cardiology',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'REG-001',
            'is_active' => true,
        ]);
        Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Beta',
            'specialization' => 'Neurology',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'REG-002',
            'is_active' => true,
        ]);
        Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Gamma',
            'specialization' => 'Orthopedics',
            'qualification' => 'MBBS, MS',
            'registration_number' => 'REG-003',
            'is_active' => true,
        ]);

        $this->assertEquals(3, $this->clinic1->fresh()->activeDoctorCount());
        $this->assertFalse($this->clinic1->fresh()->canAddDoctor());

        // Attempting to create 4th doctor must throw exact required exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat.');

        Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Delta (Unauthorized)',
            'specialization' => 'General',
            'qualification' => 'MBBS',
            'registration_number' => 'REG-004',
            'is_active' => true,
        ]);
    }

    /**
     * TEST T: Atomic Renewal (Phase 15)
     */
    public function test_t_atomic_renewal_creates_all_records(): void
    {
        $initialLicenseCount = License::count();
        $initialRenewalCount = LicenseRenewal::count();
        $initialEventCount = LicenseEvent::count();

        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            paymentReceived: true,
            paymentMethod: 'UPI',
            paymentReference: 'UPI-ATOMIC-123'
        );

        $this->assertEquals($initialLicenseCount + 1, License::count());
        $this->assertEquals($initialRenewalCount + 1, LicenseRenewal::count());
        $this->assertEquals($initialEventCount + 2, LicenseEvent::count()); // Superseded + Renewed
        $this->assertDatabaseHas('license_renewals', [
            'license_id' => $newLicense->id,
            'clinic_id' => $this->clinic1->id,
            'payment_reference' => 'UPI-ATOMIC-123',
        ]);
    }

    /**
     * TEST U: Duplicate Click / Idempotency Protection (Phase 16)
     */
    public function test_u_duplicate_click_idempotency_protection(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // Click 1
        $license1 = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            idempotencyKey: $idempotencyKey
        );

        $renewalCountAfterClick1 = LicenseRenewal::count();

        // Click 2 (Simulated second click with same idempotency key)
        $license2 = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            idempotencyKey: $idempotencyKey
        );

        $this->assertEquals($license1->id, $license2->id);
        $this->assertEquals($renewalCountAfterClick1, LicenseRenewal::count());
    }

    /**
     * TEST V: Renewal History Preserved (Phase 21)
     */
    public function test_v_renewal_history_preserved(): void
    {
        $oldId = $this->activeLicense1->id;

        $newLicense = LicenseService::renewLicense(
            licenseId: $oldId,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id
        );

        // Previous license record must remain in database with status RENEWED
        $this->assertDatabaseHas('licenses', [
            'id' => $oldId,
            'status' => 'RENEWED',
        ]);

        $this->assertDatabaseHas('licenses', [
            'id' => $newLicense->id,
            'status' => 'ACTIVE',
            'previous_license_id' => $oldId,
        ]);
    }

    /**
     * TEST W: Renewal Event Audited (Phase 15 & 21)
     */
    public function test_w_renewal_event_audited(): void
    {
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id,
            notes: 'Audited annual renewal'
        );

        $this->assertDatabaseHas('license_events', [
            'license_id' => $newLicense->id,
            'event_type' => 'RENEWED',
            'new_status' => 'ACTIVE',
        ]);
    }

    /**
     * TEST X: Clinic status becomes ACTIVE upon renewal
     */
    public function test_x_clinic_status_becomes_active(): void
    {
        // Simulate expired
        $this->activeLicense1->update(['status' => 'EXPIRED']);

        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id
        );

        $this->assertEquals('ACTIVE', $newLicense->status);
        $this->assertTrue($this->clinic1->fresh()->hasValidLicense());
    }

    /**
     * TEST Y: Clinic Admin sees new expiry in settings panel (Phase 19 & 20)
     */
    public function test_y_clinic_admin_sees_new_expiry(): void
    {
        $newLicense = LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id
        );

        $response = $this->actingAs($this->adminUser1, 'web')
            ->get(route('settings.subscription'));

        $response->assertStatus(200);
        $response->assertSee($newLicense->end_date->format('d-m-Y'));
        $response->assertSee('License & Subscription');
    }

    /**
     * TEST Z: Existing clinic users continue without further action (Phase 18)
     */
    public function test_z_existing_clinic_users_remain_usable_without_action(): void
    {
        // Expire license first
        $this->activeLicense1->update([
            'status' => 'EXPIRED',
            'end_date' => Carbon::today('Asia/Kolkata')->subDay()->toDateString(),
        ]);

        // Prior to renewal: tenant access blocked
        $responseBlocked = $this->actingAs($this->adminUser1, 'web')->get('/dashboard');
        $responseBlocked->assertStatus(403);

        // Superadmin renews
        LicenseService::renewLicense(
            licenseId: $this->activeLicense1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            planId: $this->yearlyPlan->id
        );

        \App\Services\TenantContext::setClinic(null);
        $this->adminUser1->unsetRelation('clinic');

        // Immediately after Superadmin renewal: tenant access unblocked without any password change or activation
        $responseActive = $this->actingAs($this->adminUser1, 'web')->get('/dashboard');
        $responseActive->assertStatus(200);
    }
}
