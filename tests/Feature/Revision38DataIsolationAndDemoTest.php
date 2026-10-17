<?php

namespace Tests\Feature;

use App\Livewire\Pwa\PatientBooking;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\License;
use App\Models\Patient;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\DemoService;
use App\Services\DoctorNamePresenter;
use App\Services\LicenseService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class Revision38DataIsolationAndDemoTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic1;
    protected Clinic $clinic2;
    protected Clinic $demoClinic;
    protected Doctor $doc1;
    protected Doctor $doc2;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure 5-Year Plan exists
        SubscriptionPlan::firstOrCreate(['code' => 'FIVE_YEAR'], [
            'name' => '5-Year Plan',
            'duration_months' => 60,
            'price' => 49999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 6,
        ]);

        // 1. Seed Production Clinic NX0001
        $this->clinic1 = Clinic::firstOrCreate(
            ['clinic_code' => 'NX0001'],
            [
                'name' => 'Sharma Care Clinic',
                'public_slug' => 'sharmacare',
                'is_demo' => false,
                'address' => '12, Medical Enclave, Lanka',
                'pincode' => '221005',
                'city' => 'Varanasi',
                'phone' => '9876543210',
                'activation_status' => 'ACTIVATED',
                'onboarding_status' => 'COMPLETED',
                'is_onboarded' => true,
            ]
        );

        $this->doc1 = Doctor::firstOrCreate(
            ['clinic_id' => $this->clinic1->id, 'registration_number' => 'REG-NX01'],
            [
                'name' => 'Rajesh Sharma',
                'qualification' => 'MBBS, MD',
                'specialization' => 'Cardiology',
                'is_active' => true,
            ]
        );

        // Production Fees for NX0001
        FeeMaster::updateOrCreate(
            ['clinic_id' => $this->clinic1->id, 'code' => 'CONSULTATION_PHYSICAL'],
            ['name' => 'Clinic Consultancy', 'amount' => 600.00, 'is_active' => true]
        );
        FeeMaster::updateOrCreate(
            ['clinic_id' => $this->clinic1->id, 'code' => 'CONSULTATION_ONLINE'],
            ['name' => 'Online Consultancy', 'amount' => 700.00, 'is_active' => true]
        );

        // 2. Seed Production Clinic NX0002
        $this->clinic2 = Clinic::firstOrCreate(
            ['clinic_code' => 'NX0002'],
            [
                'name' => 'Tripathi Health Care',
                'public_slug' => 'tripathicare',
                'is_demo' => false,
                'address' => '45, Hazratganj',
                'pincode' => '226001',
                'city' => 'Lucknow',
                'phone' => '9876543222',
                'activation_status' => 'ACTIVATED',
                'onboarding_status' => 'COMPLETED',
                'is_onboarded' => true,
            ]
        );

        $this->doc2 = Doctor::firstOrCreate(
            ['clinic_id' => $this->clinic2->id, 'registration_number' => 'REG-NX02'],
            [
                'name' => 'Rajesh Tripathi',
                'qualification' => 'MBBS, MS',
                'specialization' => 'Orthopedics',
                'is_active' => true,
            ]
        );

        // Production Fees for NX0002
        FeeMaster::updateOrCreate(
            ['clinic_id' => $this->clinic2->id, 'code' => 'CONSULTATION_PHYSICAL'],
            ['name' => 'Clinic Consultancy', 'amount' => 800.00, 'is_active' => true]
        );

        // 3. Seed Demo Clinic
        $this->demoClinic = DemoService::seedDemoClinic();
    }

    /**
     * Test 1: Demo has exactly 2 doctors.
     */
    public function test_1_demo_has_exactly_two_doctors(): void
    {
        $this->assertEquals(2, $this->demoClinic->doctors()->count());
        $this->assertEquals(2, $this->demoClinic->doctors()->where('is_active', true)->count());
    }

    /**
     * Test 2 & 3: Demo has demodoctor01 and demodoctor02.
     */
    public function test_2_and_3_demo_has_demodoctor01_and_demodoctor02(): void
    {
        $user1 = User::where('clinic_id', $this->demoClinic->id)->where('login_id', 'demodoctor01')->first();
        $user2 = User::where('clinic_id', $this->demoClinic->id)->where('login_id', 'demodoctor02')->first();

        $this->assertNotNull($user1);
        $this->assertNotNull($user2);
    }

    /**
     * Test 4: Both users have DOCTOR role.
     */
    public function test_4_both_users_have_doctor_role(): void
    {
        $user1 = User::where('clinic_id', $this->demoClinic->id)->where('login_id', 'demodoctor01')->first();
        $user2 = User::where('clinic_id', $this->demoClinic->id)->where('login_id', 'demodoctor02')->first();

        $this->assertEquals('DOCTOR', $user1->role);
        $this->assertEquals('DOCTOR', $user2->role);
    }

    /**
     * Test 5: Both users belong only to Demo Clinic.
     */
    public function test_5_both_users_belong_only_to_demo_clinic(): void
    {
        $user1 = User::where('login_id', 'demodoctor01')->first();
        $user2 = User::where('login_id', 'demodoctor02')->first();

        $this->assertEquals($this->demoClinic->id, $user1->clinic_id);
        $this->assertEquals($this->demoClinic->id, $user2->clinic_id);
        $this->assertNotEquals($this->clinic1->id, $user1->clinic_id);
        $this->assertNotEquals($this->clinic2->id, $user2->clinic_id);
    }

    /**
     * Test 6 & 7: Demo doctor #1 and #2 PWA display correct.
     */
    public function test_6_and_7_demo_doctors_pwa_display_correct(): void
    {
        $doc1 = Doctor::where('clinic_id', $this->demoClinic->id)->where('registration_number', 'REG-DEMO01')->first();
        $doc2 = Doctor::where('clinic_id', $this->demoClinic->id)->where('registration_number', 'REG-DEMO02')->first();

        $this->assertEquals('Amit Verma', $doc1->name);
        $this->assertEquals('Dr. Amit Verma', $doc1->formatted_name);
        $this->assertEquals('Neha Kapoor', $doc2->name);
        $this->assertEquals('Dr. Neha Kapoor', $doc2->formatted_name);

        Livewire::test(PatientBooking::class, ['clinic_code' => 'democlinic'])
            ->assertSee('Dr. Amit Verma')
            ->assertSee('Dr. Neha Kapoor');
    }

    /**
     * Test 8 & 9: No Demo PWA doctor comes from NX0001 or NX0002.
     */
    public function test_8_and_9_no_demo_pwa_doctor_comes_from_nx0001_or_nx0002(): void
    {
        Livewire::test(PatientBooking::class, ['clinic_code' => 'democlinic'])
            ->assertDontSee('Rajesh Sharma')
            ->assertDontSee('Dr. Rajesh Sharma')
            ->assertDontSee('Rajesh Tripathi')
            ->assertDontSee('Dr. Rajesh Tripathi');
    }

    /**
     * Test 10: Doctor display never produces "Dr. Dr.".
     */
    public function test_10_doctor_display_never_produces_dr_dr(): void
    {
        $this->assertEquals('Dr. Rajesh Sharma', DoctorNamePresenter::format('Dr. Rajesh Sharma'));
        $this->assertEquals('Dr. Rajesh Sharma', DoctorNamePresenter::format('Dr. Dr. Rajesh Sharma'));
        $this->assertEquals('Dr. Rajesh Sharma', DoctorNamePresenter::format('Dr Dr Rajesh Sharma'));
        $this->assertEquals('Dr. Rajesh Sharma', DoctorNamePresenter::format('Doctor Dr. Rajesh Sharma'));
        $this->assertEquals('Dr. Rajesh Sharma', DoctorNamePresenter::format('Rajesh Sharma'));

        // Blade view rendering check
        Livewire::test(PatientBooking::class, ['clinic_code' => 'democlinic'])
            ->assertDontSee('Dr. Dr.')
            ->assertDontSee('Dr Dr');
    }

    /**
     * Test 11: Existing doctor names normalize correctly without losing components.
     */
    public function test_11_existing_doctor_names_normalize_correctly(): void
    {
        $doc = new Doctor(['name' => 'Dr. Sushil Kumar Das']);
        $this->assertEquals('Sushil Kumar Das', $doc->name);
        $this->assertEquals('Dr. Sushil Kumar Das', $doc->formatted_name);

        $doc2 = new Doctor(['name' => 'Doctor Anil Verma']);
        $this->assertEquals('Anil Verma', $doc2->name);
        $this->assertEquals('Dr. Anil Verma', $doc2->formatted_name);
    }

    /**
     * Test 12 & 13: "Clinic Consultancy" appears customer-facing, "Clinic Consultation" is absent.
     */
    public function test_12_and_13_clinic_consultancy_terminology(): void
    {
        // 1. Patient PWA
        Livewire::test(PatientBooking::class, ['clinic_code' => 'democlinic'])
            ->assertSee('Clinic Consultancy')
            ->assertDontSee('Clinic Consultation');

        // 2. HTTP PWA page
        $response = $this->get('/book/democlinic');
        $response->assertStatus(200);
        $response->assertSee('Clinic Consultancy');
        $response->assertDontSee('Clinic Consultation');
    }

    /**
     * Test 14, 15, 16: Consultation fees remain stable per clinic.
     */
    public function test_14_to_16_consultation_fees_stability(): void
    {
        $demoPhysical = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->demoClinic->id);
        $demoOnline = FeeMaster::getFeeForMode('ONLINE', $this->demoClinic->id);
        $demoVisiting = FeeMaster::getFeeForMode('VISITING_CONSULTANCY', $this->demoClinic->id);

        $nx1Physical = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->clinic1->id);
        $nx1Online = FeeMaster::getFeeForMode('ONLINE', $this->clinic1->id);

        $nx2Physical = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->clinic2->id);

        $this->assertEquals(500.00, $demoPhysical);
        $this->assertEquals(400.00, $demoOnline);
        $this->assertEquals(750.00, $demoVisiting);

        $this->assertEquals(600.00, $nx1Physical);
        $this->assertEquals(700.00, $nx1Online);

        $this->assertEquals(800.00, $nx2Physical);
    }

    /**
     * Test 17 & 18: Demo fee cannot resolve NX0001 value and vice versa.
     */
    public function test_17_and_18_cross_tenant_fee_isolation(): void
    {
        // Looking up for Demo clinic should NEVER return NX0001's 600 or 700
        $this->assertNotEquals(600.00, FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->demoClinic->id));
        $this->assertNotEquals(700.00, FeeMaster::getFeeForMode('ONLINE', $this->demoClinic->id));

        // Looking up for NX0001 should NEVER return Demo's 500 or 400
        $this->assertNotEquals(500.00, FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->clinic1->id));
        $this->assertNotEquals(400.00, FeeMaster::getFeeForMode('ONLINE', $this->clinic1->id));

        // Even if NX0001 doctor ID is passed to Demo Clinic lookup, fee master must ignore it
        $feeWithForeignDoc = FeeMaster::getFeeForMode('CLINIC_PHYSICAL', $this->demoClinic->id, $this->doc1->id);
        $this->assertEquals(500.00, $feeWithForeignDoc);
    }

    /**
     * Test 19: Doctor-specific consultation availability is clinic-scoped.
     */
    public function test_19_doctor_specific_consultation_availability(): void
    {
        $doc1 = Doctor::where('clinic_id', $this->demoClinic->id)->where('registration_number', 'REG-DEMO01')->first();
        $doc2 = Doctor::where('clinic_id', $this->demoClinic->id)->where('registration_number', 'REG-DEMO02')->first();

        // Doctor 1 has Physical & Online
        $this->assertTrue(in_array('CLINIC_PHYSICAL', $doc1->enabledConsultationModes()));
        $this->assertTrue(in_array('ONLINE', $doc1->enabledConsultationModes()));
        $this->assertFalse(in_array('VISITING_CONSULTANCY', $doc1->enabledConsultationModes()));

        // Doctor 2 has Physical & Visiting
        $this->assertTrue(in_array('CLINIC_PHYSICAL', $doc2->enabledConsultationModes()));
        $this->assertFalse(in_array('ONLINE', $doc2->enabledConsultationModes()));
        $this->assertTrue(in_array('VISITING_CONSULTANCY', $doc2->enabledConsultationModes()));
    }

    /**
     * Test 20: Public PWA clinic switching cannot leak prior clinic data.
     */
    public function test_20_public_pwa_switching_cannot_leak_data(): void
    {
        // Visit Demo
        $demoComponent = Livewire::test(PatientBooking::class, ['clinic_code' => 'democlinic'])
            ->assertSee('Demo Clinic')
            ->assertSee('₹500')
            ->assertDontSee('Sharma Care Clinic')
            ->assertDontSee('₹600');

        // Visit NX0001
        $nx1Component = Livewire::test(PatientBooking::class, ['clinic_code' => 'NX0001'])
            ->assertSee('Sharma Care Clinic')
            ->assertSee('₹600')
            ->assertDontSee('Demo Clinic')
            ->assertDontSee('₹500');

        // Revisit Demo: Must strictly remain Demo Clinic
        $revisitDemo = Livewire::test(PatientBooking::class, ['clinic_code' => 'democlinic'])
            ->assertSee('Demo Clinic')
            ->assertSee('₹500')
            ->assertDontSee('Sharma Care Clinic')
            ->assertDontSee('₹600');
    }

    /**
     * Test 21, 22, 23: Query, Header, and POST tenant overrides return 403.
     */
    public function test_21_to_23_tenant_overrides_return_403(): void
    {
        // Query param override
        $resQuery = $this->get('/book/democlinic?clinic_id=' . $this->clinic1->id);
        $resQuery->assertStatus(403);

        // Header override
        $resHeader = $this->withHeader('X-Clinic-Code', 'NX0001')->get('/book/democlinic');
        $resHeader->assertStatus(403);

        // POST override
        $resPost = $this->post('/book/democlinic', [
            'clinic_id' => $this->clinic1->id,
            'clinic_code' => 'NX0001',
        ]);
        $resPost->assertStatus(403);
    }

    /**
     * Test 24: 5-Year plan duration is 60 months.
     */
    public function test_24_five_year_plan_duration_is_sixty_months(): void
    {
        $plan = SubscriptionPlan::where('code', 'FIVE_YEAR')->first();
        $this->assertNotNull($plan);
        $this->assertEquals(60, $plan->duration_months);
    }

    /**
     * Test 25, 26, 27: License date calculation and remaining days.
     */
    public function test_25_to_27_license_date_and_remaining_days_calculation(): void
    {
        $lic = $this->demoClinic->activeLicense;
        $this->assertNotNull($lic);

        // 5-Year Plan
        $this->assertEquals(60, $lic->plan->duration_months);

        $today = Carbon::today('Asia/Kolkata');
        $expectedEndDate = (clone $today)->addMonths(60);

        // Valid until should be 5 years ahead
        $this->assertEquals($expectedEndDate->toDateString(), $lic->end_date->toDateString());

        // Remaining days: calculated from actual valid_until
        $remainingDays = $lic->remainingDays();

        // 5 years has ~1,825–1,827 days depending on leap years
        $this->assertGreaterThanOrEqual(1820, $remainingDays);
        $this->assertLessThanOrEqual(1830, $remainingDays);

        // Remaining days must NOT be faked as 365 or 3653
        $this->assertNotEquals(365, $remainingDays);
        $this->assertNotEquals(3653, $remainingDays);
    }

    /**
     * Test 28, 29, 30: Demo reset recreates both doctors and leaves real clinics untouched.
     */
    public function test_28_to_30_demo_reset_atomic_isolation(): void
    {
        $nx1DocCountBefore = Doctor::where('clinic_id', $this->clinic1->id)->count();
        $nx2DocCountBefore = Doctor::where('clinic_id', $this->clinic2->id)->count();

        // Perform Demo Reset
        $result = DemoService::resetDemoData();
        $this->assertTrue($result['success']);

        // Demo has both doctors
        $this->assertEquals(2, $this->demoClinic->doctors()->count());
        $this->assertNotNull(User::where('clinic_id', $this->demoClinic->id)->where('login_id', 'demodoctor01')->first());
        $this->assertNotNull(User::where('clinic_id', $this->demoClinic->id)->where('login_id', 'demodoctor02')->first());

        // Real clinics untouched
        $this->assertEquals($nx1DocCountBefore, Doctor::where('clinic_id', $this->clinic1->id)->count());
        $this->assertEquals($nx2DocCountBefore, Doctor::where('clinic_id', $this->clinic2->id)->count());
    }

    /**
     * Test 31: Demo cap strictly rejects third doctor.
     */
    public function test_31_demo_cap_rejects_third_doctor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Doctor::create([
            'clinic_id' => $this->demoClinic->id,
            'name' => 'Third Doctor',
            'qualification' => 'MBBS',
            'registration_number' => 'REG-DEMO03',
            'is_active' => true,
        ]);
    }

    /**
     * Test 32 & 33: Demo pause blocks PWA, resume restores it.
     */
    public function test_32_and_33_demo_pause_and_resume(): void
    {
        // 1. Pause
        DemoService::pauseDemo();
        $this->assertTrue(DemoService::isDemoPaused());

        $resPaused = $this->get('/book/democlinic');
        $resPaused->assertStatus(503);

        // Production clinics unaffected
        $resProd = $this->get('/book/' . $this->clinic1->public_slug);
        $resProd->assertStatus(200);

        // 2. Resume
        DemoService::resumeDemo();
        $this->assertFalse(DemoService::isDemoPaused());

        $resResumed = $this->get('/book/democlinic');
        $resResumed->assertStatus(200);
    }
}
