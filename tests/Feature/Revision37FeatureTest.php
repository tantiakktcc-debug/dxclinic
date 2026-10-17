<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicDoctorSeatAddon;
use App\Models\DeveloperUser;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\License;
use App\Models\Patient;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\DemoService;
use App\Services\LicenseService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class Revision37FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected DeveloperUser $superadmin;
    protected SubscriptionPlan $yearlyPlan;
    protected Clinic $clinic1;
    protected Clinic $clinic2;
    protected License $license1;
    protected License $license2;
    protected Doctor $doctor1;
    protected Doctor $doctor2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Superadmin User
        $this->superadmin = DeveloperUser::create([
            'name' => 'Superadmin Developer',
            'email' => 'superadmin@dxclinic.local',
            'password' => Hash::make('SuperSecret123!'),
            'role' => 'DEVELOPER',
            'is_active' => true,
        ]);

        // 2. Subscription Plan
        $this->yearlyPlan = SubscriptionPlan::firstOrCreate(['code' => 'YEARLY'], [
            'name' => 'Yearly Professional',
            'duration_months' => 12,
            'price' => 14999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 1,
        ]);

        // 3. Clinic 1 (NX0001)
        $this->clinic1 = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'citycare',
            'name' => 'City Care Clinic',
            'phone' => '9876543210',
            'email' => 'contact@citycare.com',
            'address' => '123 Main Road',
            'city' => 'Varanasi',
            'is_active' => true,
            'activation_status' => 'ACTIVATED',
            'onboarding_status' => 'COMPLETED',
            'is_onboarded' => true,
        ]);

        License::where('clinic_id', $this->clinic1->id)->delete();

        $key1 = 'NXCL-1111-2222-3333-4444';
        $this->license1 = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $key1),
            'license_key_encrypted' => Crypt::encryptString($key1),
            'license_key_prefix' => 'NXCL',
            'start_date' => Carbon::today()->subMonths(2)->toDateString(),
            'end_date' => Carbon::today()->addMonths(10)->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seats' => 0,
            'additional_doctor_fee_per_year' => 2999.00,
            'additional_doctor_amount' => 0.00,
            'status' => 'ACTIVE',
            'activated_at' => now()->subMonths(2),
        ]);

        $this->doctor1 = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Anand Kumar',
            'qualification' => 'MBBS, MD',
            'specialization' => 'Cardiology',
            'registration_number' => 'REG-NX01-01',
            'contact_number' => '9876543211',
            'is_active' => true,
        ]);

        FeeMaster::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor1->id,
            'code' => 'CONSULTATION_PHYSICAL',
            'name' => 'Physical Consultation',
            'amount' => 500.00,
            'is_active' => true,
        ]);

        // 4. Clinic 2 (NX0002)
        $this->clinic2 = Clinic::create([
            'clinic_code' => 'NX0002',
            'public_slug' => 'apexhealthcare',
            'name' => 'Apex Healthcare',
            'phone' => '9876543220',
            'email' => 'contact@apexhealth.com',
            'address' => '456 Civil Lines',
            'city' => 'Lucknow',
            'is_active' => true,
            'activation_status' => 'ACTIVATED',
            'onboarding_status' => 'COMPLETED',
            'is_onboarded' => true,
        ]);

        License::where('clinic_id', $this->clinic2->id)->delete();

        $key2 = 'NXCL-5555-6666-7777-8888';
        $this->license2 = License::create([
            'clinic_id' => $this->clinic2->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $key2),
            'license_key_encrypted' => Crypt::encryptString($key2),
            'license_key_prefix' => 'NXCL',
            'start_date' => Carbon::today()->subMonths(1)->toDateString(),
            'end_date' => Carbon::today()->addMonths(11)->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seats' => 0,
            'additional_doctor_fee_per_year' => 2999.00,
            'additional_doctor_amount' => 0.00,
            'status' => 'ACTIVE',
            'activated_at' => now()->subMonths(1),
        ]);

        $this->doctor2 = Doctor::create([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Dr. Sunita Patel',
            'qualification' => 'MBBS, MS',
            'specialization' => 'Orthopedics',
            'registration_number' => 'REG-NX02-01',
            'contact_number' => '9876543221',
            'is_active' => true,
        ]);

        FeeMaster::create([
            'clinic_id' => $this->clinic2->id,
            'doctor_id' => $this->doctor2->id,
            'code' => 'CONSULTATION_PHYSICAL',
            'name' => 'Physical Consultation',
            'amount' => 750.00,
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // SECTION 1: MID-TERM DOCTOR ADD-ON LICENSING & CAPACITY ENGINE
    // =========================================================================

    public function test_base_clinic_has_one_included_doctor_capacity(): void
    {
        $this->assertEquals(1, $this->clinic1->totalDoctorCapacity());
        $this->assertEquals(1, $this->clinic1->activeDoctorCount());
        $this->assertEquals(0, $this->clinic1->availableDoctorSeats());
    }

    public function test_mid_term_doctor_purchase_creates_yearly_addon_and_preserves_license_key(): void
    {
        $originalHash = $this->license1->license_key_hash;
        $originalStartDate = $this->license1->start_date->toDateString();
        $originalEndDate = $this->license1->end_date->toDateString();

        $result = LicenseService::addDoctorSeats(
            clinicId: $this->clinic1->id,
            seatsCount: 1,
            paymentMethod: 'UPI',
            paymentReference: 'UPI/REF123456',
            paymentDate: '2026-09-28',
            performedById: $this->superadmin->id,
            notes: 'Added 2nd doctor Dr. Verma'
        );

        $this->assertEquals(1, $result['seats_added']);
        $this->assertEquals(2999.00, $result['amount_charged']);
        $this->assertEquals('2026-09-28', $result['valid_from']);
        $this->assertEquals('2027-09-27', $result['valid_until']); // Exactly 1 year minus 1 day
        $this->assertEquals(2, $result['total_capacity']);

        // Assert permanent License Key is UNCHANGED
        $this->license1->refresh();
        $this->assertEquals($originalHash, $this->license1->license_key_hash);
        $this->assertEquals($originalStartDate, $this->license1->start_date->toDateString());
        $this->assertEquals($originalEndDate, $this->license1->end_date->toDateString());

        // Assert NO new license record was created
        $this->assertEquals(1, License::where('clinic_id', $this->clinic1->id)->count());

        // Assert NO activation code generated
        $this->assertNull($this->license1->activation_code_hash);

        // Assert durable entitlement in clinic_doctor_seat_addons
        $addon = ClinicDoctorSeatAddon::where('clinic_id', $this->clinic1->id)->first();
        $this->assertNotNull($addon);
        $this->assertEquals(1, $addon->seats_count);
        $this->assertEquals(2999.00, (float) $addon->price);
        $this->assertEquals('UPI', $addon->payment_method);
        $this->assertEquals('UPI/REF123456', $addon->payment_reference);
        $this->assertEquals('ACTIVE', $addon->status);
        $this->assertTrue($addon->isActive());
    }

    public function test_adding_second_doctor_increases_capacity_to_three(): void
    {
        LicenseService::addDoctorSeats(
            clinicId: $this->clinic1->id,
            seatsCount: 1,
            paymentMethod: 'CASH',
            performedById: $this->superadmin->id
        );

        LicenseService::addDoctorSeats(
            clinicId: $this->clinic1->id,
            seatsCount: 1,
            paymentMethod: 'BANK_TRANSFER',
            performedById: $this->superadmin->id
        );

        $this->clinic1->refresh();
        $this->assertEquals(3, $this->clinic1->totalDoctorCapacity());
        $this->assertEquals(2, $this->clinic1->doctorSeatAddons()->count());
        $this->assertEquals(2, $this->clinic1->activeDoctorSeatAddons()->count());
    }

    public function test_duplicate_purchase_with_idempotency_key_prevents_double_billing(): void
    {
        $idempotencyKey = 'IDEM-SEAT-001';

        $firstResult = LicenseService::addDoctorSeats(
            clinicId: $this->clinic1->id,
            seatsCount: 1,
            paymentMethod: 'UPI',
            performedById: $this->superadmin->id,
            idempotencyKey: $idempotencyKey
        );

        $secondResult = LicenseService::addDoctorSeats(
            clinicId: $this->clinic1->id,
            seatsCount: 1,
            paymentMethod: 'UPI',
            performedById: $this->superadmin->id,
            idempotencyKey: $idempotencyKey
        );

        $this->assertEquals($firstResult['addon']->id, $secondResult['addon']->id);
        $this->assertEquals(1, ClinicDoctorSeatAddon::where('clinic_id', $this->clinic1->id)->count());
        $this->assertEquals(2, $this->clinic1->refresh()->totalDoctorCapacity());
    }

    public function test_expired_doctor_addon_no_longer_counts_toward_capacity(): void
    {
        // Create an expired addon
        ClinicDoctorSeatAddon::create([
            'clinic_id' => $this->clinic1->id,
            'license_id' => $this->license1->id,
            'seats_count' => 1,
            'purchase_date' => Carbon::today()->subYears(2)->toDateString(),
            'valid_from' => Carbon::today()->subYears(2)->toDateString(),
            'valid_until' => Carbon::today()->subYears(1)->subDay()->toDateString(),
            'price' => 2999.00,
            'currency' => 'INR',
            'payment_method' => 'CASH',
            'status' => 'ACTIVE',
        ]);

        $this->clinic1->refresh();
        // Since the addon is expired, total capacity should still be 1 (base only)
        $this->assertEquals(1, $this->clinic1->totalDoctorCapacity());
    }

    public function test_parent_expired_license_blocks_entire_clinic_regardless_of_addon(): void
    {
        LicenseService::addDoctorSeats(
            clinicId: $this->clinic1->id,
            seatsCount: 1,
            performedById: $this->superadmin->id
        );

        // Expire parent license
        $this->license1->update([
            'status' => 'EXPIRED',
            'end_date' => Carbon::today()->subDay()->toDateString(),
        ]);

        $this->assertFalse($this->license1->isValid());
        $this->assertNull($this->clinic1->fresh()->activeLicense);
    }

    public function test_superadmin_controller_add_doctor_seats_endpoint_works(): void
    {
        $response = $this->actingAs($this->superadmin, 'developer')
            ->post("/superadmin/clinics/{$this->clinic1->id}/add-doctor-seats", [
                'seats_count' => 2,
                'payment_method' => 'UPI',
                'payment_reference' => 'UPI/TXN998877',
                'payment_date' => '2026-09-28',
                'notes' => 'Superadmin bulk doctor addition',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->clinic1->refresh();
        $this->assertEquals(3, $this->clinic1->totalDoctorCapacity());
    }

    public function test_renewing_parent_license_reconciles_active_addons_without_double_billing(): void
    {
        // Purchase 1 mid-term add-on
        LicenseService::addDoctorSeats(
            clinicId: $this->clinic1->id,
            seatsCount: 1,
            performedById: $this->superadmin->id
        );

        $this->assertEquals(2, $this->clinic1->refresh()->totalDoctorCapacity());

        // Renew base license via LicenseService::renewLicense
        $renewedLicense = LicenseService::renewLicense(
            licenseId: $this->license1->id,
            durationMonths: 12,
            performedById: $this->superadmin->id,
            notes: 'Base annual renewal'
        );

        $this->assertNotNull($renewedLicense);
        $this->assertEquals('ACTIVE', $renewedLicense->status);

        // Capacity should still be 2 (base 1 + existing mid-term 1), and no duplicate addon was created
        $this->clinic1->refresh();
        $this->assertEquals(2, $this->clinic1->totalDoctorCapacity());
        $this->assertEquals(1, ClinicDoctorSeatAddon::where('clinic_id', $this->clinic1->id)->count());
    }

    // =========================================================================
    // SECTION 2: PERMANENT DEMO TENANT & DEMO CONTROLS
    // =========================================================================

    public function test_demo_tenant_seeding_and_single_doctor_hardcap(): void
    {
        $demoClinic = DemoService::seedDemoClinic();

        $this->assertNotNull($demoClinic);
        $this->assertEquals('democlinic', $demoClinic->clinic_code);
        $this->assertEquals('Demo Clinic', $demoClinic->name);
        $this->assertTrue($demoClinic->is_demo);
        $this->assertEquals('ACTIVE', $demoClinic->demo_status);
        $this->assertTrue($demoClinic->isDemo());
        $this->assertTrue($demoClinic->isDemoActive());
        $this->assertFalse($demoClinic->isDemoPaused());

        // Hardcap: strictly 2 doctor seats (Revision 38 Upgrade)
        $this->assertEquals(2, $demoClinic->totalDoctorCapacity());

        // Verify users exist
        $this->assertNotNull(User::where('clinic_id', $demoClinic->id)->where('login_id', 'demoadmin')->first());
        $this->assertNotNull(User::where('clinic_id', $demoClinic->id)->where('login_id', 'demooperator')->first());
        $this->assertNotNull(User::where('clinic_id', $demoClinic->id)->where('login_id', 'demodoctor')->first());

        // Verify transactional data was seeded
        $this->assertGreaterThan(0, Patient::where('clinic_id', $demoClinic->id)->count());
        $this->assertGreaterThan(0, Appointment::where('clinic_id', $demoClinic->id)->count());
    }

    public function test_demo_tenant_cannot_purchase_additional_doctor_seats(): void
    {
        $demoClinic = DemoService::seedDemoClinic();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Demo Clinic doctor capacity is strictly limited to 2 doctors');

        LicenseService::addDoctorSeats(
            clinicId: $demoClinic->id,
            seatsCount: 1,
            performedById: $this->superadmin->id
        );
    }

    public function test_demo_pause_and_resume_controls(): void
    {
        DemoService::seedDemoClinic();

        // 1. Pause Demo
        DemoService::pauseDemo();
        $this->assertTrue(DemoService::isDemoPaused());

        $demoClinic = Clinic::where('clinic_code', 'democlinic')->first();
        $this->assertEquals('PAUSED', $demoClinic->demo_status);
        $this->assertTrue($demoClinic->isDemoPaused());

        // 2. Resume Demo
        DemoService::resumeDemo();
        $this->assertFalse(DemoService::isDemoPaused());

        $demoClinic->refresh();
        $this->assertEquals('ACTIVE', $demoClinic->demo_status);
        $this->assertTrue($demoClinic->isDemoActive());
    }

    public function test_demo_pause_blocks_staff_login(): void
    {
        $demoClinic = DemoService::seedDemoClinic();
        DemoService::pauseDemo();

        $response = $this->post('/login', [
            'clinic_code' => 'democlinic',
            'login_id' => 'demoadmin',
            'password' => 'DemoAdmin123!',
        ]);

        $response->assertSessionHasErrors('login_id');
        $this->assertGuest('web');
    }

    public function test_demo_pause_blocks_public_pwa_with_503(): void
    {
        DemoService::seedDemoClinic();
        DemoService::pauseDemo();

        $response = $this->get('/book/democlinic');
        $response->assertStatus(503);
        $response->assertSee('The DxClinic demonstration environment is temporarily unavailable');

        // Short route also returns 503 after redirect
        $shortResponse = $this->get('/b/democlinic');
        $shortResponse->assertRedirect('/book/democlinic');
    }

    public function test_demo_resume_restores_public_pwa_to_200(): void
    {
        DemoService::seedDemoClinic();
        DemoService::pauseDemo();
        DemoService::resumeDemo();

        $response = $this->get('/book/democlinic');
        $response->assertStatus(200);
        $response->assertSee('Demo Clinic');
    }

    public function test_demo_reset_data_is_atomic_and_leaves_real_clinics_untouched(): void
    {
        $demoClinic = DemoService::seedDemoClinic();

        // Create a real patient in Clinic 1
        $realPatient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'REAL-001',
            'name' => 'Real Customer Patient',
            'age' => 45,
            'sex' => 'FEMALE',
            'mobile' => '9998887776',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
        ]);

        // Reset demo data
        $resetResult = DemoService::resetDemoData();
        $this->assertTrue($resetResult['success']);

        // Verify Real Patient still exists
        $this->assertDatabaseHas('patients', [
            'id' => $realPatient->id,
            'clinic_id' => $this->clinic1->id,
            'name' => 'Real Customer Patient',
        ]);

        // Verify Demo dataset exists and is clean
        $this->assertEquals(8, Patient::where('clinic_id', $demoClinic->id)->count());
        $this->assertGreaterThan(0, Appointment::where('clinic_id', $demoClinic->id)->count());
    }

    public function test_superadmin_demo_management_endpoints(): void
    {
        DemoService::seedDemoClinic();

        // Pause endpoint
        $pauseRes = $this->actingAs($this->superadmin, 'developer')
            ->post('/superadmin/demo/pause');
        $pauseRes->assertRedirect();
        $pauseRes->assertSessionHas('success');
        $this->assertTrue(DemoService::isDemoPaused());

        // Resume endpoint
        $resumeRes = $this->actingAs($this->superadmin, 'developer')
            ->post('/superadmin/demo/resume');
        $resumeRes->assertRedirect();
        $resumeRes->assertSessionHas('success');
        $this->assertFalse(DemoService::isDemoPaused());

        // Reset endpoint
        $resetRes = $this->actingAs($this->superadmin, 'developer')
            ->post('/superadmin/demo/reset');
        $resetRes->assertRedirect();
        $resetRes->assertSessionHas('success');
    }

    // =========================================================================
    // SECTION 3: STRICT PUBLIC PWA TENANT ISOLATION
    // =========================================================================

    public function test_canonical_and_short_booking_routes(): void
    {
        // Canonical citycare (Revision 47: public_slug)
        $res1 = $this->get('/book/citycare');
        $res1->assertStatus(200);
        $res1->assertSee('City Care Clinic');
        $res1->assertSee('Dr. Anand Kumar');

        // Canonical apexhealthcare
        $res2 = $this->get('/book/apexhealthcare');
        $res2->assertStatus(200);
        $res2->assertSee('Apex Healthcare');
        $res2->assertSee('Dr. Sunita Patel');

        // Short alias /b/citycare
        $shortRes1 = $this->get('/b/citycare');
        $shortRes1->assertRedirect('/book/citycare');

        // Short alias /b/apexhealthcare
        $shortRes2 = $this->get('/b/apexhealthcare');
        $shortRes2->assertRedirect('/book/apexhealthcare');

        // Non-existent clinic returns 404
        $notFoundRes = $this->get('/book/NON_EXISTENT_CLINIC');
        $notFoundRes->assertStatus(404);
    }

    public function test_public_pwa_does_not_expose_cross_tenant_doctors_or_fees(): void
    {
        // citycare URL (Revision 47: public_slug)
        $res1 = $this->get('/book/citycare');
        $res1->assertStatus(200);
        $res1->assertSee('Dr. Anand Kumar');
        $res1->assertDontSee('Dr. Sunita Patel');
        $res1->assertDontSee('Apex Healthcare');

        // apexhealthcare URL
        $res2 = $this->get('/book/apexhealthcare');
        $res2->assertStatus(200);
        $res2->assertSee('Dr. Sunita Patel');
        $res2->assertDontSee('Dr. Anand Kumar');
        $res2->assertDontSee('City Care Clinic');
    }

    public function test_cross_tenant_query_parameter_tamper_is_rejected_with_403(): void
    {
        // Query param clinic_id mismatch (Revision 47: public_slug)
        $res1 = $this->get("/book/citycare?clinic_id={$this->clinic2->id}");
        $res1->assertStatus(403);

        // Query param clinic_code mismatch
        $res2 = $this->get('/book/citycare?clinic_code=NX0002');
        $res2->assertStatus(403);
    }

    public function test_cross_tenant_header_tamper_is_rejected_with_403(): void
    {
        // Header X-Clinic-ID mismatch (Revision 47: public_slug)
        $res1 = $this->withHeaders(['X-Clinic-ID' => (string) $this->clinic2->id])
            ->get('/book/citycare');
        $res1->assertStatus(403);

        // Header X-Clinic-Code mismatch
        $res2 = $this->withHeaders(['X-Clinic-Code' => 'NX0002'])
            ->get('/book/citycare');
        $res2->assertStatus(403);
    }

    public function test_cross_tenant_appointment_booking_with_foreign_patient_is_blocked(): void
    {
        // Patient belongs to Clinic 2
        $patient2 = Patient::create([
            'clinic_id' => $this->clinic2->id,
            'patient_id' => 'P-NX02-001',
            'name' => 'Foreign Patient',
            'age' => 30,
            'sex' => 'MALE',
            'mobile' => '9988776655',
            'address' => 'Hazratganj',
            'city' => 'Lucknow',
        ]);

        $appointmentService = app(AppointmentService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Patient does not belong to this clinic');

        // Attempt to book appointment in Clinic 1 with patient from Clinic 2
        $appointmentService->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient2->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);
    }
}
