<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClinicClock;
use App\Services\QueueViewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LiveQueueFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic1;
    protected Clinic $clinic2;
    protected Doctor $doctor1;
    protected Doctor $doctor2;
    protected User $adminUser;
    protected User $operatorUser;
    protected User $doctorUser1;
    protected User $foreignAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic1 = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'sharmacare',
            'name' => 'Sharma Care Clinic',
            'phone' => '9876543210',
            'email' => 'clinic1@test.com',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $this->clinic2 = Clinic::create([
            'clinic_code' => 'NX0002',
            'public_slug' => 'arogya',
            'name' => 'Arogya Clinic',
            'phone' => '9876543211',
            'email' => 'clinic2@test.com',
            'address' => 'Sigra',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $this->doctor1 = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'UP-MC-11111',
            'specialization' => 'Cardiologist',
            'contact_number' => '9876543210',
            'is_active' => true,
        ]);

        $this->doctor2 = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Priya Das',
            'qualification' => 'MBBS, DCH',
            'registration_number' => 'UP-MC-22222',
            'specialization' => 'Pediatrician',
            'contact_number' => '9876543212',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Admin User',
            'email' => 'admin@clinic1.com',
            'role' => 'ADMIN',
            'password' => Hash::make('password123'),
            'status' => 'ACTIVE',
        ]);

        $this->operatorUser = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Operator User',
            'email' => 'operator@clinic1.com',
            'role' => 'OPERATOR',
            'password' => Hash::make('password123'),
            'status' => 'ACTIVE',
        ]);

        $this->doctorUser1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr Rajesh',
            'email' => 'doctor1@clinic1.com',
            'role' => 'DOCTOR',
            'doctor_id' => $this->doctor1->id,
            'password' => Hash::make('password123'),
            'status' => 'ACTIVE',
        ]);

        $this->foreignAdminUser = User::create([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Foreign Admin',
            'email' => 'admin@clinic2.com',
            'role' => 'ADMIN',
            'password' => Hash::make('password123'),
            'status' => 'ACTIVE',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_live_queue(): void
    {
        $response = $this->get(route('queue.live', ['public_slug' => $this->clinic1->public_slug]));
        $response->assertRedirect(route('login'));
    }

    public function test_cross_clinic_access_is_forbidden(): void
    {
        // User from Clinic 2 trying to access Clinic 1's Live Queue
        $response = $this->actingAs($this->foreignAdminUser)
            ->get(route('queue.live', ['public_slug' => $this->clinic1->public_slug]));

        $response->assertStatus(403);
    }

    public function test_admin_and_operator_can_access_live_queue(): void
    {
        $r1 = $this->actingAs($this->adminUser)
            ->get(route('queue.live', ['public_slug' => $this->clinic1->public_slug]));
        $r1->assertStatus(200);

        $r2 = $this->actingAs($this->operatorUser)
            ->get(route('queue.live', ['public_slug' => $this->clinic1->public_slug]));
        $r2->assertStatus(200);
    }

    public function test_live_queue_orders_strictly_by_token_number_ascending(): void
    {
        $today = ClinicClock::today()->toDateString();

        $patientA = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-00101',
            'name' => 'Patient Alpha',
            'age' => 30,
            'sex' => 'MALE',
            'mobile' => '9870000001',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);
        $patientB = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-00102',
            'name' => 'Patient Beta',
            'age' => 28,
            'sex' => 'FEMALE',
            'mobile' => '9870000002',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);
        $patientC = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-00103',
            'name' => 'Patient Gamma',
            'age' => 35,
            'sex' => 'MALE',
            'mobile' => '9870000003',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);

        // Insert appointments with out-of-order creation times to verify token ordering (Section 38)
        Appointment::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patientB->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
            'token_number' => 2,
            'formatted_token' => '002',
            'status' => 'BOOKED',
            'secure_token' => 'sec_token_002',
            'created_at' => now()->subMinutes(10),
        ]);

        Appointment::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patientC->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'WHATSAPP',
            'token_number' => 3,
            'formatted_token' => '003',
            'status' => 'BOOKED',
            'secure_token' => 'sec_token_003',
            'created_at' => now()->subMinutes(20), // Earlier created, but higher token
        ]);

        Appointment::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patientA->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 1,
            'formatted_token' => '001',
            'status' => 'BOOKED',
            'secure_token' => 'sec_token_001',
            'created_at' => now()->subMinutes(5), // Latest created, but lowest token
        ]);

        $service = app(QueueViewService::class);
        $data = $service->getQueueData($this->clinic1, $today);

        $tokens = $data['items']->pluck('token_number')->toArray();
        $this->assertEquals([1, 2, 3], $tokens);

        // Sources displayed
        $sources = $data['items']->pluck('booking_source')->toArray();
        $this->assertEquals(['CLINIC', 'ONLINE', 'WHATSAPP'], $sources);
    }

    public function test_doctor_scope_isolates_appointments_to_assigned_doctor(): void
    {
        $today = ClinicClock::today()->toDateString();

        $patient1 = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-00201',
            'name' => 'Doc1 Patient',
            'age' => 40,
            'sex' => 'MALE',
            'mobile' => '9870000011',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);
        $patient2 = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-00202',
            'name' => 'Doc2 Patient',
            'age' => 32,
            'sex' => 'FEMALE',
            'mobile' => '9870000012',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);

        // Appointment for Doctor 1
        Appointment::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient1->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 1,
            'formatted_token' => '001',
            'status' => 'BOOKED',
            'secure_token' => 'sec_doc1',
        ]);

        // Appointment for Doctor 2
        Appointment::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient2->id,
            'doctor_id' => $this->doctor2->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'WHATSAPP',
            'token_number' => 2,
            'formatted_token' => '002',
            'status' => 'BOOKED',
            'secure_token' => 'sec_doc2',
        ]);

        $service = app(QueueViewService::class);

        // 1. Admin sees both appointments (total = 2)
        $adminData = $service->getQueueData($this->clinic1, $today, 'ALL', null, $this->adminUser);
        $this->assertEquals(2, $adminData['items']->count());

        // 2. Doctor 1 sees ONLY their own appointment (total = 1)
        $docData = $service->getQueueData($this->clinic1, $today, 'ALL', null, $this->doctorUser1);
        $this->assertEquals(1, $docData['items']->count());
        $this->assertEquals($this->doctor1->id, $docData['items']->first()['doctor_id']);
    }

    public function test_computed_status_reflects_visit_arrival_without_db_mutation(): void
    {
        $today = ClinicClock::today()->toDateString();
        $patient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-00301',
            'name' => 'Arrival Test Patient',
            'age' => 25,
            'sex' => 'MALE',
            'mobile' => '9870000031',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);

        $app = Appointment::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'WHATSAPP',
            'token_number' => 1,
            'formatted_token' => '001',
            'status' => 'BOOKED',
            'secure_token' => 'sec_app_arrival',
        ]);

        $service = app(QueueViewService::class);

        // Before arrival -> BOOKED
        $this->assertEquals('BOOKED', $service->computeDisplayStatus($app));

        // Create visit marked ARRIVED / REGISTERED
        Visit::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_id' => $app->id,
            'visit_date' => $today,
            'session_type' => 'MORNING',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'REGISTERED',
            'arrived_at' => now(),
        ]);

        $app->refresh();
        $this->assertEquals('ARRIVED', $service->computeDisplayStatus($app));
    }

    public function test_queue_updated_event_broadcasts_on_private_clinic_channel(): void
    {
        Event::fake([QueueUpdated::class]);

        $today = ClinicClock::today()->toDateString();
        $patient = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'DXP-00401',
            'name' => 'Broadcast Test Patient',
            'age' => 29,
            'sex' => 'MALE',
            'mobile' => '9870000041',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
        ]);

        $appointment = app(\App\Services\AppointmentService::class)->bookAppointment([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_date' => $today,
            'session_type' => 'MORNING',
            'booking_source' => 'WHATSAPP',
        ]);

        Event::assertDispatched(QueueUpdated::class, function (QueueUpdated $e) use ($appointment) {
            $channels = $e->broadcastOn();
            $this->assertEquals('private-clinic.' . $this->clinic1->id . '.queue', $channels[0]->name);
            $this->assertEquals($appointment->id, $e->appointmentId);
            $this->assertEquals(1, $e->tokenNumber);
            return true;
        });
    }
}
