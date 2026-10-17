<?php

namespace Tests\Feature;

use App\Livewire\Onboarding\ClinicOnboarding;
use App\Models\Clinic;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireOnboardingUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);
    }

    public function test_onboarding_wizard_step_navigation_and_validation(): void
    {
        $component = Livewire::test(ClinicOnboarding::class)
            ->assertSee('Clinic Onboarding Wizard')
            ->assertSee('1. Clinic Details')
            ->set('clinic_name', '')
            ->call('nextStep')
            ->assertHasErrors(['clinic_name'])
            ->assertSet('currentStep', 1);

        // Fill Step 1 and proceed to Step 2
        $component->set('clinic_name', 'Sharma Care Clinic')
            ->set('clinic_address', '123, MG Road')
            ->set('clinic_city', 'Varanasi')
            ->set('clinic_phone', '9876543210')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 2)
            ->assertSee('2. Doctor Details');

        // Fill Step 2 and proceed to Step 3
        $component->set('doctor_name', 'Dr. Rajesh Sharma')
            ->set('doctor_qualification', 'MBBS, MD')
            ->set('doctor_specialization', 'General Physician')
            ->set('opd_fees', 600)
            ->set('consultation_duration', 10)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 3)
            ->assertSee('3. Banking Details');

        // Fill Step 3 and proceed to Step 4
        $component->set('bank_name', 'State Bank of India')
            ->set('account_holder', 'Sharma Care Clinic')
            ->set('account_number', '123456789012')
            ->set('ifsc', 'SBIN0001234')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 4)
            ->assertSee('4. Opening Balances');

        // Fill Step 4 and proceed to Step 5
        $component->set('opening_bank_balance', 50000.00)
            ->set('opening_cash_in_hand', 10000.00)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 5)
            ->assertSee('5. Review & Complete', false);

        // Test Back button
        $component->call('prevStep')
            ->assertSet('currentStep', 4)
            ->call('nextStep')
            ->assertSet('currentStep', 5);

        // Test Confirmation checkbox required
        $component->set('is_confirmed', false)
            ->call('completeOnboarding')
            ->assertHasErrors(['is_confirmed']);

        // Complete onboarding
        $component->set('is_confirmed', true)
            ->call('completeOnboarding')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('clinics', [
            'name' => 'Sharma Care Clinic',
            'is_onboarded' => true,
        ]);
    }
}
