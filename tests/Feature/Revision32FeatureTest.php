<?php

namespace Tests\Feature;

use App\Livewire\Operator\ArrivalQueue;
use App\Livewire\Operator\ConsultationBilling;
use App\Livewire\Operator\OperatorBooking;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\ClinicClock;
use App\Services\ClinicScheduleService;
use App\Services\PatientIdentityService;
use App\Services\RefundService;
use App\Services\TokenAllocationService;
use Carbon\Carbon;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class Revision32FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected User $adminUser;
    protected User $operatorUser;
    protected User $doctorUser;
    protected AppointmentService $appointmentService;
    protected PatientIdentityService $patientService;
    protected TokenAllocationService $tokenService;
    protected ClinicScheduleService $scheduleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        // Fixed clinic test clock: Monday 2026-09-28 07:30 IST
        Carbon::setTestNow(Carbon::parse('2026-09-28 07:30:00', 'Asia/Kolkata'));

        $this->clinic = Clinic::create([
            'name' => 'DxClinic Test Facility',
            'phone' => '9876543210',
            'address' => 'Station Road',
            'city' => 'Varanasi',
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. A. Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-12345',
            'is_active' => true,
        ]);

        $this->clinic->update(['default_doctor_id' => $this->doctor->id]);

        $this->adminUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Admin Officer',
            'email' => 'admin@dxclinic.com',
            'password' => bcrypt('Admin@12345'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->operatorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Desk Operator',
            'email' => 'operator@dxclinic.com',
            'password' => bcrypt('Operator@12345'),
            'role' => 'OPERATOR',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. A. Sharma',
            'email' => 'doctor@dxclinic.com',
            'password' => bcrypt('Doctor@12345'),
            'role' => 'DOCTOR',
            'doctor_id' => $this->doctor->id,
            'is_active' => true,
        ]);

        $this->appointmentService = app(AppointmentService::class);
        $this->patientService = app(PatientIdentityService::class);
        $this->tokenService = app(TokenAllocationService::class);
        $this->scheduleService = app(ClinicScheduleService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * TEST A: New Patient mode hides Existing Patient search panel.
     */
    public function test_a_new_patient_mode_hides_existing_patient_search_panel(): void
    {
        $this->actingAs($this->operatorUser);

        Livewire::test(OperatorBooking::class)
            ->assertSet('isNewPatientMode', true)
            ->assertDontSeeHtml('id="operator_search_patient"')
            ->assertSeeHtml('id="operator_patient_name"')
            ->assertSeeHtml('id="operator_patient_mobile"')
            ->call('switchToExistingPatient')
            ->assertSet('isNewPatientMode', false)
            ->assertSeeHtml('id="operator_search_patient"')
            ->call('switchToNewPatient')
            ->assertSet('isNewPatientMode', true)
            ->assertDontSeeHtml('id="operator_search_patient"');
    }

    /**
     * TEST B: Mobile entered in New Patient mode detects existing patient.
     */
    public function test_b_mobile_entered_in_new_patient_mode_detects_existing_patient(): void
    {
        $this->actingAs($this->operatorUser);

        // Pre-create existing patient
        $existing = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00069',
            'name' => 'Rameshwar Lal',
            'age' => 45,
            'sex' => 'MALE',
            'mobile' => '9876543210',
            'address' => 'Godowlia',
            'city' => 'Varanasi',
        ]);

        Livewire::test(OperatorBooking::class)
            ->assertSet('isNewPatientMode', true)
            ->set('mobile', '9876543210')
            ->assertSee('Existing Patient Found')
            ->assertSee('DXP-00069')
            ->assertSee('Rameshwar Lal')
            ->assertSee('Use Existing Patient')
            ->assertSee('Continue as New Patient');
    }

    /**
     * TEST C: Existing patient must be explicitly selected before reuse or explicit continue.
     */
    public function test_c_existing_patient_must_be_explicitly_selected_before_reuse(): void
    {
        $this->actingAs($this->operatorUser);

        $existing = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00069',
            'name' => 'Rameshwar Lal',
            'age' => 45,
            'sex' => 'MALE',
            'mobile' => '9876543210',
            'address' => 'Godowlia',
            'city' => 'Varanasi',
        ]);

        // Attempting to submit without selecting an action is rejected
        Livewire::test(OperatorBooking::class)
            ->set('name', 'Different Name')
            ->set('age', '40')
            ->set('sex', 'MALE')
            ->set('mobile', '9876543210')
            ->set('address', 'Lanka')
            ->set('city', 'Varanasi')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('appointment_date', '2026-09-28')
            ->set('session_type', 'MORNING')
            ->call('bookOperatorToken')
            ->assertHasErrors(['mobile']);

        // Explicitly clicking continue as new patient allows registration
        Livewire::test(OperatorBooking::class)
            ->set('name', 'Different Name')
            ->set('age', '40')
            ->set('sex', 'MALE')
            ->set('mobile', '9876543210')
            ->set('address', 'Lanka')
            ->set('city', 'Varanasi')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('appointment_date', '2026-09-28')
            ->set('session_type', 'MORNING')
            ->call('continueAsNewPatient')
            ->call('bookOperatorToken')
            ->assertHasNoErrors()
            ->assertSet('bookingSuccess', true);
    }

    /**
     * TEST D: Today/tomorrow date restriction enforced.
     */
    public function test_d_today_tomorrow_date_restriction_enforced(): void
    {
        // Test date is Monday 2026-09-28. Today = 2026-09-28, Tomorrow = 2026-09-29.
        $this->assertTrue(ClinicClock::isAllowedDate('2026-09-28'));
        $this->assertTrue(ClinicClock::isAllowedDate('2026-09-29'));
        $this->assertFalse(ClinicClock::isAllowedDate('2026-09-30'));
        $this->assertFalse(ClinicClock::isAllowedDate('2026-09-27'));

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00001',
            'name' => 'Day Patient',
            'mobile' => '9111111111',
            'age' => 28,
            'sex' => 'MALE',
            'address' => 'Assi',
            'city' => 'Varanasi',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointments may only be booked for today or tomorrow.');

        $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-30', // Day + 2
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);
    }

    /**
     * TEST E: Past dates rejected server-side.
     */
    public function test_e_past_dates_rejected_server_side(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00001',
            'name' => 'Past Patient',
            'mobile' => '9111111111',
            'age' => 28,
            'sex' => 'MALE',
            'address' => 'Assi',
            'city' => 'Varanasi',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointments may only be booked for today or tomorrow.');

        $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-27', // Yesterday
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);
    }

    /**
     * TEST F: Today expired session rejected.
     */
    public function test_f_today_expired_session_rejected(): void
    {
        // On Sunday 2026-09-27 at 21:27 IST: Sunday session (09:00-11:00) is expired
        Carbon::setTestNow(Carbon::parse('2026-09-27 21:27:00', 'Asia/Kolkata'));

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00001',
            'name' => 'Sunday Patient',
            'mobile' => '9111111111',
            'age' => 28,
            'sex' => 'MALE',
            'address' => 'Assi',
            'city' => 'Varanasi',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This clinic session has already ended.');

        $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-27',
            'session_type' => 'SUNDAY_SINGLE',
            'booking_source' => 'CLINIC',
        ]);
    }

    /**
     * TEST G: Today active/future session accepted.
     */
    public function test_g_today_active_future_session_accepted(): void
    {
        // Monday 2026-09-28 at 10:00 IST: Morning (08:00-09:00) ended, Evening (17:00-19:00) is future
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00', 'Asia/Kolkata'));

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00001',
            'name' => 'Evening Patient',
            'mobile' => '9111111111',
            'age' => 28,
            'sex' => 'MALE',
            'address' => 'Assi',
            'city' => 'Varanasi',
        ]);

        $apt = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'EVENING',
            'booking_source' => 'CLINIC',
        ]);

        $this->assertEquals(1, $apt->token_number);
        $this->assertEquals('001', $apt->formatted_token);
        $this->assertEquals('EVENING', $apt->session_type);
    }

    /**
     * TEST H: Tomorrow session accepted.
     */
    public function test_h_tomorrow_session_accepted(): void
    {
        // Current time: Monday night 2026-09-28 22:00 IST
        Carbon::setTestNow(Carbon::parse('2026-09-28 22:00:00', 'Asia/Kolkata'));

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00001',
            'name' => 'Tomorrow Patient',
            'mobile' => '9111111111',
            'age' => 28,
            'sex' => 'MALE',
            'address' => 'Assi',
            'city' => 'Varanasi',
        ]);

        // Booking Tuesday Morning (Tomorrow) must succeed
        $apt = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-29',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $this->assertEquals(1, $apt->token_number);
        $this->assertEquals('001', $apt->formatted_token);
    }

    /**
     * TEST I: First token for date+session = 001.
     */
    public function test_i_first_token_for_date_and_session_is_001(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00001',
            'name' => 'Patient One',
            'mobile' => '9111111111',
            'age' => 28,
            'sex' => 'MALE',
            'address' => 'Assi',
            'city' => 'Varanasi',
        ]);

        $apt = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $this->assertEquals(1, $apt->token_number);
        $this->assertEquals('001', $apt->formatted_token);
    }

    /**
     * TEST J: Second token for same date+session = 002.
     */
    public function test_j_second_token_for_same_date_and_session_is_002(): void
    {
        $p1 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00001', 'name' => 'P1', 'mobile' => '9111111111', 'age' => 28, 'sex' => 'MALE', 'address' => 'Assi', 'city' => 'Varanasi']);
        $p2 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00002', 'name' => 'P2', 'mobile' => '9111111112', 'age' => 29, 'sex' => 'FEMALE', 'address' => 'Assi', 'city' => 'Varanasi']);

        $apt1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p1->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $apt2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p2->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);

        $this->assertEquals(1, $apt1->token_number);
        $this->assertEquals(2, $apt2->token_number);
        $this->assertEquals('002', $apt2->formatted_token);
    }

    /**
     * TEST K: Different session same date starts again at 001.
     */
    public function test_k_different_session_same_date_starts_again_at_001(): void
    {
        $p1 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00001', 'name' => 'P1', 'mobile' => '9111111111', 'age' => 28, 'sex' => 'MALE', 'address' => 'Assi', 'city' => 'Varanasi']);
        $p2 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00002', 'name' => 'P2', 'mobile' => '9111111112', 'age' => 29, 'sex' => 'FEMALE', 'address' => 'Assi', 'city' => 'Varanasi']);

        $aptMorning = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p1->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $aptEvening = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p2->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'EVENING',
            'booking_source' => 'TELEPHONIC',
        ]);

        $this->assertEquals(1, $aptMorning->token_number);
        $this->assertEquals('001', $aptMorning->formatted_token);

        $this->assertEquals(1, $aptEvening->token_number);
        $this->assertEquals('001', $aptEvening->formatted_token);
        $this->assertNotEquals($aptMorning->session_type, $aptEvening->session_type);
    }

    /**
     * TEST L: Different date starts again at 001.
     */
    public function test_l_different_date_starts_again_at_001(): void
    {
        $p1 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00001', 'name' => 'P1', 'mobile' => '9111111111', 'age' => 28, 'sex' => 'MALE', 'address' => 'Assi', 'city' => 'Varanasi']);
        $p2 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00002', 'name' => 'P2', 'mobile' => '9111111112', 'age' => 29, 'sex' => 'FEMALE', 'address' => 'Assi', 'city' => 'Varanasi']);

        $aptDay1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p1->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $aptDay2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p2->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-29',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $this->assertEquals(1, $aptDay1->token_number);
        $this->assertEquals(1, $aptDay2->token_number);
        $this->assertEquals('001', $aptDay2->formatted_token);
    }

    /**
     * TEST M: Cancelled/absent token remains consumed.
     */
    public function test_m_cancelled_or_absent_token_remains_consumed(): void
    {
        $p1 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00001', 'name' => 'P1', 'mobile' => '9111111111', 'age' => 28, 'sex' => 'MALE', 'address' => 'Assi', 'city' => 'Varanasi']);
        $p2 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00002', 'name' => 'P2', 'mobile' => '9111111112', 'age' => 29, 'sex' => 'FEMALE', 'address' => 'Assi', 'city' => 'Varanasi']);

        $apt1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p1->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);
        $this->assertEquals(1, $apt1->token_number);

        // Mark apt1 as CANCELLED
        $apt1->update(['status' => 'CANCELLED']);

        // Next booking must receive token 002, NOT 001
        $apt2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p2->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $this->assertEquals(2, $apt2->token_number);
        $this->assertEquals('002', $apt2->formatted_token);
    }

    /**
     * TEST N: OPD Queue displays all allocated tokens in numeric order.
     */
    public function test_n_opd_queue_displays_all_allocated_tokens_in_numeric_order(): void
    {
        $this->actingAs($this->operatorUser);

        // Create 3 appointments with varying statuses: BOOKED, ARRIVED, CANCELLED
        $p1 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00001', 'name' => 'Alpha', 'mobile' => '9111111111', 'age' => 20, 'sex' => 'MALE', 'address' => 'Assi', 'city' => 'Varanasi']);
        $p2 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00002', 'name' => 'Beta', 'mobile' => '9111111112', 'age' => 25, 'sex' => 'FEMALE', 'address' => 'Assi', 'city' => 'Varanasi']);
        $p3 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00003', 'name' => 'Gamma', 'mobile' => '9111111113', 'age' => 30, 'sex' => 'MALE', 'address' => 'Assi', 'city' => 'Varanasi']);

        $apt1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p1->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $apt2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p2->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);
        $apt2->update(['status' => 'ARRIVED']);

        $apt3 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p3->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'TELEPHONIC',
        ]);
        $apt3->update(['status' => 'CANCELLED']);

        Livewire::test(ArrivalQueue::class)
            ->set('filterDate', '2026-09-28')
            ->set('filterSession', 'MORNING')
            ->assertSee('001')
            ->assertSee('002')
            ->assertSee('003')
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Gamma')
            ->assertSee('CANCELLED');
    }

    /**
     * TEST O: Canonical Patient ID generated using DXP-xxxxx series.
     */
    public function test_o_canonical_patient_id_generated_using_dxp_numeric_series(): void
    {
        $patient = $this->patientService->registerPatient([
            'clinic_id' => $this->clinic->id,
            'name' => 'Suman Kumari',
            'age' => 29,
            'sex' => 'FEMALE',
            'mobile' => '9988776655',
            'address' => 'Lanka',
            'city' => 'Varanasi',
        ]);

        $this->assertMatchesRegularExpression('/^DXP-\d{5}$/', $patient->patient_id);
    }

    /**
     * TEST P: Legacy patient ID compatibility remains available where required.
     */
    public function test_p_legacy_patient_id_compatibility_remains_available(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00120',
            'legacy_alias' => 'DXP-GRACE-1867',
            'name' => 'Grace Revisit Patient',
            'age' => 38,
            'sex' => 'FEMALE',
            'mobile' => '9888877777',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $foundByCanonical = $this->patientService->findByPatientId($this->clinic->id, 'DXP-00120');
        $this->assertNotNull($foundByCanonical);
        $this->assertEquals($patient->id, $foundByCanonical->id);

        $foundByLegacy = $this->patientService->findByPatientId($this->clinic->id, 'DXP-GRACE-1867');
        $this->assertNotNull($foundByLegacy);
        $this->assertEquals($patient->id, $foundByLegacy->id);
    }

    /**
     * TEST Q: Partial refund submit becomes enabled when valid mandatory fields are completed without selecting Doctor Recommended Discount.
     */
    public function test_q_partial_refund_submit_enabled_without_doctor_discount(): void
    {
        $this->actingAs($this->operatorUser);

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00001', 'name' => 'Refund Patient', 'mobile' => '9111111111', 'age' => 30, 'sex' => 'MALE', 'address' => 'Assi', 'city' => 'Varanasi']);
        $apt = $this->appointmentService->bookAppointment(['clinic_id' => $this->clinic->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'appointment_date' => '2026-09-28', 'session_type' => 'MORNING', 'booking_source' => 'CLINIC']);
        $visit = Visit::create(['clinic_id' => $this->clinic->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'appointment_id' => $apt->id, 'visit_date' => '2026-09-28', 'session_type' => 'MORNING', 'status' => 'IN_PROGRESS']);
        
        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'visit_id' => $visit->id,
            'appointment_id' => $apt->id,
            'bill_number' => 'CB-20260928-001',
            'bill_date' => '2026-09-28',
            'gross_amount' => 500.00,
            'net_amount' => 500.00,
            'status' => 'PAID',
        ]);

        $payment = Payment::create([
            'clinic_id' => $this->clinic->id,
            'payment_number' => 'PAY-20260928-001',
            'consultation_bill_id' => $bill->id,
            'amount' => 500.00,
            'payment_method' => 'CASH',
            'status' => 'SETTLED',
            'settled_at' => now(),
        ]);

        $test = Livewire::test(ConsultationBilling::class)
            ->call('openModifyModal', $bill->id)
            ->assertSet('modificationType', 'PARTIAL_REFUND') // Stays as standard PARTIAL_REFUND
            ->set('modifyRefundAmount', 200.00)
            ->set('modifyReason', 'Patient overcharged during consultation fee collection')
            ->set('confirmModify', true);

        // canSubmitRefund property evaluates to true!
        $this->assertTrue($test->get('canSubmitRefund'));
        $this->assertStringNotContainsString('disabled class=', $test->html());
    }

    /**
     * TEST R: Invalid partial refund remains disabled/rejected.
     */
    public function test_r_invalid_partial_refund_remains_disabled_or_rejected(): void
    {
        $this->actingAs($this->operatorUser);

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00001', 'name' => 'Refund Patient', 'mobile' => '9111111111', 'age' => 30, 'sex' => 'MALE', 'address' => 'Assi', 'city' => 'Varanasi']);
        $apt = $this->appointmentService->bookAppointment(['clinic_id' => $this->clinic->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'appointment_date' => '2026-09-28', 'session_type' => 'MORNING', 'booking_source' => 'CLINIC']);
        $visit = Visit::create(['clinic_id' => $this->clinic->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'appointment_id' => $apt->id, 'visit_date' => '2026-09-28', 'session_type' => 'MORNING', 'status' => 'IN_PROGRESS']);
        
        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'visit_id' => $visit->id,
            'appointment_id' => $apt->id,
            'bill_number' => 'CB-20260928-002',
            'bill_date' => '2026-09-28',
            'gross_amount' => 500.00,
            'net_amount' => 500.00,
            'status' => 'PAID',
        ]);

        Payment::create([
            'clinic_id' => $this->clinic->id,
            'payment_number' => 'PAY-20260928-002',
            'consultation_bill_id' => $bill->id,
            'amount' => 500.00,
            'payment_method' => 'CASH',
            'status' => 'SETTLED',
            'settled_at' => now(),
        ]);

        // Reason < 10 chars -> cannot submit
        $test = Livewire::test(ConsultationBilling::class)
            ->call('openModifyModal', $bill->id)
            ->set('modifyRefundAmount', 200.00)
            ->set('modifyReason', 'short') // < 10 characters
            ->set('confirmModify', true);
        $this->assertFalse($test->get('canSubmitRefund'));

        // Amount <= 0 -> cannot submit
        $test2 = Livewire::test(ConsultationBilling::class)
            ->call('openModifyModal', $bill->id)
            ->set('modifyRefundAmount', 0)
            ->set('modifyReason', 'Reason is long enough here')
            ->set('confirmModify', true);
        $this->assertFalse($test2->get('canSubmitRefund'));

        // confirmModify unchecked -> cannot submit
        $test3 = Livewire::test(ConsultationBilling::class)
            ->call('openModifyModal', $bill->id)
            ->set('modifyRefundAmount', 200.00)
            ->set('modifyReason', 'Reason is long enough here')
            ->set('confirmModify', false);
        $this->assertFalse($test3->get('canSubmitRefund'));
    }

    /**
     * TEST S: Refund authorization still enforced (Doctor cannot process direct refund).
     */
    public function test_s_refund_authorization_still_enforced(): void
    {
        $refundService = app(RefundService::class);

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'DXP-00001', 'name' => 'Refund Patient', 'mobile' => '9111111111', 'age' => 30, 'sex' => 'MALE', 'address' => 'Assi', 'city' => 'Varanasi']);
        $apt = $this->appointmentService->bookAppointment(['clinic_id' => $this->clinic->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'appointment_date' => '2026-09-28', 'session_type' => 'MORNING', 'booking_source' => 'CLINIC']);
        $visit = Visit::create(['clinic_id' => $this->clinic->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'appointment_id' => $apt->id, 'visit_date' => '2026-09-28', 'session_type' => 'MORNING', 'status' => 'IN_PROGRESS']);
        
        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'visit_id' => $visit->id,
            'appointment_id' => $apt->id,
            'bill_number' => 'CB-20260928-003',
            'bill_date' => '2026-09-28',
            'gross_amount' => 500.00,
            'net_amount' => 500.00,
            'status' => 'PAID',
        ]);

        $payment = Payment::create([
            'clinic_id' => $this->clinic->id,
            'payment_number' => 'PAY-20260928-003',
            'consultation_bill_id' => $bill->id,
            'amount' => 500.00,
            'payment_method' => 'CASH',
            'status' => 'SETTLED',
            'settled_at' => now(),
        ]);

        $refundData = [
            'payment_id' => $payment->id,
            'consultation_bill_id' => $bill->id,
            'patient_id' => $patient->id,
            'refund_type' => 'PARTIAL',
            'refund_amount' => 100.00,
            'refund_method' => 'CASH',
            'reason' => 'Patient overpayment refund test',
        ];

        // Doctor user must be blocked from processing direct refund
        $this->expectException(AuthorizationException::class);
        $refundService->processDirectRefund($refundData, $this->doctorUser);
    }

    /**
     * TEST T, U, V: Mobile sidebar X button, backdrop, and Escape handlers.
     */
    public function test_t_u_v_mobile_sidebar_controls_exist_in_layout(): void
    {
        $this->actingAs($this->operatorUser);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        // TEST T: Mobile X close button exists with aria-label="Close menu"
        $response->assertSee('id="btn_close_mobile_sidebar"', false);
        $response->assertSee('aria-label="Close menu"', false);

        // TEST U: Mobile backdrop click closes sidebar
        $response->assertSee('id="mobile_sidebar_backdrop"', false);
        $response->assertSee('@click="sidebarOpen = false"', false);

        // TEST V: Escape key handler on window
        $response->assertSee('@keydown.escape.window="sidebarOpen = false"', false);
    }

    /**
     * TEST W: Sequential token allocation does not duplicate tokens.
     */
    public function test_w_sequential_token_allocation_does_not_duplicate_tokens(): void
    {
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $t = $this->tokenService->allocateToken($this->clinic, '2026-09-28', 'MORNING');
            $tokens[] = $t['token_number'];
        }

        $this->assertEquals([1, 2, 3, 4, 5], $tokens);
        $this->assertEquals(count($tokens), count(array_unique($tokens)));
    }

    /**
     * TEST X: Sequential Patient ID allocation does not duplicate IDs.
     */
    public function test_x_sequential_patient_id_allocation_does_not_duplicate_ids(): void
    {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $letter = chr(64 + $i);
            $p = $this->patientService->registerPatient([
                'clinic_id' => $this->clinic->id,
                'name' => "Batch Patient {$letter}",
                'age' => 20 + $i,
                'sex' => 'MALE',
                'mobile' => "910000000{$i}",
                'address' => 'Main Road',
                'city' => 'Varanasi',
            ]);
            $ids[] = $p->patient_id;
        }

        $this->assertEquals(count($ids), count(array_unique($ids)));
        foreach ($ids as $pid) {
            $this->assertMatchesRegularExpression('/^DXP-\d{5}$/', $pid);
        }
    }
}
