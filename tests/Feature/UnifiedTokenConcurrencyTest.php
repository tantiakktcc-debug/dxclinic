<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\TokenSequence;
use App\Services\AppointmentService;
use App\Services\ClinicClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedTokenConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected AppointmentService $appointmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'sharmacare',
            'name' => 'Sharma Care Clinic',
            'phone' => '9876543210',
            'email' => 'clinic@test.com',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
            'is_active' => true,
            'schedule_config' => [
                'monday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'tuesday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'wednesday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'thursday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'friday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'saturday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '20:00']],
                'sunday' => ['single' => ['09:00', '12:00']],
            ],
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'UP-MC-12345',
            'specialization' => 'General Medicine',
            'contact_number' => '9876543210',
            'is_active' => true,
        ]);

        $this->appointmentService = app(AppointmentService::class);
    }

    public function test_all_four_booking_channels_share_one_authoritative_token_sequence(): void
    {
        $today = ClinicClock::today()->toDateString();

        $patients = [];
        for ($i = 1; $i <= 4; $i++) {
            $patients[$i] = Patient::create([
                'clinic_id' => $this->clinic->id,
                'patient_id' => "DXP-000{$i}",
                'name' => "Patient Number {$i}",
                'age' => 25 + $i,
                'sex' => 'MALE',
                'mobile' => "987000000{$i}",
                'address' => 'Varanasi',
                'city' => 'Varanasi',
            ]);
        }

        // 1. Reception Walk-in Booking
        $app1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patients[1]->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        // 2. Patient PWA Self-Booking
        $app2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patients[2]->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);

        // 3. WhatsApp Automated Booking
        $app3 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patients[3]->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'WHATSAPP',
        ]);

        // 4. Telephonic Registration
        $app4 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patients[4]->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'TELEPHONIC',
        ]);

        // Contract Section 2: Exactly ONE common authoritative daily token sequence
        // Reception -> 001
        // PWA       -> 002
        // WhatsApp  -> 003
        // Telephony -> 004
        $this->assertEquals(1, $app1->token_number);
        $this->assertEquals('001', $app1->formatted_token);
        $this->assertEquals('CLINIC', $app1->booking_source);

        $this->assertEquals(2, $app2->token_number);
        $this->assertEquals('002', $app2->formatted_token);
        $this->assertEquals('ONLINE', $app2->booking_source);

        $this->assertEquals(3, $app3->token_number);
        $this->assertEquals('003', $app3->formatted_token);
        $this->assertEquals('WHATSAPP', $app3->booking_source);

        $this->assertEquals(4, $app4->token_number);
        $this->assertEquals('004', $app4->formatted_token);
        $this->assertEquals('TELEPHONIC', $app4->booking_source);

        // Verify exactly one TokenSequence record exists in database (Section 2, 24)
        $sequences = TokenSequence::where('clinic_id', $this->clinic->id)
            ->whereDate('token_date', $today)
            ->where('session_type', 'MORNING')
            ->get();

        $this->assertCount(1, $sequences);
        $this->assertEquals(4, $sequences->first()->current_token);
    }

    public function test_cancellation_consumes_token_and_never_reuses_number(): void
    {
        $today = ClinicClock::today()->toDateString();

        $patient1 = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00051',
            'name' => 'Patient One',
            'age' => 30,
            'sex' => 'MALE',
            'mobile' => '9870000051',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);

        $patient2 = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00052',
            'name' => 'Patient Two',
            'age' => 32,
            'sex' => 'FEMALE',
            'mobile' => '9870000052',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);

        // Book Token 1 via WhatsApp
        $app1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient1->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'WHATSAPP',
        ]);
        $this->assertEquals(1, $app1->token_number);

        // Cancel Token 1 (Section 62)
        $app1->update(['status' => 'CANCELLED']);

        // Next booking (from PWA) MUST receive Token 2, NOT reuse Token 1!
        $app2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient2->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);

        $this->assertEquals(2, $app2->token_number);
        $this->assertEquals('002', $app2->formatted_token);
    }

    public function test_tokens_are_monotonic_never_reused_and_absence_retires_token(): void
    {
        $today = ClinicClock::today()->toDateString();

        $patientA = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00061',
            'name' => 'Patient Alpha',
            'age' => 25,
            'sex' => 'MALE',
            'mobile' => '9870000061',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);

        $patientB = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00062',
            'name' => 'Patient Beta',
            'age' => 28,
            'sex' => 'FEMALE',
            'mobile' => '9870000062',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);

        $patientC = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00063',
            'name' => 'Patient Gamma',
            'age' => 35,
            'sex' => 'MALE',
            'mobile' => '9870000063',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);

        // 1. Allocate Token 1 via Walk-in Reception
        $app1 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patientA->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $today,
            'session_type' => 'EVENING',
            'booking_source' => 'CLINIC',
        ]);
        $this->assertEquals(1, $app1->token_number);

        // Mark Patient 1 as ABSENT / NO_SHOW
        $app1->update(['status' => 'ABSENT']);

        // 2. Next booking via WhatsApp MUST receive Token 2 (Token 1 is permanently consumed/retired)
        $app2 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patientB->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $today,
            'session_type' => 'EVENING',
            'booking_source' => 'WHATSAPP',
        ]);
        $this->assertEquals(2, $app2->token_number);

        // Cancel Token 2
        $app2->update(['status' => 'CANCELLED']);

        // 3. Next booking via PWA MUST receive Token 3 (Neither Token 1 nor Token 2 is reused)
        $app3 = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patientC->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $today,
            'session_type' => 'EVENING',
            'booking_source' => 'ONLINE',
        ]);
        $this->assertEquals(3, $app3->token_number);

        // Tokens are unique, monotonic, never reused, not guaranteed gapless
        $tokens = [$app1->token_number, $app2->token_number, $app3->token_number];
        $this->assertEquals([1, 2, 3], $tokens);
        $this->assertCount(3, array_unique($tokens));
    }
}
