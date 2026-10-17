<?php

namespace Tests\Feature;

use App\Livewire\Onboarding\ClinicOnboarding;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\Journal;
use App\Models\LedgerAccount;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard chart of accounts
        $this->seed(SystemLedgerSeeder::class);
    }

    public function test_onboarding_page_renders_successfully(): void
    {
        $response = $this->get(route('onboarding'));
        $response->assertStatus(200);
    }

    public function test_completes_full_onboarding_and_creates_opening_journals(): void
    {
        Livewire::test(ClinicOnboarding::class)
            // Step 1: Clinic Profile
            ->set('clinic_name', 'Arogya Super Speciality Clinic')
            ->set('clinic_phone', '9876543210')
            ->set('clinic_email', 'info@arogyaclinic.com')
            ->set('clinic_address', 'B-12, Lanka Road')
            ->set('clinic_city', 'Varanasi')
            ->call('nextStep')
            ->assertSet('currentStep', 2)

            // Step 2: Doctor Profile
            ->set('doctor_name', 'Dr. Arvind Sharma')
            ->set('doctor_qualification', 'MBBS, MD (Medicine)')
            ->set('doctor_registration_number', 'MCI-88210')
            ->set('doctor_specialization', 'Physician & Cardiologist')
            ->set('opd_fees', 600.00)
            ->set('doctor_contact_number', '9876543211')
            ->call('nextStep')
            ->assertSet('currentStep', 3)

            // Step 3: Banking Details
            ->set('bank_name', 'State Bank of India')
            ->set('account_holder', 'Arogya Super Speciality Clinic')
            ->set('account_number', '30291823910')
            ->set('ifsc', 'SBIN0001234')
            ->set('branch', 'BHU Varanasi Branch')
            ->set('upi_id', 'arogya@sbi')
            ->call('nextStep')
            ->assertSet('currentStep', 4)

            // Step 4: Opening Bank Balance
            ->set('opening_bank_balance', 25000.00)
            ->call('nextStep')
            ->assertSet('currentStep', 5)

            // Step 5: Opening Physical Cash Balance & Complete
            ->set('opening_cash_in_hand', 5000.00)
            ->call('completeOnboarding')
            ->assertSet('is_completed', true);

        // Verify Database Records
        $clinic = Clinic::first();
        $this->assertNotNull($clinic);
        $this->assertEquals('Arogya Super Speciality Clinic', $clinic->name);
        $this->assertEquals('Varanasi', $clinic->city);
        $this->assertTrue((bool)$clinic->is_onboarded);

        $doctor = Doctor::first();
        $this->assertNotNull($doctor);
        $this->assertEquals('Dr. Arvind Sharma', $doctor->formatted_name);

        $feeMaster = FeeMaster::where('clinic_id', $clinic->id)->first();
        $this->assertNotNull($feeMaster);
        $this->assertEquals(600, $feeMaster->amount);

        // Verify Opening Balance Accounting Invariant (Debit == Credit)
        $openingJournal = Journal::where('journal_number', 'like', 'OB-%')->first();
        $this->assertNotNull($openingJournal, 'Opening balance journal must be recorded');
        $this->assertEquals(30000.00, (float)$openingJournal->total_debit);
        $this->assertEquals(30000.00, (float)$openingJournal->total_credit);
        $this->assertEquals($openingJournal->total_debit, $openingJournal->total_credit);

        // Cash account (1001) debit entry
        $cashEntry = $openingJournal->entries()->whereHas('account', fn($q) => $q->where('code', '1001'))->first();
        $this->assertNotNull($cashEntry);
        $this->assertEquals(5000.00, (float)$cashEntry->debit);

        // Bank account debit entry
        $bankEntry = $openingJournal->entries()->whereHas('account', fn($q) => $q->where('type', 'ASSET')->where('subtype', 'BANK_ACCOUNT'))->first();
        $this->assertNotNull($bankEntry);
        $this->assertEquals(25000.00, (float)$bankEntry->debit);

        // Opening Balance Equity (3001) credit entry
        $equityEntry = $openingJournal->entries()->whereHas('account', fn($q) => $q->where('code', '3001'))->first();
        $this->assertNotNull($equityEntry);
        $this->assertEquals(30000.00, (float)$equityEntry->credit);
    }
}
