<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\TokenAllocationService;
use Carbon\Carbon;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TokenAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Doctor $doctor;
    private AppointmentService $appointmentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'Arogya Clinic',
            'phone' => '9876543210',
            'address' => 'Lanka Road',
            'city' => 'Varanasi',
            'schedule_config' => [
                'monday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '09:00', 'end' => '13:00', 'max_tokens' => 30],
                    ['type' => 'EVENING', 'start' => '17:00', 'end' => '21:00', 'max_tokens' => 30],
                ]],
                'sunday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '10:00', 'end' => '13:00', 'max_tokens' => 20],
                ]],
            ],
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-12345',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-28 07:00:00', 'Asia/Kolkata'));

        $this->appointmentService = app(AppointmentService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_common_continuous_token_sequence_across_all_sources(): void
    {
        $date = '2026-09-28'; // Monday

        $p1 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'P-001', 'name' => 'Patient One', 'mobile' => '9000000001', 'age' => 30, 'sex' => 'MALE', 'address' => 'Assi Ghat', 'city' => 'Varanasi']);
        $p2 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'P-002', 'name' => 'Patient Two', 'mobile' => '9000000002', 'age' => 35, 'sex' => 'FEMALE', 'address' => 'Assi Ghat', 'city' => 'Varanasi']);
        $p3 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'P-003', 'name' => 'Patient Three', 'mobile' => '9000000003', 'age' => 40, 'sex' => 'MALE', 'address' => 'Assi Ghat', 'city' => 'Varanasi']);

        // 1. ONLINE Booking gets Token 1
        $app1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p1->id,
            'appointment_date' => $date,
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);
        $this->assertEquals(1, $app1->token_number);
        $this->assertEquals('001', $app1->formatted_token);
        $this->assertStringNotContainsString('ON-', $app1->formatted_token);

        // 2. TELEPHONIC Booking gets Token 2 (Continuous)
        $app2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p2->id,
            'appointment_date' => $date,
            'session_type' => 'MORNING',
            'booking_source' => 'TELEPHONIC',
        ]);
        $this->assertEquals(2, $app2->token_number);
        $this->assertEquals('002', $app2->formatted_token);
        $this->assertStringNotContainsString('TEL-', $app2->formatted_token);

        // 3. CLINIC Walk-in Booking gets Token 3 (Continuous)
        $app3 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p3->id,
            'appointment_date' => $date,
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);
        $this->assertEquals(3, $app3->token_number);
        $this->assertEquals('003', $app3->formatted_token);
    }

    public function test_morning_and_evening_sessions_have_independent_sequence_starting_at_001(): void
    {
        $date = '2026-09-28'; // Monday

        $p1 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'P-001', 'name' => 'Morning Patient', 'mobile' => '9000000001', 'age' => 25, 'sex' => 'MALE', 'address' => 'Assi Ghat', 'city' => 'Varanasi']);
        $p2 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'P-002', 'name' => 'Evening Patient', 'mobile' => '9000000002', 'age' => 45, 'sex' => 'FEMALE', 'address' => 'Assi Ghat', 'city' => 'Varanasi']);

        // Morning Token (Starts at 001)
        $app1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p1->id,
            'appointment_date' => $date,
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);
        $this->assertEquals(1, $app1->token_number);
        $this->assertEquals('001', $app1->formatted_token);

        // Evening Token (Revision 32: Starts at 001 for each date + session)
        $app2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p2->id,
            'appointment_date' => $date,
            'session_type' => 'EVENING',
            'booking_source' => 'ONLINE',
        ]);
        $this->assertEquals(1, $app2->token_number);
        $this->assertEquals('001', $app2->formatted_token);
    }

    public function test_new_date_resets_token_sequence_to_one(): void
    {
        $day1 = '2026-09-28'; // Monday (today)
        $day2 = '2026-09-29'; // Tuesday (tomorrow)

        $p1 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'P-001', 'name' => 'Patient Day 1', 'mobile' => '9000000001', 'age' => 25, 'sex' => 'MALE', 'address' => 'Assi Ghat', 'city' => 'Varanasi']);
        $p2 = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'P-002', 'name' => 'Patient Day 2', 'mobile' => '9000000002', 'age' => 45, 'sex' => 'FEMALE', 'address' => 'Assi Ghat', 'city' => 'Varanasi']);

        $app1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p1->id,
            'appointment_date' => $day1,
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);
        $this->assertEquals(1, $app1->token_number);

        $app2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p2->id,
            'appointment_date' => $day2,
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);
        $this->assertEquals(1, $app2->token_number);
        $this->assertEquals('001', $app2->formatted_token);
    }

    public function test_prevents_booking_on_closed_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-27 08:00:00', 'Asia/Kolkata'));
        $sunday = '2026-09-27'; // Sunday (Only Morning is scheduled, Evening is closed)

        $p = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'P-001', 'name' => 'Sunday Patient', 'mobile' => '9000000001', 'age' => 25, 'sex' => 'MALE', 'address' => 'Assi Ghat', 'city' => 'Varanasi']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid session');

        $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p->id,
            'appointment_date' => $sunday,
            'session_type' => 'EVENING',
            'booking_source' => 'ONLINE',
        ]);
    }

    public function test_generates_unique_secure_token_for_verification(): void
    {
        $date = '2026-09-28';
        $p = Patient::create(['clinic_id' => $this->clinic->id, 'patient_id' => 'P-001', 'name' => 'Secure Patient', 'mobile' => '9000000001', 'age' => 25, 'sex' => 'MALE', 'address' => 'Assi Ghat', 'city' => 'Varanasi']);

        $app = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $p->id,
            'appointment_date' => $date,
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);

        $this->assertNotEmpty($app->secure_token);
        $this->assertGreaterThanOrEqual(64, strlen($app->secure_token));
    }
}
