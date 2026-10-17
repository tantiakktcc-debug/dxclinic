<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
        $response->assertSee('Sign In to Clinic Portal', false);
        $response->assertSee('admin@dxclinic.local');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Dr. Sharma',
            'email' => 'doctor@dxclinic.local',
            'role' => 'DOCTOR',
            'password' => Hash::make('DxClinic@Doc2026'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'doctor@dxclinic.local',
            'password' => 'DxClinic@Doc2026',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::create([
            'name' => 'Reception Operator',
            'email' => 'operator@dxclinic.local',
            'role' => 'OPERATOR',
            'password' => Hash::make('DxClinic@Op2026'),
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'operator@dxclinic.local',
            'password' => 'WrongPassword',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@dxclinic.local',
            'role' => 'ADMIN',
            'password' => Hash::make('DxClinic@Admin2026'),
        ]);

        $this->actingAs($user);
        $response = $this->get(route('logout'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_api_health_endpoint(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'database',
            'server_time',
        ]);
        $response->assertJsonMissing(['clinic_name', 'is_onboarded']);
    }

    public function test_api_diagnostics_frequent_endpoint(): void
    {
        $response = $this->getJson('/api/diagnostics/frequent');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'count',
            'items',
        ]);
    }
}
