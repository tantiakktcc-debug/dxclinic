<?php

namespace Tests\Feature;

use App\Livewire\ClinicDoctorManager;
use App\Livewire\Settings\LicenseSubscriptionManager;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicDoctorSeatAddon;
use App\Models\Doctor;
use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class DoctorSeatAddonSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected SubscriptionPlan $plan;
    protected License $license;
    protected User $adminUser;
    protected User $operatorUser;
    protected User $doctorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX8888',
            'public_slug' => 'seatclinic',
            'name' => 'Seat Test Clinic',
            'phone' => '8888888888',
            'email' => 'seat@test.com',
            'address' => 'Station Road',
            'city' => 'Lucknow',
            'is_active' => true,
        ]);

        $this->plan = SubscriptionPlan::create([
            'name' => 'Professional Plan',
            'code' => 'PROFESSIONAL',
            'duration_months' => 12,
            'price' => 9999.00,
            'price_per_year' => 9999.00,
            'max_doctors' => 2,
            'additional_doctor_seat_price' => 1999.00,
            'features' => ['all'],
            'is_active' => true,
        ]);

        $this->license = License::create([
            'clinic_id' => $this->clinic->id,
            'plan_id' => $this->plan->id,
            'license_key_hash' => hash('sha256', 'KEY-SEAT-TEST'),
            'license_key_encrypted' => Crypt::encryptString('KEY-SEAT-TEST'),
            'license_key_prefix' => 'NXCL-SEAT',
            'status' => 'ACTIVE',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'max_doctors' => 2,
            'included_doctor_seats' => 2,
            'additional_doctor_seats' => 0,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@seatclinic.com',
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'ADMIN',
        ]);

        $this->operatorUser = User::create([
            'name' => 'Operator User',
            'email' => 'operator@seatclinic.com',
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'OPERATOR',
        ]);

        $this->doctorUser = User::create([
            'name' => 'Doctor User',
            'email' => 'doctor@seatclinic.com',
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'DOCTOR',
        ]);
    }

    public function test_seat_limit_reached_displays_buy_seat_cta_in_doctor_manager(): void
    {
        // Create 2 doctors to hit the limit of 2
        Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. First Doctor',
            'qualification' => 'MBBS',
            'registration_number' => 'REG-1',
            'is_active' => true,
        ]);
        Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Second Doctor',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'REG-2',
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser);

        $this->assertEquals(2, $this->clinic->totalDoctorCapacity());
        $this->assertEquals(2, $this->clinic->activeDoctorCount());
        $this->assertEquals(0, $this->clinic->availableDoctorSeats());
        $this->assertFalse($this->clinic->canAddDoctor());

        $component = Livewire::test(ClinicDoctorManager::class);
        $component->assertSee('Doctor Seats: 2 / 2');
        $component->assertSee('Seat Limit Reached');
        $component->assertSee('Request Additional Doctor Seat');
        $component->assertSee(route('subscription'));
    }

    public function test_admin_can_purchase_doctor_seat_addon_and_increase_entitlement(): void
    {
        $this->actingAs($this->adminUser);

        $this->assertEquals(2, $this->clinic->totalDoctorCapacity());

        $component = Livewire::test(LicenseSubscriptionManager::class);
        $component->set('addonSeatsCount', 2);
        $component->call('purchaseDoctorSeatAddon');

        $component->assertSee('Additional doctor seat request submitted.');
        $component->assertSee('Please contact the application developer to make payment and activate the requested doctor seat(s).');

        // Entitlement remains 2 while request is PENDING
        $this->assertEquals(2, $this->clinic->fresh()->totalDoctorCapacity());

        // Verify LicenseRequest was created in PENDING status
        $req = \App\Models\LicenseRequest::where('clinic_id', $this->clinic->id)
            ->where('request_type', 'DOCTOR_SEAT_ADDON')
            ->first();
        $this->assertNotNull($req);
        $this->assertEquals('PENDING', $req->status);
        $this->assertEquals(2, $req->requested_doctor_count);

        // Fulfill via Superadmin/LicenseRequestService
        app(\App\Services\LicenseRequestService::class)->fulfillDoctorSeatAddonRequest(
            $req->id,
            1,
            ['payment_method' => 'BANK_TRANSFER', 'payment_reference' => 'TXN-SEAT-01']
        );

        // Verify license entitlement increased only after fulfillment
        $this->license->refresh();
        $this->assertEquals(2, $this->license->additional_doctor_seats);
        $this->assertEquals(4, $this->clinic->fresh()->totalDoctorCapacity());

        // Verify ClinicDoctorSeatAddon was recorded
        $this->assertDatabaseHas('clinic_doctor_seat_addons', [
            'license_id' => $this->license->id,
            'seats_count' => 2,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_operator_or_doctor_cannot_purchase_seat_addon(): void
    {
        $this->actingAs($this->operatorUser);

        Livewire::test(LicenseSubscriptionManager::class)
            ->set('addonSeatsCount', 1)
            ->call('purchaseDoctorSeatAddon')
            ->assertStatus(403);

        $this->assertEquals(2, $this->clinic->fresh()->totalDoctorCapacity());

        $this->actingAs($this->doctorUser);

        Livewire::test(LicenseSubscriptionManager::class)
            ->set('addonSeatsCount', 1)
            ->call('purchaseDoctorSeatAddon')
            ->assertStatus(403);

        $this->assertEquals(2, $this->clinic->fresh()->totalDoctorCapacity());
    }

    public function test_cancelled_payment_does_not_increase_entitlement(): void
    {
        $this->actingAs($this->adminUser);

        $component = Livewire::test(LicenseSubscriptionManager::class);
        $component->call('simulateCancelledPayment');

        $component->assertSee('Payment was cancelled or failed.');
        $this->assertEquals(2, $this->clinic->fresh()->totalDoctorCapacity());
    }
}
