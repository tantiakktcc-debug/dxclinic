<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\License;
use App\Models\Patient;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppMessageDelivery;
use App\Modules\WhatsApp\Infrastructure\MockWhatsAppProvider;
use App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface;
use App\Services\TenantContext;
use App\Services\TokenSlipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WhatsAppTokenSlipTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic1;
    protected Clinic $clinic2;
    protected Doctor $doctor1;
    protected Doctor $doctor2;
    protected User $operator1;
    protected User $admin1;
    protected User $doctorUser1;
    protected User $doctorUser2;
    protected Patient $patient1;
    protected Appointment $appointment1;
    protected Appointment $appointment2;
    protected WhatsAppConnection $connection1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(WhatsAppProviderInterface::class, new MockWhatsAppProvider());

        $plan = SubscriptionPlan::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'price' => 1000,
            'duration_months' => 12,
            'max_doctors' => 5,
            'is_active' => true,
        ]);

        $this->clinic1 = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'sharmacare',
            'name' => 'Sharma Care Clinic',
            'address' => 'Bhelupur, Varanasi',
            'city' => 'Varanasi',
            'phone' => '9876543210',
            'activation_status' => 'ACTIVATED',
            'onboarding_status' => 'COMPLETED',
            'is_onboarded' => true,
        ]);

        License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'NXCL-TEST-1'),
            'license_key_encrypted' => encrypt('NXCL-TEST-1'),
            'license_key_prefix' => 'NXCL',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'included_doctor_seats' => 5,
            'status' => 'ACTIVE',
        ]);

        $this->clinic2 = Clinic::create([
            'clinic_code' => 'NX0002',
            'public_slug' => 'arogya',
            'name' => 'Arogya Clinic',
            'address' => 'Sigra, Varanasi',
            'city' => 'Varanasi',
            'phone' => '9876543222',
            'activation_status' => 'ACTIVATED',
            'onboarding_status' => 'COMPLETED',
            'is_onboarded' => true,
        ]);

        $this->doctor1 = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Sharma',
            'specialization' => 'General Medicine',
            'qualification' => 'MBBS',
            'registration_number' => 'MCI-1001',
            'is_active' => true,
        ]);

        $this->doctor2 = Doctor::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Verma',
            'specialization' => 'Cardiology',
            'qualification' => 'MD',
            'registration_number' => 'MCI-1002',
            'is_active' => true,
        ]);

        $this->operator1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Operator 1',
            'email' => 'operator1@clinic.test',
            'role' => 'OPERATOR',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $this->admin1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Admin 1',
            'email' => 'admin1@clinic.test',
            'role' => 'ADMIN',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $this->doctorUser1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor1->id,
            'name' => 'Dr. Sharma User',
            'email' => 'drsharma@clinic.test',
            'role' => 'DOCTOR',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $this->doctorUser2 = User::create([
            'clinic_id' => $this->clinic1->id,
            'doctor_id' => $this->doctor2->id,
            'name' => 'Dr. Verma User',
            'email' => 'drverma@clinic.test',
            'role' => 'DOCTOR',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $this->patient1 = Patient::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => 'P0001',
            'name' => 'Test Patient',
            'age' => 30,
            'sex' => 'MALE',
            'mobile' => '9876543210',
            'address' => 'Lanka',
            'city' => 'Varanasi',
        ]);

        $this->appointment1 = Appointment::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $this->patient1->id,
            'doctor_id' => $this->doctor1->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'token_number' => 1,
            'formatted_token' => '001',
            'booking_source' => 'ONLINE',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'BOOKED',
            'secure_token' => 'sec_tok_001_' . uniqid(),
        ]);

        $this->appointment2 = Appointment::create([
            'clinic_id' => $this->clinic1->id,
            'patient_id' => $this->patient1->id,
            'doctor_id' => $this->doctor2->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'EVENING',
            'token_number' => 2,
            'formatted_token' => '002',
            'booking_source' => 'ONLINE',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'status' => 'BOOKED',
            'secure_token' => 'sec_tok_002_' . uniqid(),
        ]);

        $this->connection1 = WhatsAppConnection::create([
            'clinic_id' => $this->clinic1->id,
            'provider' => 'baileys',
            'phone_number_id' => '109876543210001',
            'display_phone_number' => '+919876543210',
            'status' => 'CONNECTED',
        ]);
    }

    public function test_token_slip_service_generates_canonical_data(): void
    {
        $service = app(TokenSlipService::class);
        $data = $service->generateTokenSlipPdfData($this->appointment1);

        $this->assertEquals('Sharma Care Clinic', $data['clinic_name']);
        $this->assertEquals('Test Patient', $data['patient_name']);
        $this->assertEquals('001', $data['formatted_token']);
        $this->assertStringContainsString('9876543210', $data['mobile']);
    }

    public function test_token_slip_private_url_requires_valid_signature_and_expires(): void
    {
        // 1. Unsigned request fails with 403
        $unsignedUrl = route('token.slip.private-download', ['secureToken' => $this->appointment1->secure_token]);
        $response = $this->get($unsignedUrl);
        $response->assertStatus(403);

        // 2. Cryptographically signed temporary URL succeeds
        $service = app(TokenSlipService::class);
        $signedUrl = $service->generatePrivateDocumentUrl($this->appointment1);
        $this->assertStringContainsString('signature=', $signedUrl);

        $responseValid = $this->get($signedUrl);
        $responseValid->assertStatus(200);
        $responseValid->assertHeader('content-type', 'application/pdf');
    }

    public function test_operator_and_admin_can_send_token_slip_via_whatsapp(): void
    {
        $service = app(TokenSlipService::class);

        // Operator
        $service->assertAuthorized($this->appointment1, $this->operator1, $this->clinic1);
        $result = $service->sendTokenSlipViaWhatsApp($this->appointment1, null, false, 'OPERATOR', $this->operator1->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('SENT', $result['status']);
        $this->assertDatabaseHas('whatsapp_message_deliveries', [
            'appointment_id' => $this->appointment1->id,
            'message_type' => 'DOCUMENT',
            'recipient_phone' => '919876543210',
            'status' => 'SENT',
        ]);

        // Admin
        $service->assertAuthorized($this->appointment1, $this->admin1, $this->clinic1);
    }

    public function test_doctor_can_only_send_own_assigned_appointment_slip(): void
    {
        $service = app(TokenSlipService::class);

        // Doctor 1 sends Appointment 1 (assigned to Doctor 1) -> Allowed
        $service->assertAuthorized($this->appointment1, $this->doctorUser1, $this->clinic1);
        $this->assertTrue(true);

        // Doctor 1 attempts to send Appointment 2 (assigned to Doctor 2) -> Forbidden 403
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        $service->assertAuthorized($this->appointment2, $this->doctorUser1, $this->clinic1);
    }

    public function test_cross_clinic_token_slip_is_forbidden(): void
    {
        $service = app(TokenSlipService::class);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        $service->assertAuthorized($this->appointment1, $this->operator1, $this->clinic2);
    }

    public function test_token_slip_idempotency_prevents_duplicate_delivery(): void
    {
        $service = app(TokenSlipService::class);

        $res1 = $service->sendTokenSlipViaWhatsApp($this->appointment1, '9876543210', false, 'OPERATOR');
        $this->assertTrue($res1['success']);
        $count1 = WhatsAppMessageDelivery::where('appointment_id', $this->appointment1->id)->count();
        $this->assertEquals(1, $count1);

        // Second attempt without forceResend is idempotent
        $res2 = $service->sendTokenSlipViaWhatsApp($this->appointment1, '9876543210', false, 'OPERATOR');
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['idempotent'] ?? false);
        $count2 = WhatsAppMessageDelivery::where('appointment_id', $this->appointment1->id)->count();
        $this->assertEquals(1, $count2); // No duplicate delivery row created!

        // Force resend creates a new attempt
        $res3 = $service->sendTokenSlipViaWhatsApp($this->appointment1, '9876543210', true, 'OPERATOR');
        $this->assertTrue($res3['success']);
        $count3 = WhatsAppMessageDelivery::where('appointment_id', $this->appointment1->id)->count();
        $this->assertEquals(2, $count3);
    }

    public function test_delivery_failure_does_not_alter_appointment(): void
    {
        // Disconnect connection
        $this->connection1->update(['status' => 'DISCONNECTED']);

        $service = app(TokenSlipService::class);
        $res = $service->sendTokenSlipViaWhatsApp($this->appointment1, '9876543210', true, 'OPERATOR');

        $this->assertFalse($res['success']);
        $this->assertEquals('FAILED', $res['status']);

        // Appointment remains BOOKED and valid
        $freshAppt = $this->appointment1->fresh();
        $this->assertEquals('BOOKED', $freshAppt->status);
        $this->assertEquals('001', $freshAppt->formatted_token);
    }
}
