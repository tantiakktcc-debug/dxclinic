<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Livewire\Operator\ArrivalQueue;
use App\Livewire\Queue\LiveQueue;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\Doctor;
use App\Models\License;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Visit;
use App\Services\FeeStatusResolver;
use App\Services\VisitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class FeeGateMarkSeenTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected User $adminUser;
    protected User $operatorUser;
    protected User $doctorUser;
    protected Patient $patient;
    protected FeeStatusResolver $feeResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->feeResolver = app(FeeStatusResolver::class);

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX8001',
            'public_slug' => 'sharma-care-test',
            'name' => 'Sharma Care Clinic',
            'phone' => '9876543210',
            'email' => 'admin@sharmacare.local',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Pro Plan',
            'code' => 'PRO',
            'duration_months' => 12,
            'price' => 9999.00,
            'price_per_year' => 9999.00,
            'max_doctors' => 5,
            'features' => ['all'],
            'is_active' => true,
        ]);

        License::create([
            'clinic_id' => $this->clinic->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'FEE-TEST-KEY'),
            'license_key_encrypted' => Crypt::encryptString('FEE-TEST-KEY'),
            'license_key_prefix' => 'NXCL-8888',
            'status' => 'ACTIVE',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'max_doctors' => 5,
            'included_doctor_seats' => 5,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Amit Verma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'REG-FEE-01',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Clinic Admin',
            'email' => 'admin@sharma.local',
            'password' => bcrypt('password123'),
            'clinic_id' => $this->clinic->id,
            'role' => 'ADMIN',
        ]);

        $this->operatorUser = User::create([
            'name' => 'Clinic Operator',
            'email' => 'operator@sharma.local',
            'password' => bcrypt('password123'),
            'clinic_id' => $this->clinic->id,
            'role' => 'OPERATOR',
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dr. Amit Verma',
            'email' => 'amit@sharma.local',
            'password' => bcrypt('password123'),
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'role' => 'DOCTOR',
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-8001',
            'name' => 'Ramesh Kumar',
            'mobile' => '9888888888',
            'sex' => 'MALE',
            'age' => 40,
            'city' => 'Varanasi',
            'address' => 'Assi Ghat',
        ]);
    }

    protected function makeAppointment(int $token = 1, string $status = 'ARRIVED'): Appointment
    {
        return Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'token_number' => $token,
            'formatted_token' => sprintf('%03d', $token),
            'secure_token' => bin2hex(random_bytes(16)),
            'status' => $status,
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);
    }

    protected function createVisitWithBill(Appointment $appointment, float $amount = 500.00, string $status = 'PENDING'): ConsultationBill
    {
        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'WAITING',
            'token_number' => $appointment->token_number,
        ]);

        return ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'visit_id' => $visit->id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $appointment->doctor_id,
            'bill_number' => 'BILL-' . uniqid(),
            'bill_date' => now()->toDateString(),
            'total_amount' => $amount,
            'discount_amount' => 0.00,
            'net_amount' => $amount,
            'status' => $status,
        ]);
    }

    protected function recordPayment(ConsultationBill $bill, float $amount = 500.00, string $method = 'UPI'): Payment
    {
        return Payment::create([
            'clinic_id' => $this->clinic->id,
            'payment_number' => 'PAY-' . uniqid(),
            'consultation_bill_id' => $bill->id,
            'amount' => $amount,
            'payment_method' => $method,
            'status' => 'SETTLED',
            'settled_at' => now(),
        ]);
    }

    public function test_unpaid_appointment_resolves_fee_as_unpaid(): void
    {
        $appointment = $this->makeAppointment(1, 'ARRIVED');
        $this->assertEquals('UNPAID', $this->feeResolver->resolve($appointment));
    }

    public function test_unpaid_appointment_blocks_backend_visit_service_mark_seen(): void
    {
        $appointment = $this->makeAppointment(2, 'ARRIVED');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(VisitService::class)->markSeen($appointment, $this->adminUser);
    }

    public function test_unpaid_appointment_blocks_live_queue_mark_seen_for_admin_operator_doctor(): void
    {
        $appointment = $this->makeAppointment(3, 'ARRIVED');

        foreach ([$this->adminUser, $this->operatorUser, $this->doctorUser] as $user) {
            $this->actingAs($user);

            Livewire::test(LiveQueue::class, ['public_slug' => $this->clinic->public_slug])
                ->call('markSeen', $appointment->id)
                ->assertStatus(422);

            $this->assertDatabaseMissing('visits', [
                'appointment_id' => $appointment->id,
            ]);
            $this->assertEquals('ARRIVED', $appointment->fresh()->status);
        }
    }

    public function test_partial_payment_blocks_mark_seen(): void
    {
        $appointment = $this->makeAppointment(4, 'ARRIVED');
        $bill = $this->createVisitWithBill($appointment, 500.00, 'PENDING');
        $this->recordPayment($bill, 200.00, 'CASH');

        $this->assertEquals('PARTIAL', $this->feeResolver->resolve($appointment->fresh()));

        $this->actingAs($this->adminUser);

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinic->public_slug])
            ->call('markSeen', $appointment->id)
            ->assertStatus(422);

        $this->assertEquals('WAITING', $appointment->visit->fresh()->status);
    }

    public function test_paid_appointment_allows_mark_seen_and_creates_in_progress_visit(): void
    {
        $appointment = $this->makeAppointment(5, 'ARRIVED');
        $bill = $this->createVisitWithBill($appointment, 500.00, 'PAID');
        $this->recordPayment($bill, 500.00, 'UPI');

        $this->assertEquals('PAID', $this->feeResolver->resolve($appointment->fresh()));

        Event::fake([QueueUpdated::class]);

        $this->actingAs($this->adminUser);

        Livewire::test(LiveQueue::class, ['public_slug' => $this->clinic->public_slug])
            ->call('markSeen', $appointment->id)
            ->assertOk();

        $appointment->refresh();
        $this->assertEquals('IN_PROGRESS', $appointment->status);

        $visit = Visit::where('appointment_id', $appointment->id)->first();
        $this->assertNotNull($visit);
        $this->assertEquals('IN_PROGRESS', $visit->status);
        $this->assertNotNull($visit->started_at);

        Event::assertDispatched(QueueUpdated::class);
    }

    public function test_operator_arrival_queue_shows_authoritative_fee_status_and_actions(): void
    {
        $unpaidAppointment = $this->makeAppointment(6, 'BOOKED');
        $paidAppointment = $this->makeAppointment(7, 'ARRIVED');
        $bill = $this->createVisitWithBill($paidAppointment, 500.00, 'PAID');
        $this->recordPayment($bill, 500.00, 'CASH');

        $this->actingAs($this->operatorUser);

        Livewire::test(ArrivalQueue::class, ['public_slug' => $this->clinic->public_slug])
            ->assertSee('UNPAID')
            ->assertSee('PAID')
            ->assertSeeHtml('Fee & Pad')
            ->assertSee('Mark Seen');
    }

    public function test_doctor_seat_request_workflow_creates_pending_license_request_without_immediate_capacity_jump(): void
    {
        $initialCapacity = $this->clinic->totalDoctorCapacity();

        $this->actingAs($this->adminUser);

        Livewire::test(\App\Livewire\Settings\LicenseSubscriptionManager::class)
            ->set('addonSeatsCount', 1)
            ->call('requestDoctorSeatAddon')
            ->assertSet('purchaseErrorMessage', null)
            ->assertSee('Additional doctor seat request submitted');

        // Assert capacity remains unchanged until Superadmin fulfills it
        $this->assertEquals($initialCapacity, $this->clinic->fresh()->totalDoctorCapacity());

        // Assert request was recorded in database with status PENDING and type DOCTOR_SEAT_ADDON
        $seatRequest = \App\Models\LicenseRequest::where('clinic_id', $this->clinic->id)
            ->where('request_type', 'DOCTOR_SEAT_ADDON')
            ->first();

        $this->assertNotNull($seatRequest);
        $this->assertEquals('PENDING', $seatRequest->status);
        $this->assertEquals(1, $seatRequest->requested_doctor_count);

        // Assert duplicate request is blocked
        Livewire::test(\App\Livewire\Settings\LicenseSubscriptionManager::class)
            ->set('addonSeatsCount', 1)
            ->call('requestDoctorSeatAddon')
            ->assertSee('already exists');
    }

    public function test_superadmin_can_fulfill_doctor_seat_addon_and_increase_capacity(): void
    {
        $initialCapacity = $this->clinic->totalDoctorCapacity();

        // 1. Submit request as clinic admin
        $this->actingAs($this->adminUser);
        $request = \App\Services\LicenseRequestService::submitDoctorSeatAddonRequest(
            ['addon_seats_count' => 1],
            $this->adminUser,
            $this->clinic
        );

        $this->assertEquals('PENDING', $request->status);
        $this->assertEquals($initialCapacity, $this->clinic->fresh()->totalDoctorCapacity());

        // 2. Fulfill request as superadmin developer user
        $devUser = \App\Models\DeveloperUser::create([
            'name' => 'Kundan Kumar',
            'email' => 'kundan@nexora.local',
            'password' => bcrypt('password123'),
        ]);

        $result = \App\Services\LicenseRequestService::fulfillDoctorSeatAddonRequest(
            $request->id,
            $devUser->id,
            [
                'payment_method' => 'UPI',
                'payment_reference' => 'UPI-ADDON-TEST-999',
                'admin_notes' => 'Payment received via UPI, activating 1 seat',
            ]
        );

        // 3. Assert request marked FULFILLED and capacity increased by 1
        $request->refresh();
        $this->assertEquals('FULFILLED', $request->status);
        $this->assertEquals('UPI', $request->payment_method);
        $this->assertEquals($initialCapacity + 1, $this->clinic->fresh()->totalDoctorCapacity());
        $this->assertEquals($initialCapacity + 1, $result['total_capacity']);
    }
}
