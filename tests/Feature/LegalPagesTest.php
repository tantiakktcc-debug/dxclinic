<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_about_page_renders_with_authoritative_developer_info(): void
    {
        $response = $this->get('/about');
        $response->assertStatus(200);
        $response->assertSee('Nexora Solutions');
        $response->assertSee('Kundan Kumar');
        $response->assertSee('+91 98765 43210');
        $response->assertSee('contact@nexorasolutionsind.co.in');
    }

    public function test_privacy_policy_page_renders_truthful_disclosures(): void
    {
        $response = $this->get('/privacy-policy');
        $response->assertStatus(200);
        $response->assertSee('Privacy Policy');
        $response->assertSee('Nexora Solutions');
        $response->assertSee('zero-telemetry');
        $response->assertSee('Kundan Kumar');
    }

    public function test_terms_and_conditions_page_renders_operational_terms(): void
    {
        $response = $this->get('/terms-and-conditions');
        $response->assertStatus(200);
        $response->assertSee('Terms & Conditions', false);
        $response->assertSee('Nexora Solutions');
        $response->assertSee('Soundbox');
        $response->assertSee('Kundan Kumar');
    }
}
