<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use App\Services\TenantContext;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiveQueueRoutingAndTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $sharmaCare;
    protected Clinic $demoClinic;
    protected User $sharmaAdmin;
    protected User $demoAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sharmaCare = Clinic::firstOrCreate(
            ['clinic_code' => 'NX0001'],
            [
                'name' => 'Sharma Care Clinic',
                'public_slug' => 'sharmacare',
                'address' => '123 MG Road',
                'phone' => '9876543210',
                'city' => 'Varanasi',
                'activation_status' => 'ACTIVATED',
                'onboarding_status' => 'COMPLETED',
                'is_onboarded' => true,
            ]
        );
        if ($this->sharmaCare->public_slug !== 'sharmacare') {
            $this->sharmaCare->update(['public_slug' => 'sharmacare']);
        }

        $this->demoClinic = Clinic::firstOrCreate(
            ['clinic_code' => 'democlinic'],
            [
                'name' => 'Demo Clinic',
                'public_slug' => 'democlinic',
                'address' => '456 Cyber Blvd',
                'phone' => '9876543211',
                'city' => 'Varanasi',
                'is_demo' => true,
                'activation_status' => 'ACTIVATED',
                'onboarding_status' => 'COMPLETED',
                'is_onboarded' => true,
            ]
        );
        if ($this->demoClinic->public_slug !== 'democlinic') {
            $this->demoClinic->update(['public_slug' => 'democlinic']);
        }

        $this->sharmaAdmin = User::firstOrCreate(
            ['email' => 'admin_test_sc@dxclinic.local'],
            [
                'name' => 'Sharma Admin',
                'role' => 'ADMIN',
                'clinic_id' => $this->sharmaCare->id,
                'status' => 'ACTIVE',
                'password' => bcrypt('Password123!'),
            ]
        );

        $this->demoAdmin = User::firstOrCreate(
            ['email' => 'admin_test_demo@dxclinic.local'],
            [
                'name' => 'Demo Admin',
                'role' => 'ADMIN',
                'clinic_id' => $this->demoClinic->id,
                'status' => 'ACTIVE',
                'password' => bcrypt('Password123!'),
            ]
        );
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_authenticated_democlinic_user_can_access_own_live_queue(): void
    {
        $response = $this->actingAs($this->demoAdmin, 'web')
            ->get('/clinic/democlinic/queue/live');

        $response->assertStatus(200);
        $response->assertSee('Demo Clinic');
    }

    public function test_authenticated_democlinic_user_cannot_access_sharmacare_live_queue_returns_403(): void
    {
        $response = $this->actingAs($this->demoAdmin, 'web')
            ->get('/clinic/sharmacare/queue/live');

        $response->assertStatus(403);
    }

    public function test_authenticated_sharmacare_user_can_access_own_live_queue(): void
    {
        $response = $this->actingAs($this->sharmaAdmin, 'web')
            ->get('/clinic/sharmacare/queue/live');

        $response->assertStatus(200);
        $response->assertSee('Sharma Care Clinic');
    }

    public function test_authenticated_sharmacare_user_cannot_access_democlinic_live_queue_returns_403(): void
    {
        $response = $this->actingAs($this->sharmaAdmin, 'web')
            ->get('/clinic/democlinic/queue/live');

        $response->assertStatus(403);
    }

    public function test_unslugged_queue_live_redirects_to_canonical_user_clinic_url(): void
    {
        $response = $this->actingAs($this->demoAdmin, 'web')
            ->get('/queue/live');

        $response->assertRedirect('/clinic/democlinic/queue/live');

        $responseSharma = $this->actingAs($this->sharmaAdmin, 'web')
            ->get('/queue/live');

        $responseSharma->assertRedirect('/clinic/sharmacare/queue/live');
    }

    public function test_navbar_launcher_generates_correct_canonical_url_for_each_clinic(): void
    {
        // Demo Clinic user on dashboard
        $resDemo = $this->actingAs($this->demoAdmin, 'web')
            ->get('/dashboard');

        $resDemo->assertStatus(200);
        $resDemo->assertSee('/clinic/democlinic/queue/live');
        $resDemo->assertDontSee('/clinic/sharmacare/queue/live');

        // Sharma Care user on dashboard
        $resSharma = $this->actingAs($this->sharmaAdmin, 'web')
            ->get('/dashboard');

        $resSharma->assertStatus(200);
        $resSharma->assertSee('/clinic/sharmacare/queue/live');
        $resSharma->assertDontSee('/clinic/democlinic/queue/live');
    }
}
