<?php

namespace Tests\Feature\Operator;

use App\Livewire\Operator\WhatsAppDesk;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Infrastructure\MockWhatsAppProvider;
use App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppDeskTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic1;
    protected Clinic $clinic2;
    protected User $admin1;
    protected User $operator1;
    protected User $doctor1;
    protected User $admin2;
    protected WhatsAppConnection $connection1;
    protected WhatsAppConnection $connection2;

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
            'status' => 'ACTIVE',
        ]);

        $this->clinic2 = Clinic::create([
            'clinic_code' => 'NX0002',
            'public_slug' => 'arogya',
            'name' => 'Arogya Super Specialty Clinic',
            'address' => 'Sigra, Varanasi',
            'city' => 'Varanasi',
            'phone' => '9876543222',
            'activation_status' => 'ACTIVATED',
            'onboarding_status' => 'COMPLETED',
            'is_onboarded' => true,
        ]);

        License::create([
            'clinic_id' => $this->clinic2->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'NXCL-TEST-2'),
            'license_key_encrypted' => encrypt('NXCL-TEST-2'),
            'license_key_prefix' => 'NXCL',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $this->admin1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Clinic Admin 1',
            'email' => 'admin1@dxclinic.local',
            'role' => 'ADMIN',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
            'must_change_password' => false,
        ]);

        $this->operator1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Operator 1',
            'email' => 'operator1@dxclinic.local',
            'role' => 'OPERATOR',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
            'must_change_password' => false,
        ]);

        $this->doctor1 = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Sharma',
            'email' => 'doctor1@dxclinic.local',
            'role' => 'DOCTOR',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
            'must_change_password' => false,
        ]);

        $this->admin2 = User::create([
            'clinic_id' => $this->clinic2->id,
            'name' => 'Admin 2',
            'email' => 'admin2@dxclinic.local',
            'role' => 'ADMIN',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
            'must_change_password' => false,
        ]);

        $this->connection1 = WhatsAppConnection::create([
            'clinic_id' => $this->clinic1->id,
            'provider' => 'baileys',
            'phone_number_id' => '109876543210001',
            'display_phone_number' => '+919876543210',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $this->connection2 = WhatsAppConnection::create([
            'clinic_id' => $this->clinic2->id,
            'provider' => 'baileys',
            'phone_number_id' => '109876543210002',
            'display_phone_number' => '+919876543002',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_whatsapp_desk(): void
    {
        $response = $this->get(route('operator.whatsapp-desk'));
        $response->assertRedirect(route('login'));
    }

    public function test_doctor_cannot_access_whatsapp_desk(): void
    {
        $this->actingAs($this->doctor1);
        TenantContext::setClinic($this->clinic1);

        $response = $this->get(route('operator.whatsapp-desk'));
        $response->assertStatus(403);
    }

    public function test_admin_and_operator_can_access_whatsapp_desk(): void
    {
        $this->actingAs($this->admin1);
        TenantContext::setClinic($this->clinic1);

        $response = $this->get(route('operator.whatsapp-desk'));
        $response->assertStatus(200);
        $response->assertSee('WhatsApp Desk');

        $this->actingAs($this->operator1);
        $responseOp = $this->get(route('operator.whatsapp-desk'));
        $responseOp->assertStatus(200);
    }

    public function test_strict_tenant_isolation_conversations_never_leak(): void
    {
        // Clinic 1 conversation
        $conv1 = WhatsAppConversation::create([
            'clinic_id' => $this->clinic1->id,
            'whatsapp_connection_id' => $this->connection1->id,
            'wa_id' => '919888811111',
            'current_state' => 'MENU',
            'last_patient_message_at' => now(),
        ]);
        WhatsAppMessage::create([
            'clinic_id' => $this->clinic1->id,
            'whatsapp_conversation_id' => $conv1->id,
            'direction' => 'INBOUND',
            'content' => 'Hello from Clinic 1 patient',
            'status' => 'DELIVERED',
        ]);

        // Clinic 2 conversation
        $conv2 = WhatsAppConversation::create([
            'clinic_id' => $this->clinic2->id,
            'whatsapp_connection_id' => $this->connection2->id,
            'wa_id' => '919999922222',
            'current_state' => 'MENU',
            'last_patient_message_at' => now(),
        ]);
        WhatsAppMessage::create([
            'clinic_id' => $this->clinic2->id,
            'whatsapp_conversation_id' => $conv2->id,
            'direction' => 'INBOUND',
            'content' => 'Secret message in Clinic 2',
            'status' => 'DELIVERED',
        ]);

        // Login as Clinic 1 Admin
        $this->actingAs($this->admin1);
        TenantContext::setClinic($this->clinic1);

        Livewire::test(WhatsAppDesk::class)
            ->assertSee('919888811111')
            ->assertSee('Hello from Clinic 1 patient')
            ->assertDontSee('919999922222')
            ->assertDontSee('Secret message in Clinic 2');

        // Login as Clinic 2 Admin
        $this->actingAs($this->admin2);
        TenantContext::setClinic($this->clinic2);

        Livewire::test(WhatsAppDesk::class)
            ->assertSee('919999922222')
            ->assertSee('Secret message in Clinic 2')
            ->assertDontSee('919888811111')
            ->assertDontSee('Hello from Clinic 1 patient');
    }

    public function test_selecting_conversation_marks_inbound_messages_as_read(): void
    {
        $conv = WhatsAppConversation::create([
            'clinic_id' => $this->clinic1->id,
            'whatsapp_connection_id' => $this->connection1->id,
            'wa_id' => '919888833333',
            'current_state' => 'MENU',
            'last_patient_message_at' => now(),
        ]);

        $msg = WhatsAppMessage::create([
            'clinic_id' => $this->clinic1->id,
            'whatsapp_conversation_id' => $conv->id,
            'direction' => 'INBOUND',
            'content' => 'Unread enquiry message',
            'status' => 'DELIVERED',
        ]);

        $this->assertEquals('DELIVERED', $msg->fresh()->status);

        $this->actingAs($this->operator1);
        TenantContext::setClinic($this->clinic1);

        Livewire::test(WhatsAppDesk::class)
            ->call('selectConversation', $conv->id);

        $this->assertEquals('READ', $msg->fresh()->status);
    }

    public function test_operator_manual_reply_dispatches_message_and_audits(): void
    {
        $conv = WhatsAppConversation::create([
            'clinic_id' => $this->clinic1->id,
            'whatsapp_connection_id' => $this->connection1->id,
            'wa_id' => '919888844444',
            'current_state' => 'MENU',
            'last_patient_message_at' => now(),
        ]);

        $this->actingAs($this->operator1);
        TenantContext::setClinic($this->clinic1);

        Livewire::test(WhatsAppDesk::class)
            ->call('selectConversation', $conv->id)
            ->set('replyText', 'Namaste, Dr. Sharma will see you at 10:30 AM.')
            ->call('sendManualReply');

        // Verify message was created
        $createdMsg = WhatsAppMessage::where('whatsapp_conversation_id', $conv->id)
            ->where('direction', 'OUTBOUND')
            ->latest('id')
            ->first();

        $this->assertNotNull($createdMsg);
        $this->assertEquals('Namaste, Dr. Sharma will see you at 10:30 AM.', $createdMsg->content);
        $this->assertEquals('OPERATOR', $createdMsg->payload['sender_type']);
        $this->assertEquals($this->operator1->id, $createdMsg->payload['sent_by_user_id']);

        // Verify conversation transitioned to HUMAN_HANDOFF so AI doesn't send conflicting replies
        $convFresh = $conv->fresh();
        $this->assertTrue($convFresh->human_handoff_active);
        $this->assertEquals('HUMAN_HANDOFF', $convFresh->current_state);

        // Verify Audit Log
        $audit = AuditLog::where('action', 'WHATSAPP_MANUAL_MESSAGE_SENT')
            ->where('clinic_id', $this->clinic1->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->operator1->id, $audit->user_id);
    }

    public function test_takeover_and_resume_ai_controls(): void
    {
        $conv = WhatsAppConversation::create([
            'clinic_id' => $this->clinic1->id,
            'whatsapp_connection_id' => $this->connection1->id,
            'wa_id' => '919888855555',
            'current_state' => 'MENU',
            'human_handoff_active' => false,
            'last_patient_message_at' => now(),
        ]);

        $this->actingAs($this->admin1);
        TenantContext::setClinic($this->clinic1);

        $test = Livewire::test(WhatsAppDesk::class)
            ->call('selectConversation', $conv->id);

        // Take Over
        $test->call('takeOver');
        $this->assertTrue($conv->fresh()->human_handoff_active);
        $this->assertEquals('HUMAN_HANDOFF', $conv->fresh()->current_state);

        // Resume AI
        $test->call('resumeAi');
        $this->assertFalse($conv->fresh()->human_handoff_active);
        $this->assertEquals('MENU', $conv->fresh()->current_state);
    }
}
