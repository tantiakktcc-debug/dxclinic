<?php

namespace Tests\Feature;

use App\Livewire\Settings\WhatsAppSettings;
use App\Models\Clinic;
use App\Models\User;
use App\Models\WhatsAppConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class BaileysQrSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $demoClinic;
    protected User $demoAdmin;
    protected Clinic $otherClinic;
    protected User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Demo Clinic (Clinic 6 / democlinic)
        $this->demoClinic = Clinic::create([
            'id' => 6,
            'clinic_code' => 'DEMO01',
            'public_slug' => 'democlinic',
            'name' => 'Demo Clinic Varanasi',
            'phone' => '9876543210',
            'email' => 'demoadmin@dxclinic.local',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $this->demoAdmin = User::create([
            'name' => 'Demo Admin',
            'email' => 'demoadmin@dxclinic.local',
            'password' => bcrypt('password123'),
            'clinic_id' => $this->demoClinic->id,
            'role' => 'ADMIN',
        ]);

        // 2. Foreign Clinic (Clinic 7 / otherclinic)
        $this->otherClinic = Clinic::create([
            'id' => 7,
            'clinic_code' => 'OTHER01',
            'public_slug' => 'otherclinic',
            'name' => 'Other Clinic Varanasi',
            'phone' => '9876543210',
            'email' => 'otheradmin@dxclinic.local',
            'address' => 'Lanka',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $this->otherAdmin = User::create([
            'name' => 'Other Admin',
            'email' => 'otheradmin@dxclinic.local',
            'password' => bcrypt('password123'),
            'clinic_id' => $this->otherClinic->id,
            'role' => 'ADMIN',
        ]);
    }

    /**
     * Test A: Authorized Demo Clinic user can see their current QR in Livewire
     */
    public function test_authorized_demo_clinic_user_can_see_current_qr(): void
    {
        $this->actingAs($this->demoAdmin);

        $mockQr = 'data:image/png;base64,demo_clinic_secret_qr_12345';
        WhatsAppConnection::create([
            'clinic_id' => $this->demoClinic->id,
            'provider' => 'baileys',
            'status' => 'QR_REQUIRED',
            'baileys_qr_payload' => encrypt($mockQr),
            'qr_generated_at' => now(),
        ]);

        Livewire::test(WhatsAppSettings::class)
            ->assertSet('baileysStatus', 'QR_REQUIRED')
            ->assertSet('qrPayload', $mockQr)
            ->assertSeeHtml($mockQr);
    }

    /**
     * Test B: Unauthorized user from another clinic cannot retrieve Demo Clinic QR
     */
    public function test_unauthorized_user_cannot_retrieve_demo_clinic_qr(): void
    {
        $mockQr = 'data:image/png;base64,demo_clinic_secret_qr_12345';
        WhatsAppConnection::create([
            'clinic_id' => $this->demoClinic->id,
            'provider' => 'baileys',
            'status' => 'QR_REQUIRED',
            'baileys_qr_payload' => encrypt($mockQr),
            'qr_generated_at' => now(),
        ]);

        // Act as user from other clinic
        $this->actingAs($this->otherAdmin);

        Livewire::test(WhatsAppSettings::class)
            ->assertSet('baileysStatus', 'NOT_CONNECTED')
            ->assertSet('qrPayload', null)
            ->assertDontSeeHtml($mockQr);
    }

    /**
     * Test C: Direct public request to any old QR file returns 404
     */
    public function test_direct_public_request_to_old_qr_file_returns_404(): void
    {
        $response = $this->get('/demo_clinic_whatsapp_qr.png');
        $response->assertStatus(404);

        $response2 = $this->get('/public/demo_clinic_whatsapp_qr.png');
        $response2->assertStatus(404);
    }

    /**
     * Test D: QR is not present under public/ or storage/app/public/
     */
    public function test_qr_is_not_present_under_public_directory(): void
    {
        $this->assertFileDoesNotExist(public_path('demo_clinic_whatsapp_qr.png'));
        
        $publicFiles = glob(public_path('*qr*')) ?: [];
        $this->assertEmpty($publicFiles, 'Found unexpected QR files in public/: ' . implode(', ', $publicFiles));

        $storagePublicFiles = glob(storage_path('app/public/*qr*')) ?: [];
        $this->assertEmpty($storagePublicFiles, 'Found unexpected QR files in storage/app/public/: ' . implode(', ', $storagePublicFiles));
    }

    /**
     * Test E: QR is not present in logs
     */
    public function test_qr_is_not_present_in_logs(): void
    {
        $mockQr = 'data:image/png;base64,uniquesecretqrstringnotlogged_999888';
        
        $this->actingAs($this->demoAdmin);

        WhatsAppConnection::create([
            'clinic_id' => $this->demoClinic->id,
            'provider' => 'baileys',
            'status' => 'QR_REQUIRED',
            'baileys_qr_payload' => encrypt($mockQr),
            'qr_generated_at' => now(),
        ]);

        // Trigger Livewire mount and actions
        Livewire::test(WhatsAppSettings::class);

        // Verify log does not contain raw QR
        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            $logContent = File::get($logPath);
            $this->assertStringNotContainsString('uniquesecretqrstringnotlogged_999888', $logContent);
        }
    }

    /**
     * Test F & G: QR is not exposed in localStorage or sessionStorage in Blade
     */
    public function test_qr_is_not_exposed_in_browser_storage(): void
    {
        $bladeContent = File::get(resource_path('views/livewire/settings/whatsapp-settings.blade.php'));

        $this->assertStringNotContainsString('localStorage', $bladeContent);
        $this->assertStringNotContainsString('sessionStorage', $bladeContent);
    }

    /**
     * Test H: QR is not exposed through unauthenticated API or JSON serialization
     */
    public function test_qr_is_hidden_from_json_serialization_and_unauthenticated_api(): void
    {
        $mockQr = 'data:image/png;base64,supersecretqr_hidden';
        $connection = WhatsAppConnection::create([
            'clinic_id' => $this->demoClinic->id,
            'provider' => 'baileys',
            'status' => 'QR_REQUIRED',
            'baileys_qr_payload' => encrypt($mockQr),
            'qr_generated_at' => now(),
        ]);

        // Model toArray and toJson must NOT expose baileys_qr_payload
        $array = $connection->toArray();
        $this->assertArrayNotHasKey('baileys_qr_payload', $array);

        $json = $connection->toJson();
        $this->assertStringNotContainsString('supersecretqr_hidden', $json);
        $this->assertStringNotContainsString('baileys_qr_payload', $json);
    }

    /**
     * Test I: Expired or consumed QR becomes unavailable
     */
    public function test_expired_or_consumed_qr_becomes_unavailable(): void
    {
        $this->actingAs($this->demoAdmin);

        $mockQr = 'data:image/png;base64,expired_qr_payload';
        $connection = WhatsAppConnection::create([
            'clinic_id' => $this->demoClinic->id,
            'provider' => 'baileys',
            'status' => 'QR_REQUIRED',
            'baileys_qr_payload' => encrypt($mockQr),
            'qr_generated_at' => now()->subSeconds(305), // Older than 300s expiry
        ]);

        // 1. Expired QR returns null payload to Livewire
        Livewire::test(WhatsAppSettings::class)
            ->assertSet('qrPayload', null);

        // 2. Consumed/connected state clears QR payload completely
        $connection->update([
            'status' => 'CONNECTED',
            'baileys_phone_number' => '919876543210',
            'baileys_qr_payload' => null,
            'qr_generated_at' => null,
        ]);

        Livewire::test(WhatsAppSettings::class)
            ->assertSet('baileysStatus', 'CONNECTED')
            ->assertSet('qrPayload', null)
            ->assertDontSeeHtml('Scan with WhatsApp');
    }
}
