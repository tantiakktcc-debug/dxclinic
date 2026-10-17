<?php

namespace Tests\Feature;

use App\Livewire\Settings\WhatsAppSettings;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use App\Models\WhatsAppConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppSettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'sharmacare',
            'name' => 'Sharma Care Clinic',
            'phone' => '9876543210',
            'email' => 'clinic@sharma.com',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Dr. Sharma',
            'email' => 'admin@sharma.com',
            'password' => bcrypt('secret123'),
            'clinic_id' => $this->clinic->id,
            'role' => 'ADMIN',
        ]);
    }

    public function test_unauthenticated_user_redirected_from_whatsapp_settings(): void
    {
        $response = $this->get('/settings/whatsapp');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_admin_can_view_whatsapp_settings_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/settings/whatsapp');
        $response->assertStatus(200);
        $response->assertSeeText('WhatsApp Integration & Automation');
    }

    public function test_whatsapp_settings_component_mounts_and_saves_settings(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(WhatsAppSettings::class)
            ->assertStatus(200)
            ->set('assistantEnabled', true)
            ->set('bookingEnabled', false)
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->assertSee('WhatsApp configuration and assistant settings updated successfully.');

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->adminUser->id,
            'action' => 'WHATSAPP_SETTINGS_UPDATED',
            'entity_type' => WhatsAppConnection::class,
        ]);
    }

    public function test_whatsapp_settings_connect_and_disconnect_lifecycle(): void
    {
        $this->actingAs($this->adminUser);

        $testComp = Livewire::test(WhatsAppSettings::class)
            ->set('phoneNumberId', '109876543210002')
            ->set('displayPhoneNumber', '+91 98765 43210')
            ->set('businessAccountId', '109876543210987')
            ->set('accessToken', 'EAABtest_token_secret')
            ->call('connectWhatsApp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('whatsapp_connections', [
            'clinic_id' => $this->clinic->id,
            'phone_number_id' => '109876543210002',
            'status' => 'CONNECTED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'WHATSAPP_CONNECTED',
            'entity_type' => WhatsAppConnection::class,
        ]);

        // Disconnect
        $testComp->call('disconnectWhatsApp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('whatsapp_connections', [
            'clinic_id' => $this->clinic->id,
            'status' => 'DISCONNECTED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'WHATSAPP_DISCONNECTED',
            'entity_type' => WhatsAppConnection::class,
        ]);
    }

    public function test_meta_embedded_signup_onboarding_callback_flow(): void
    {
        $this->actingAs($this->adminUser);

        $testComp = Livewire::test(WhatsAppSettings::class)
            ->assertStatus(200)
            ->assertSee('Status: NOT CONNECTED')
            ->assertSee('CONNECT META WHATSAPP')
            ->call('launchMetaOnboarding')
            ->assertSet('showSetupModal', true)
            ->call('handleMetaOnboardingCallback', [
                'phone_number_id' => '109876543210002',
                'waba_id' => '109876543210987',
                'display_phone_number' => '+91 98765 43210',
                'business_display_name' => 'Sharma Care Clinic',
            ])
            ->assertHasNoErrors()
            ->assertSee('CONFIGURED — LIVE META VERIFICATION PENDING')
            ->assertSee('Assistant')
            ->assertSee('Booking')
            ->assertSee('Notifications')
            ->assertSee('Human Handoff');

        $this->assertDatabaseHas('whatsapp_connections', [
            'clinic_id' => $this->clinic->id,
            'phone_number_id' => '109876543210002',
            'business_account_id' => '109876543210987',
            'status' => 'CONNECTED',
        ]);

        $connection = WhatsAppConnection::where('clinic_id', $this->clinic->id)->first();
        $this->assertNotNull($connection);
        $this->assertNotEmpty($connection->access_token_encrypted);
        $this->assertNotEquals('EAAB', substr($connection->access_token_encrypted, 0, 4)); // Encrypted string

        // Verify token is never exposed in array serialization
        $serialized = $connection->toArray();
        $this->assertArrayNotHasKey('access_token', $serialized);
        $this->assertArrayNotHasKey('access_token_encrypted', $serialized);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'WHATSAPP_CONNECTED',
            'entity_type' => WhatsAppConnection::class,
            'entity_id' => $connection->id,
        ]);
    }

    public function test_initiate_meta_signup_creates_audit_log_and_preserves_modal(): void
    {
        $this->actingAs($this->adminUser);

        $testComp = Livewire::test(WhatsAppSettings::class)
            ->call('launchMetaOnboarding')
            ->assertSet('showSetupModal', true)
            ->call('initiateMetaSignup')
            ->assertSet('showSetupModal', true) // Must NEVER close modal prematurely
            ->assertSet('metaFeedbackStatus', 'OPENED')
            ->assertSet('metaFeedbackType', 'info');

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->adminUser->id,
            'action' => 'META_SIGNUP_INITIATED',
            'entity_type' => WhatsAppConnection::class,
        ]);

        $this->assertNotNull(session('meta_oauth_state_' . $this->clinic->id));
    }

    public function test_record_meta_signup_client_events_handles_all_outcomes(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Popup Blocked
        Livewire::test(WhatsAppSettings::class)
            ->call('launchMetaOnboarding')
            ->call('recordMetaSignupClientEvent', 'POPUP_BLOCKED', 'Meta Embedded Signup could not be opened because the browser blocked the popup.')
            ->assertSet('metaFeedbackStatus', 'POPUP_BLOCKED')
            ->assertSet('metaFeedbackType', 'warning')
            ->assertSee('Meta Embedded Signup could not be opened because the browser blocked the popup.');

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'META_SIGNUP_POPUP_BLOCKED',
        ]);

        // 2. Window Closed Without Result
        Livewire::test(WhatsAppSettings::class)
            ->call('launchMetaOnboarding')
            ->call('recordMetaSignupClientEvent', 'WINDOW_CLOSED_WITHOUT_RESULT', 'Meta Embedded Signup was closed before completion. You may click Launch to try again.')
            ->assertSet('metaFeedbackStatus', 'WINDOW_CLOSED_WITHOUT_RESULT')
            ->assertSet('metaFeedbackType', 'warning')
            ->assertSee('Meta Embedded Signup was closed before completion.');

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'META_SIGNUP_WINDOW_CLOSED_WITHOUT_RESULT',
        ]);

        // 3. Cancelled
        Livewire::test(WhatsAppSettings::class)
            ->call('launchMetaOnboarding')
            ->call('recordMetaSignupClientEvent', 'CANCELLED', 'Meta onboarding cancelled.')
            ->assertSet('metaFeedbackStatus', 'CANCELLED')
            ->assertSet('metaFeedbackType', 'warning');

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'META_SIGNUP_CANCELLED',
        ]);

        // 4. Meta Error
        Livewire::test(WhatsAppSettings::class)
            ->call('launchMetaOnboarding')
            ->call('recordMetaSignupClientEvent', 'META_ERROR', 'Meta onboarding failed: Configuration invalid.')
            ->assertSet('metaFeedbackStatus', 'META_ERROR')
            ->assertSet('metaFeedbackType', 'error');

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'META_SIGNUP_META_ERROR',
        ]);
    }

    public function test_tenant_binding_is_authoritative_to_logged_in_clinic(): void
    {
        $secondClinic = Clinic::create([
            'clinic_code' => 'NX0002',
            'public_slug' => 'secondclinic',
            'name' => 'Second Clinic',
            'phone' => '9111111111',
            'email' => 'second@clinic.com',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $secondAdmin = User::create([
            'name' => 'Dr. Second',
            'email' => 'admin@second.com',
            'password' => bcrypt('secret123'),
            'clinic_id' => $secondClinic->id,
            'role' => 'ADMIN',
        ]);

        $this->actingAs($secondAdmin);

        Livewire::test(WhatsAppSettings::class)
            ->call('handleMetaOnboardingCallback', [
                'phone_number_id' => '209876543210003',
                'waba_id' => '209876543210988',
                'display_phone_number' => '+91 91111 11111',
                'business_display_name' => 'Second Clinic',
            ])
            ->assertHasNoErrors();

        // Must connect to secondClinic, NOT clinic 1
        $this->assertDatabaseHas('whatsapp_connections', [
            'clinic_id' => $secondClinic->id,
            'phone_number_id' => '209876543210003',
        ]);

        $this->assertDatabaseMissing('whatsapp_connections', [
            'clinic_id' => $this->clinic->id,
            'phone_number_id' => '209876543210003',
        ]);
    }
}


