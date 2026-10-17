<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Livewire\Queue\LiveQueue;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\License;
use App\Models\Patient;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Visit;
use App\Services\VisitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class LiveQueueMarkSeenAuthoritativeFixTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic1;
    protected Clinic $clinic2;
    protected Doctor $doctor1;
    protected Doctor $doctor2;
    protected User $adminUser;
    protected User $doctorUser1;
    protected User $doctorUser2;
    protected User $operatorUser;
    protected Patient $patient1;
    protected Appointment $appointment1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic1 = Clinic::create([
            'clinic_code' => 'NX7001',
            'public_slug' => 'clinic-one',
            'name' => 'Clinic One',
            'phone' => '9000000001',
            'email' => 'one@clinic.com',
            'address' => 'Station Road',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $this->clinic2 = Clinic::create([
            'clinic_code' => 'NX7002',
            'public_slug' => 'clinic-two',
            'name' => 'Clinic Two',
            'phone' => '9000000002',
            'email' => 'two@clinic.com',
            'address' => 'Civil Lines',
            'city' => 'Allahabad',
            'is_active' => true,
        ]);

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

        License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'KEY-1'),
            'license_key_encrypted' => Crypt::encryptString('KEY-1'),
            'license_key_prefix' => 'NXCL-1111',
            'status' => 'ACTIVE',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'max_doctors' => 5,
            'included_doctor_seats' => 5,
        ]);

        $this->doctor1 = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'REG-DOC-1',
            'is_active' => true,
        ]);

        $this->doctor2 = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Pooja Sharma',
            'qualification' => 'MBBS, MS',
            'registration_number' => 'REG-DOC-2',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin Clinic One',
            'email' => 'admin@clinic1.com',
            'password' => bcrypt('secret'),
            'clinic_id' => $this->clinic1->id,
            'role' => 'ADMIN',
        ]);

        $this->operatorUser = User::create([
            'name' => 'Operator Clinic One',
            'email' => 'operator@clinic1.com',
            'password' => bcrypt('secret'),
            'clinic_id' => $this->clinic1->id,
            'role' => 'OPERATOR',
        ]);

        $this->doctorUser1 = User::create([
            'name' => 'Dr. Rajesh Sharma',
            'email' => 'rajesh@clinic1.com',
            'password' => bcrypt('secret'),
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor1->id,
            'role' => 'DOCTOR',
        ]);

        $this->doctorUser2 = User::create([
            'name' => 'Dr. Pooja Sharma',
            'email' => 'pooja@clinic1.com',
            'password' => bcrypt('secret'),
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor2->id,
            'role' => 'DOCTOR',
        ]);

        $this->patient1 = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'P-7001',
            'name' => 'Sunil Kumar',
            'mobile' => '9111111111',
            'sex' => 'MALE',
            'age' => 35,
            'city' => 'Varanasi',
            'address' => 'Station Road',
        ]);

        $this->appointment1 = $this->createAppointment($this->clinic1, $this->doctor1, $this->patient1, 41, 'ARRIVED');
    }

    protected function createAppointment(Clinic $clinic, Doctor $doctor, Patient $patient, int $token, string $status = 'BOOKED'): Appointment
    {
        $app = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'token_number' => $token,
            'formatted_token' => sprintf('%03d', $token),
            'secure_token' => bin2hex(random_bytes(16)),
            'status' => $status,
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        $visit = Visit::create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $app->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'WAITING',
            'token_number' => $token,
        ]);

        $bill = \App\Models\ConsultationBill::create([
            'clinic_id' => $clinic->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'appointment_id' => $app->id,
            'doctor_id' => $doctor->id,
            'bill_number' => 'BILL-' . uniqid(),
            'bill_date' => now()->toDateString(),
            'total_amount' => 500.00,
            'discount_amount' => 0.00,
            'net_amount' => 500.00,
            'status' => 'PAID',
        ]);

        \App\Models\Payment::create([
            'clinic_id' => $clinic->id,
            'payment_number' => 'PAY-' . uniqid(),
            'consultation_bill_id' => $bill->id,
            'amount' => 500.00,
            'payment_method' => 'CASH',
            'status' => 'SETTLED',
            'settled_at' => now(),
        ]);

        return $app;
    }

    public function test_mark_seen_transitions_visit_and_appointment_to_in_progress_with_started_at(): void
    {
        $this->actingAs($this->adminUser);

        Event::fake([QueueUpdated::class]);

        $component = Livewire::test(LiveQueue::class, ['public_slug' => $this->clinic1->public_slug]);
        $component->call('markSeen', $this->appointment1->id);

        $this->appointment1->refresh();
        $this->assertEquals('IN_PROGRESS', $this->appointment1->status);

        $visit = Visit::where('appointment_id', $this->appointment1->id)->first();
        $this->assertNotNull($visit);
        $this->assertEquals('IN_PROGRESS', $visit->status);
        $this->assertNotNull($visit->started_at);
        $this->assertEquals($this->clinic1->id, $visit->clinic_id);
        $this->assertEquals($this->doctor1->id, $visit->doctor_id);

        Event::assertDispatched(QueueUpdated::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DOCTOR_CONSULTATION_STARTED',
            'entity_type' => Visit::class,
            'entity_id' => $visit->id,
        ]);
    }

    public function test_mark_seen_is_strictly_idempotent(): void
    {
        $this->actingAs($this->adminUser);

        $component = Livewire::test(LiveQueue::class, ['public_slug' => $this->clinic1->public_slug]);

        // First click
        $component->call('markSeen', $this->appointment1->id);
        $visitCountFirst = Visit::where('appointment_id', $this->appointment1->id)->count();
        $this->assertEquals(1, $visitCountFirst);

        $visit = Visit::where('appointment_id', $this->appointment1->id)->first();
        $firstStartedAt = $visit->started_at;

        // Second click
        $component->call('markSeen', $this->appointment1->id);
        $visitCountSecond = Visit::where('appointment_id', $this->appointment1->id)->count();

        $this->assertEquals(1, $visitCountSecond, 'Repeated click must not create duplicate visit records.');
        $visit->refresh();
        $this->assertEquals($firstStartedAt->timestamp, $visit->started_at->timestamp, 'Started at must be preserved.');
    }

    public function test_doctor_can_mark_seen_own_appointment_but_forbidden_on_other_doctor_appointment(): void
    {
        // Doctor 1 marking seen own assigned appointment -> Allowed
        $this->actingAs($this->doctorUser1);
        $component = Livewire::test(LiveQueue::class, ['public_slug' => $this->clinic1->public_slug]);
        $component->call('markSeen', $this->appointment1->id);

        $this->appointment1->refresh();
        $this->assertEquals('IN_PROGRESS', $this->appointment1->status);

        // Appointment assigned to Doctor 2
        $appointment2 = $this->createAppointment($this->clinic1, $this->doctor2, $this->patient1, 42, 'ARRIVED');

        // Doctor 1 attempts to mark seen Doctor 2's appointment -> Denied with 403
        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinic1->public_slug])
            ->call('markSeen', $appointment2->id)
            ->assertStatus(403);

        $appointment2->refresh();
        $this->assertEquals('ARRIVED', $appointment2->status, 'Other doctor appointment must not be modified.');
    }

    public function test_cross_clinic_mark_seen_is_denied(): void
    {
        $this->actingAs($this->adminUser);

        // Create appointment in Clinic 2
        $patient2 = Patient::create([
            'clinic_id' => $this->clinic2->id,
            'patient_id' => 'P-7002',
            'name' => 'Other Patient',
            'mobile' => '9222222222',
            'sex' => 'FEMALE',
            'age' => 28,
            'city' => 'Allahabad',
            'address' => 'Civil Lines',
        ]);

        $doctorInClinic2 = Doctor::create([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Dr. Clinic Two Doc',
            'qualification' => 'MBBS',
            'registration_number' => 'REG-DOC-99',
            'is_active' => true,
        ]);

        $appointmentInClinic2 = $this->createAppointment($this->clinic2, $doctorInClinic2, $patient2, 99, 'ARRIVED');

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinic1->public_slug])
            ->call('markSeen', $appointmentInClinic2->id)
            ->assertStatus(404);
    }
}
