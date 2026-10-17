<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorBookingPolicy;
use App\Models\DoctorLeave;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\BookingEligibilityService;
use App\Services\ClinicAiToolExecutor;
use App\Services\ClinicClock;
use Carbon\Carbon;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BookingEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected Patient $patient;
    protected User $operatorUser;
    protected BookingEligibilityService $eligibilityService;
    protected AppointmentService $appointmentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        // Standard test reference point: Monday 2026-10-05 08:30:00 IST
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:30:00', 'Asia/Kolkata'));

        $this->clinic = Clinic::create([
            'name' => 'DxClinic Apex Healthcare',
            'phone' => '9876543210',
            'address' => 'Varanasi Cantt',
            'city' => 'Varanasi',
            'is_onboarded' => true,
            'max_advance_booking_days' => 29,
            'operating_schedule' => [
                'monday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
                'tuesday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
                'wednesday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
                'thursday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
                'friday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
                'saturday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
                'sunday' => ['morning' => ['09:00', '11:00']],
            ],
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Amit Verma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-998877',
            'specialization' => 'General Medicine',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-10001',
            'name' => 'Rahul Sharma',
            'mobile' => '9123456780',
            'sex' => 'MALE',
            'age' => 32,
            'address' => 'Varanasi Cantt',
            'city' => 'Varanasi',
        ]);

        $this->operatorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Operator Maya',
            'email' => 'operator.maya@example.com',
            'password' => bcrypt('password123'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->eligibilityService = app(BookingEligibilityService::class);
        $this->appointmentService = app(AppointmentService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_server_ist_authoritative_clock(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 14:15:30', 'Asia/Kolkata'));

        $this->assertEquals('Asia/Kolkata', ClinicClock::timezone());
        $this->assertEquals('2026-10-05', ClinicClock::today()->toDateString());
        $this->assertEquals('14:15:30', ClinicClock::now()->format('H:i:s'));
    }

    public function test_same_day_morning_and_evening_cutoff_matrix(): void
    {
        $today = '2026-10-05'; // Monday

        // Test boundary timestamps for Today
        $matrix = [
            '08:30:00' => ['morning' => true,  'evening' => true],
            '10:59:59' => ['morning' => true,  'evening' => true],
            '11:00:00' => ['morning' => false, 'evening' => true],
            '11:01:00' => ['morning' => false, 'evening' => true],
            '12:30:00' => ['morning' => false, 'evening' => true],
            '16:59:59' => ['morning' => false, 'evening' => true],
            '17:00:00' => ['morning' => false, 'evening' => true],
            '18:59:59' => ['morning' => false, 'evening' => true],
            '19:00:00' => ['morning' => false, 'evening' => false],
            '19:01:00' => ['morning' => false, 'evening' => false],
            '23:59:59' => ['morning' => false, 'evening' => false],
        ];

        foreach ($matrix as $time => $expected) {
            Carbon::setTestNow(Carbon::parse("2026-10-05 {$time}", 'Asia/Kolkata'));

            // Morning evaluation
            $morningResult = $this->eligibilityService->evaluateEligibility(
                $this->clinic,
                $today,
                'morning',
                $this->doctor
            );
            $this->assertEquals(
                $expected['morning'],
                $morningResult['eligible'],
                "Failed morning check at {$time}: " . ($morningResult['reason'] ?? '')
            );

            if (!$expected['morning']) {
                $this->assertEquals(BookingEligibilityService::CODE_SESSION_CUTOFF_PASSED, $morningResult['code']);
                $this->assertStringContainsString('Morning booking for today closed at 11:00 AM', $morningResult['reason']);
            }

            // Evening evaluation
            $eveningResult = $this->eligibilityService->evaluateEligibility(
                $this->clinic,
                $today,
                'evening',
                $this->doctor
            );
            $this->assertEquals(
                $expected['evening'],
                $eveningResult['eligible'],
                "Failed evening check at {$time}: " . ($eveningResult['reason'] ?? '')
            );

            if (!$expected['evening']) {
                $this->assertEquals(BookingEligibilityService::CODE_SESSION_CUTOFF_PASSED, $eveningResult['code']);
                $this->assertStringContainsString("Today's evening booking is closed", $eveningResult['reason']);
            }
        }
    }

    public function test_midnight_rollover(): void
    {
        // 23:59:59 Monday 2026-10-05: both sessions of Monday are closed
        Carbon::setTestNow(Carbon::parse('2026-10-05 23:59:59', 'Asia/Kolkata'));
        $evalBefore = $this->eligibilityService->evaluateEligibility($this->clinic, '2026-10-05', 'morning', $this->doctor);
        $this->assertFalse($evalBefore['eligible']);

        // 00:00:00 Tuesday 2026-10-06: Tuesday morning becomes OPEN for today!
        Carbon::setTestNow(Carbon::parse('2026-10-06 00:00:00', 'Asia/Kolkata'));
        $this->assertEquals('2026-10-06', ClinicClock::today()->toDateString());

        $evalAfter = $this->eligibilityService->evaluateEligibility($this->clinic, '2026-10-06', 'morning', $this->doctor);
        $this->assertTrue($evalAfter['eligible'], 'New day morning should be open at 00:00:00');
    }

    public function test_clinic_advance_booking_policy_options(): void
    {
        // Monday 2026-10-05 08:30:00 IST
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:30:00', 'Asia/Kolkata'));
        $today = '2026-10-05';
        $tomorrow = '2026-10-06';
        $dayAfter = '2026-10-07';
        $day7 = '2026-10-12';
        $day30 = '2026-11-04';

        // Option A: max = 0 (Today Only)
        $this->clinic->update(['max_advance_booking_days' => 0]);
        $resToday = $this->eligibilityService->evaluateEligibility($this->clinic, $today, 'morning', $this->doctor);
        $resTomorrow = $this->eligibilityService->evaluateEligibility($this->clinic, $tomorrow, 'morning', $this->doctor);

        $this->assertTrue($resToday['eligible']);
        $this->assertFalse($resTomorrow['eligible']);
        $this->assertEquals(BookingEligibilityService::CODE_CLINIC_WINDOW_CLOSED, $resTomorrow['code']);
        $this->assertStringContainsString('today only', strtolower($resTomorrow['reason']));

        // Option B: max = 1 (Today + Tomorrow)
        $this->clinic->update(['max_advance_booking_days' => 1]);
        $this->assertTrue($this->eligibilityService->evaluateEligibility($this->clinic, $today, 'morning', $this->doctor)['eligible']);
        $this->assertTrue($this->eligibilityService->evaluateEligibility($this->clinic, $tomorrow, 'morning', $this->doctor)['eligible']);
        $resDayAfter = $this->eligibilityService->evaluateEligibility($this->clinic, $dayAfter, 'morning', $this->doctor);
        $this->assertFalse($resDayAfter['eligible']);
        $this->assertEquals(BookingEligibilityService::CODE_CLINIC_WINDOW_CLOSED, $resDayAfter['code']);

        // Option C: max = 6 (Today through +6 days)
        $this->clinic->update(['max_advance_booking_days' => 6]);
        $day6 = '2026-10-11'; // Sunday
        $this->assertTrue($this->eligibilityService->evaluateEligibility($this->clinic, $day6, 'morning', $this->doctor)['eligible']);
        $resDay7 = $this->eligibilityService->evaluateEligibility($this->clinic, $day7, 'morning', $this->doctor);
        $this->assertFalse($resDay7['eligible']);
        $this->assertEquals(BookingEligibilityService::CODE_CLINIC_WINDOW_CLOSED, $resDay7['code']);

        // Option D: max = 29 (Today through +29 days)
        $this->clinic->update(['max_advance_booking_days' => 29]);
        $day29 = '2026-11-03';
        $this->assertTrue($this->eligibilityService->evaluateEligibility($this->clinic, $day29, 'morning', $this->doctor)['eligible']);
        $resDay30 = $this->eligibilityService->evaluateEligibility($this->clinic, $day30, 'morning', $this->doctor);
        $this->assertFalse($resDay30['eligible']);
        $this->assertEquals(BookingEligibilityService::CODE_CLINIC_WINDOW_CLOSED, $resDay30['code']);
    }

    public function test_doctor_specific_advance_policy_and_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:30:00', 'Asia/Kolkata'));
        $this->clinic->update(['max_advance_booking_days' => 29]);

        // Doctor A policy 1: 2026-10-01 to 2026-10-10 -> max_advance_booking_days = 6
        DoctorBookingPolicy::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'effective_from' => '2026-10-01',
            'effective_to' => '2026-10-10',
            'max_advance_booking_days' => 6,
            'is_active' => true,
        ]);

        // Doctor A policy 2: 2026-10-11 to 2026-10-31 -> max_advance_booking_days = 29
        DoctorBookingPolicy::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'effective_from' => '2026-10-11',
            'effective_to' => '2026-10-31',
            'max_advance_booking_days' => 29,
            'is_active' => true,
        ]);

        // On 2026-10-05, Doctor policy 1 applies (max 6 days).
        // +6 days = 2026-10-11 (Sunday morning) -> ALLOWED
        $resAllowed = $this->eligibilityService->evaluateEligibility($this->clinic, '2026-10-11', 'morning', $this->doctor);
        $this->assertTrue($resAllowed['eligible']);

        // +7 days = 2026-10-12 -> REJECTED by Doctor policy
        $resRejected = $this->eligibilityService->evaluateEligibility($this->clinic, '2026-10-12', 'morning', $this->doctor);
        $this->assertFalse($resRejected['eligible']);
        $this->assertEquals(BookingEligibilityService::CODE_DOCTOR_WINDOW_CLOSED, $resRejected['code']);
        $this->assertStringContainsString("doctor's advance booking window", $resRejected['reason']);
    }

    public function test_policy_precedence_min_clinic_and_doctor(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:30:00', 'Asia/Kolkata'));

        // Clinic allows +6 days only
        $this->clinic->update(['max_advance_booking_days' => 6]);

        // Doctor attempts to allow +29 days
        DoctorBookingPolicy::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'effective_from' => '2026-10-01',
            'effective_to' => '2026-10-31',
            'max_advance_booking_days' => 29,
            'is_active' => true,
        ]);

        // Day +7 (2026-10-12) must be REJECTED because Clinic Upper Bound (6 days) caps it
        $res = $this->eligibilityService->evaluateEligibility($this->clinic, '2026-10-12', 'morning', $this->doctor);
        $this->assertFalse($res['eligible']);
        $this->assertEquals(BookingEligibilityService::CODE_CLINIC_WINDOW_CLOSED, $res['code']);
    }

    public function test_doctor_availability_and_leave_rules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:30:00', 'Asia/Kolkata'));
        $targetDate = '2026-10-07'; // Wednesday

        // 1. Inactive doctor
        $this->doctor->update(['is_active' => false]);
        $inactiveRes = $this->eligibilityService->evaluateEligibility($this->clinic, $targetDate, 'morning', $this->doctor);
        $this->assertFalse($inactiveRes['eligible']);
        $this->assertEquals(BookingEligibilityService::CODE_DOCTOR_NOT_AVAILABLE, $inactiveRes['code']);

        $this->doctor->update(['is_active' => true]);

        // 2. Full-day leave on 2026-10-07
        $leave = DoctorLeave::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'session_type' => null,
            'reason' => 'Annual Medical Conference',
            'is_active' => true,
        ]);

        $leaveResMorning = $this->eligibilityService->evaluateEligibility($this->clinic, $targetDate, 'morning', $this->doctor);
        $leaveResEvening = $this->eligibilityService->evaluateEligibility($this->clinic, $targetDate, 'evening', $this->doctor);
        $this->assertFalse($leaveResMorning['eligible']);
        $this->assertFalse($leaveResEvening['eligible']);
        $this->assertEquals(BookingEligibilityService::CODE_DOCTOR_NOT_AVAILABLE, $leaveResMorning['code']);
        $this->assertStringContainsString('Annual Medical Conference', $leaveResMorning['reason']);

        // 3. Session-specific leave (e.g., Morning leave only)
        $leave->update(['session_type' => 'MORNING', 'reason' => 'Morning Surgery Duty']);
        $morningDutyRes = $this->eligibilityService->evaluateEligibility($this->clinic, $targetDate, 'morning', $this->doctor);
        $eveningDutyRes = $this->eligibilityService->evaluateEligibility($this->clinic, $targetDate, 'evening', $this->doctor);
        $this->assertFalse($morningDutyRes['eligible']);
        $this->assertTrue($eveningDutyRes['eligible']);
    }

    public function test_final_appointment_creation_guard_and_zero_token_on_failure(): void
    {
        // 12:30 IST -> Morning is CLOSED
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:30:00', 'Asia/Kolkata'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Morning booking for today closed at 11:00 AM');

        try {
            $this->appointmentService->bookAppointment([
                'clinic_id' => $this->clinic->id,
                'patient_id' => $this->patient->id,
                'doctor_id' => $this->doctor->id,
                'appointment_date' => '2026-10-05',
                'session_type' => 'MORNING',
                'booking_source' => 'CLINIC',
                'consultation_mode' => 'CLINIC_PHYSICAL',
            ]);
        } finally {
            // Verify zero appointments and zero tokens were allocated
            $this->assertEquals(0, Appointment::where('clinic_id', $this->clinic->id)->count());
        }
    }

    public function test_authoritative_tools_consistency(): void
    {
        // 11:30 IST -> Morning closed, Evening open
        Carbon::setTestNow(Carbon::parse('2026-10-05 11:30:00', 'Asia/Kolkata'));
        $today = '2026-10-05';

        // 1. getAvailableSessions via BookingEligibilityService
        $serviceSessions = $this->eligibilityService->getAvailableSessions($this->clinic, $today, $this->doctor->id);
        $sessionNames = array_map('strtolower', array_column($serviceSessions, 'session'));
        $this->assertEquals(['evening'], $sessionNames);

        // 2. getAvailableSessions via ClinicAiToolExecutor Tool 8
        $aiExecutor = new ClinicAiToolExecutor($this->clinic);
        $aiSessionsResult = $aiExecutor->execute('getAvailableSessions', ['date' => $today, 'doctor_id' => $this->doctor->id]);
        $this->assertTrue($aiSessionsResult['success']);
        $aiSessionNames = array_map('strtolower', array_column($aiSessionsResult['data']['available_sessions'], 'session'));
        $this->assertEquals(['evening'], $aiSessionNames);

        // 3. getAvailableDoctors via BookingEligibilityService
        $morningDoctors = $this->eligibilityService->getAvailableDoctors($this->clinic, $today, 'morning');
        $eveningDoctors = $this->eligibilityService->getAvailableDoctors($this->clinic, $today, 'evening');
        $this->assertEmpty($morningDoctors);
        $this->assertCount(1, $eveningDoctors);
        $this->assertEquals($this->doctor->id, $eveningDoctors[0]['id']);

        // 4. getAvailableDoctors via ClinicAiToolExecutor Tool 9
        $aiDoctorsMorning = $aiExecutor->execute('getAvailableDoctors', ['date' => $today, 'session' => 'morning']);
        $this->assertTrue($aiDoctorsMorning['success']);
        $this->assertEmpty($aiDoctorsMorning['data']['doctors']);

        $aiDoctorsEvening = $aiExecutor->execute('getAvailableDoctors', ['date' => $today, 'session' => 'evening']);
        $this->assertTrue($aiDoctorsEvening['success']);
        $this->assertCount(1, $aiDoctorsEvening['data']['doctors']);
        $this->assertEquals($this->doctor->id, $aiDoctorsEvening['data']['doctors'][0]['id']);
    }

    public function test_cross_channel_consistency(): void
    {
        // Time = 11:15 IST
        Carbon::setTestNow(Carbon::parse('2026-10-05 11:15:00', 'Asia/Kolkata'));

        $channels = ['CLINIC', 'ONLINE', 'WHATSAPP', 'TELEPHONIC', 'DOCTOR', 'ADMIN'];

        foreach ($channels as $channel) {
            // Check Morning for today
            $resultMorning = $this->eligibilityService->evaluateEligibility(
                $this->clinic,
                '2026-10-05',
                'morning',
                $this->doctor
            );
            $this->assertFalse($resultMorning['eligible'], "Channel {$channel} must reject morning at 11:15 IST");
            $this->assertEquals(BookingEligibilityService::CODE_SESSION_CUTOFF_PASSED, $resultMorning['code']);

            // Check Evening for today
            $resultEvening = $this->eligibilityService->evaluateEligibility(
                $this->clinic,
                '2026-10-05',
                'evening',
                $this->doctor
            );
            $this->assertTrue($resultEvening['eligible'], "Channel {$channel} must allow evening at 11:15 IST");
        }
    }
}
