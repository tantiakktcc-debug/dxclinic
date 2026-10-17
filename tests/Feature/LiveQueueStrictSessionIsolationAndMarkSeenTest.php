<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Livewire\Queue\LiveQueue;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\VisitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class LiveQueueStrictSessionIsolationAndMarkSeenTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinicA;
    protected Clinic $clinicB;
    protected Doctor $doctorA1;
    protected Doctor $doctorA2;
    protected Doctor $doctorB1;
    protected User $adminA;
    protected User $operatorA;
    protected User $doctorUserA1;
    protected User $doctorUserA2;
    protected User $adminB;
    protected Patient $patientA;
    protected Patient $patientB;

    protected function setUp(): void
    {
        parent::setUp();

        // Clinic A
        $this->clinicA = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'sharmacare',
            'name' => 'Sharma Care Clinic',
            'phone' => '9876543210',
            'email' => 'clinic@sharma.com',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        // Clinic B
        $this->clinicB = Clinic::create([
            'clinic_code' => 'NX0002',
            'public_slug' => 'arogyaclinic',
            'name' => 'Arogya Health Clinic',
            'phone' => '9876543211',
            'email' => 'clinic@arogya.com',
            'address' => 'Sigra',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'Professional Plan',
            'code' => 'PROFESSIONAL',
            'duration_months' => 12,
            'price' => 9999.00,
            'price_per_year' => 9999.00,
            'max_doctors' => 5,
            'features' => ['all'],
            'is_active' => true,
        ]);

        \App\Models\License::create([
            'clinic_id' => $this->clinicA->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'KEY-CLINIC-A'),
            'license_key_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString('KEY-CLINIC-A'),
            'license_key_prefix' => 'NXCL-AAAA',
            'status' => 'ACTIVE',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'max_doctors' => 5,
            'included_doctor_seats' => 5,
        ]);

        \App\Models\License::create([
            'clinic_id' => $this->clinicB->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'KEY-CLINIC-B'),
            'license_key_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString('KEY-CLINIC-B'),
            'license_key_prefix' => 'NXCL-BBBB',
            'status' => 'ACTIVE',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'max_doctors' => 5,
            'included_doctor_seats' => 5,
        ]);

        // Doctors for Clinic A
        $this->doctorA1 = Doctor::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'UP-MC-12345',
            'is_active' => true,
        ]);

        $this->doctorA2 = Doctor::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Pooja Sharma',
            'qualification' => 'MBBS, DNB',
            'registration_number' => 'UP-MC-67890',
            'is_active' => true,
        ]);

        // Doctor for Clinic B
        $this->doctorB1 = Doctor::create([
            'clinic_id' => $this->clinicB->id,
            'name' => 'Amitabh Verma',
            'qualification' => 'MBBS, MS',
            'registration_number' => 'UP-MC-11111',
            'is_active' => true,
        ]);

        // Users for Clinic A
        $this->adminA = User::create([
            'name' => 'Admin Sharma',
            'email' => 'admin@sharma.com',
            'password' => bcrypt('secret123'),
            'clinic_id' => $this->clinicA->id,
            'role' => 'ADMIN',
        ]);

        $this->operatorA = User::create([
            'name' => 'Operator Reception',
            'email' => 'operator@sharma.com',
            'password' => bcrypt('secret123'),
            'clinic_id' => $this->clinicA->id,
            'role' => 'OPERATOR',
        ]);

        $this->doctorUserA1 = User::create([
            'name' => 'Dr. Rajesh Sharma',
            'email' => 'doctor1@sharma.com',
            'password' => bcrypt('secret123'),
            'clinic_id' => $this->clinicA->id,
            'doctor_id' => $this->doctorA1->id,
            'role' => 'DOCTOR',
        ]);

        $this->doctorUserA2 = User::create([
            'name' => 'Dr. Pooja Sharma',
            'email' => 'doctor2@sharma.com',
            'password' => bcrypt('secret123'),
            'clinic_id' => $this->clinicA->id,
            'doctor_id' => $this->doctorA2->id,
            'role' => 'DOCTOR',
        ]);

        // User for Clinic B
        $this->adminB = User::create([
            'name' => 'Admin Arogya',
            'email' => 'admin@arogya.com',
            'password' => bcrypt('secret123'),
            'clinic_id' => $this->clinicB->id,
            'role' => 'ADMIN',
        ]);

        // Patients
        $this->patientA = Patient::create([
            'clinic_id' => $this->clinicA->id,
            'patient_id' => 'DXP100001',
            'name' => 'Raj Kumar',
            'sex' => 'MALE',
            'age' => 35,
            'mobile' => '9876543210',
            'city' => 'Varanasi',
            'address' => 'Bhelupur',
        ]);

        $this->patientB = Patient::create([
            'clinic_id' => $this->clinicB->id,
            'patient_id' => 'DXP200001',
            'name' => 'Sita Verma',
            'sex' => 'FEMALE',
            'age' => 28,
            'mobile' => '9876543212',
            'city' => 'Varanasi',
            'address' => 'Sigra',
        ]);
    }

    protected function createAppointment(Clinic $clinic, Doctor $doctor, Patient $patient, int $token, string $status = 'BOOKED'): Appointment
    {
        return Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'token_number' => $token,
            'formatted_token' => sprintf('%03d', $token),
            'secure_token' => bin2hex(random_bytes(16)),
            'status' => $status,
            'booking_source' => 'WHATSAPP',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);
    }

    protected function markAppointmentFeePaid(Appointment $app): void
    {
        $visit = Visit::firstOrCreate(
            ['appointment_id' => $app->id],
            [
                'clinic_id' => $app->clinic_id,
                'patient_id' => $app->patient_id,
                'doctor_id' => $app->doctor_id,
                'visit_number' => 'V-' . uniqid(),
                'visit_date' => $app->appointment_date,
                'session_type' => $app->session_type ?? 'MORNING',
                'status' => 'WAITING',
            ]
        );

        $bill = \App\Models\ConsultationBill::create([
            'clinic_id' => $app->clinic_id,
            'visit_id' => $visit->id,
            'patient_id' => $app->patient_id,
            'appointment_id' => $app->id,
            'doctor_id' => $app->doctor_id,
            'bill_number' => 'BILL-' . uniqid(),
            'bill_date' => now()->toDateString(),
            'total_amount' => 500.00,
            'discount_amount' => 0.00,
            'net_amount' => 500.00,
            'status' => 'PAID',
        ]);

        \App\Models\Payment::create([
            'clinic_id' => $app->clinic_id,
            'payment_number' => 'PAY-' . uniqid(),
            'consultation_bill_id' => $bill->id,
            'amount' => 500.00,
            'payment_method' => 'CASH',
            'status' => 'SETTLED',
            'settled_at' => now(),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_live_queue(): void
    {
        $response = $this->get("/clinic/{$this->clinicA->public_slug}/queue/live");
        $this->assertTrue(in_array($response->status(), [302, 401], true));
    }

    public function test_authenticated_admin_can_access_own_clinic_queue(): void
    {
        $response = $this->actingAs($this->adminA)->get("/clinic/{$this->clinicA->public_slug}/queue/live");
        $response->assertStatus(200);
        $response->assertSee('LIVE QUEUE');
    }

    public function test_authenticated_operator_can_access_own_clinic_queue(): void
    {
        $response = $this->actingAs($this->operatorA)->get("/clinic/{$this->clinicA->public_slug}/queue/live");
        $response->assertStatus(200);
    }

    public function test_authenticated_doctor_can_access_own_clinic_queue(): void
    {
        $response = $this->actingAs($this->doctorUserA1)->get("/clinic/{$this->clinicA->public_slug}/queue/live");
        $response->assertStatus(200);
    }

    public function test_doctor_sees_only_own_appointments_and_cannot_view_another_doctors_appointments(): void
    {
        // App 1 for Doctor A1, App 2 for Doctor A2
        $app1 = $this->createAppointment($this->clinicA, $this->doctorA1, $this->patientA, 1);
        $app2 = $this->createAppointment($this->clinicA, $this->doctorA2, $this->patientA, 2);

        $this->actingAs($this->doctorUserA1);

        $testComp = Livewire::test(LiveQueue::class, ['public_slug' => $this->clinicA->public_slug])
            ->assertStatus(200)
            ->assertSee($app1->formatted_token)
            ->assertDontSee($app2->formatted_token);

        // Attempting to filter by Doctor A2 is ignored for DOCTOR role
        $testComp->call('setDoctorFilter', $this->doctorA2->id)
            ->assertSee($app1->formatted_token)
            ->assertDontSee($app2->formatted_token);
    }

    public function test_cross_clinic_access_is_rejected_with_403_forbidden(): void
    {
        // Admin of Clinic A attempts to access Clinic B's Live Queue
        $response = $this->actingAs($this->adminA)->get("/clinic/{$this->clinicB->public_slug}/queue/live");
        $response->assertStatus(403);
    }

    public function test_cross_clinic_component_mounting_is_rejected_with_403(): void
    {
        $this->actingAs($this->adminA);

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinicB->public_slug])
            ->assertStatus(403);
    }

    public function test_admin_can_mark_seen_own_clinic_appointment(): void
    {
        Event::fake([QueueUpdated::class]);

        $app = $this->createAppointment($this->clinicA, $this->doctorA1, $this->patientA, 1);
        $this->markAppointmentFeePaid($app);

        $this->actingAs($this->adminA);

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinicA->public_slug])
            ->assertSee('NOT SEEN')
            ->assertSee('Mark Seen')
            ->call('markSeen', $app->id)
            ->assertHasNoErrors()
            ->assertSee('SEEN');

        // Verify authoritative database state
        $app->refresh();
        $this->assertEquals('IN_PROGRESS', $app->status);

        $visit = Visit::where('appointment_id', $app->id)->first();
        $this->assertNotNull($visit);
        $this->assertEquals('IN_PROGRESS', $visit->status);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinicA->id,
            'user_id' => $this->adminA->id,
            'action' => 'DOCTOR_CONSULTATION_STARTED',
            'entity_type' => Visit::class,
            'entity_id' => $visit->id,
        ]);

        Event::assertDispatched(QueueUpdated::class);
    }

    public function test_operator_can_mark_seen_own_clinic_appointment(): void
    {
        Event::fake([QueueUpdated::class]);

        $app = $this->createAppointment($this->clinicA, $this->doctorA1, $this->patientA, 2);
        $this->markAppointmentFeePaid($app);

        $this->actingAs($this->operatorA);

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinicA->public_slug])
            ->call('markSeen', $app->id)
            ->assertHasNoErrors();

        $app->refresh();
        $this->assertEquals('IN_PROGRESS', $app->status);
    }

    public function test_doctor_can_mark_seen_own_assigned_appointment(): void
    {
        Event::fake([QueueUpdated::class]);

        $app = $this->createAppointment($this->clinicA, $this->doctorA1, $this->patientA, 3);
        $this->markAppointmentFeePaid($app);

        $this->actingAs($this->doctorUserA1);

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinicA->public_slug])
            ->call('markSeen', $app->id)
            ->assertHasNoErrors();

        $app->refresh();
        $this->assertEquals('IN_PROGRESS', $app->status);
    }

    public function test_doctor_cannot_mark_seen_another_doctors_appointment(): void
    {
        $appOfDoc2 = $this->createAppointment($this->clinicA, $this->doctorA2, $this->patientA, 4);

        $this->actingAs($this->doctorUserA1);

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinicA->public_slug])
            ->call('markSeen', $appOfDoc2->id)
            ->assertStatus(403);
    }

    public function test_user_cannot_mark_seen_another_clinics_appointment(): void
    {
        $appOfClinicB = $this->createAppointment($this->clinicB, $this->doctorB1, $this->patientB, 1);

        $this->actingAs($this->adminA);

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinicA->public_slug])
            ->call('markSeen', $appOfClinicB->id)
            ->assertStatus(404);
    }

    public function test_mark_seen_action_is_idempotent(): void
    {
        $app = $this->createAppointment($this->clinicA, $this->doctorA1, $this->patientA, 5);
        $this->markAppointmentFeePaid($app);

        $this->actingAs($this->adminA);

        $testComp = Livewire::test(LiveQueue::class, ['public_slug' => $this->clinicA->public_slug])
            ->call('markSeen', $app->id)
            ->assertHasNoErrors();

        $visitsCountAfterFirst = Visit::where('appointment_id', $app->id)->count();
        $this->assertEquals(1, $visitsCountAfterFirst);

        // Second invocation
        $testComp->call('markSeen', $app->id)
            ->assertHasNoErrors();

        $visitsCountAfterSecond = Visit::where('appointment_id', $app->id)->count();
        $this->assertEquals(1, $visitsCountAfterSecond); // Zero duplicate visits
    }

    public function test_logged_out_user_cannot_perform_live_queue_actions(): void
    {
        $app = $this->createAppointment($this->clinicA, $this->doctorA1, $this->patientA, 6);

        // Not acting as any user
        auth()->logout();

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinicA->public_slug])
            ->assertStatus(401);
    }

    public function test_reauthentication_establishes_correct_tenant_context(): void
    {
        // 1. First act as Admin A on Clinic A
        $this->actingAs($this->adminA);
        $resA = $this->get("/clinic/{$this->clinicA->public_slug}/queue/live");
        $resA->assertStatus(200);

        // 2. Logout Admin A
        auth()->logout();

        // 3. Login Admin B on Clinic B
        $this->actingAs($this->adminB);
        $resB = $this->get("/clinic/{$this->clinicB->public_slug}/queue/live");
        $resB->assertStatus(200);

        // 4. Admin B cannot access Clinic A
        $resForbidden = $this->get("/clinic/{$this->clinicA->public_slug}/queue/live");
        $resForbidden->assertStatus(403);
    }
}

