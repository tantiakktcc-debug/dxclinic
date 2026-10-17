<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DeveloperUser;
use App\Models\Doctor;
use App\Models\License;
use App\Models\LicenseRequest;
use App\Models\LicenseRequestEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LicenseRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LicenseHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected DeveloperUser $developer;
    protected Clinic $clinic;
    protected SubscriptionPlan $plan;
    protected User $clinicAdmin;
    protected User $doctorUser;
    protected User $operatorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->developer = DeveloperUser::where('email', 'superadmin@dxclinic.local')->first()
            ?? DeveloperUser::create([
                'name' => 'Superadmin Developer',
                'email' => 'superadmin@dxclinic.local',
                'password' => Hash::make('TestAdminPass123!'),
                'is_active' => true,
            ]);

        $this->plan = SubscriptionPlan::create([
            'name' => '5-Year Plan',
            'code' => 'YEARLY_5',
            'duration_months' => 60,
            'price' => 49999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 5,
        ]);

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX0004',
            'public_slug' => 'newlife',
            'name' => 'New Life Clinic',
            'phone' => '4444444444',
            'email' => 'dr.narendra@example.test',
            'address' => 'Abc Tower, Lanka',
            'city' => 'Varanasi',
            'pincode' => '221005',
            'activation_status' => 'ACTIVATED',
        ]);

        $this->clinicAdmin = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Narendra Pathak',
            'email' => 'dr.narendra@example.test',
            'login_id' => 'ADM0001',
            'role' => 'ADMIN',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);

        $doctorRecord = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Narendra Pathak',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'REG-12345',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Narendra Pathak',
            'email' => 'doctor@newlife.local',
            'login_id' => 'DOC0001',
            'role' => 'DOCTOR',
            'doctor_id' => $doctorRecord->id,
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);

        $this->operatorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Operator User',
            'email' => 'op@newlife.local',
            'login_id' => 'OPR0001',
            'role' => 'OPERATOR',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);
    }

    public function test_superadmin_can_access_license_history_and_does_not_show_no_paid_renewals_when_records_exist(): void
    {
        $req = LicenseRequest::create([
            'request_number' => 'LR-000001',
            'request_type' => 'INITIAL',
            'status' => 'FULFILLED',
            'clinic_name' => 'New Life Clinic',
            'owner_name' => 'Narendra Pathak',
            'mobile' => '4444444444',
            'email' => 'dr.narendra@example.test',
            'plan_id' => $this->plan->id,
            'plan_name_snapshot' => '5-Year Plan',
            'plan_duration_months' => 60,
            'net_amount' => 49999.00,
        ]);

        $response = $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history'));

        $response->assertStatus(200);
        $response->assertSee('LR-000001');
        $response->assertSee('New Life Clinic');
        $response->assertDontSee('No paid renewals recorded yet.');
    }

    public function test_all_request_types_are_visible_in_history(): void
    {
        // Initial
        LicenseRequest::create([
            'request_number' => 'LR-000001',
            'request_type' => 'INITIAL',
            'status' => 'FULFILLED',
            'clinic_name' => 'Clinic Initial',
            'owner_name' => 'Owner One',
            'mobile' => '9000000001',
            'email' => 'one@test.local',
            'net_amount' => 49999.00,
        ]);

        // Renewal
        LicenseRequest::create([
            'request_number' => 'LR-000002',
            'request_type' => 'RENEWAL',
            'status' => 'FULFILLED',
            'clinic_name' => 'Clinic Renewal',
            'owner_name' => 'Owner Two',
            'mobile' => '9000000002',
            'email' => 'two@test.local',
            'clinic_id' => $this->clinic->id,
            'clinic_code' => 'NX0004',
            'net_amount' => 9999.00,
        ]);

        // Doctor Seat Add-on
        LicenseRequest::create([
            'request_number' => 'LR-000003',
            'request_type' => 'DOCTOR_SEAT_ADDON',
            'status' => 'PENDING',
            'clinic_name' => 'Clinic Addon',
            'owner_name' => 'Owner Three',
            'mobile' => '9000000003',
            'email' => 'three@test.local',
            'clinic_id' => $this->clinic->id,
            'clinic_code' => 'NX0004',
            'requested_doctor_count' => 1,
            'additional_doctor_count' => 1,
            'net_amount' => 2999.00,
        ]);

        $response = $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history'));

        $response->assertStatus(200);
        $response->assertSee('LR-000001');
        $response->assertSee('Initial License');
        $response->assertSee('LR-000002');
        $response->assertSee('Renewal');
        $response->assertSee('LR-000003');
        $response->assertSee('Doctor Seat Add-on');
    }

    public function test_rejected_and_cancelled_requests_remain_visible(): void
    {
        LicenseRequest::create([
            'request_number' => 'LR-000010',
            'request_type' => 'INITIAL',
            'status' => 'REJECTED',
            'clinic_name' => 'Rejected Clinic',
            'owner_name' => 'Rejected Owner',
            'mobile' => '9111111111',
            'email' => 'rejected@test.local',
            'rejection_reason' => 'Invalid clinic documentation',
            'net_amount' => 49999.00,
        ]);

        LicenseRequest::create([
            'request_number' => 'LR-000011',
            'request_type' => 'DOCTOR_SEAT_ADDON',
            'status' => 'CANCELLED',
            'clinic_name' => 'Cancelled Clinic',
            'owner_name' => 'Cancelled Owner',
            'mobile' => '9222222222',
            'email' => 'cancelled@test.local',
            'cancellation_reason' => 'Duplicate request by operator',
            'net_amount' => 2999.00,
        ]);

        $response = $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history'));

        $response->assertStatus(200);
        $response->assertSee('LR-000010');
        $response->assertSee('REJECTED');
        $response->assertSee('LR-000011');
        $response->assertSee('CANCELLED');
    }

    public function test_filters_by_status_and_type(): void
    {
        LicenseRequest::create([
            'request_number' => 'LR-000101',
            'request_type' => 'INITIAL',
            'status' => 'FULFILLED',
            'clinic_name' => 'Fulfilled Initial',
            'owner_name' => 'Alpha',
            'mobile' => '9333333331',
            'email' => 'alpha@test.local',
            'net_amount' => 49999.00,
        ]);

        LicenseRequest::create([
            'request_number' => 'LR-000102',
            'request_type' => 'DOCTOR_SEAT_ADDON',
            'status' => 'PENDING',
            'clinic_name' => 'Pending Addon',
            'owner_name' => 'Beta',
            'mobile' => '9333333332',
            'email' => 'beta@test.local',
            'net_amount' => 2999.00,
        ]);

        // Filter by Status = FULFILLED
        $resFulfilled = $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history', ['status' => 'FULFILLED']));
        $resFulfilled->assertSee('LR-000101');
        $resFulfilled->assertDontSee('LR-000102');

        // Filter by Type = DOCTOR_SEAT_ADDON
        $resAddon = $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history', ['type' => 'DOCTOR_SEAT_ADDON']));
        $resAddon->assertSee('LR-000102');
        $resAddon->assertDontSee('LR-000101');
    }

    public function test_search_by_clinic_name_code_slug_owner_and_license_key(): void
    {
        $rawKey = 'NXCL-24LG-8DDG-WJSV-PYQC';
        $license = License::create([
            'clinic_id' => $this->clinic->id,
            'plan_id' => $this->plan->id,
            'license_key_hash' => hash('sha256', strtoupper($rawKey)),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'license_key_prefix' => 'NXCL',
            'start_date' => '2026-10-04',
            'end_date' => '2031-11-04',
            'amount' => 49999.00,
            'net_amount' => 49999.00,
            'status' => 'ACTIVE',
        ]);

        $req = LicenseRequest::create([
            'request_number' => 'LR-000002',
            'request_type' => 'INITIAL',
            'status' => 'FULFILLED',
            'clinic_name' => 'New Life Clinic',
            'owner_name' => 'Narendra Pathak',
            'mobile' => '4444444444',
            'email' => 'dr.narendra@example.test',
            'fulfilled_clinic_id' => $this->clinic->id,
            'fulfilled_license_id' => $license->id,
            'net_amount' => 49999.00,
        ]);

        // Search by Clinic Name
        $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history', ['q' => 'New Life Clinic']))
            ->assertSee('LR-000002');

        // Search by Clinic Code
        $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history', ['q' => 'NX0004']))
            ->assertSee('LR-000002');

        // Search by Slug
        $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history', ['q' => 'newlife']))
            ->assertSee('LR-000002');

        // Search by Owner
        $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history', ['q' => 'Narendra Pathak']))
            ->assertSee('LR-000002');

        // Search by License Key
        $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history', ['q' => $rawKey]))
            ->assertSee('LR-000002');
    }

    public function test_history_detail_view_shows_complete_snapshots_and_protects_credentials(): void
    {
        $rawKey = 'NXCL-24LG-8DDG-WJSV-PYQC';
        $license = License::create([
            'clinic_id' => $this->clinic->id,
            'plan_id' => $this->plan->id,
            'license_key_hash' => hash('sha256', strtoupper($rawKey)),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'license_key_prefix' => 'NXCL',
            'start_date' => '2026-10-04',
            'end_date' => '2031-11-04',
            'amount' => 49999.00,
            'net_amount' => 49999.00,
            'status' => 'ACTIVE',
        ]);

        $req = LicenseRequest::create([
            'request_number' => 'LR-000002',
            'request_type' => 'INITIAL',
            'status' => 'FULFILLED',
            'clinic_name' => 'New Life Clinic',
            'owner_name' => 'Narendra Pathak',
            'mobile' => '4444444444',
            'email' => 'dr.narendra@example.test',
            'address' => 'Abc Tower, Lanka',
            'city' => 'Varanasi',
            'pincode' => '221005',
            'plan_id' => $this->plan->id,
            'plan_name_snapshot' => '5-Year Plan',
            'plan_duration_months' => 60,
            'trial_months' => 1,
            'wants_trial' => true,
            'requested_doctor_count' => 1,
            'included_doctor_count' => 1,
            'additional_doctor_count' => 0,
            'base_amount' => 49999.00,
            'additional_doctor_amount' => 0.00,
            'discount_amount' => 0.00,
            'net_amount' => 49999.00,
            'payment_method' => 'CASH',
            'payment_reference' => 'cash-ref-001',
            'payment_date' => '2026-10-04',
            'payment_confirmed_at' => now(),
            'fulfilled_clinic_id' => $this->clinic->id,
            'fulfilled_license_id' => $license->id,
            'account_issuance_metadata' => [
                'admin' => [
                    'role' => 'ADMINISTRATOR',
                    'login_id' => 'ADM0001',
                    'email' => 'dr.narendra@example.test',
                    'name' => 'Narendra Pathak',
                    'status' => 'ACTIVE',
                    'credential_issued' => true,
                ],
                'operator' => [
                    'role' => 'OPERATOR',
                    'login_id' => 'OPR0001',
                    'email' => 'dr.narendra@example.test',
                    'name' => 'Operator',
                    'status' => 'ACTIVE',
                    'credential_issued' => true,
                ],
                'doctor' => [
                    'role' => 'DOCTOR',
                    'login_id' => 'DOC0001',
                    'email' => 'dr.narendra@example.test',
                    'name' => 'Doctor',
                    'doctor_name' => 'Dr. Narendra Pathak',
                    'qualification' => 'MBBS, MD',
                    'status' => 'ACTIVE',
                    'credential_issued' => true,
                ],
                'activation_code' => 'ISSUED',
            ],
            'snapshot_data' => [
                'clinic_code' => 'NX0004',
                'public_slug' => 'newlife',
                'clinic_name' => 'New Life Clinic',
                'owner_name' => 'Narendra Pathak',
                'mobile' => '4444444444',
                'email' => 'dr.narendra@example.test',
                'city' => 'Varanasi',
                'pincode' => '221005',
                'address' => 'Abc Tower, Lanka',
                'plan_name' => '5-Year Plan',
                'plan_duration_months' => 60,
                'trial_months' => 1,
                'included_doctor_count' => 1,
                'additional_doctor_count' => 0,
                'base_amount' => 49999.00,
                'net_amount' => 49999.00,
                'license_key_prefix' => 'NXCL',
                'start_date' => '2026-10-04',
                'end_date' => '2031-11-04',
            ],
        ]);

        LicenseRequestEvent::create([
            'license_request_id' => $req->id,
            'event_type' => 'SUBMITTED',
            'performed_by_type' => 'public',
            'old_status' => null,
            'new_status' => 'SUBMITTED',
            'notes' => 'Initial license request submitted for New Life Clinic',
            'created_at' => now()->subHours(2),
        ]);

        LicenseRequestEvent::create([
            'license_request_id' => $req->id,
            'event_type' => 'FULFILLED',
            'performed_by_type' => 'developer_user',
            'old_status' => 'SUBMITTED',
            'new_status' => 'FULFILLED',
            'notes' => 'License generated: Clinic NX0004, License #12',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history.show', $req));

        $response->assertStatus(200);

        // Request Snapshot
        $response->assertSee('LR-000002');
        $response->assertSee('Initial License');
        $response->assertSee('FULFILLED');

        // Clinic Snapshot
        $response->assertSee('New Life Clinic');
        $response->assertSee('Narendra Pathak');
        $response->assertSee('4444444444');
        $response->assertSee('dr.narendra@example.test');
        $response->assertSee('Varanasi');
        $response->assertSee('221005');
        $response->assertSee('Abc Tower, Lanka');
        $response->assertSee('NX0004');
        $response->assertSee('newlife');

        // Plan & Pricing Snapshot
        $response->assertSee('5-Year Plan');
        $response->assertSee('60 Months');
        $response->assertSee('1 month FREE trial');
        $response->assertSee('61 Months');
        $response->assertSee('49,999.00');

        // Payment Snapshot
        $response->assertSee('CASH');
        $response->assertSee('cash-ref-001');

        // Issuance Snapshot
        $response->assertSee('ACTIVE');

        // Account Issuance Audit (Non-secret only)
        $response->assertSee('ADM0001');
        $response->assertSee('OPR0001');
        $response->assertSee('DOC0001');
        $response->assertSee('Dr. Narendra Pathak');

        // STRICT SECURITY: Temporary passwords & activation codes NEVER shown in plaintext
        $response->assertDontSee('raw_temp_password');
        $response->assertSee('ISSUED (Masked: ********)');
        $response->assertSee('Protected — Displayed Once in Onboarding Package');

        // Event Timeline
        $response->assertSee('SUBMITTED');
        $response->assertSee('FULFILLED');
    }

    public function test_clinic_users_are_denied_access_with_403(): void
    {
        // Clinic Admin
        $resAdmin = $this->actingAs($this->clinicAdmin, 'web')
            ->get(route('superadmin.license-history'));
        $resAdmin->assertStatus(403);

        // Doctor
        $resDoctor = $this->actingAs($this->doctorUser, 'web')
            ->get(route('superadmin.license-history'));
        $resDoctor->assertStatus(403);

        // Operator
        $resOperator = $this->actingAs($this->operatorUser, 'web')
            ->get(route('superadmin.license-history'));
        $resOperator->assertStatus(403);
    }

    public function test_guest_is_redirected_to_superadmin_login(): void
    {
        $response = $this->get(route('superadmin.license-history'));
        $response->assertRedirect(route('superadmin.login'));
    }

    public function test_historical_snapshot_is_immutable_even_if_current_clinic_profile_changes(): void
    {
        $req = LicenseRequest::create([
            'request_number' => 'LR-000099',
            'request_type' => 'INITIAL',
            'status' => 'FULFILLED',
            'clinic_name' => 'Original Clinic Name',
            'owner_name' => 'Original Owner',
            'mobile' => '9999999999',
            'email' => 'original@clinic.com',
            'city' => 'Original City',
            'pincode' => '111111',
            'address' => 'Original Address',
            'net_amount' => 49999.00,
            'snapshot_data' => [
                'clinic_name' => 'Original Clinic Name',
                'owner_name' => 'Original Owner',
                'mobile' => '9999999999',
                'email' => 'original@clinic.com',
                'city' => 'Original City',
                'pincode' => '111111',
                'address' => 'Original Address',
            ],
            'fulfilled_clinic_id' => $this->clinic->id,
        ]);

        // Mutate current clinic
        $this->clinic->update([
            'name' => 'Completely Different Mutated Name',
            'city' => 'Different City',
        ]);

        $response = $this->actingAs($this->developer, 'developer')
            ->get(route('superadmin.license-history.show', $req));

        $response->assertStatus(200);
        $response->assertSee('Original Clinic Name');
        $response->assertSee('Original City');
        $response->assertDontSee('Completely Different Mutated Name');
    }
}
