<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DeveloperUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperadminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected DeveloperUser $developer;
    protected Clinic $clinic;
    protected User $clinicUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->developer = DeveloperUser::where('email', 'superadmin@dxclinic.local')->first()
            ?? DeveloperUser::create([
                'name' => 'DxClinic Superadmin',
                'email' => 'superadmin@dxclinic.local',
                'password' => Hash::make('TestAdminPass123!'),
                'is_active' => true,
            ]);

        $this->developer->update([
            'password' => Hash::make('TestAdminPass123!'),
            'is_active' => true,
        ]);

        $this->clinic = Clinic::create([
            'name' => 'Test Clinic',
            'clinic_code' => 'NX0001',
            'phone' => '9876543210',
            'email' => 'clinic@test.com',
            'address' => '123 Test St',
            'city' => 'Varanasi',
            'public_slug' => 'testclinic',
            'status' => 'ACTIVE',
            'is_onboarded' => true,
        ]);

        $this->clinicUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Clinic Admin',
            'email' => 'admin@clinic.test',
            'login_id' => 'ADM0001',
            'password' => Hash::make('ClinicAdmin123!'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_superadmin_login_page_renders_successfully(): void
    {
        $response = $this->get(route('superadmin.login'));

        $response->assertStatus(200);
        $response->assertSee('Superadmin Sign In');
        $response->assertSee(route('superadmin.login.submit'));
    }

    public function test_superadmin_valid_login_redirects_to_superadmin_dashboard_not_login(): void
    {
        $response = $this->post(route('superadmin.login.submit'), [
            'email' => 'superadmin@dxclinic.local',
            'password' => 'TestAdminPass123!',
        ]);

        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertTrue(Auth::guard('developer')->check());
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_superadmin_login_ignores_cross_boundary_clinic_intended_url(): void
    {
        // Simulate previous unauthenticated clinic hit leaving url.intended = /dashboard
        session(['url.intended' => route('dashboard')]);

        $response = $this->post(route('superadmin.login.submit'), [
            'email' => 'superadmin@dxclinic.local',
            'password' => 'TestAdminPass123!',
        ]);

        // Must redirect to superadmin.dashboard, NOT to clinic /dashboard (which redirects to /login)
        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertNotEquals(route('login'), $response->headers->get('Location'));
    }

    public function test_superadmin_login_preserves_valid_superadmin_intended_url(): void
    {
        $intendedSuperadminRoute = route('superadmin.license-requests');
        session(['url.intended' => $intendedSuperadminRoute]);

        $response = $this->post(route('superadmin.login.submit'), [
            'email' => 'superadmin@dxclinic.local',
            'password' => 'TestAdminPass123!',
        ]);

        $response->assertRedirect($intendedSuperadminRoute);
    }

    public function test_superadmin_unauthenticated_request_redirects_to_superadmin_login(): void
    {
        $response = $this->get(route('superadmin.dashboard'));

        $response->assertRedirect(route('superadmin.login'));
    }

    public function test_clinic_user_cannot_access_superadmin_dashboard(): void
    {
        $response = $this->actingAs($this->clinicUser, 'web')
            ->get(route('superadmin.dashboard'));

        // Guard developer is not checked, so it redirects to superadmin.login
        $response->assertRedirect(route('superadmin.login'));
    }

    public function test_clinic_credentials_cannot_authenticate_at_superadmin_login(): void
    {
        $response = $this->post(route('superadmin.login.submit'), [
            'email' => $this->clinicUser->email,
            'password' => 'ClinicAdmin123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('developer')->check());
    }

    public function test_superadmin_credentials_cannot_authenticate_at_clinic_login(): void
    {
        $response = $this->post('/login', [
            'clinic_code' => 'NX0001',
            'login_id' => 'superadmin@dxclinic.local',
            'password' => 'TestAdminPass123!',
        ]);

        $response->assertSessionHasErrors();
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_unauthenticated_clinic_request_redirects_to_clinic_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_superadmin_logout_clears_session_and_redirects_to_superadmin_login(): void
    {
        $this->actingAs($this->developer, 'developer');
        $this->assertTrue(Auth::guard('developer')->check());

        $response = $this->post(route('superadmin.logout'));

        $response->assertRedirect(route('superadmin.login'));
        $this->assertFalse(Auth::guard('developer')->check());
    }
}
