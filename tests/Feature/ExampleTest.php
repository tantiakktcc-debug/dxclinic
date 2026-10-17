<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_dashboard(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('dashboard'));
    }

    public function test_patient_booking_page_renders_successfully(): void
    {
        $clinic = \App\Models\Clinic::create([
            'clinic_code' => 'NX0001',
            'name' => 'Default Clinic',
            'phone' => '9876543210',
            'email' => 'clinic@default.com',
            'address' => 'Main Road',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $response = $this->get(route('pwa.book', ['public_slug' => $clinic->public_slug]));
        $response->assertStatus(200);
    }
}
