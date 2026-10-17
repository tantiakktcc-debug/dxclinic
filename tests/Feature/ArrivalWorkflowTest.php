<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Vital;
use App\Services\AppointmentService;
use App\Services\VisitService;
use App\Services\VitalService;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArrivalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Doctor $doctor;
    private AppointmentService $appointmentService;
    private VisitService $visitService;
    private VitalService $vitalService;

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

        $this->appointmentService = app(AppointmentService::class);
        $this->visitService = app(VisitService::class);
        $this->vitalService = app(VitalService::class);
    }

    public function test_marks_arrival_and_creates_encounter_visit(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-001',
            'name' => 'Arun Pandey',
            'mobile' => '9876543210',
            'age' => 42,
            'sex' => 'MALE',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
        ]);

        $appointment = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);

        $this->assertEquals('BOOKED', $appointment->status);

        // Mark arrival
        $visit = $this->visitService->markArrival($appointment);

        $this->assertNotNull($visit);
        $this->assertEquals($appointment->id, $visit->appointment_id);
        $this->assertEquals($patient->id, $visit->patient_id);
        $this->assertEquals('REGISTERED', $visit->status);

        $appointment->refresh();
        $this->assertEquals('ARRIVED', $appointment->status);
    }

    public function test_arrival_is_strictly_idempotent_on_repeated_calls(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-002',
            'name' => 'Suman Devi',
            'mobile' => '9876543211',
            'age' => 36,
            'sex' => 'FEMALE',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
        ]);

        $appointment = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'TELEPHONIC',
        ]);

        // First click
        $visit1 = $this->visitService->markArrival($appointment);
        // Second click (accidental double-click)
        $visit2 = $this->visitService->markArrival($appointment);

        $this->assertEquals($visit1->id, $visit2->id);
        $this->assertEquals(1, Visit::where('appointment_id', $appointment->id)->count());
    }

    public function test_records_vitals_and_updates_idempotently(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-003',
            'name' => 'Ramesh Verma',
            'mobile' => '9876543212',
            'age' => 55,
            'sex' => 'MALE',
            'address' => 'Godowlia',
            'city' => 'Varanasi',
        ]);

        $appointment = $this->appointmentService->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2026-09-28',
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        $visit = $this->visitService->markArrival($appointment);

        // Record initial vitals
        $vital = $this->vitalService->recordVitals($visit, [
            'bp_systolic' => 120,
            'bp_diastolic' => 80,
            'pulse' => 74,
            'weight' => 68.5,
            'height' => 172.0,
            'temperature' => 98.4,
            'spo2' => 99,
            'rbs' => 110.0,
        ]);

        $this->assertEquals(120, $vital->bp_systolic);
        $this->assertEquals(80, $vital->bp_diastolic);
        $this->assertEquals(74, $vital->pulse);
        $this->assertEquals(68.5, (float)$vital->weight);
        $this->assertEquals(110.0, $vital->blood_sugar);
        $this->assertEquals(1, Vital::where('visit_id', $visit->id)->count());

        // Update vitals (re-measurement)
        $updatedVital = $this->vitalService->recordVitals($visit, [
            'bp_systolic' => 124,
            'bp_diastolic' => 82,
            'pulse' => 76,
            'weight' => 68.5,
            'height' => 172.0,
            'temperature' => 98.6,
            'spo2' => 99,
            'rbs' => 112.0,
        ]);

        $this->assertEquals($vital->id, $updatedVital->id);
        $this->assertEquals(124, $updatedVital->bp_systolic);
        $this->assertEquals(1, Vital::where('visit_id', $visit->id)->count());
    }
}
