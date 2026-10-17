<?php

namespace Tests\Feature;

use App\Livewire\Operator\OperatorBooking;
use App\Livewire\Pwa\PatientBooking;
use App\Livewire\Settings\BookingTypeManager;
use App\Livewire\Settings\FeeMasterManager;
use App\Models\Appointment;
use App\Models\BookingType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorBookingTypeAvailability;
use App\Models\FeeMaster;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\BookingTypeService;
use App\Services\ClinicClock;
use App\Services\ClinicScheduleService;
use App\Services\PatientIdentityService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class Revision45DoctorBookingTypeFeeFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic1;
    protected Clinic $clinic2;
    protected Doctor $doctorA;
    protected Doctor $doctorB;
    protected Doctor $doctorC;
    protected User $adminUser1;
    protected User $operatorUser1;
    protected User $adminUser2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        // Monday 2026-09-28 07:30 IST
        ClinicClock::setTestNow('2026-09-28 07:30:00');

        // Setup Clinic 1 (NX0001)
        $this->clinic1 = Clinic::create([
            'name' => 'Apex Healthcare',
            'clinic_code' => 'NX0001',
            'phone' => '9876543210',
            'address' => 'Sigra',
            'city' => 'Varanasi',
            'pincode' => '221001',
            'is_onboarded' => true,
        ]);

        // Setup Clinic 2 (NX0002)
        $this->clinic2 = Clinic::create([
            'name' => 'Metro Care Clinic',
            'clinic_code' => 'NX0002',
            'phone' => '9876543220',
            'address' => 'Lanka',
            'city' => 'Varanasi',
            'pincode' => '221005',
            'is_onboarded' => true,
        ]);

        TenantContext::setClinic($this->clinic1);

        // Doctor A in Clinic 1
        $this->doctorA = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'UP-MED-2026-4501',
            'specialization' => 'Cardiologist',
            'contact_number' => '9876543211',
            'is_active' => true,
            'schedule_config' => [
                'monday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'tuesday' => ['morning' => ['09:00', '13:00']],
            ],
        ]);

        // Doctor B in Clinic 1
        $this->doctorB = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Amit Verma',
            'qualification' => 'MBBS, MS',
            'registration_number' => 'UP-MED-2026-4502',
            'specialization' => 'Orthopedic',
            'contact_number' => '9876543212',
            'is_active' => true,
            'schedule_config' => [
                'monday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'tuesday' => ['morning' => ['09:00', '13:00']],
            ],
        ]);

        // Doctor C in Clinic 2
        TenantContext::setClinic($this->clinic2);
        $this->doctorC = Doctor::create([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Dr. Neha Kapoor',
            'qualification' => 'MBBS, DGO',
            'registration_number' => 'UP-MED-2026-4503',
            'specialization' => 'Gynecologist',
            'contact_number' => '9876543221',
            'is_active' => true,
            'schedule_config' => [
                'monday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'tuesday' => ['morning' => ['09:00', '13:00']],
            ],
        ]);

        TenantContext::setClinic($this->clinic1);

        $this->adminUser1 = User::create([
            'name' => 'Admin NX0001',
            'email' => 'admin@nx0001.com',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'clinic_id' => $this->clinic1->id,
            'is_active' => true,
        ]);

        $this->operatorUser1 = User::create([
            'name' => 'Operator NX0001',
            'email' => 'operator@nx0001.com',
            'password' => bcrypt('Password123!'),
            'role' => 'OPERATOR',
            'clinic_id' => $this->clinic1->id,
            'is_active' => true,
        ]);

        $this->adminUser2 = User::create([
            'name' => 'Admin NX0002',
            'email' => 'admin@nx0002.com',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'clinic_id' => $this->clinic2->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Booking type belongs to clinic.
     */
    public function test_01_booking_type_belongs_to_clinic(): void
    {
        $types1 = BookingType::where('clinic_id', $this->clinic1->id)->get();
        $this->assertNotEmpty($types1);
        foreach ($types1 as $bt) {
            $this->assertEquals($this->clinic1->id, $bt->clinic_id);
            $this->assertEquals($this->clinic1->id, $bt->clinic->id);
        }
    }

    /**
     * Test 2: Doctor belongs to clinic.
     */
    public function test_02_doctor_belongs_to_clinic(): void
    {
        $this->assertEquals($this->clinic1->id, $this->doctorA->clinic_id);
        $this->assertEquals($this->clinic1->id, $this->doctorB->clinic_id);
        $this->assertEquals($this->clinic2->id, $this->doctorC->clinic_id);
    }

    /**
     * Test 3: Doctor booking type mapping belongs to same clinic.
     */
    public function test_03_doctor_booking_type_mapping_belongs_to_same_clinic(): void
    {
        $availabilities = DoctorBookingTypeAvailability::with(['doctor', 'bookingType'])->get();
        foreach ($availabilities as $av) {
            $this->assertEquals($av->clinic_id, $av->doctor->clinic_id);
            $this->assertEquals($av->clinic_id, $av->bookingType->clinic_id);
        }
    }

    /**
     * Test 4: Duplicate clinic + doctor + booking type mapping is blocked.
     */
    public function test_04_duplicate_clinic_doctor_booking_type_mapping_blocked(): void
    {
        $bt = BookingType::where('clinic_id', $this->clinic1->id)->first();

        $this->expectException(\Illuminate\Database\QueryException::class);
        DoctorBookingTypeAvailability::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctorA->id,
            'booking_type_id' => $bt->id,
            'is_enabled' => true,
        ]);
    }

    /**
     * Test 5 & 6: Doctor A and Doctor B see independent mapped types.
     */
    public function test_05_and_06_doctors_see_independent_mapped_types(): void
    {
        $btService = app(BookingTypeService::class);

        $typesA = BookingType::where('clinic_id', $this->clinic1->id)->get()->keyBy('code');
        $clinicBt = $typesA->get('CLINIC_CONSULTANCY');
        $onlineBt = $typesA->get('ONLINE_CONSULTANCY');
        $emergencyBt = $typesA->get('EMERGENCY_CONSULTANCY');

        // Configure Rajesh (Doctor A): Clinic (600), Online (700), Emergency (1500)
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $clinicBt->id, 600.0);
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $onlineBt->id, 700.0);
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $emergencyBt->id, 1500.0);

        // Configure Amit (Doctor B): Clinic (800), Emergency (1800), Disable Online
        $btService->setDoctorFee($this->clinic1->id, $this->doctorB->id, $clinicBt->id, 800.0);
        $btService->setDoctorFee($this->clinic1->id, $this->doctorB->id, $emergencyBt->id, 1800.0);
        $btService->setDoctorAvailability($this->clinic1->id, $this->doctorB->id, $onlineBt->id, false);

        $enabledA = $btService->getEnabledForDoctor($this->doctorA->id, $this->clinic1->id);
        $enabledB = $btService->getEnabledForDoctor($this->doctorB->id, $this->clinic1->id);

        $codesA = $enabledA->pluck('code')->all();
        $codesB = $enabledB->pluck('code')->all();

        $this->assertContains('CLINIC_CONSULTANCY', $codesA);
        $this->assertContains('ONLINE_CONSULTANCY', $codesA);
        $this->assertContains('EMERGENCY_CONSULTANCY', $codesA);

        $this->assertContains('CLINIC_CONSULTANCY', $codesB);
        $this->assertNotContains('ONLINE_CONSULTANCY', $codesB);
        $this->assertContains('EMERGENCY_CONSULTANCY', $codesB);
    }

    /**
     * Test 7: Clinic A cannot see Clinic B types.
     */
    public function test_07_clinic_a_cannot_see_clinic_b_types(): void
    {
        $btService = app(BookingTypeService::class);
        $types1 = $btService->getActiveForClinic($this->clinic1->id);
        $types2 = $btService->getActiveForClinic($this->clinic2->id);

        foreach ($types1 as $t1) {
            $this->assertEquals($this->clinic1->id, $t1->clinic_id);
            $this->assertNotEquals($this->clinic2->id, $t1->clinic_id);
        }

        foreach ($types2 as $t2) {
            $this->assertEquals($this->clinic2->id, $t2->clinic_id);
            $this->assertNotEquals($this->clinic1->id, $t2->clinic_id);
        }
    }

    /**
     * Test 8 & 9: Disabled mapping disappears from booking; active mapping appears.
     */
    public function test_08_and_09_disabled_mapping_disappears_and_active_appears(): void
    {
        $btService = app(BookingTypeService::class);
        $onlineBt = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'ONLINE_CONSULTANCY')->first();

        // Initially enabled with fee
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $onlineBt->id, 750.0);
        $enabledInitial = $btService->getEnabledForDoctor($this->doctorA->id, $this->clinic1->id);
        $this->assertTrue($enabledInitial->contains('id', $onlineBt->id));

        // Disable mapping
        $btService->setDoctorAvailability($this->clinic1->id, $this->doctorA->id, $onlineBt->id, false);
        $enabledAfter = $btService->getEnabledForDoctor($this->doctorA->id, $this->clinic1->id);
        $this->assertFalse($enabledAfter->contains('id', $onlineBt->id));

        // Re-enable
        $btService->setDoctorAvailability($this->clinic1->id, $this->doctorA->id, $onlineBt->id, true);
        $enabledRe = $btService->getEnabledForDoctor($this->doctorA->id, $this->clinic1->id);
        $this->assertTrue($enabledRe->contains('id', $onlineBt->id));
    }

    /**
     * Test 10: Custom type remains tenant-specific.
     */
    public function test_10_custom_booking_type_remains_tenant_specific(): void
    {
        $btService = app(BookingTypeService::class);
        $customBt = $btService->createCustom($this->clinic1->id, [
            'name' => 'Home Healthcare Visit',
            'code' => 'HOME_HEALTHCARE_VISIT',
            'consultation_mode' => 'VISITING_CONSULTANCY',
            'is_public_bookable' => false,
        ]);

        $this->assertEquals($this->clinic1->id, $customBt->clinic_id);

        // Clinic 2 cannot see it
        $c2Types = $btService->getActiveForClinic($this->clinic2->id);
        $this->assertFalse($c2Types->contains('id', $customBt->id));
    }

    /**
     * Test 11: System types are not globally assumed for every doctor.
     */
    public function test_11_system_types_are_not_globally_assumed_for_every_doctor(): void
    {
        $btService = app(BookingTypeService::class);
        $emergencyBt = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'EMERGENCY_CONSULTANCY')->first();

        // Disable emergency for Doctor B
        $btService->setDoctorAvailability($this->clinic1->id, $this->doctorB->id, $emergencyBt->id, false);

        $enabledB = $btService->getEnabledForDoctor($this->doctorB->id, $this->clinic1->id, requireActiveFee: false);
        $this->assertFalse($enabledB->contains('id', $emergencyBt->id));
    }

    /**
     * Test 12, 13, 14, 15: Exact fee mapping exists for doctor + booking type and is clinic-specific.
     */
    public function test_12_to_15_doctor_booking_type_fee_isolation(): void
    {
        $btService = app(BookingTypeService::class);
        $clinicBt1 = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'CLINIC_CONSULTANCY')->first();
        $clinicBt2 = BookingType::where('clinic_id', $this->clinic2->id)->where('code', 'CLINIC_CONSULTANCY')->first();

        // Clinic 1 Doctor A = 600
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $clinicBt1->id, 600.0);
        // Clinic 1 Doctor B = 800
        $btService->setDoctorFee($this->clinic1->id, $this->doctorB->id, $clinicBt1->id, 800.0);
        // Clinic 2 Doctor C = 950
        $btService->setDoctorFee($this->clinic2->id, $this->doctorC->id, $clinicBt2->id, 950.0);

        $feeA = FeeMaster::getFeeForBookingType($this->clinic1->id, $this->doctorA->id, $clinicBt1->id);
        $feeB = FeeMaster::getFeeForBookingType($this->clinic1->id, $this->doctorB->id, $clinicBt1->id);
        $feeC = FeeMaster::getFeeForBookingType($this->clinic2->id, $this->doctorC->id, $clinicBt2->id);

        $this->assertEquals(600.0, $feeA);
        $this->assertEquals(800.0, $feeB);
        $this->assertEquals(950.0, $feeC);

        // Doctor A fee does not resolve for Doctor B
        $this->assertNotEquals($feeA, $feeB);

        // Doctor A does not resolve Clinic 2 booking type fee
        $feeCross = FeeMaster::getFeeForBookingType($this->clinic1->id, $this->doctorA->id, $clinicBt2->id);
        $this->assertNull($feeCross);

        // Clinic 1 does not resolve Doctor C fee
        $feeCrossClinic = FeeMaster::getFeeForBookingType($this->clinic1->id, $this->doctorC->id, $clinicBt1->id);
        $this->assertNull($feeCrossClinic);
    }

    /**
     * Test 16: Missing fee blocks booking (no silent fallback).
     */
    public function test_16_missing_fee_blocks_booking(): void
    {
        $btService = app(BookingTypeService::class);
        $emergencyBt = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'EMERGENCY_CONSULTANCY')->first();

        // Enable emergency mapping for Doctor A but DELETE fee configuration
        $btService->setDoctorAvailability($this->clinic1->id, $this->doctorA->id, $emergencyBt->id, true);
        FeeMaster::where('clinic_id', $this->clinic1->id)
            ->where('doctor_id', $this->doctorA->id)
            ->where('booking_type_id', $emergencyBt->id)
            ->delete();

        // Doctor's available types with requireActiveFee must NOT include emergency
        $enabled = $btService->getEnabledForDoctor($this->doctorA->id, $this->clinic1->id, requireActiveFee: true);
        $this->assertFalse($enabled->contains('id', $emergencyBt->id));

        // Direct booking attempt must throw InvalidArgumentException
        $apptService = app(AppointmentService::class);
        $patient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-45001',
            'name' => 'Sunil Verma',
            'age' => 40,
            'sex' => 'MALE',
            'mobile' => '9876543210',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fee is not configured for this doctor and consultancy type.');

        $apptService->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'booking_type_id' => $emergencyBt->id,
        ]);
    }

    /**
     * Test 17: Explicit zero fee remains valid.
     */
    public function test_17_explicit_zero_fee_remains_valid(): void
    {
        $btService = app(BookingTypeService::class);
        $clinicBt = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'CLINIC_CONSULTANCY')->first();

        // Explicitly set 0.00 fee
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $clinicBt->id, 0.0);

        $fee = FeeMaster::getFeeForBookingType($this->clinic1->id, $this->doctorA->id, $clinicBt->id);
        $this->assertSame(0.0, $fee);

        $enabled = $btService->getEnabledForDoctor($this->doctorA->id, $this->clinic1->id, requireActiveFee: true);
        $this->assertTrue($enabled->contains('id', $clinicBt->id));
        $this->assertSame(0.0, (float) $enabled->firstWhere('id', $clinicBt->id)->resolved_fee);

        // Booking succeeds with 0 fee
        $apptService = app(AppointmentService::class);
        $patient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-45002',
            'name' => 'Free Consultation Patient',
            'age' => 25,
            'sex' => 'MALE',
            'mobile' => '9876543210',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $appt = $apptService->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'booking_type_id' => $clinicBt->id,
        ]);

        $this->assertSame(0.0, (float) $appt->fee_amount);
    }

    /**
     * Test 18 & 32: Fee changes do not alter historical billing; historical appointment retains fee & type.
     */
    public function test_18_and_32_historical_appointment_fee_immutability(): void
    {
        $btService = app(BookingTypeService::class);
        $clinicBt = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'CLINIC_CONSULTANCY')->first();

        // Set fee to 600
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $clinicBt->id, 600.0);

        $patient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-45003',
            'name' => 'Historical Patient',
            'age' => 50,
            'sex' => 'FEMALE',
            'mobile' => '9876543210',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $apptService = app(AppointmentService::class);
        $appt1 = $apptService->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'booking_type_id' => $clinicBt->id,
        ]);

        $this->assertEquals(600.0, (float) $appt1->fee_amount);

        // Now admin increases fee to 750
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $clinicBt->id, 750.0);

        // Appt 1 must remain 600
        $appt1Fresh = Appointment::find($appt1->id);
        $this->assertEquals(600.0, (float) $appt1Fresh->fee_amount);
        $this->assertEquals($clinicBt->id, $appt1Fresh->booking_type_id);

        // New Appt 2 gets 750
        $appt2 = $apptService->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'EVENING',
            'booking_source' => 'CLINIC',
            'booking_type_id' => $clinicBt->id,
        ]);

        $this->assertEquals(750.0, (float) $appt2->fee_amount);
        $this->assertEquals(600.0, (float) $appt1Fresh->fee_amount);
    }

    /**
     * Test 19, 20, 21, 22, 23: Booking saves booking_type_id, doctor_id, derived mode, independent source, and unified token.
     */
    public function test_19_to_23_appointment_saved_fields_and_unified_tokens(): void
    {
        $btService = app(BookingTypeService::class);
        $clinicBt = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'CLINIC_CONSULTANCY')->first();
        $onlineBt = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'ONLINE_CONSULTANCY')->first();

        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $clinicBt->id, 600.0);
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $onlineBt->id, 700.0);

        $patient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-45004',
            'name' => 'Token Sequence Patient',
            'age' => 30,
            'sex' => 'MALE',
            'mobile' => '9876543210',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $apptService = app(AppointmentService::class);

        // Booking 1: CLINIC source, CLINIC_CONSULTANCY type
        $appt1 = $apptService->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'booking_type_id' => $clinicBt->id,
        ]);

        $this->assertEquals($this->clinic1->id, $appt1->clinic_id);
        $this->assertEquals($this->doctorA->id, $appt1->doctor_id);
        $this->assertEquals($clinicBt->id, $appt1->booking_type_id);
        $this->assertEquals('CLINIC_PHYSICAL', $appt1->consultation_mode);
        $this->assertEquals('CLINIC', $appt1->booking_source);
        $this->assertEquals(1, $appt1->token_number);

        // Booking 2: ONLINE source, ONLINE_CONSULTANCY type
        $appt2 = $apptService->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
            'booking_type_id' => $onlineBt->id,
        ]);

        $this->assertEquals($this->doctorA->id, $appt2->doctor_id);
        $this->assertEquals($onlineBt->id, $appt2->booking_type_id);
        $this->assertEquals('ONLINE', $appt2->consultation_mode);
        $this->assertEquals('ONLINE', $appt2->booking_source);
        // Unified token sequence for same date and session
        $this->assertEquals(2, $appt2->token_number);
    }

    /**
     * Test 24, 25, 26, 27: Cross-tenant and cross-doctor tampering blocked.
     */
    public function test_24_to_27_cross_tenant_and_cross_doctor_tampering_blocked(): void
    {
        $btService = app(BookingTypeService::class);
        $clinicBt1 = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'CLINIC_CONSULTANCY')->first();
        $clinicBt2 = BookingType::where('clinic_id', $this->clinic2->id)->where('code', 'CLINIC_CONSULTANCY')->first();

        $patient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-45005',
            'name' => 'Tamper Test Patient',
            'age' => 35,
            'sex' => 'MALE',
            'mobile' => '9876543210',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $apptService = app(AppointmentService::class);

        // Test 24: Cross-tenant booking_type ID tampering
        try {
            $apptService->bookAppointment([
                'clinic_id' => $this->clinic1->id,
                'patient_id' => $patient->id,
                'doctor_id' => $this->doctorA->id,
                'appointment_date' => '2026-09-28',
                'session_type' => 'MORNING',
                'booking_source' => 'CLINIC',
                'booking_type_id' => $clinicBt2->id, // Belongs to Clinic 2!
            ]);
            $this->fail('Expected InvalidArgumentException for cross-tenant booking type ID.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('belongs to another clinic', $e->getMessage());
        }

        // Test 25: Cross-doctor booking_type tampering (type unmapped for Doctor B)
        $btService->setDoctorAvailability($this->clinic1->id, $this->doctorB->id, $clinicBt1->id, false);
        try {
            $apptService->bookAppointment([
                'clinic_id' => $this->clinic1->id,
                'patient_id' => $patient->id,
                'doctor_id' => $this->doctorB->id,
                'appointment_date' => '2026-09-28',
                'session_type' => 'MORNING',
                'booking_source' => 'CLINIC',
                'booking_type_id' => $clinicBt1->id,
            ]);
            $this->fail('Expected InvalidArgumentException for unmapped doctor booking type.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('not enabled for the selected doctor', $e->getMessage());
        }
    }

    /**
     * Test 28: Public PWA shows only mapped and public types.
     */
    public function test_28_public_pwa_shows_only_mapped_and_public_types(): void
    {
        $btService = app(BookingTypeService::class);
        $types = BookingType::where('clinic_id', $this->clinic1->id)->get()->keyBy('code');

        $clinicBt = $types->get('CLINIC_CONSULTANCY');
        $onlineBt = $types->get('ONLINE_CONSULTANCY');
        $emergencyBt = $types->get('EMERGENCY_CONSULTANCY');

        // Configure Rajesh (A): Clinic (600), Online (700)
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $clinicBt->id, 600.0);
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $onlineBt->id, 700.0);

        // Emergency is not public bookable by default
        $emergencyBt->update(['is_public_bookable' => false]);

        $publicTypes = $btService->getPublicBookableForClinic($this->clinic1->id, $this->doctorA->id);
        $publicCodes = $publicTypes->pluck('code')->all();

        $this->assertContains('CLINIC_CONSULTANCY', $publicCodes);
        $this->assertContains('ONLINE_CONSULTANCY', $publicCodes);
        $this->assertNotContains('EMERGENCY_CONSULTANCY', $publicCodes);
    }

    /**
     * Test 29: Operator desk shows only mapped types for selected doctor.
     */
    public function test_29_operator_desk_shows_only_mapped_types(): void
    {
        $this->actingAs($this->operatorUser1);
        TenantContext::setClinic($this->clinic1);

        $btService = app(BookingTypeService::class);
        $types = BookingType::where('clinic_id', $this->clinic1->id)->get()->keyBy('code');
        $clinicBt = $types->get('CLINIC_CONSULTANCY');
        $onlineBt = $types->get('ONLINE_CONSULTANCY');
        $emergencyBt = $types->get('EMERGENCY_CONSULTANCY');

        // Doctor A has Clinic and Emergency only
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $clinicBt->id, 600.0);
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $emergencyBt->id, 1200.0);
        $btService->setDoctorAvailability($this->clinic1->id, $this->doctorA->id, $onlineBt->id, false);

        Livewire::test(OperatorBooking::class)
            ->set('selected_doctor_id', $this->doctorA->id)
            ->call('loadBookingTypes')
            ->assertSet('selected_doctor_id', $this->doctorA->id)
            ->assertSee($clinicBt->name)
            ->assertSee($emergencyBt->name)
            ->assertDontSee($onlineBt->name);
    }

    /**
     * Test 30: Telephonic booking uses same mapping and fee architecture.
     */
    public function test_30_telephonic_booking_uses_same_mapping_and_fee(): void
    {
        $btService = app(BookingTypeService::class);
        $clinicBt = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'CLINIC_CONSULTANCY')->first();
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $clinicBt->id, 650.0);

        $patient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-45006',
            'name' => 'Telephone Caller Patient',
            'age' => 45,
            'sex' => 'FEMALE',
            'mobile' => '9876543210',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $apptService = app(AppointmentService::class);
        $appt = $apptService->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorA->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'TELEPHONIC',
            'booking_type_id' => $clinicBt->id,
        ]);

        $this->assertEquals('TELEPHONIC', $appt->booking_source);
        $this->assertEquals($clinicBt->id, $appt->booking_type_id);
        $this->assertEquals(650.0, (float) $appt->fee_amount);
    }

    /**
     * Test 31: Emergency requires configured doctor-specific fee.
     */
    public function test_31_emergency_requires_configured_doctor_specific_fee(): void
    {
        $btService = app(BookingTypeService::class);
        $emergencyBt = BookingType::where('clinic_id', $this->clinic1->id)->where('code', 'EMERGENCY_CONSULTANCY')->first();

        // Enable emergency for Doctor A with 1500 fee
        $btService->setDoctorFee($this->clinic1->id, $this->doctorA->id, $emergencyBt->id, 1500.0);
        // Doctor B has NO fee configured for emergency
        FeeMaster::where('clinic_id', $this->clinic1->id)
            ->where('doctor_id', $this->doctorB->id)
            ->where('booking_type_id', $emergencyBt->id)
            ->delete();

        // Doctor A can resolve emergency fee
        $feeA = FeeMaster::getFeeForBookingType($this->clinic1->id, $this->doctorA->id, $emergencyBt->id);
        $this->assertEquals(1500.0, $feeA);

        // Doctor B does NOT resolve emergency fee (and does not borrow Doctor A's fee)
        $feeB = FeeMaster::getFeeForBookingType($this->clinic1->id, $this->doctorB->id, $emergencyBt->id);
        $this->assertNull($feeB);

        $patient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-45007',
            'name' => 'Emergency Patient',
            'age' => 60,
            'sex' => 'MALE',
            'mobile' => '9876543210',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $apptService = app(AppointmentService::class);

        // Doctor B emergency booking fails
        $this->expectException(InvalidArgumentException::class);
        $apptService->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorB->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'booking_type_id' => $emergencyBt->id,
        ]);
    }
}
