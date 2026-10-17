<?php

namespace Tests\Feature;

use App\Livewire\Pwa\PatientBooking;
use App\Models\Appointment;
use App\Models\BookingType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorBookingTypeAvailability;
use App\Models\FeeMaster;
use App\Models\License;
use App\Models\Patient;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PublicSlugService;
use App\Services\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class Revision47PublicSlugFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinicA;
    protected Clinic $clinicB;
    protected Clinic $demoClinic;
    protected Doctor $doctorA;
    protected Doctor $doctorB;
    protected BookingType $bookingTypeA;
    protected BookingType $bookingTypeB;
    protected User $operatorA;
    protected User $operatorB;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::reset();

        // 1. Subscription Plan
        $plan = SubscriptionPlan::create([
            'name' => 'Professional Plan',
            'code' => 'PROFESSIONAL',
            'duration_months' => 12,
            'price' => 9999.00,
            'price_per_year' => 9999.00,
            'max_doctors' => 5,
            'features' => ['all'],
            'is_active' => true,
        ]);

        // 2. Clinic A (Sharma Care Clinic -> sharmacare)
        $this->clinicA = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'sharmacare',
            'name' => 'Sharma Care Clinic',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
            'phone' => '9876543001',
            'is_onboarded' => true,
            'onboarding_status' => 'COMPLETED',
            'activation_status' => 'ACTIVATED',
            'max_advance_booking_days' => 7,
            'max_patients_per_mobile' => 4,
        ]);

        License::create([
            'clinic_id' => $this->clinicA->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'KEY-CLINIC-A'),
            'license_key_encrypted' => Crypt::encryptString('KEY-CLINIC-A'),
            'license_key_prefix' => 'NXCL-AAAA',
            'status' => 'ACTIVE',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'max_doctors' => 5,
        ]);

        $this->doctorA = Doctor::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Dr. Ramesh Sharma',
            'qualification' => 'MBBS, MD',
            'specialization' => 'Cardiology',
            'registration_number' => 'DOC-A-REG',
            'is_active' => true,
            'consultation_fee' => 500,
        ]);

        $this->bookingTypeA = BookingType::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Clinic Consultancy',
            'code' => 'CLINIC_PHYSICAL',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'is_active' => true,
        ]);

        DoctorBookingTypeAvailability::create([
            'clinic_id' => $this->clinicA->id,
            'doctor_id' => $this->doctorA->id,
            'booking_type_id' => $this->bookingTypeA->id,
            'is_available' => true,
        ]);

        FeeMaster::create([
            'clinic_id' => $this->clinicA->id,
            'doctor_id' => $this->doctorA->id,
            'booking_type_id' => $this->bookingTypeA->id,
            'code' => 'OPD_FEE_DOC_A',
            'name' => 'Consultation Fee',
            'fee_type' => 'OPD',
            'amount' => 500,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $this->operatorA = User::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Operator A',
            'email' => 'operator_a@sharmacare.local',
            'login_id' => 'NX0001-OP01',
            'role' => 'OPERATOR',
            'password' => bcrypt('Password@123'),
            'must_change_password' => false,
            'status' => 'ACTIVE',
        ]);

        // 3. Clinic B (Arogya Super Specialty Clinic -> arogya)
        $this->clinicB = Clinic::create([
            'clinic_code' => 'NX0002',
            'public_slug' => 'arogya',
            'name' => 'Arogya Super Specialty Clinic',
            'address' => 'Hazratganj',
            'city' => 'Lucknow',
            'phone' => '9876543002',
            'is_onboarded' => true,
            'onboarding_status' => 'COMPLETED',
            'activation_status' => 'ACTIVATED',
            'max_advance_booking_days' => 7,
            'max_patients_per_mobile' => 4,
        ]);

        License::create([
            'clinic_id' => $this->clinicB->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'KEY-CLINIC-B'),
            'license_key_encrypted' => Crypt::encryptString('KEY-CLINIC-B'),
            'license_key_prefix' => 'NXCL-BBBB',
            'status' => 'ACTIVE',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'max_doctors' => 5,
        ]);

        $this->doctorB = Doctor::create([
            'clinic_id' => $this->clinicB->id,
            'name' => 'Dr. Suresh Arogya',
            'qualification' => 'MBBS, MS',
            'specialization' => 'Orthopedics',
            'registration_number' => 'DOC-B-REG',
            'is_active' => true,
            'consultation_fee' => 700,
        ]);

        $this->bookingTypeB = BookingType::create([
            'clinic_id' => $this->clinicB->id,
            'name' => 'Clinic Consultancy',
            'code' => 'CLINIC_PHYSICAL',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'is_active' => true,
        ]);

        DoctorBookingTypeAvailability::create([
            'clinic_id' => $this->clinicB->id,
            'doctor_id' => $this->doctorB->id,
            'booking_type_id' => $this->bookingTypeB->id,
            'is_available' => true,
        ]);

        FeeMaster::create([
            'clinic_id' => $this->clinicB->id,
            'doctor_id' => $this->doctorB->id,
            'booking_type_id' => $this->bookingTypeB->id,
            'code' => 'OPD_FEE_DOC_B',
            'name' => 'Consultation Fee',
            'fee_type' => 'OPD',
            'amount' => 700,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $this->operatorB = User::create([
            'clinic_id' => $this->clinicB->id,
            'name' => 'Operator B',
            'email' => 'operator_b@arogya.local',
            'login_id' => 'NX0002-OP01',
            'role' => 'OPERATOR',
            'password' => bcrypt('Password@123'),
            'must_change_password' => false,
            'status' => 'ACTIVE',
        ]);

        // 4. Demo Clinic (democlinic)
        $this->demoClinic = Clinic::create([
            'clinic_code' => 'democlinic',
            'public_slug' => 'democlinic',
            'name' => 'Demo Clinic',
            'address' => 'Cyber City',
            'city' => 'Varanasi',
            'phone' => '9876543210',
            'is_demo' => true,
            'demo_status' => 'ACTIVE',
            'is_onboarded' => true,
            'onboarding_status' => 'COMPLETED',
            'activation_status' => 'ACTIVATED',
            'max_advance_booking_days' => 2,
            'max_patients_per_mobile' => 4,
        ]);
    }

    // =========================================================================
    // SECTION 60: PUBLIC SLUG SERVICE TESTS
    // =========================================================================

    public function test_01_public_slug_normalization_and_base_generation(): void
    {
        $this->assertEquals('sharmacare', PublicSlugService::normalize('Sharma Care'));
        $this->assertEquals('sharmacare', PublicSlugService::normalize('SHARMA CARE!'));
        $this->assertEquals('sharmacareclinic', PublicSlugService::normalize('Sharma-Care_Clinic...'));

        // Base generation stripping noise words
        $this->assertEquals('sharmacare', PublicSlugService::generateBaseSlug('Sharma Care Clinic'));
        $this->assertEquals('sharmacare', PublicSlugService::generateBaseSlug('Sharma-Care_Clinic...'));
        $this->assertEquals('arogya', PublicSlugService::generateBaseSlug('Arogya Super Specialty Clinic'));
        $this->assertEquals('democlinic', PublicSlugService::generateBaseSlug('Demo Clinic'));
        $this->assertEquals('healthplus', PublicSlugService::generateBaseSlug('Health Plus Hospital'));
    }

    public function test_02_human_readable_candidate_progression(): void
    {
        $candidates = PublicSlugService::generateCandidates('Sharma Care Clinic', 'Varanasi');

        $this->assertNotEmpty($candidates);
        $this->assertEquals('sharmacare', $candidates[0]);
        $this->assertEquals('sharmacarevns', $candidates[1]);
        $this->assertEquals('sharmacarevaranasi', $candidates[2]);
        $this->assertEquals('sharmacareclinic', $candidates[3]);

        // Verify none of the candidates contain random hashes or uppercase or punctuation
        foreach ($candidates as $candidate) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $candidate);
            $this->assertLessThanOrEqual(50, strlen($candidate));
        }
    }

    public function test_03_slug_availability_check(): void
    {
        $this->assertFalse(PublicSlugService::isSlugAvailable('sharmacare'));
        $this->assertFalse(PublicSlugService::isSlugAvailable('arogya'));
        $this->assertFalse(PublicSlugService::isSlugAvailable('democlinic'));

        $this->assertTrue(PublicSlugService::isSlugAvailable('uniqueclinicname'));

        // Excluding own clinic ID returns true
        $this->assertTrue(PublicSlugService::isSlugAvailable('sharmacare', $this->clinicA->id));
        $this->assertFalse(PublicSlugService::isSlugAvailable('sharmacare', $this->clinicB->id));
    }

    public function test_04_first_available_unique_slug_generation_on_collision(): void
    {
        // First clinic with name Sharma Care Clinic exists as 'sharmacare'
        // Second clinic with same name in Varanasi should generate 'sharmacarevns'
        $slug2 = PublicSlugService::generateUniqueSlug('Sharma Care Clinic', 'Varanasi');
        $this->assertEquals('sharmacarevns', $slug2);

        // If 'sharmacarevns' is also taken, candidate progresses to 'sharmacarevaranasi'
        Clinic::create([
            'clinic_code' => 'NX0003',
            'public_slug' => 'sharmacarevns',
            'name' => 'Sharma Care Varanasi',
            'address' => 'Lanka',
            'city' => 'Varanasi',
            'phone' => '9876543003',
        ]);

        $slug3 = PublicSlugService::generateUniqueSlug('Sharma Care Clinic', 'Varanasi');
        $this->assertEquals('sharmacarevaranasi', $slug3);
    }

    public function test_05_custom_slug_validation_and_assignment(): void
    {
        // Own existing slug allowed
        $slug = PublicSlugService::validateAndAssignSlug($this->clinicA, 'sharmacare');
        $this->assertEquals('sharmacare', $slug);

        // New available custom slug allowed
        $slugNew = PublicSlugService::validateAndAssignSlug($this->clinicA, 'mysharmaclinic');
        $this->assertEquals('mysharmaclinic', $slugNew);

        // Slug taken by another clinic is rejected
        $this->expectException(InvalidArgumentException::class);
        PublicSlugService::validateAndAssignSlug($this->clinicA, 'arogya');
    }

    public function test_06_database_unique_constraint_prevents_duplicate_slugs(): void
    {
        $this->expectException(QueryException::class);

        // Attempt direct database insert with existing public_slug
        DB::table('clinics')->insert([
            'clinic_code' => 'NX9999',
            'public_slug' => 'sharmacare', // Duplicate!
            'name' => 'Imposter Clinic',
            'address' => 'Fake',
            'city' => 'Varanasi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_07_concurrent_creation_retry_protection(): void
    {
        // Test saveWithConcurrencyProtection handles duplicate-key collision cleanly
        $newClinic = new Clinic([
            'clinic_code' => 'NX0004',
            'name' => 'Sharma Care Clinic',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
            'phone' => '9876543004',
        ]);

        $savedClinic = PublicSlugService::saveWithConcurrencyProtection($newClinic);
        $this->assertNotEmpty($savedClinic->public_slug);
        $this->assertNotEquals('sharmacare', $savedClinic->public_slug); // Got collision-resolved slug!
    }

    // =========================================================================
    // SECTION 47 / 61: PUBLIC PWA ROUTE ACCEPTANCE CASES
    // =========================================================================

    public function test_08_case_a_get_book_without_slug_returns_404(): void
    {
        $response = $this->get('/book');
        $response->assertStatus(404);
    }

    public function test_09_case_b_get_b_without_slug_returns_404(): void
    {
        $response = $this->get('/b');
        $response->assertStatus(404);
    }

    public function test_10_case_c_get_book_valid_demo_slug_returns_200(): void
    {
        $response = $this->get('/book/democlinic');
        $response->assertStatus(200);
        $response->assertSee('Demo Clinic');
    }

    public function test_11_case_c_get_book_valid_commercial_slug_returns_200(): void
    {
        $response = $this->get('/book/sharmacare');
        $response->assertStatus(200);
        $response->assertSee('Sharma Care Clinic');

        $responseB = $this->get('/book/arogya');
        $responseB->assertStatus(200);
        $responseB->assertSee('Arogya Super Specialty Clinic');
    }

    public function test_12_case_d_get_b_valid_slug_redirects_canonically(): void
    {
        $response = $this->get('/b/sharmacare');
        $response->assertStatus(302);
        $response->assertRedirect('/book/sharmacare');

        $responseDemo = $this->get('/b/democlinic');
        $responseDemo->assertStatus(302);
        $responseDemo->assertRedirect('/book/democlinic');
    }

    public function test_13_case_e_get_book_invalid_slug_returns_404(): void
    {
        $response = $this->get('/book/nonexistentclinic99');
        $response->assertStatus(404);

        $responseShort = $this->get('/b/nonexistentclinic99');
        $responseShort->assertStatus(404);
    }

    public function test_14_case_f_g_h_old_internal_identifiers_return_404(): void
    {
        // /book/NX0001 must return 404
        $this->get('/book/NX0001')->assertStatus(404);
        $this->get('/book/NX0002')->assertStatus(404);

        // Numeric ID routes /book/1, /book/2 must return 404
        $this->get('/book/1')->assertStatus(404);
        $this->get('/book/2')->assertStatus(404);

        // Short routes /b/NX0001 must return 404
        $this->get('/b/NX0001')->assertStatus(404);
    }

    // =========================================================================
    // SECTION 47 / 62: OPERATOR QUEUE ROUTE ACCEPTANCE CASES
    // =========================================================================

    public function test_15_case_b_get_operator_queue_generic_returns_404(): void
    {
        $response = $this->get('/operator/queue');
        $response->assertStatus(404);
    }

    public function test_16_case_i_get_clinic_nx0001_operator_queue_is_not_valid_public_route(): void
    {
        // Unauthenticated access to /clinic/NX0001/operator/queue redirects to login
        $response = $this->get('/clinic/NX0001/operator/queue');
        $response->assertStatus(302);
        $response->assertRedirect('/login');

        // Authenticated access with invalid slug NX0001 returns 404 (not a valid public_slug)
        $authResponse = $this->actingAs($this->operatorA)
            ->get('/clinic/NX0001/operator/queue');
        $authResponse->assertStatus(404);
    }

    public function test_17_case_j_operator_queue_unauthenticated_redirects_to_login(): void
    {
        $response = $this->get('/clinic/sharmacare/operator/queue');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_18_case_j_operator_queue_authenticated_same_clinic_returns_200(): void
    {
        $response = $this->actingAs($this->operatorA)
            ->get('/clinic/sharmacare/operator/queue');

        $response->assertStatus(200);
        $response->assertSee('Operator A');
        $response->assertSee('OPD Queue');
    }

    public function test_19_case_j_operator_queue_authenticated_cross_clinic_denied_403(): void
    {
        // Operator A (belongs to Sharma Care Clinic) attempts to access Clinic B's queue (Arogya)
        $response = $this->actingAs($this->operatorA)
            ->get('/clinic/arogya/operator/queue');

        $response->assertStatus(403);
    }

    // =========================================================================
    // SECTION 47: TENANT TAMPERING AND IDOR TESTS
    // =========================================================================

    public function test_20_case_k_tampering_query_parameters_rejected_403(): void
    {
        // Query parameter clinic_id for another tenant
        $response = $this->get('/book/sharmacare?clinic_id=' . $this->clinicB->id);
        $response->assertStatus(403);

        // Query parameter clinic_code for another tenant
        $responseCode = $this->get('/book/sharmacare?clinic_code=NX0002');
        $responseCode->assertStatus(403);

        // Query parameter public_slug mismatch
        $responseSlug = $this->get('/book/sharmacare?public_slug=arogya');
        $responseSlug->assertStatus(403);
    }

    public function test_21_case_m_tampering_headers_rejected_403(): void
    {
        // Spoofed X-Clinic-ID
        $responseId = $this->withHeaders(['X-Clinic-ID' => $this->clinicB->id])
            ->get('/book/sharmacare');
        $responseId->assertStatus(403);

        // Spoofed X-Tenant-ID
        $responseTenant = $this->withHeaders(['X-Tenant-ID' => $this->clinicB->id])
            ->get('/book/sharmacare');
        $responseTenant->assertStatus(403);

        // Spoofed X-Clinic-Code
        $responseCode = $this->withHeaders(['X-Clinic-Code' => 'NX0002'])
            ->get('/book/sharmacare');
        $responseCode->assertStatus(403);
    }

    public function test_22_case_l_idor_cross_clinic_doctor_rejected_in_public_booking(): void
    {
        // Try booking an appointment on Clinic A (/book/sharmacare) but supplying Doctor B (from Clinic B)
        Livewire::test(PatientBooking::class, ['public_slug' => 'sharmacare'])
            ->set('name', 'Sunil Verma')
            ->set('age', '35')
            ->set('sex', 'MALE')
            ->set('mobile', '9876543210')
            ->set('address', 'Lanka')
            ->set('city', 'Varanasi')
            ->set('selected_doctor_id', $this->doctorB->id) // Doctor B belongs to Clinic B!
            ->call('bookToken')
            ->assertHasErrors(['selected_doctor_id']);

        // Confirm appointment was NOT booked
        $this->assertEquals(0, Appointment::where('clinic_id', $this->clinicA->id)->count());
    }

    public function test_23_clinic_model_public_url_helpers(): void
    {
        $this->assertStringEndsWith('/book/sharmacare', $this->clinicA->publicBookingUrl());
        $this->assertStringEndsWith('/b/sharmacare', $this->clinicA->shortBookingUrl());
        $this->assertStringEndsWith('/clinic/sharmacare/operator/queue', $this->clinicA->operatorQueueUrl());

        $this->assertStringEndsWith('/book/arogya', $this->clinicB->publicBookingUrl());
        $this->assertStringEndsWith('/b/arogya', $this->clinicB->shortBookingUrl());
        $this->assertStringEndsWith('/clinic/arogya/operator/queue', $this->clinicB->operatorQueueUrl());
    }

    public function test_24_no_global_public_clinic_directory_exists(): void
    {
        $response = $this->get('/clinics');
        $response->assertStatus(404);

        $responseDir = $this->get('/directory');
        $responseDir->assertStatus(404);
    }
}
