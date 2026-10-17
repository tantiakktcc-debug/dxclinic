<?php

namespace Tests\Feature;

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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class Revision46DatabaseIntegrityFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinicA;
    protected Clinic $clinicB;
    protected Doctor $docA1;
    protected Doctor $docA2;
    protected Doctor $docB1;
    protected BookingType $btClinicA;
    protected BookingType $btOnlineA;
    protected BookingType $btEmergA;
    protected BookingType $btCustomA;
    protected BookingType $btClinicB;
    protected BookingType $btOnlineB;
    protected Patient $patientA;
    protected Patient $patientB;
    protected BookingTypeService $btService;
    protected AppointmentService $apptService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->btService = app(BookingTypeService::class);
        $this->apptService = app(AppointmentService::class);

        // Clinic A
        $this->clinicA = Clinic::create([
            'name'                     => 'Clinic A Alpha',
            'clinic_code'              => 'NX0001',
            'phone'                    => '9876543210',
            'address'                  => '123 Main Road',
            'city'                     => 'Varanasi',
            'max_advance_booking_days' => 1,
            'is_onboarded'             => true,
            'activation_status'        => 'ACTIVATED',
        ]);

        // Clinic B
        $this->clinicB = Clinic::create([
            'name'                     => 'Clinic B Beta',
            'clinic_code'              => 'NX0002',
            'phone'                    => '9876543222',
            'address'                  => '456 Crossing',
            'city'                     => 'Lucknow',
            'max_advance_booking_days' => 1,
            'is_onboarded'             => true,
            'activation_status'        => 'ACTIVATED',
        ]);

        // Doctors for Clinic A
        $this->docA1 = Doctor::create([
            'clinic_id'           => $this->clinicA->id,
            'name'                => 'Dr. Rajesh Sharma',
            'qualification'       => 'MBBS, MD',
            'registration_number' => 'MCI-1001',
            'is_active'           => true,
        ]);

        $this->docA2 = Doctor::create([
            'clinic_id'           => $this->clinicA->id,
            'name'                => 'Dr. Amit Verma',
            'qualification'       => 'MBBS, MS',
            'registration_number' => 'MCI-1002',
            'is_active'           => true,
        ]);

        // Doctor for Clinic B
        $this->docB1 = Doctor::create([
            'clinic_id'           => $this->clinicB->id,
            'name'                => 'Dr. Neha Kapoor',
            'qualification'       => 'MBBS, DNB',
            'registration_number' => 'MCI-2001',
            'is_active'           => true,
        ]);

        // Booking Types for Clinic A
        $this->btClinicA = BookingType::where('clinic_id', $this->clinicA->id)->where('code', 'CLINIC_CONSULTANCY')->first();
        $this->btOnlineA = BookingType::where('clinic_id', $this->clinicA->id)->where('code', 'ONLINE_CONSULTANCY')->first();
        $this->btEmergA  = BookingType::where('clinic_id', $this->clinicA->id)->where('code', 'EMERGENCY_CONSULTANCY')->first();

        $this->btCustomA = BookingType::firstOrCreate([
            'clinic_id' => $this->clinicA->id,
            'code'      => 'HOME_HEALTHCARE',
        ], [
            'name'               => 'Home Healthcare Visit',
            'consultation_mode'  => 'CLINIC_PHYSICAL',
            'is_system'          => false,
            'is_active'          => true,
            'is_public_bookable' => true,
            'display_order'      => 40,
        ]);

        // Booking Types for Clinic B
        $this->btClinicB = BookingType::where('clinic_id', $this->clinicB->id)->where('code', 'CLINIC_CONSULTANCY')->first();
        $this->btOnlineB = BookingType::where('clinic_id', $this->clinicB->id)->where('code', 'ONLINE_CONSULTANCY')->first();

        // Patients
        $this->patientA = Patient::create([
            'clinic_id'   => $this->clinicA->id,
            'patient_id'  => 'NX0001-P-4601',
            'name'        => 'Rahul Verma',
            'mobile'      => '9876500001',
            'age'         => 35,
            'sex'         => 'MALE',
            'city'        => 'Varanasi',
            'address'     => 'Sigra',
        ]);

        $this->patientB = Patient::create([
            'clinic_id'   => $this->clinicB->id,
            'patient_id'  => 'NX0002-P-4601',
            'name'        => 'Sunita Gupta',
            'mobile'      => '9876500002',
            'age'         => 28,
            'sex'         => 'FEMALE',
            'city'        => 'Lucknow',
            'address'     => 'Hazratganj',
        ]);
    }

    /** 1. Composite doctor/clinic relationship */
    public function test_01_composite_doctor_clinic_relationship(): void
    {
        $this->assertEquals($this->clinicA->id, $this->docA1->clinic_id);
        $this->assertEquals($this->clinicA->id, $this->docA2->clinic_id);
        $this->assertEquals($this->clinicB->id, $this->docB1->clinic_id);
    }

    /** 2. Composite booking type/clinic relationship */
    public function test_02_composite_booking_type_clinic_relationship(): void
    {
        $this->assertEquals($this->clinicA->id, $this->btClinicA->clinic_id);
        $this->assertEquals($this->clinicB->id, $this->btClinicB->clinic_id);
        $this->assertNotEquals($this->btClinicA->clinic_id, $this->btClinicB->clinic_id);
    }

    /** 3. Composite availability relationship */
    public function test_03_composite_availability_relationship(): void
    {
        $avail = DoctorBookingTypeAvailability::create([
            'clinic_id'       => $this->clinicA->id,
            'doctor_id'       => $this->docA1->id,
            'booking_type_id' => $this->btCustomA->id,
            'is_enabled'      => true,
        ]);

        $this->assertEquals($this->clinicA->id, $avail->clinic_id);
        $this->assertEquals($this->docA1->id, $avail->doctor_id);
        $this->assertEquals($this->btCustomA->id, $avail->booking_type_id);
    }

    /** 4. Composite fee relationship */
    public function test_04_composite_fee_relationship(): void
    {
        $fee = $this->btService->setDoctorFee(
            $this->clinicA->id,
            $this->docA1->id,
            $this->btClinicA->id,
            600.00
        );

        $this->assertEquals($this->clinicA->id, $fee->clinic_id);
        $this->assertEquals($this->docA1->id, $fee->doctor_id);
        $this->assertEquals($this->btClinicA->id, $fee->booking_type_id);
        $this->assertEquals(600.00, (float) $fee->amount);
    }

    /** 5. Appointment relationship */
    public function test_05_appointment_relationship(): void
    {
        // Setup Doc A1 with Clinic Consultancy and fee 600
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, 600.00);

        $appt = $this->apptService->bookAppointment([
            'clinic_id'        => $this->clinicA->id,
            'patient_id'       => $this->patientA->id,
            'doctor_id'        => $this->docA1->id,
            'booking_type_id'  => $this->btClinicA->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'session_type'     => 'MORNING',
            'booking_source'   => 'CLINIC',
        ]);

        $this->assertEquals($this->clinicA->id, $appt->clinic_id);
        $this->assertEquals($this->docA1->id, $appt->doctor_id);
        $this->assertEquals($this->btClinicA->id, $appt->booking_type_id);
        $this->assertEquals(600.00, (float) $appt->fee_amount);
        $this->assertEquals('CLINIC_PHYSICAL', $appt->consultation_mode);
    }

    /** 6. Cross-clinic insert rejection */
    public function test_06_cross_clinic_insert_rejection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Attempt to map Clinic B doctor to Clinic A
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docB1->id, $this->btClinicA->id, true);
    }

    /** 7. Cross-doctor insert rejection */
    public function test_07_cross_doctor_insert_rejection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Attempt to set fee for Clinic B doctor inside Clinic A
        $this->btService->setDoctorFee($this->clinicA->id, $this->docB1->id, $this->btClinicA->id, 650.00);
    }

    /** 8. Unmapped booking rejected */
    public function test_08_unmapped_booking_rejected(): void
    {
        // Doctor A1 is NOT enabled for btCustomA
        DoctorBookingTypeAvailability::where('clinic_id', $this->clinicA->id)
            ->where('doctor_id', $this->docA1->id)
            ->where('booking_type_id', $this->btCustomA->id)
            ->delete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("not enabled for the selected doctor");

        $this->apptService->bookAppointment([
            'clinic_id'        => $this->clinicA->id,
            'patient_id'       => $this->patientA->id,
            'doctor_id'        => $this->docA1->id,
            'booking_type_id'  => $this->btCustomA->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'session_type'     => 'MORNING',
            'booking_source'   => 'CLINIC',
        ]);
    }

    /** 9. Missing fee rejected */
    public function test_09_missing_fee_rejected(): void
    {
        // Map Doctor A1 to btCustomA but ensure fee is missing
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btCustomA->id, true);
        FeeMaster::where('clinic_id', $this->clinicA->id)
            ->where('doctor_id', $this->docA1->id)
            ->where('booking_type_id', $this->btCustomA->id)
            ->delete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Fee is not configured for this doctor and consultancy type.");

        $this->apptService->bookAppointment([
            'clinic_id'        => $this->clinicA->id,
            'patient_id'       => $this->patientA->id,
            'doctor_id'        => $this->docA1->id,
            'booking_type_id'  => $this->btCustomA->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'session_type'     => 'MORNING',
            'booking_source'   => 'CLINIC',
        ]);
    }

    /** 10. Exact fee returned */
    public function test_10_exact_fee_returned(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, 600.00);

        $fee = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docA1->id, $this->btClinicA->id);
        $this->assertEquals(600.00, $fee);
    }

    /** 11. Different doctors receive different fees */
    public function test_11_different_doctors_receive_different_fees(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, 600.00);

        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA2->id, $this->btClinicA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA2->id, $this->btClinicA->id, 800.00);

        $fee1 = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docA1->id, $this->btClinicA->id);
        $fee2 = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docA2->id, $this->btClinicA->id);

        $this->assertEquals(600.00, $fee1);
        $this->assertEquals(800.00, $fee2);
        $this->assertNotEquals($fee1, $fee2);
    }

    /** 12. Different clinics receive different fees */
    public function test_12_different_clinics_receive_different_fees(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, 600.00);

        $this->btService->setDoctorAvailability($this->clinicB->id, $this->docB1->id, $this->btClinicB->id, true);
        $this->btService->setDoctorFee($this->clinicB->id, $this->docB1->id, $this->btClinicB->id, 900.00);

        $feeA = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docA1->id, $this->btClinicA->id);
        $feeB = FeeMaster::getFeeForBookingType($this->clinicB->id, $this->docB1->id, $this->btClinicB->id);

        $this->assertEquals(600.00, $feeA);
        $this->assertEquals(900.00, $feeB);

        // Attempt cross-lookup: Clinic A asking for Doctor B fee
        $crossFee = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docB1->id, $this->btClinicA->id);
        $this->assertNull($crossFee);
    }

    /** 13. Historical fee snapshot preserved */
    public function test_13_historical_fee_snapshot_preserved(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, 600.00);

        $appt1 = $this->apptService->bookAppointment([
            'clinic_id'        => $this->clinicA->id,
            'patient_id'       => $this->patientA->id,
            'doctor_id'        => $this->docA1->id,
            'booking_type_id'  => $this->btClinicA->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'session_type'     => 'MORNING',
            'booking_source'   => 'CLINIC',
        ]);

        $this->assertEquals(600.00, (float) $appt1->fee_amount);

        // Admin updates Doctor A1's fee in Fee Master to 750
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, 750.00);

        // Re-read appt1 from database
        $appt1Fresh = Appointment::find($appt1->id);
        $this->assertEquals(600.00, (float) $appt1Fresh->fee_amount, "Historical appointment fee amount must remain unchanged!");

        // New appointment uses new fee
        $appt2 = $this->apptService->bookAppointment([
            'clinic_id'        => $this->clinicA->id,
            'patient_id'       => $this->patientA->id,
            'doctor_id'        => $this->docA1->id,
            'booking_type_id'  => $this->btClinicA->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'session_type'     => 'MORNING',
            'booking_source'   => 'CLINIC',
        ]);

        $this->assertEquals(750.00, (float) $appt2->fee_amount);
    }

    /** 14. Effective fee version resolution */
    public function test_14_effective_fee_version_resolution(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);

        // Version 1: 2026-01-01 to 2026-09-30 = 600
        $this->btService->setDoctorFee(
            $this->clinicA->id,
            $this->docA1->id,
            $this->btClinicA->id,
            600.00,
            true,
            '2026-01-01',
            '2026-09-30'
        );

        // Version 2: 2026-10-01 onwards = 700
        $this->btService->setDoctorFee(
            $this->clinicA->id,
            $this->docA1->id,
            $this->btClinicA->id,
            700.00,
            true,
            '2026-10-01',
            null
        );

        $feePast = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, '2026-05-15');
        $this->assertEquals(600.00, $feePast);

        $feeFuture = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, '2026-10-15');
        $this->assertEquals(700.00, $feeFuture);
    }

    /** 15. No overlapping fee periods */
    public function test_15_no_overlapping_fee_periods(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);

        // Set fixed period fee: 2026-01-01 to 2026-06-30
        $this->btService->setDoctorFee(
            $this->clinicA->id,
            $this->docA1->id,
            $this->btClinicA->id,
            500.00,
            true,
            '2026-01-01',
            '2026-06-30'
        );

        // Attempting to create an overlapping fee within 2026-03-01 to 2026-05-01 should fail
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Conflicting overlapping fee version exists");

        $this->btService->setDoctorFee(
            $this->clinicA->id,
            $this->docA1->id,
            $this->btClinicA->id,
            550.00,
            true,
            '2026-03-01',
            '2026-05-01'
        );
    }

    /** 16. Public PWA scoped */
    public function test_16_public_pwa_scoped(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, 600.00);

        // Emergency is mapped with fee, but public_bookable is false
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btEmergA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btEmergA->id, 1500.00);

        $pwaTypes = $this->btService->getPublicBookableForClinic($this->clinicA->id, $this->docA1->id);

        $codes = $pwaTypes->pluck('code')->all();
        $this->assertContains('CLINIC_CONSULTANCY', $codes);
        $this->assertNotContains('EMERGENCY_CONSULTANCY', $codes, "Emergency must be safely excluded from public PWA!");
    }

    /** 17. Operator scoped */
    public function test_17_operator_scoped(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, 600.00);

        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btEmergA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btEmergA->id, 1500.00);

        $opTypes = $this->btService->getEnabledForDoctor($this->docA1->id, $this->clinicA->id);

        $codes = $opTypes->pluck('code')->all();
        $this->assertContains('CLINIC_CONSULTANCY', $codes);
        $this->assertContains('EMERGENCY_CONSULTANCY', $codes, "Emergency should be available to desk operator!");
    }

    /** 18. Unified token sequence */
    public function test_18_unified_token_sequence(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btClinicA->id, 600.00);

        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btEmergA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btEmergA->id, 1500.00);

        $appt1 = $this->apptService->bookAppointment([
            'clinic_id'        => $this->clinicA->id,
            'patient_id'       => $this->patientA->id,
            'doctor_id'        => $this->docA1->id,
            'booking_type_id'  => $this->btClinicA->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'session_type'     => 'MORNING',
            'booking_source'   => 'CLINIC',
        ]);

        $appt2 = $this->apptService->bookAppointment([
            'clinic_id'        => $this->clinicA->id,
            'patient_id'       => $this->patientA->id,
            'doctor_id'        => $this->docA1->id,
            'booking_type_id'  => $this->btEmergA->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'session_type'     => 'MORNING',
            'booking_source'   => 'CLINIC',
        ]);

        $this->assertEquals(1, $appt1->token_number);
        $this->assertEquals(2, $appt2->token_number, "Token sequence must be continuous regardless of booking type!");
    }

    /** 19. Emergency exact fee isolation */
    public function test_19_emergency_exact_fee_isolation(): void
    {
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btEmergA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btEmergA->id, 1500.00);

        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA2->id, $this->btEmergA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA2->id, $this->btEmergA->id, 1800.00);

        $feeA1 = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docA1->id, $this->btEmergA->id);
        $feeA2 = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docA2->id, $this->btEmergA->id);

        $this->assertEquals(1500.00, $feeA1);
        $this->assertEquals(1800.00, $feeA2);
        $this->assertNotEquals($feeA1, $feeA2, "Doctors must maintain independent emergency tariffs!");
    }

    /** 20. Existing Revision 45 regressions preserved */
    public function test_20_existing_revision_45_regressions_preserved(): void
    {
        // Custom booking type remains strictly clinic-scoped
        $this->assertEquals($this->clinicA->id, $this->btCustomA->clinic_id);
        $clinicBTypes = BookingType::forClinic($this->clinicB->id)->get();
        $this->assertFalse($clinicBTypes->contains('id', $this->btCustomA->id));

        // Explicit 0 fee is valid
        $this->btService->setDoctorAvailability($this->clinicA->id, $this->docA1->id, $this->btCustomA->id, true);
        $this->btService->setDoctorFee($this->clinicA->id, $this->docA1->id, $this->btCustomA->id, 0.00);

        $fee = FeeMaster::getFeeForBookingType($this->clinicA->id, $this->docA1->id, $this->btCustomA->id);
        $this->assertSame(0.0, $fee);

        $apptZero = $this->apptService->bookAppointment([
            'clinic_id'        => $this->clinicA->id,
            'patient_id'       => $this->patientA->id,
            'doctor_id'        => $this->docA1->id,
            'booking_type_id'  => $this->btCustomA->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'session_type'     => 'MORNING',
            'booking_source'   => 'CLINIC',
        ]);
        $this->assertEquals(0.00, (float) $apptZero->fee_amount);
    }
}
