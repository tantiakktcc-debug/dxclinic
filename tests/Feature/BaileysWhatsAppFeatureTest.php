<?php

namespace Tests\Feature;

use App\Http\Controllers\BaileysGatewayWebhookController;
use App\Livewire\Settings\WhatsAppSettings;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Application\WhatsAppService;
use App\Modules\WhatsApp\Infrastructure\BaileysWhatsAppProvider;
use App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface;
use App\Services\BaileysConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BaileysWhatsAppFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected User $adminUser;
    protected string $gatewaySecret = 'test_baileys_gateway_secret';
    protected string $webhookSecret = 'test_baileys_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'dxclinic.whatsapp.provider' => 'baileys',
            'dxclinic.whatsapp.baileys.gateway_url' => 'http://127.0.0.1:9090',
            'dxclinic.whatsapp.baileys.gateway_secret' => $this->gatewaySecret,
            'dxclinic.whatsapp.baileys.webhook_secret' => $this->webhookSecret,
        ]);

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX9999',
            'public_slug' => 'testclinic',
            'name' => 'Test Clinic Varanasi',
            'phone' => '9876543210',
            'email' => 'clinic@test.com',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Dr. Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'clinic_id' => $this->clinic->id,
            'role' => 'ADMIN',
        ]);
    }

    public function test_baileys_provider_implements_interface(): void
    {
        $provider = app(BaileysWhatsAppProvider::class);
        $this->assertInstanceOf(WhatsAppProviderInterface::class, $provider);
    }

    public function test_baileys_signature_verification_succeeds_for_valid_hmac(): void
    {
        $provider = app(BaileysWhatsAppProvider::class);
        $payload = json_encode(['event' => 'ping', 'timestamp' => time()]);
        $expectedSig = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookSecret);

        $this->assertTrue($provider->verifyWebhookSignature($payload, $expectedSig, $this->webhookSecret));
    }

    public function test_baileys_signature_verification_fails_for_tampered_payload(): void
    {
        $provider = app(BaileysWhatsAppProvider::class);
        $payload = json_encode(['event' => 'ping']);
        $sig = 'sha256=' . hash_hmac('sha256', $payload, $this->webhookSecret);

        $tampered = json_encode(['event' => 'tampered']);
        $this->assertFalse($provider->verifyWebhookSignature($tampered, $sig, $this->webhookSecret));
    }

    public function test_gateway_webhook_rejects_missing_or_invalid_signature(): void
    {
        $response = $this->postJson('/api/internal/baileys/webhook', [
            'event' => 'qr',
            'clinic_id' => $this->clinic->id,
            'data' => ['qr' => 'data:image/png;base64,sample'],
        ]);

        $response->assertStatus(401);
    }

    public function test_gateway_webhook_rejects_stale_timestamp_replay_attack(): void
    {
        $data = [
            'event' => 'qr',
            'clinic_id' => $this->clinic->id,
            'data' => ['qr' => 'data:image/png;base64,sample'],
        ];
        $payload = json_encode($data);

        $staleTs = time() - 400; // > 300s
        $nonce = 'test_nonce_replay';
        $signData = "{$nonce}.{$staleTs}.{$payload}";
        $sig = 'sha256=' . hash_hmac('sha256', $signData, $this->webhookSecret);

        $response = $this->postJson('/api/internal/baileys/webhook', $data, [
            'X-Gateway-Signature' => $sig,
            'X-Gateway-Nonce' => $nonce,
            'X-Gateway-Timestamp' => (string) $staleTs,
        ]);

        $response->assertStatus(401);
    }

    public function test_gateway_webhook_accepts_valid_qr_event_and_updates_db(): void
    {
        $qrPayloadData = 'data:image/png;base64,sample_scannable_qr_code';
        $data = [
            'event' => 'qr',
            'clinic_id' => $this->clinic->id,
            'data' => ['qr' => $qrPayloadData],
        ];
        $payload = json_encode($data);

        $ts = time();
        $nonce = 'valid_nonce_qr';
        $signData = "{$nonce}.{$ts}.{$payload}";
        $sig = 'sha256=' . hash_hmac('sha256', $signData, $this->webhookSecret);

        $response = $this->postJson('/api/internal/baileys/webhook', $data, [
            'X-Gateway-Signature' => $sig,
            'X-Gateway-Nonce' => $nonce,
            'X-Gateway-Timestamp' => (string) $ts,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);

        $con = WhatsAppConnection::where('clinic_id', $this->clinic->id)
            ->where('provider', 'baileys')
            ->first();

        $this->assertNotNull($con);
        $this->assertEquals('QR_REQUIRED', $con->status);
        $this->assertNotNull($con->baileys_qr_payload);
        $this->assertEquals($qrPayloadData, decrypt($con->baileys_qr_payload));
    }

    public function test_gateway_webhook_accepts_connection_state_event(): void
    {
        $data = [
            'event' => 'connection_state',
            'clinic_id' => $this->clinic->id,
            'data' => [
                'state' => 'CONNECTED',
                'phone' => '919876543210',
            ],
        ];
        $payload = json_encode($data);

        $ts = time();
        $nonce = 'valid_nonce_state';
        $signData = "{$nonce}.{$ts}.{$payload}";
        $sig = 'sha256=' . hash_hmac('sha256', $signData, $this->webhookSecret);

        $response = $this->postJson('/api/internal/baileys/webhook', $data, [
            'X-Gateway-Signature' => $sig,
            'X-Gateway-Nonce' => $nonce,
            'X-Gateway-Timestamp' => (string) $ts,
        ]);

        $response->assertStatus(200);

        $con = WhatsAppConnection::where('clinic_id', $this->clinic->id)
            ->where('provider', 'baileys')
            ->first();

        $this->assertNotNull($con);
        $this->assertEquals('CONNECTED', $con->status);
        $this->assertEquals('919876543210', $con->baileys_phone_number);
        $this->assertNull($con->baileys_qr_payload); // Cleared on connection
    }

    public function test_livewire_connect_baileys_initiates_connection_flow(): void
    {
        $this->actingAs($this->adminUser);

        // Fake the gateway start endpoint
        Http::fake([
            'http://127.0.0.1:9090/connection/start' => Http::response([
                'success' => true,
                'status' => 'QR_REQUIRED',
                'qr' => 'data:image/png;base64,mock_qr_data',
            ], 200),
        ]);

        Livewire::test(WhatsAppSettings::class)
            ->call('connectBaileys')
            ->assertHasNoErrors()
            ->assertSet('baileysStatus', 'QR_REQUIRED');

        $this->assertDatabaseHas('whatsapp_connections', [
            'clinic_id' => $this->clinic->id,
            'provider' => 'baileys',
            'status' => 'QR_REQUIRED',
        ]);
    }

    public function test_livewire_disconnect_baileys_sets_disconnected_state(): void
    {
        $this->actingAs($this->adminUser);

        // Pre-create connected state
        WhatsAppConnection::create([
            'clinic_id' => $this->clinic->id,
            'provider' => 'baileys',
            'status' => 'CONNECTED',
            'baileys_phone_number' => '919876543210',
            'connected_at' => now(),
        ]);

        Http::fake([
            'http://127.0.0.1:9090/connection/disconnect' => Http::response(['success' => true], 200),
        ]);

        Livewire::test(WhatsAppSettings::class)
            ->call('disconnectBaileys')
            ->assertHasNoErrors()
            ->assertSet('baileysStatus', 'DISCONNECTED');

        $this->assertDatabaseHas('whatsapp_connections', [
            'clinic_id' => $this->clinic->id,
            'provider' => 'baileys',
            'status' => 'DISCONNECTED',
        ]);
    }
}
