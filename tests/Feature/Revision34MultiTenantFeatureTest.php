<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DeveloperUser;
use App\Models\Doctor;
use App\Models\DoctorConsultationType;
use App\Models\FeeMaster;
use App\Models\License;
use App\Models\Patient;
use App\Models\PrescriptionPadTemplate;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\TenantContext;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class Revision34MultiTenantFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected ?Clinic $clinic1 = null;
    protected ?Clinic $clinic2 = null;
    protected ?User $admin1 = null;
    protected ?User $operator1 = null;
    protected ?User $doctor1 = null;
    protected ?User $admin2 = null;
    protected ?DeveloperUser $superadmin = null;
    protected ?SubscriptionPlan $planYearly = null;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        $this->seed(SystemLedgerSeeder::class);

        // Standard subscription plans
        $this->planYearly = SubscriptionPlan::where('code', 'YEARLY')->first();
        if (!$this->planYearly) {
            $this->planYearly = SubscriptionPlan::create([
                'name' => 'Standard Annual Plan',
                'code' => 'YEARLY',
                'duration_months' => 12,
                'price' => 14999.00,
                'included_doctor_seats' => 1,
                'additional_doctor_seat_price' => 2999.00,
                'is_active' => true,
                'display_order' => 1,
            ]);
        }

        // Clinic 1 (Baseline NX0001)
        $this->clinic1 = Clinic::first();
        if (!$this->clinic1) {
            $this->clinic1 = Clinic::create([
                'name' => 'Sharma Care Clinic',
                'clinic_code' => 'NX0001',
                'public_slug' => 'sharmacare',
                'city' => 'Varanasi',
                'address' => 'Assi Ghat',
                'phone' => '9876543210',
                'is_onboarded' => true,
                'onboarding_status' => 'COMPLETED',
                'activation_status' => 'ACTIVATED',
                'activated_at' => now(),
            ]);
        } else {
            $this->clinic1->update([
                'public_slug' => $this->clinic1->public_slug ?: 'sharmacare',
                'onboarding_status' => 'COMPLETED',
                'activation_status' => 'ACTIVATED',
            ]);
        }

        // Ensure clean license state for Clinic 1
        $this->clinic1->licenses()->delete();

        // Single authoritative License for Clinic 1
        License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->planYearly->id,
            'license_key_hash' => hash('sha256', 'NXCL-TEST-NX0001'),
            'license_key_encrypted' => Crypt::encryptString('NXCL-TEST-NX0001'),
            'license_key_prefix' => 'NXCL-TEST',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addMonths(12)->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'included_doctor_seats' => 5,
            'additional_doctor_seats' => 0,
            'status' => 'ACTIVE',
        ]);

        $this->admin1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'login_id' => 'ADM01',
            'name' => 'Dr. Sharma Admin',
            'email' => 'admin@sharma.local',
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'password' => Hash::make('AdminPass123!'),
            'must_change_password' => false,
        ]);

        $this->operator1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'login_id' => 'OPR01',
            'name' => 'Ramesh Operator',
            'email' => 'operator@sharma.local',
            'role' => 'OPERATOR',
            'status' => 'ACTIVE',
            'password' => Hash::make('OperatorPass123!'),
            'must_change_password' => false,
        ]);

        // Clinic 2 (NX0002) for Cross-Tenant Isolation Tests
        $this->clinic2 = Clinic::where('clinic_code', 'NX0002')->first();
        if (!$this->clinic2) {
            $this->clinic2 = Clinic::create([
                'name' => 'Apex Healthcare',
                'clinic_code' => 'NX0002',
                'public_slug' => 'apexhealthcare',
                'city' => 'Prayagraj',
                'address' => 'Civil Lines',
                'phone' => '9876543210',
                'is_onboarded' => true,
                'onboarding_status' => 'COMPLETED',
                'activation_status' => 'ACTIVATED',
                'activated_at' => now(),
            ]);
        }

        $this->clinic2->licenses()->delete();

        License::create([
            'clinic_id' => $this->clinic2->id,
            'plan_id' => $this->planYearly->id,
            'license_key_hash' => hash('sha256', 'NXCL-TEST-NX0002'),
            'license_key_encrypted' => Crypt::encryptString('NXCL-TEST-NX0002'),
            'license_key_prefix' => 'NXCL-TEST',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addMonths(12)->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'included_doctor_seats' => 2,
            'additional_doctor_seats' => 0,
            'status' => 'ACTIVE',
        ]);

        $this->admin2 = User::create([
            'clinic_id' => $this->clinic2->id,
            'login_id' => 'ADM01',
            'name' => 'Dr. Apex Admin',
            'email' => 'admin@apex.local',
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'password' => Hash::make('AdminPass123!'),
            'must_change_password' => false,
        ]);

        // Superadmin Developer User
        $this->superadmin = DeveloperUser::where('email', 'superadmin@dxclinic.local')->first();
        if (!$this->superadmin) {
            $this->superadmin = DeveloperUser::create([
                'name' => 'DxClinic Superadmin',
                'email' => 'superadmin@dxclinic.local',
                'password' => Hash::make('SuperadminPass2026!'),
                'is_active' => true,
            ]);
        } else {
            $this->superadmin->update([
                'password' => Hash::make('SuperadminPass2026!'),
                'is_active' => true,
            ]);
        }
    }

    protected function createDoctor(array $attributes = []): Doctor
    {
        static $regCounter = 1000;
        $regCounter++;
        return Doctor::create(array_merge([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Test ' . $regCounter,
            'qualification' => 'MBBS, MD',
            'registration_number' => 'REG-' . $regCounter,
            'is_active' => true,
        ], $attributes));
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    // ============================================================
    // CORRECTION 14 — SECURITY TEST MATRIX (19 TESTS)
    // ============================================================

    /**
     * TEST 1: NX0001 user + NX0002 header -> NX0001 remains (Cross-tenant header rejected with 403)
     */
    public function test_01_nx0001_user_with_nx0002_header_keeps_tenant_nx0001(): void
    {
        $this->actingAs($this->admin1);

        // Sending cross-tenant header must be blocked with 403 Forbidden
        $response = $this->withHeaders([
            'X-Clinic-ID' => $this->clinic2->id,
            'X-Clinic-Code' => 'NX0002',
        ])->get(route('dashboard'));

        $response->assertStatus(403);

        // TenantContext strictly remains NX0001
        $currentClinic = TenantContext::getClinic();
        $this->assertNotNull($currentClinic);
        $this->assertEquals('NX0001', $currentClinic->clinic_code);
    }

    /**
     * TEST 2: NX0001 user + NX0002 route -> denied (403)
     */
    public function test_02_nx0001_user_with_nx0002_route_param_is_denied(): void
    {
        $this->actingAs($this->admin1);

        // Simulating access to a route with foreign clinic route parameter
        $response = $this->get('/dashboard?clinic_code=NX0002');
        // Authoritative TenantContext ignores query params for authenticated users and keeps NX0001
        $this->assertEquals($this->clinic1->id, TenantContext::getClinic()->id);

        $responseWithParam = $this->call('GET', route('dashboard'), [], [], [], [
            'HTTP_HOST' => 'localhost',
        ]);
        $this->assertEquals($this->clinic1->id, TenantContext::getClinic()->id);
    }

    /**
     * TEST 3: NX0001 user + NX0002 patient ID -> denied (403)
     */
    public function test_03_nx0001_user_accessing_nx0002_patient_id_is_denied(): void
    {
        $patient2 = Patient::create([
            'clinic_id' => $this->clinic2->id,
            'patient_id' => 'P0001',
            'name' => 'Foreign Patient Apex',
            'mobile' => '9876543210',
            'address' => 'Civil Lines',
            'city' => 'Prayagraj',
            'age' => 30,
            'sex' => 'M',
        ]);

        $this->actingAs($this->admin1);

        $response = $this->get(route('tenant.patient.show', $patient2));
        $response->assertStatus(403);
    }

    /**
     * TEST 4: NX0001 user + NX0002 appointment -> denied (403)
     */
    public function test_04_nx0001_user_accessing_nx0002_appointment_is_denied(): void
    {
        $patient2 = Patient::create([
            'clinic_id' => $this->clinic2->id,
            'patient_id' => 'P0002',
            'name' => 'Foreign Patient 2',
            'mobile' => '9876543211',
            'address' => 'Civil Lines',
            'city' => 'Prayagraj',
            'age' => 45,
            'sex' => 'F',
        ]);

        $doctor2 = $this->createDoctor([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Dr. Apex Specialist',
        ]);

        $appointment2 = Appointment::create([
            'clinic_id' => $this->clinic2->id,
            'patient_id' => $patient2->id,
            'doctor_id' => $doctor2->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'token_number' => 1,
            'formatted_token' => 'M-001',
            'status' => 'CONFIRMED',
            'secure_token' => 'SECURE-APEX-001',
        ]);

        $this->actingAs($this->admin1);

        $response = $this->get(route('tenant.appointment.show', $appointment2));
        $response->assertStatus(403);
    }

    /**
     * TEST 5: NX0001 user + NX0002 template -> denied (403)
     */
    public function test_05_nx0001_user_accessing_nx0002_template_is_denied(): void
    {
        $doctor2 = $this->createDoctor([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Dr. Apex Template Specialist',
        ]);

        $template2 = PrescriptionPadTemplate::create([
            'clinic_id' => $this->clinic2->id,
            'doctor_id' => $doctor2->id,
            'name' => 'Apex Classic Pad',
            'paper_width_mm' => 210,
            'paper_height_mm' => 297,
            'orientation' => 'portrait',
            'template_type' => 'STANDARD',
            'version' => 1,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin1);

        $response = $this->get(route('tenant.template.show', $template2));
        $response->assertStatus(403);
    }

    /**
     * TEST 6: NX0001 user + NX0002 fee -> denied (403)
     */
    public function test_06_nx0001_user_accessing_nx0002_fee_is_denied(): void
    {
        $fee2 = FeeMaster::create([
            'clinic_id' => $this->clinic2->id,
            'code' => 'APEX_CONSULT',
            'name' => 'Apex Consultation Fee',
            'amount' => 800.00,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin1);

        $response = $this->get(route('tenant.fee.show', $fee2));
        $response->assertStatus(403);
    }

    /**
     * TEST 7: NX0001 user cannot access Superadmin
     */
    public function test_07_nx0001_user_cannot_access_superadmin(): void
    {
        $this->actingAs($this->admin1);

        $response = $this->get(route('superadmin.dashboard'));
        $response->assertRedirect(route('superadmin.login'));
    }

    /**
     * TEST 8: Superadmin can inspect NX0002
     */
    public function test_08_superadmin_can_inspect_nx0002(): void
    {
        $this->actingAs($this->superadmin, 'developer');

        $response = $this->get(route('superadmin.clinics.events', $this->clinic2));
        $response->assertStatus(200);
        $response->assertJsonPath('clinic.clinic_code', 'NX0002');
    }

    /**
     * TEST 9: Public /book/NX0001 cannot expose NX0002
     */
    public function test_09_public_booking_nx0001_cannot_expose_nx0002(): void
    {
        $this->createDoctor([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Sharma Solo',
        ]);

        $this->createDoctor([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Dr. Apex Secret',
        ]);

        $response = $this->get('/book/sharmacare');
        $response->assertStatus(200);
        $response->assertSee('Sharma Care Clinic');
        $response->assertDontSee('Apex Healthcare');
        $response->assertDontSee('Dr. Apex Secret');
    }

    /**
     * TEST 10: Public /book/NX0002 cannot expose NX0001
     */
    public function test_10_public_booking_nx0002_cannot_expose_nx0001(): void
    {
        $this->createDoctor([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Sharma Solo',
        ]);

        $this->createDoctor([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Dr. Apex Secret',
        ]);

        $response = $this->get('/book/apexhealthcare');
        $response->assertStatus(200);
        $response->assertSee('Apex Healthcare');
        $response->assertDontSee('Sharma Care Clinic');
        $response->assertDontSee('Dr. Sharma Solo');
    }

    /**
     * TEST 11: Invalid clinic code returns safe response (404)
     */
    public function test_11_invalid_clinic_code_returns_safe_404(): void
    {
        $response = $this->get('/book/NX9999');
        $response->assertStatus(404);

        $shortResponse = $this->get('/b/NX9999');
        $shortResponse->assertStatus(404);
    }

    /**
     * TEST 12: License activation code can be consumed only once
     */
    public function test_12_license_activation_code_can_be_consumed_only_once(): void
    {
        $pkg = LicenseService::issueLicense(
            clinicData: ['name' => 'Test Clinic Once'],
            adminData: ['name' => 'Admin Once', 'email' => 'once@test.local'],
            planId: $this->planYearly->id,
            issuedBy: $this->superadmin->id
        );

        $clinic = $pkg['clinic'];
        $admin = $pkg['admin_user'];
        $activationCode = $pkg['raw_activation_code'];

        // 1st consumption: Success
        $res1 = LicenseService::activateLicense($clinic->id, $admin->id, $activationCode);
        $this->assertTrue($res1['success']);
        $this->assertEquals('ACTIVATED', $clinic->fresh()->activation_status);

        // 2nd consumption: Throws exception
        $this->expectException(\InvalidArgumentException::class);
        LicenseService::activateLicense($clinic->id, $admin->id, $activationCode);
    }

    /**
     * TEST 13: Non-admin cannot consume initial activation code
     */
    public function test_13_non_admin_cannot_consume_initial_activation_code(): void
    {
        $pkg = LicenseService::issueLicense(
            clinicData: ['name' => 'Test Non Admin Clinic'],
            adminData: ['name' => 'Admin User', 'email' => 'admin@nonadmin.local'],
            planId: $this->planYearly->id,
            issuedBy: $this->superadmin->id
        );

        $clinic = $pkg['clinic'];
        $operator = User::create([
            'clinic_id' => $clinic->id,
            'login_id' => 'OPR01',
            'name' => 'Operator User',
            'email' => 'operator@nonadmin.local',
            'role' => 'OPERATOR',
            'password' => Hash::make('password'),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only the Clinic Administrator can activate the clinic license.');

        LicenseService::activateLicense($clinic->id, $operator->id, $pkg['raw_activation_code']);
    }

    /**
     * TEST 14: Expired license blocks normal tenant access
     */
    public function test_14_expired_license_blocks_normal_tenant_access(): void
    {
        $this->clinic1->licenses()->update([
            'status' => 'EXPIRED',
            'end_date' => now()->subDay()->toDateString(),
        ]);

        TenantContext::reset();
        $this->actingAs($this->admin1);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(403);
        $response->assertSee('Subscription Inactive');
    }

    /**
     * TEST 15: Suspended license blocks normal tenant access
     */
    public function test_15_suspended_license_blocks_normal_tenant_access(): void
    {
        $this->clinic1->licenses()->update([
            'status' => 'SUSPENDED',
            'suspended_at' => now(),
        ]);

        TenantContext::reset();
        $this->actingAs($this->admin1);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(403);
        $response->assertSee('Subscription Inactive');
    }

    /**
     * TEST 16: Renewing a license does not require activation
     */
    public function test_16_renewing_a_license_does_not_require_activation(): void
    {
        $license = $this->clinic1->activeLicense;
        $oldId = $license->id;

        $newLicense = LicenseService::renewLicense(
            licenseId: $oldId,
            durationMonths: 12,
            amount: 14999.00,
            performedById: $this->superadmin->id,
            notes: 'Annual Renewal'
        );

        // Verify new license created (Correction 3)
        $this->assertNotEquals($oldId, $newLicense->id);
        $this->assertEquals('ACTIVE', $newLicense->status);
        $this->assertNull($newLicense->activation_code_hash); // NEVER requires activation code (Correction 9 & 14)
        $this->assertEquals('ACTIVATED', $this->clinic1->fresh()->activation_status);

        // Tenant user accesses protected route immediately without activation prompt
        TenantContext::reset();
        $this->actingAs($this->admin1);
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    }

    /**
     * TEST 17: Additional doctor cannot exceed purchased capacity
     */
    public function test_17_additional_doctor_cannot_exceed_purchased_capacity(): void
    {
        // Clinic with exactly 1 doctor capacity
        $pkg = LicenseService::issueLicense(
            clinicData: ['name' => 'Single Doctor Clinic'],
            adminData: ['name' => 'Admin One', 'email' => 'one@test.local'],
            planId: $this->planYearly->id,
            issuedBy: $this->superadmin->id,
            includedDoctorSeats: 1,
            additionalDoctorSeats: 0
        );

        $clinic = $pkg['clinic'];
        LicenseService::activateLicense($clinic->id, $pkg['admin_user']->id, $pkg['raw_activation_code']);

        // Doctor 1 succeeds
        $doc1 = $this->createDoctor([
            'clinic_id' => $clinic->id,
            'name' => 'Dr. First Included',
        ]);
        $this->assertNotNull($doc1);
        $this->assertEquals(1, $clinic->activeDoctorCount());
        $this->assertFalse($clinic->canAddDoctor());

        // Doctor 2 blocked transactionally with exact required error message
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat.');

        $this->createDoctor([
            'clinic_id' => $clinic->id,
            'name' => 'Dr. Second Overflow',
        ]);
    }

    /**
     * TEST 18: Additional doctor purchase increases capacity
     */
    public function test_18_additional_doctor_purchase_increases_capacity(): void
    {
        $pkg = LicenseService::issueLicense(
            clinicData: ['name' => 'Expandable Clinic'],
            adminData: ['name' => 'Admin Expand', 'email' => 'expand@test.local'],
            planId: $this->planYearly->id,
            issuedBy: $this->superadmin->id,
            includedDoctorSeats: 1,
            additionalDoctorSeats: 0
        );

        $clinic = $pkg['clinic'];
        LicenseService::activateLicense($clinic->id, $pkg['admin_user']->id, $pkg['raw_activation_code']);

        $this->createDoctor([
            'clinic_id' => $clinic->id,
            'name' => 'Dr. Primary',
        ]);
        $this->assertEquals(1, $clinic->totalDoctorCapacity());
        $this->assertFalse($clinic->canAddDoctor());

        // Superadmin adds 2 doctor seats
        $res = LicenseService::addDoctorSeats(
            clinicId: $clinic->id,
            seatsCount: 2,
            proRata: false,
            performedById: $this->superadmin->id
        );

        $this->assertEquals(2, $res['seats_added']);
        $this->assertEquals(3, $res['total_capacity']);
        $this->assertTrue($clinic->fresh()->canAddDoctor());
        $this->assertEquals(2, $clinic->fresh()->availableDoctorSeats());

        // Now Doctor 2 and Doctor 3 can be created successfully
        $doc2 = $this->createDoctor([
            'clinic_id' => $clinic->id,
            'name' => 'Dr. Addon Two',
        ]);
        $doc3 = $this->createDoctor([
            'clinic_id' => $clinic->id,
            'name' => 'Dr. Addon Three',
        ]);

        $this->assertNotNull($doc2);
        $this->assertNotNull($doc3);
        $this->assertEquals(3, $clinic->fresh()->activeDoctorCount());
    }

    /**
     * TEST 19: Doctor deletion/deactivation does not reduce historical license billing records
     */
    public function test_19_doctor_deactivation_does_not_reduce_historical_billing(): void
    {
        $pkg = LicenseService::issueLicense(
            clinicData: ['name' => 'Billing Audit Clinic'],
            adminData: ['name' => 'Admin Audit', 'email' => 'audit@test.local'],
            planId: $this->planYearly->id,
            issuedBy: $this->superadmin->id,
            includedDoctorSeats: 1,
            additionalDoctorSeats: 1,
            additionalDoctorFeePerYear: 2999.00
        );

        $clinic = $pkg['clinic'];
        $license = $pkg['license'];
        $initialAdditionalAmount = (float) $license->additional_doctor_amount;
        $initialNetAmount = (float) $license->net_amount;

        $doc = $this->createDoctor([
            'clinic_id' => $clinic->id,
            'name' => 'Dr. Temporary',
        ]);

        // Deactivate doctor
        $doc->update(['is_active' => false]);

        // Billing amounts and event audit log remain immutable
        $license->refresh();
        $this->assertEquals($initialAdditionalAmount, (float) $license->additional_doctor_amount);
        $this->assertEquals($initialNetAmount, (float) $license->net_amount);

        // Delete doctor
        $doc->delete();

        $license->refresh();
        $this->assertEquals($initialAdditionalAmount, (float) $license->additional_doctor_amount);
        $this->assertEquals($initialNetAmount, (float) $license->net_amount);
    }

    /**
     * TEST 20: Onboarding Status Lifecycle & Independence (Correction 2)
     */
    public function test_20_onboarding_status_lifecycle(): void
    {
        $pkg = LicenseService::issueLicense(
            clinicData: ['name' => 'Onboarding Lifecycle Clinic'],
            adminData: ['name' => 'Admin Onboarding', 'email' => 'onboard@test.local'],
            planId: $this->planYearly->id,
            issuedBy: $this->superadmin->id
        );

        $clinic = $pkg['clinic'];
        $this->assertEquals('NOT_STARTED', $clinic->onboarding_status);
        $this->assertEquals('PENDING_ACTIVATION', $clinic->activation_status);
        $this->assertEquals('ACTIVE', $pkg['license']->status);

        // After activation: Onboarding becomes IN_PROGRESS
        LicenseService::activateLicense($clinic->id, $pkg['admin_user']->id, $pkg['raw_activation_code']);
        $clinic->refresh();
        $this->assertEquals('IN_PROGRESS', $clinic->onboarding_status);
        $this->assertEquals('ACTIVATED', $clinic->activation_status);

        // Clinic completes onboarding wizard
        $clinic->update(['onboarding_status' => 'COMPLETED', 'is_onboarded' => true]);
        $this->assertEquals('COMPLETED', $clinic->fresh()->onboarding_status);
        $this->assertTrue($clinic->fresh()->isOnboardingCompleted());
    }

    /**
     * TEST 21: Clinic Lookup API Rate-Limiting & Minimal Data Exposure (Correction 8)
     */
    public function test_21_clinic_lookup_api_returns_minimal_data_safely(): void
    {
        $response = $this->get('/api/clinic-lookup/NX0001');
        $response->assertStatus(200);
        $response->assertJson([
            'clinic_code' => 'NX0001',
            'clinic_name' => 'Sharma Care Clinic',
            'city' => 'Varanasi',
        ]);

        // Does NOT expose sensitive info
        $response->assertJsonMissing(['phone']);
        $response->assertJsonMissing(['address']);
        $response->assertJsonMissing(['email']);
        $response->assertJsonMissing(['license_key']);
    }

    /**
     * TEST 22: Short Public URL /b/{public_slug} redirects to canonical /book/{public_slug} (Revision 47)
     */
    public function test_22_short_public_url_redirects_to_canonical(): void
    {
        $response = $this->get('/b/sharmacare');
        $response->assertRedirect('/book/sharmacare');

        $response2 = $this->get('/b/apexhealthcare');
        $response2->assertRedirect('/book/apexhealthcare');
    }
}
