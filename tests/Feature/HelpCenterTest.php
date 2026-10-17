<?php

namespace Tests\Feature;

use App\Livewire\HelpCenter;
use App\Models\Clinic;
use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected User $operator;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = SubscriptionPlan::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'price' => 1000,
            'duration_months' => 12,
            'max_doctors' => 5,
            'is_active' => true,
        ]);

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'democlinic',
            'name' => 'Demo Clinic Varanasi',
            'address' => 'Bhelupur, Varanasi',
            'city' => 'Varanasi',
            'phone' => '9876543210',
            'activation_status' => 'ACTIVATED',
            'onboarding_status' => 'COMPLETED',
            'is_onboarded' => true,
        ]);

        License::create([
            'clinic_id' => $this->clinic->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'NXCL-TEST-1'),
            'license_key_encrypted' => encrypt('NXCL-TEST-1'),
            'license_key_prefix' => 'NXCL',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $this->operator = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Operator Desk',
            'email' => 'operator@dxclinic.local',
            'role' => 'OPERATOR',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
            'must_change_password' => false,
        ]);

        $this->admin = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Clinic Admin',
            'email' => 'admin@dxclinic.local',
            'role' => 'ADMIN',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
            'must_change_password' => false,
        ]);
    }

    public function test_help_center_accessible_to_authenticated_users(): void
    {
        $this->actingAs($this->operator);
        TenantContext::setClinic($this->clinic);

        $response = $this->get(route('help'));
        $response->assertStatus(200);
        $response->assertSee('Help Center & Official Manuals', false);
        $response->assertSee('https://nexorasolutionsind.co.in/');
    }

    public function test_help_center_catalog_tabs_render_correctly(): void
    {
        $this->actingAs($this->operator);
        TenantContext::setClinic($this->clinic);

        $tabs = [
            'operator-quick',
            'operator',
            'doctor-quick',
            'doctor',
            'admin-quick',
            'admin',
            'whatsapp',
            'financial-workflows',
            'faq',
            'troubleshooting',
        ];

        foreach ($tabs as $tab) {
            $comp = Livewire::test(HelpCenter::class, ['tab' => $tab])
                ->assertStatus(200)
                ->assertSee('https://nexorasolutionsind.co.in/');
            
            $this->assertNotEmpty($comp->get('currentMeta')['full_title']);
        }
    }

    public function test_help_center_whatsapp_manual_tab_renders_desk_and_ai_sections(): void
    {
        $this->actingAs($this->admin);
        TenantContext::setClinic($this->clinic);

        Livewire::test(HelpCenter::class, ['tab' => 'whatsapp'])
            ->assertStatus(200)
            ->assertSee('WHATSAPP OPERATOR DESK & AI BOOKING MANUAL')
            ->assertSee('OPERATOR DESK WORKSPACE')
            ->assertSee('AUTHORITATIVE BOOKING ELIGIBILITY & IST RULES')
            ->assertSee('https://nexorasolutionsind.co.in/');
    }

    public function test_manual_pdf_download_route_generates_valid_response(): void
    {
        $this->actingAs($this->operator);
        TenantContext::setClinic($this->clinic);

        $response = $this->get(route('pdf.manual', 'whatsapp'));
        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('Content-Type') ?? '', 'pdf') ||
            str_contains($response->headers->get('Content-Disposition') ?? '', 'pdf')
        );
    }
}
