<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicDoctorSeatAddon;
use App\Models\DeveloperUser;
use App\Models\Doctor;
use App\Models\License;
use App\Models\LicenseRenewal;
use App\Models\PlatformExpense;
use App\Models\PlatformInvoiceItem;
use App\Models\PlatformSalesInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\PlatformInvoiceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Revision42PlatformSalesTest extends TestCase
{
    use RefreshDatabase;

    protected DeveloperUser $superadmin;
    protected SubscriptionPlan $yearlyPlan;
    protected SubscriptionPlan $trialPlan;
    protected Clinic $clinic1;
    protected Clinic $clinic2;
    protected Clinic $demoClinic;
    protected User $tenantAdmin1;
    protected User $tenantAdmin2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Superadmin User
        $this->superadmin = DeveloperUser::where('email', 'superadmin@dxclinic.local')->first()
            ?? DeveloperUser::create([
                'name' => 'Superadmin Developer',
                'email' => 'superadmin@dxclinic.local',
                'password' => Hash::make('SuperSecret123!'),
                'is_active' => true,
            ]);

        // 2. Subscription Plans
        $this->trialPlan = SubscriptionPlan::firstOrCreate(['code' => 'TRIAL'], [
            'name' => 'Trial Plan',
            'duration_months' => 1,
            'price' => 0.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->yearlyPlan = SubscriptionPlan::firstOrCreate(['code' => 'YEARLY'], [
            'name' => 'Yearly Plan',
            'duration_months' => 12,
            'price' => 14999.00,
            'included_doctor_seats' => 1,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 2,
        ]);

        // 3. Clinic 1 (NX0001)
        $this->clinic1 = Clinic::where('clinic_code', 'NX0001')->first()
            ?? Clinic::create([
                'clinic_code' => 'NX0001',
                'name' => 'Sharma Care Clinic',
                'phone' => '9876543210',
                'email' => 'sharma@example.com',
                'address' => 'Varanasi Cantt',
                'city' => 'Varanasi',
                'activation_status' => 'ACTIVATED',
                'onboarding_status' => 'COMPLETED',
                'is_onboarded' => true,
            ]);

        // Clear pre-seeded licenses for clean test state
        License::where('clinic_id', $this->clinic1->id)->delete();
        PlatformSalesInvoice::where('clinic_id', $this->clinic1->id)->delete();

        $this->tenantAdmin1 = User::firstOrCreate(['clinic_id' => $this->clinic1->id, 'login_id' => 'ADM0001'], [
            'name' => 'Admin Sharma',
            'email' => 'admin@sharma.local',
            'role' => 'ADMIN',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);

        // 4. Clinic 2 (NX0002)
        $this->clinic2 = Clinic::where('clinic_code', 'NX0002')->first()
            ?? Clinic::create([
                'clinic_code' => 'NX0002',
                'name' => 'Arogya Clinic',
                'phone' => '9876543211',
                'email' => 'arogya@example.com',
                'address' => 'Sigra',
                'city' => 'Varanasi',
                'activation_status' => 'ACTIVATED',
                'onboarding_status' => 'COMPLETED',
                'is_onboarded' => true,
            ]);

        License::where('clinic_id', $this->clinic2->id)->delete();
        PlatformSalesInvoice::where('clinic_id', $this->clinic2->id)->delete();

        $this->tenantAdmin2 = User::firstOrCreate(['clinic_id' => $this->clinic2->id, 'login_id' => 'ADM0002'], [
            'name' => 'Admin Arogya',
            'email' => 'admin@arogya.local',
            'role' => 'ADMIN',
            'password' => Hash::make('Password123!'),
            'status' => 'ACTIVE',
        ]);

        // 5. Demo Clinic (democlinic)
        $this->demoClinic = Clinic::where('clinic_code', 'democlinic')->first()
            ?? Clinic::create([
                'clinic_code' => 'democlinic',
                'name' => 'Demo Clinic',
                'phone' => '9876543210',
                'email' => 'demo@dxclinic.local',
                'address' => 'Demo Complex',
                'city' => 'Varanasi',
                'is_demo' => true,
                'demo_status' => 'ACTIVE',
                'activation_status' => 'ACTIVATED',
                'onboarding_status' => 'COMPLETED',
                'is_onboarded' => true,
            ]);

        License::where('clinic_id', $this->demoClinic->id)->delete();
        PlatformSalesInvoice::where('clinic_id', $this->demoClinic->id)->delete();
    }

    /**
     * TEST 1: Initial License fulfillment generates authoritative invoice with line items
     */
    public function test_01_initial_license_generates_commercial_invoice_with_correct_items(): void
    {
        $rawKey = 'NXCL-TEST-1111-2222-3333';
        $license = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $rawKey),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'license_key_prefix' => 'NXCL',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'discount_amount' => 1000.00,
            'net_amount' => 13999.00,
            'payment_method' => 'UPI',
            'payment_reference' => 'UPI-TXN-987654',
            'payment_date' => Carbon::today()->toDateString(),
            'included_doctor_seats' => 1,
            'additional_doctor_seats' => 2,
            'additional_doctor_fee_per_year' => 2999.00,
            'additional_doctor_amount' => 5998.00,
            'status' => 'ACTIVE',
            'issued_by' => $this->superadmin->id,
            'issued_at' => now(),
        ]);

        $invoice = PlatformInvoiceService::createForInitialLicense($license, $this->superadmin->id);

        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('INV-' . date('Y') . '-', $invoice->invoice_number);
        $this->assertEquals($this->clinic1->id, $invoice->clinic_id);
        $this->assertEquals($license->id, $invoice->license_id);
        $this->assertEquals('INITIAL_LICENSE', $invoice->sale_type);
        $this->assertEquals('INITIAL_LICENSE', $invoice->source_type);
        $this->assertEquals($license->id, $invoice->source_id);
        $this->assertEquals(20997.00, (float) $invoice->subtotal); // 14999 + 5998
        $this->assertEquals(1000.00, (float) $invoice->discount);
        $this->assertEquals(19997.00, (float) $invoice->net_amount);
        $this->assertEquals('PAID', $invoice->status);
        $this->assertEquals('UPI', $invoice->payment_method);
        $this->assertEquals('UPI-TXN-987654', $invoice->payment_reference);

        // Check line items
        $items = $invoice->items;
        $this->assertCount(2, $items);
        $this->assertEquals('BASE_PLAN', $items[0]->item_type);
        $this->assertEquals(14999.00, (float) $items[0]->amount);
        $this->assertEquals('ADDITIONAL_DOCTOR_SEATS', $items[1]->item_type);
        $this->assertEquals(2, $items[1]->quantity);
        $this->assertEquals(5998.00, (float) $items[1]->amount);
    }

    /**
     * TEST 2: License Renewal generates authoritative invoice linked to license_renewals.id
     */
    public function test_02_license_renewal_generates_renewal_invoice(): void
    {
        $rawKey = 'NXCL-RENEW-1111-2222-3333';
        $license = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $rawKey),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'start_date' => Carbon::today()->subYear()->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'status' => 'ACTIVE',
        ]);

        $renewal = LicenseRenewal::create([
            'clinic_id' => $this->clinic1->id,
            'license_id' => $license->id,
            'renewal_number' => 1,
            'plan_id' => $this->yearlyPlan->id,
            'new_start_date' => Carbon::today()->addDay()->toDateString(),
            'new_end_date' => Carbon::today()->addYear()->toDateString(),
            'duration_months' => 12,
            'base_amount' => 14999.00,
            'renew_existing_doctor_seats' => true,
            'existing_doctor_seats_renewed' => 1,
            'existing_doctor_seat_amount' => 2999.00,
            'new_doctor_seats_purchased' => 1,
            'new_doctor_seat_amount' => 2999.00,
            'total_doctor_seat_amount' => 5998.00,
            'discount_amount' => 500.00,
            'net_amount' => 20497.00,
            'payment_received' => true,
            'payment_method' => 'BANK_TRANSFER',
            'payment_reference' => 'NEFT-883719',
            'payment_date' => Carbon::today()->toDateString(),
            'renewed_by' => $this->superadmin->id,
        ]);

        $invoice = PlatformInvoiceService::createForRenewal($renewal, $this->superadmin->id);

        $this->assertNotNull($invoice);
        $this->assertEquals('LICENSE_RENEWAL', $invoice->sale_type);
        $this->assertEquals('LICENSE_RENEWAL', $invoice->source_type);
        $this->assertEquals($renewal->id, $invoice->source_id);
        $this->assertEquals(20997.00, (float) $invoice->subtotal);
        $this->assertEquals(500.00, (float) $invoice->discount);
        $this->assertEquals(20497.00, (float) $invoice->net_amount);
        $this->assertEquals('PAID', $invoice->status);
        $this->assertEquals('BANK_TRANSFER', $invoice->payment_method);
        $this->assertEquals('NEFT-883719', $invoice->payment_reference);

        // Check line items: Renewal Plan, Existing Seats, New Seats
        $items = $invoice->items;
        $this->assertCount(3, $items);
        $this->assertEquals('RENEWAL_PLAN', $items[0]->item_type);
        $this->assertEquals('EXISTING_DOCTOR_SEATS', $items[1]->item_type);
        $this->assertEquals('NEW_DOCTOR_SEATS', $items[2]->item_type);
    }

    /**
     * TEST 3: Mid-Term Doctor Add-on creates add-on invoice without renewing clinic or changing key
     */
    public function test_03_mid_term_doctor_addon_generates_addon_invoice(): void
    {
        $rawKey = 'NXCL-ADDON-1111-2222-3333';
        $license = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $rawKey),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'start_date' => Carbon::today()->subMonths(3)->toDateString(),
            'end_date' => Carbon::today()->addMonths(9)->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'status' => 'ACTIVE',
        ]);

        $addon = ClinicDoctorSeatAddon::create([
            'clinic_id' => $this->clinic1->id,
            'license_id' => $license->id,
            'seats_count' => 1,
            'purchase_date' => Carbon::today()->toDateString(),
            'valid_from' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addYear()->subDay()->toDateString(),
            'price' => 2999.00,
            'currency' => 'INR',
            'payment_method' => 'CASH',
            'payment_date' => Carbon::today()->toDateString(),
            'status' => 'ACTIVE',
            'created_by_developer_user_id' => $this->superadmin->id,
        ]);

        $invoice = PlatformInvoiceService::createForSeatAddon($addon, $this->superadmin->id);

        $this->assertNotNull($invoice);
        $this->assertEquals('DOCTOR_ADDON', $invoice->sale_type);
        $this->assertEquals('DOCTOR_ADDON', $invoice->source_type);
        $this->assertEquals($addon->id, $invoice->source_id);
        $this->assertEquals(2999.00, (float) $invoice->subtotal);
        $this->assertEquals(0.00, (float) $invoice->discount);
        $this->assertEquals(2999.00, (float) $invoice->net_amount);
        $this->assertEquals('PAID', $invoice->status);

        $item = $invoice->items->first();
        $this->assertEquals('DOCTOR_ADDON', $item->item_type);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(2999.00, (float) $item->amount);
    }

    /**
     * TEST 4: Duplicate Invoice creation is blocked at service and database level
     */
    public function test_04_duplicate_invoice_protection_and_database_uniqueness(): void
    {
        $rawKey = 'NXCL-DUP-1111-2222-3333';
        $license = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $rawKey),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'status' => 'ACTIVE',
        ]);

        // First creation
        $invoice1 = PlatformInvoiceService::createForInitialLicense($license, $this->superadmin->id);
        $this->assertNotNull($invoice1);

        // Repeated creation attempt
        $invoice2 = PlatformInvoiceService::createForInitialLicense($license, $this->superadmin->id);
        $this->assertEquals($invoice1->id, $invoice2->id);
        $this->assertEquals(1, PlatformSalesInvoice::where('source_type', 'INITIAL_LICENSE')->where('source_id', $license->id)->count());

        // Database composite unique constraint violation check
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('platform_sales_invoices')->insert([
            'invoice_number' => 'INV-2026-99999',
            'invoice_date' => Carbon::today()->toDateString(),
            'clinic_id' => $this->clinic1->id,
            'license_id' => $license->id,
            'sale_type' => 'INITIAL_LICENSE',
            'source_type' => 'INITIAL_LICENSE',
            'source_id' => $license->id, // DUPLICATE SOURCE TRANSACTION
            'subtotal' => 14999.00,
            'discount' => 0.00,
            'net_amount' => 14999.00,
            'status' => 'PAID',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * TEST 5: Invoice immutability is strictly enforced
     */
    public function test_05_invoice_immutability_enforced(): void
    {
        $rawKey = 'NXCL-IMMUT-1111-2222-3333';
        $license = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $rawKey),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'status' => 'ACTIVE',
        ]);

        $invoice = PlatformInvoiceService::createForInitialLicense($license, $this->superadmin->id);

        // Attempting to modify net_amount directly throws DomainException
        try {
            $invoice->net_amount = 5000.00;
            $invoice->save();
            $this->fail('Expected DomainException was not thrown on modifying immutable invoice net_amount');
        } catch (DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Controlled cancellation/credit-note mechanism works
        $cancelled = PlatformInvoiceService::cancelInvoice($invoice->fresh(), 'Customer cancelled order prior to activation', $this->superadmin->id);
        $this->assertEquals('CANCELLED', $cancelled->status);
        $this->assertEquals('Customer cancelled order prior to activation', $cancelled->cancellation_reason);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertEquals($this->superadmin->id, $cancelled->cancelled_by);

        // Once cancelled, cannot be reactivated
        try {
            $cancelled->status = 'PAID';
            $cancelled->save();
            $this->fail('Expected DomainException was not thrown when reactivating cancelled invoice');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cannot be reactivated', $e->getMessage());
        }
    }

    /**
     * TEST 6: Superadmin Sales Dashboard shows correct accounting KPIs and formulas
     */
    public function test_06_superadmin_sales_dashboard_metrics_and_formulas(): void
    {
        // 1. Sale for Clinic 1: ₹14,999 gross, ₹1,000 discount => ₹13,999 net, PAID
        $license1 = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', 'NXCL-K1'),
            'license_key_encrypted' => Crypt::encryptString('NXCL-K1'),
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'discount_amount' => 1000.00,
            'net_amount' => 13999.00,
            'status' => 'ACTIVE',
        ]);
        $inv1 = PlatformInvoiceService::createForInitialLicense($license1, $this->superadmin->id);

        // 2. Add-on for Clinic 1: ₹2,999, PAID via CASH
        $addon1 = ClinicDoctorSeatAddon::create([
            'clinic_id' => $this->clinic1->id,
            'license_id' => $license1->id,
            'seats_count' => 1,
            'purchase_date' => Carbon::today()->toDateString(),
            'valid_from' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addYear()->toDateString(),
            'price' => 2999.00,
            'currency' => 'INR',
            'payment_method' => 'CASH',
            'status' => 'ACTIVE',
        ]);
        $inv2 = PlatformInvoiceService::createForSeatAddon($addon1, $this->superadmin->id);

        // 3. Platform Expense: ₹4,000 AWS hosting
        PlatformExpense::create([
            'category' => 'SERVER_HOSTING',
            'description' => 'AWS Production Infrastructure',
            'amount' => 4000.00,
            'expense_date' => Carbon::today()->toDateString(),
            'payment_method' => 'CREDIT_CARD',
            'created_by' => $this->superadmin->id,
        ]);

        $this->actingAs($this->superadmin, 'developer');

        $response = $this->get(route('superadmin.sales'));
        $response->assertStatus(200);

        // Gross: 14999 + 2999 = 17998.00
        // Discounts: 1000.00
        // Net Sales: 16998.00
        // Collected: 16998.00
        // Expenses: 4000.00
        // Operating Profit: 16998.00 - 4000.00 = 12998.00
        $response->assertViewHas('grossSales', 17998.00);
        $response->assertViewHas('discounts', 1000.00);
        $response->assertViewHas('netSales', 16998.00);
        $response->assertViewHas('collected', 16998.00);
        $response->assertViewHas('platformExpenses', 4000.00);
        $response->assertViewHas('operatingProfit', 12998.00);
    }

    /**
     * TEST 7: Filtering dashboard by clinic and sale type
     */
    public function test_07_dashboard_filtering_by_clinic_and_sale_type(): void
    {
        $lic1 = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', 'NXCL-F1'),
            'license_key_encrypted' => Crypt::encryptString('NXCL-F1'),
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'status' => 'ACTIVE',
        ]);
        PlatformInvoiceService::createForInitialLicense($lic1, $this->superadmin->id);

        $lic2 = License::create([
            'clinic_id' => $this->clinic2->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', 'NXCL-F2'),
            'license_key_encrypted' => Crypt::encryptString('NXCL-F2'),
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'status' => 'ACTIVE',
        ]);
        PlatformInvoiceService::createForInitialLicense($lic2, $this->superadmin->id);

        $this->actingAs($this->superadmin, 'developer');

        // Filter by Clinic 1
        $response = $this->get(route('superadmin.sales', ['clinic_id' => $this->clinic1->id]));
        $response->assertStatus(200);
        $response->assertViewHas('netSales', 14999.00);

        // Filter by Sale Type
        $response2 = $this->get(route('superadmin.sales', ['sale_type' => 'DOCTOR_ADDON']));
        $response2->assertStatus(200);
        $response2->assertViewHas('netSales', 0.00);
    }

    /**
     * TEST 8: Invoice list and detail display masked license key
     */
    public function test_08_invoice_list_and_detail_with_masked_license_key(): void
    {
        $rawKey = 'NXCL-SECRET-9999-8888-7777';
        $license = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $rawKey),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'license_key_prefix' => 'NXCL',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'status' => 'ACTIVE',
        ]);
        $invoice = PlatformInvoiceService::createForInitialLicense($license, $this->superadmin->id);

        $this->actingAs($this->superadmin, 'developer');

        // Invoice List
        $resList = $this->get(route('superadmin.invoices'));
        $resList->assertStatus(200);
        $resList->assertSee($invoice->invoice_number);
        $resList->assertSee('NXCL-••••-••••-••••-••••');
        $resList->assertDontSee($rawKey); // NEVER expose full key

        // Invoice Detail
        $resDetail = $this->get(route('superadmin.invoices.show', $invoice->id));
        $resDetail->assertStatus(200);
        $resDetail->assertSee($invoice->invoice_number);
        $resDetail->assertSee('NXCL-••••-••••-••••-••••');
        $resDetail->assertDontSee($rawKey);
    }

    /**
     * TEST 9: Invoice print view and PDF generation
     */
    public function test_09_invoice_print_and_pdf_generation(): void
    {
        $rawKey = 'NXCL-PDF-1111-2222-3333';
        $license = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $rawKey),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'status' => 'ACTIVE',
        ]);
        $invoice = PlatformInvoiceService::createForInitialLicense($license, $this->superadmin->id);

        $this->actingAs($this->superadmin, 'developer');

        // Print view
        $resPrint = $this->get(route('superadmin.invoices.print', $invoice->id));
        $resPrint->assertStatus(200);
        $resPrint->assertSee('COMMERCIAL INVOICE');
        $resPrint->assertSee($invoice->invoice_number);

        // PDF view (stream/download)
        $resPdf = $this->get(route('superadmin.invoices.pdf', $invoice->id));
        $resPdf->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resPdf->headers->get('Content-Type'));
    }

    /**
     * TEST 10: Collection reporting with individual transactions and aggregation modes
     */
    public function test_10_collections_report_individual_and_aggregations(): void
    {
        $rawKey = 'NXCL-COLL-1111-2222-3333';
        $license = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $rawKey),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'payment_method' => 'UPI',
            'payment_date' => Carbon::today()->toDateString(),
            'status' => 'ACTIVE',
        ]);
        $invoice = PlatformInvoiceService::createForInitialLicense($license, $this->superadmin->id);

        $this->actingAs($this->superadmin, 'developer');

        // Transactions View
        $res = $this->get(route('superadmin.collections', ['view_type' => 'transactions']));
        $res->assertStatus(200);
        $res->assertViewHas('totalCollection', 14999.00);
        $res->assertViewHas('upiTotal', 14999.00);
        $res->assertViewHas('cashTotal', 0.00);

        // Daily Aggregation View
        $resDaily = $this->get(route('superadmin.collections', ['view_type' => 'daily']));
        $resDaily->assertStatus(200);
        $this->assertNotEmpty($resDaily->viewData('groupedData'));

        // Monthly Aggregation View
        $resMonthly = $this->get(route('superadmin.collections', ['view_type' => 'monthly']));
        $resMonthly->assertStatus(200);
        $this->assertNotEmpty($resMonthly->viewData('groupedData'));

        // FY Aggregation View
        $resFy = $this->get(route('superadmin.collections', ['view_type' => 'fy']));
        $resFy->assertStatus(200);
        $this->assertNotEmpty($resFy->viewData('groupedData'));
    }

    /**
     * TEST 11: Per-Clinic commercial history lists chronological events
     */
    public function test_11_per_clinic_commercial_history(): void
    {
        $rawKey = 'NXCL-CHIST-1111-2222-3333';
        $license = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $rawKey),
            'license_key_encrypted' => Crypt::encryptString($rawKey),
            'license_key_prefix' => 'NXCL',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'status' => 'ACTIVE',
        ]);
        $invoice = PlatformInvoiceService::createForInitialLicense($license, $this->superadmin->id);

        $this->actingAs($this->superadmin, 'developer');

        $res = $this->get(route('superadmin.clinics.sales', $this->clinic1));
        $res->assertStatus(200);
        $res->assertSee($this->clinic1->name);
        $res->assertSee($invoice->invoice_number);
        $res->assertSee($license->masked_key);
        $this->assertNotEmpty($res->viewData('sortedTimeline'));
    }

    /**
     * TEST 12: Platform Expenses have no clinic_id and do not mix with clinic expenses
     */
    public function test_12_platform_expenses_isolated_from_clinic_accounting(): void
    {
        $this->actingAs($this->superadmin, 'developer');

        $res = $this->post(route('superadmin.expenses.store'), [
            'category' => 'SERVER_HOSTING',
            'description' => 'Dedicated Database Hosting Cluster',
            'amount' => 7500.00,
            'expense_date' => Carbon::today()->toDateString(),
            'payment_method' => 'BANK_TRANSFER',
            'reference' => 'TXN-HOSTING-001',
            'notes' => 'Monthly DB cluster billing',
        ]);

        $res->assertRedirect(route('superadmin.expenses'));

        $expense = PlatformExpense::where('reference', 'TXN-HOSTING-001')->first();
        $this->assertNotNull($expense);
        $this->assertEquals(7500.00, (float) $expense->amount);
        $this->assertEquals('SERVER_HOSTING', $expense->category);

        // Verify database schema has NO clinic_id column
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('platform_expenses', 'clinic_id'));
    }

    /**
     * TEST 13: Tenant isolation — Tenant users (NX0001, NX0002) are strictly denied from Superadmin commercial routes
     */
    public function test_13_tenant_isolation_and_authorization_denial(): void
    {
        // Tenant Admin 1 tries to access Superadmin Sales Dashboard
        $this->actingAs($this->tenantAdmin1);

        $res1 = $this->get(route('superadmin.sales'));
        $res1->assertRedirect(route('superadmin.login'));

        $res2 = $this->get(route('superadmin.invoices'));
        $res2->assertRedirect(route('superadmin.login'));

        $res3 = $this->get(route('superadmin.collections'));
        $res3->assertRedirect(route('superadmin.login'));

        $res4 = $this->get(route('superadmin.clinics.sales', $this->clinic1));
        $res4->assertRedirect(route('superadmin.login'));

        $res5 = $this->get(route('superadmin.expenses'));
        $res5->assertRedirect(route('superadmin.login'));

        // JSON requests receive 403 Forbidden
        $resJson = $this->getJson(route('superadmin.sales'));
        $resJson->assertStatus(403);
    }
}
