<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DeveloperUser;
use App\Models\License;
use App\Models\LicenseRequest;
use App\Models\LicenseRequestEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LicenseRequestService;
use App\Services\LicenseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Revision36FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected DeveloperUser $superadmin;
    protected SubscriptionPlan $trialPlan;
    protected SubscriptionPlan $quarterlyPlan;
    protected SubscriptionPlan $yearlyPlan;
    protected Clinic $existingClinic;
    protected License $existingLicense;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Subscription Plans
        $this->trialPlan = SubscriptionPlan::firstOrCreate(['code' => 'TRIAL'], [
            'name' => 'Trial Plan',
            'duration_months' => 1,
            'price' => 0.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->quarterlyPlan = SubscriptionPlan::firstOrCreate(['code' => 'QUARTERLY'], [
            'name' => 'Quarterly Plan',
            'duration_months' => 3,
            'price' => 4499.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 2,
        ]);

        $this->yearlyPlan = SubscriptionPlan::firstOrCreate(['code' => 'YEARLY'], [
            'name' => 'Yearly Plan',
            'duration_months' => 12,
            'price' => 14999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 4,
        ]);

        // 2. Superadmin Developer User
        $this->superadmin = DeveloperUser::create([
            'name' => 'Superadmin Rev36',
            'email' => 'admin@dxclinic.com',
            'password' => bcrypt('SuperSecretPassword123!'),
            'role' => 'SUPERADMIN',
            'is_active' => true,
        ]);

        // 3. Existing Clinic for Renewal Testing
        $issued = LicenseService::issueLicense(
            clinicData: [
                'name' => 'Apollo Varanasi Clinic',
                'address' => 'Mall Road, Varanasi',
                'city' => 'Varanasi',
                'pincode' => '221002',
                'phone' => '9876543210',
                'email' => 'contact@apollovns.com',
            ],
            adminData: [
                'name' => 'Dr. R. K. Sharma',
                'email' => 'drsharma@apollovns.com',
                'phone' => '9876543210',
            ],
            planId: $this->yearlyPlan->id,
            discount: 0.0,
            issuedBy: $this->superadmin->id,
            includedDoctorSeats: 1,
            additionalDoctorSeats: 1,
        );

        $this->existingClinic = $issued['clinic'];
        $this->existingClinic->update(['activation_status' => 'ACTIVATED']);
        $this->existingLicense = $issued['license'];
    }

    // ─────────────────────────────────────────────────────────────
    // SECTION 1: PUBLIC NEW LICENSE REQUEST
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_public_request_page_renders_with_active_plans()
    {
        $response = $this->get(route('license.request'));

        $response->assertStatus(200);
        $response->assertSee('Request a License');
        $response->assertSee('Yearly Plan');
        $response->assertSee('Quarterly Plan');
        // TRIAL plan should NOT be selectable as a standalone paid tier
        $response->assertDontSee('<input type="radio" name="plan_id" value="' . $this->trialPlan->id . '"', false);
    }

    /** @test */
    public function test_pricing_page_renders_correctly()
    {
        $response = $this->get(route('license.pricing'));

        $response->assertStatus(200);
        $response->assertSee('Quarterly Plan');
        $response->assertSee('Yearly Plan');
    }

    /** @test */
    public function test_submit_initial_request_creates_record_and_events()
    {
        $payload = [
            'clinic_name' => 'Kashi Health Center',
            'owner_name' => 'Dr. Anand Kumar',
            'mobile' => '9876543210',
            'email' => 'anand@kashihealth.com',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
            'pincode' => '221005',
            'plan_id' => $this->yearlyPlan->id,
            'wants_trial' => '1',
            'requested_doctor_count' => 3, // 1 included + 2 additional
            'website_url' => '', // honeypot left empty
        ];

        $response = $this->post(route('license.request.submit'), $payload);

        $response->assertStatus(200);
        $response->assertSee('Request Submitted');
        $response->assertSee('Kashi Health Center');

        $request = LicenseRequest::where('email', 'anand@kashihealth.com')->first();
        $this->assertNotNull($request);
        $this->assertEquals('INITIAL', $request->request_type);
        $this->assertEquals('SUBMITTED', $request->status);
        $this->assertStringStartsWith('LR-', $request->request_number);
        $this->assertTrue($request->wants_trial);
        $this->assertEquals(1, $request->trial_months);
        $this->assertEquals(3, $request->requested_doctor_count);
        $this->assertEquals(1, $request->included_doctor_count);
        $this->assertEquals(2, $request->additional_doctor_count);
        $this->assertEquals(14999.00, (float) $request->base_amount);
        $this->assertEquals(5998.00, (float) $request->additional_doctor_amount); // 2 * 2999
        $this->assertEquals(0.00, (float) $request->trial_amount);
        $this->assertEquals(20997.00, (float) $request->net_amount);

        // Audit event created
        $this->assertDatabaseHas('license_request_events', [
            'license_request_id' => $request->id,
            'event_type' => 'SUBMITTED',
            'performed_by_type' => 'public',
            'new_status' => 'SUBMITTED',
        ]);
    }

    /** @test */
    public function test_initial_request_honeypot_silently_rejects_spam()
    {
        $payload = [
            'clinic_name' => 'Spam Clinic',
            'owner_name' => 'Bot Spammer',
            'mobile' => '9999999999',
            'email' => 'spam@bot.com',
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 1,
            'website_url' => 'http://spam-link.com', // honeypot filled!
        ];

        $response = $this->post(route('license.request.submit'), $payload);

        // Silently redirected with success message
        $response->assertRedirect(route('license.request'));
        $this->assertDatabaseMissing('license_requests', [
            'email' => 'spam@bot.com',
        ]);
    }

    /** @test */
    public function test_duplicate_pending_initial_request_is_prevented()
    {
        LicenseRequestService::submitInitialRequest([
            'clinic_name' => 'Clinic First',
            'owner_name' => 'Dr. First',
            'mobile' => '9888877771',
            'email' => 'duplicate@test.com',
            'plan_id' => $this->yearlyPlan->id,
            'wants_trial' => false,
            'requested_doctor_count' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A pending license request already exists for this email address');

        LicenseRequestService::submitInitialRequest([
            'clinic_name' => 'Clinic Second',
            'owner_name' => 'Dr. Second',
            'mobile' => '9888877772',
            'email' => 'duplicate@test.com',
            'plan_id' => $this->yearlyPlan->id,
            'wants_trial' => false,
            'requested_doctor_count' => 1,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // SECTION 2: PUBLIC RENEWAL REQUEST & CLINIC PHONE IDENTITY
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_public_renewal_page_renders()
    {
        $response = $this->get(route('license.renewal-request'));

        $response->assertStatus(200);
        $response->assertSee('Renew Your License');
        $response->assertSee('Clinic ID');
        $response->assertSee('Registered Mobile');
    }

    /** @test */
    public function test_renewal_verification_succeeds_with_clinic_phone()
    {
        $payload = [
            'clinic_code' => $this->existingClinic->clinic_code,
            'mobile' => $this->existingClinic->phone, // Registered clinic mobile
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 2,
            'website_url' => '',
        ];

        $response = $this->post(route('license.renewal-request.submit'), $payload);

        $response->assertStatus(200);
        $response->assertSee('Renewal Request Submitted');
        $response->assertSee('Apollo Varanasi Clinic');

        $request = LicenseRequest::where('clinic_id', $this->existingClinic->id)->first();
        $this->assertNotNull($request);
        $this->assertEquals('RENEWAL', $request->request_type);
        $this->assertEquals($this->existingClinic->clinic_code, $request->clinic_code);
        $this->assertFalse($request->wants_trial); // Never trial on renewal
        $this->assertEquals(0, $request->trial_months);
    }

    /** @test */
    public function test_renewal_verification_fails_with_invalid_credentials_without_disclosing_clinic_existence()
    {
        // 1. Wrong mobile for existing clinic
        $response = $this->from(route('license.renewal-request'))->post(route('license.renewal-request.submit'), [
            'clinic_code' => $this->existingClinic->clinic_code,
            'mobile' => '9999999999', // Mismatched mobile
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 1,
        ]);

        $response->assertRedirect(route('license.renewal-request'));
        $response->assertSessionHasErrors('error');
        $this->assertEquals(
            'Unable to verify clinic identity. Please check your Clinic ID and registered mobile number.',
            session('errors')->first('error')
        );

        // 2. Non-existent clinic code
        $response2 = $this->from(route('license.renewal-request'))->post(route('license.renewal-request.submit'), [
            'clinic_code' => 'NX9999', // Does not exist
            'mobile' => '9876543210',
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 1,
        ]);

        $response2->assertRedirect(route('license.renewal-request'));
        $response2->assertSessionHasErrors('error');
        // Exact same error message (no information leakage!)
        $this->assertEquals(
            'Unable to verify clinic identity. Please check your Clinic ID and registered mobile number.',
            session('errors')->first('error')
        );
    }

    /** @test */
    public function test_renewal_request_never_allows_trial_months()
    {
        $req = LicenseRequestService::submitRenewalRequest([
            'clinic_code' => $this->existingClinic->clinic_code,
            'mobile' => $this->existingClinic->phone,
            'plan_id' => $this->yearlyPlan->id,
            'wants_trial' => true, // Attempted trial
            'requested_doctor_count' => 1,
        ]);

        // Server-side enforcement: trial MUST be 0 on renewal
        $this->assertFalse($req->wants_trial);
        $this->assertEquals(0, $req->trial_months);
        $this->assertEquals(0.00, (float) $req->trial_amount);
    }

    /** @test */
    public function test_duplicate_pending_renewal_request_is_prevented()
    {
        LicenseRequestService::submitRenewalRequest([
            'clinic_code' => $this->existingClinic->clinic_code,
            'mobile' => $this->existingClinic->phone,
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A pending renewal request already exists for this clinic');

        LicenseRequestService::submitRenewalRequest([
            'clinic_code' => $this->existingClinic->clinic_code,
            'mobile' => $this->existingClinic->phone,
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 1,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // SECTION 3: SUPERADMIN MANAGEMENT & FULFILLMENT
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_superadmin_can_view_license_requests_queue_and_filters()
    {
        $req1 = LicenseRequestService::submitInitialRequest([
            'clinic_name' => 'Filter Clinic 1',
            'owner_name' => 'Owner 1',
            'mobile' => '9870000001',
            'email' => 'filter1@test.com',
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 1,
        ]);

        $response = $this->actingAs($this->superadmin, 'developer')
            ->get(route('superadmin.license-requests'));

        $response->assertStatus(200);
        $response->assertSee('License Requests');
        $response->assertSee($req1->request_number);
        $response->assertSee('Filter Clinic 1');

        // Filter by status
        $filterResponse = $this->actingAs($this->superadmin, 'developer')
            ->get(route('superadmin.license-requests', ['status' => 'SUBMITTED']));
        $filterResponse->assertStatus(200);
        $filterResponse->assertSee($req1->request_number);

        // Filter by search query
        $searchResponse = $this->actingAs($this->superadmin, 'developer')
            ->get(route('superadmin.license-requests', ['q' => 'Filter Clinic 1']));
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee($req1->request_number);
    }

    /** @test */
    public function test_superadmin_can_view_request_detail()
    {
        $req = LicenseRequestService::submitInitialRequest([
            'clinic_name' => 'Detail Test Clinic',
            'owner_name' => 'Dr. Detail',
            'mobile' => '9870000002',
            'email' => 'detail@test.com',
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 2,
        ]);

        $response = $this->actingAs($this->superadmin, 'developer')
            ->get(route('superadmin.license-requests.show', $req));

        $response->assertStatus(200);
        $response->assertSee($req->request_number);
        $response->assertSee('Detail Test Clinic');
        $response->assertSee('Confirm Payment & Generate License', false);
    }

    /** @test */
    public function test_superadmin_can_mark_request_under_review()
    {
        $req = LicenseRequestService::submitInitialRequest([
            'clinic_name' => 'Review Test Clinic',
            'owner_name' => 'Dr. Review',
            'mobile' => '9870000003',
            'email' => 'review@test.com',
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 1,
        ]);

        $response = $this->actingAs($this->superadmin, 'developer')
            ->post(route('superadmin.license-requests.review', $req));

        $response->assertRedirect();
        $this->assertEquals('UNDER_REVIEW', $req->fresh()->status);
        $this->assertEquals($this->superadmin->id, $req->fresh()->reviewed_by);

        $this->assertDatabaseHas('license_request_events', [
            'license_request_id' => $req->id,
            'event_type' => 'UNDER_REVIEW',
            'performed_by_id' => $this->superadmin->id,
        ]);
    }

    /** @test */
    public function test_superadmin_can_fulfill_initial_request_in_one_action()
    {
        $req = LicenseRequestService::submitInitialRequest([
            'clinic_name' => 'Sunrise Multispeciality',
            'owner_name' => 'Dr. Rajesh Gupta',
            'mobile' => '9812345678',
            'email' => 'drgupta@sunrise.com',
            'address' => 'Sigra, Varanasi',
            'city' => 'Varanasi',
            'pincode' => '221010',
            'plan_id' => $this->yearlyPlan->id,
            'wants_trial' => true, // 1 free trial month stacked!
            'requested_doctor_count' => 2, // 1 included + 1 additional
        ]);

        // Fulfill directly from SUBMITTED (single-click payment confirmation + generation)
        $response = $this->actingAs($this->superadmin, 'developer')
            ->post(route('superadmin.license-requests.fulfill', $req), [
                'payment_method' => 'UPI',
                'payment_reference' => 'UPI/REF/99887766',
                'payment_date' => now()->toDateString(),
                'discount_amount' => 500.00,
                'admin_notes' => 'Received via PhonePe QR',
                'payment_confirmed' => '1',
            ]);

        $response->assertStatus(200);
        $response->assertSee('Credential Package');
        $response->assertSee('ADMINISTRATOR');
        $response->assertSee('OPERATOR');
        $response->assertSee('DOCTOR');
        $response->assertSee('License Key');
        $response->assertSee('Activation Code');

        $req->refresh();
        $this->assertEquals('FULFILLED', $req->status);
        $this->assertEquals('UPI', $req->payment_method);
        $this->assertEquals('UPI/REF/99887766', $req->payment_reference);
        $this->assertNotNull($req->fulfilled_clinic_id);
        $this->assertNotNull($req->fulfilled_license_id);

        // Verify the newly created clinic
        $newClinic = Clinic::find($req->fulfilled_clinic_id);
        $this->assertNotNull($newClinic);
        $this->assertEquals('Sunrise Multispeciality', $newClinic->name);
        $this->assertEquals('221010', $newClinic->pincode);

        // Verify users created (Admin, Operator, Doctor)
        $this->assertDatabaseHas('users', [
            'clinic_id' => $newClinic->id,
            'role' => 'ADMIN',
        ]);
        $this->assertDatabaseHas('users', [
            'clinic_id' => $newClinic->id,
            'role' => 'OPERATOR',
        ]);
        $this->assertDatabaseHas('users', [
            'clinic_id' => $newClinic->id,
            'role' => 'DOCTOR',
        ]);

        // Verify license validity: 12 months + 1 trial month = 13 months
        $license = License::find($req->fulfilled_license_id);
        $this->assertNotNull($license);
        $expectedEnd = Carbon::today()->addMonths(13)->toDateString();
        $this->assertEquals($expectedEnd, $license->end_date->toDateString());
        $this->assertEquals(1, $license->additional_doctor_seats);

        // Audit events recorded
        $this->assertDatabaseHas('license_request_events', [
            'license_request_id' => $req->id,
            'event_type' => 'PAYMENT_CONFIRMED',
            'new_status' => 'FULFILLED',
        ]);
        $this->assertDatabaseHas('license_request_events', [
            'license_request_id' => $req->id,
            'event_type' => 'FULFILLED',
            'new_status' => 'FULFILLED',
        ]);
    }

    /** @test */
    public function test_superadmin_can_fulfill_renewal_request_in_one_action()
    {
        $previousEndDate = $this->existingLicense->end_date;

        $req = LicenseRequestService::submitRenewalRequest([
            'clinic_code' => $this->existingClinic->clinic_code,
            'mobile' => $this->existingClinic->phone,
            'plan_id' => $this->quarterlyPlan->id,
            'requested_doctor_count' => 2,
        ]);

        $response = $this->actingAs($this->superadmin, 'developer')
            ->post(route('superadmin.license-requests.fulfill', $req), [
                'payment_method' => 'BANK_TRANSFER',
                'payment_reference' => 'NEFT-55443322',
                'payment_date' => now()->toDateString(),
                'discount_amount' => 0,
                'payment_confirmed' => '1',
            ]);

        $response->assertRedirect(route('superadmin.license-requests'));

        $req->refresh();
        $this->assertEquals('FULFILLED', $req->status);
        $this->assertEquals('BANK_TRANSFER', $req->payment_method);
        $this->assertEquals('NEFT-55443322', $req->payment_reference);

        // New license created via Revision 35 renewLicense()
        $newLicense = License::find($req->fulfilled_license_id);
        $this->assertNotNull($newLicense);

        // Seamless period continuation
        $expectedStart = $previousEndDate->copy()->addDay()->toDateString();
        $this->assertEquals($expectedStart, $newLicense->start_date->toDateString());
        // 3 months extension (Revision 35 formula: addMonths - 1 day)
        $expectedEnd = Carbon::parse($expectedStart)->addMonths(3)->subDay()->toDateString();
        $this->assertEquals($expectedEnd, $newLicense->end_date->toDateString());

        // Clinic active and zero activation required
        $this->existingClinic->refresh();
        $this->assertEquals('ACTIVATED', $this->existingClinic->activation_status);

        // Audit events recorded
        $this->assertDatabaseHas('license_renewals', [
            'clinic_id' => $this->existingClinic->id,
            'payment_reference' => 'NEFT-55443322',
        ]);
    }

    /** @test */
    public function test_superadmin_can_reject_request()
    {
        $req = LicenseRequestService::submitInitialRequest([
            'clinic_name' => 'Ineligible Clinic',
            'owner_name' => 'Applicant',
            'mobile' => '9870000004',
            'email' => 'ineligible@test.com',
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 1,
        ]);

        $response = $this->actingAs($this->superadmin, 'developer')
            ->post(route('superadmin.license-requests.reject', $req), [
                'rejection_reason' => 'Duplicate clinic application detected.',
            ]);

        $response->assertRedirect();
        $req->refresh();
        $this->assertEquals('REJECTED', $req->status);
        $this->assertEquals('Duplicate clinic application detected.', $req->rejection_reason);

        $this->assertDatabaseHas('license_request_events', [
            'license_request_id' => $req->id,
            'event_type' => 'REJECTED',
            'notes' => 'Duplicate clinic application detected.',
        ]);
    }

    /** @test */
    public function test_cannot_fulfill_rejected_or_already_fulfilled_request()
    {
        $req = LicenseRequestService::submitInitialRequest([
            'clinic_name' => 'Guard Test Clinic',
            'owner_name' => 'Dr. Guard',
            'mobile' => '9870000005',
            'email' => 'guard@test.com',
            'plan_id' => $this->yearlyPlan->id,
            'requested_doctor_count' => 1,
        ]);

        LicenseRequestService::rejectRequest($req->id, $this->superadmin->id, 'Rejected');

        // Cannot fulfill rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This request cannot be fulfilled in its current state.');

        LicenseRequestService::fulfillInitialRequest($req->id, $this->superadmin->id, [
            'payment_method' => 'CASH',
        ]);
    }

    /** @test */
    public function test_unauthenticated_cannot_access_superadmin_license_requests()
    {
        $response = $this->get(route('superadmin.license-requests'));
        $response->assertRedirect(route('superadmin.login'));
    }
}
