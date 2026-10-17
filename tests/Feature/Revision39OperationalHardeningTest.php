<?php

namespace Tests\Feature;

use App\Livewire\Pwa\ChatbotWidget;
use App\Livewire\Pwa\PatientBooking;
use App\Livewire\Settings\BackupManager;
use App\Models\Clinic;
use App\Models\DeveloperUser;
use App\Models\Doctor;
use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BackupService;
use App\Services\ClinicClock;
use App\Services\DemoService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Revision39OperationalHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic1;
    protected Clinic $demoClinic;
    protected DeveloperUser $superadmin;
    protected User $adminUser;
    protected User $operatorUser;
    protected SubscriptionPlan $yearlyPlan;
    protected License $license1;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Subscription Plans
        $this->yearlyPlan = SubscriptionPlan::firstOrCreate(['code' => 'YEARLY'], [
            'name' => 'Yearly Plan (12 mo)',
            'duration_months' => 12,
            'price' => 14999.00,
            'included_doctor_seats' => 5,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 1,
        ]);

        SubscriptionPlan::firstOrCreate(['code' => 'FIVE_YEAR'], [
            'name' => '5-Year Plan',
            'duration_months' => 60,
            'price' => 49999.00,
            'included_doctor_seats' => 2,
            'additional_doctor_seat_price' => 2999.00,
            'is_active' => true,
            'display_order' => 6,
        ]);

        // 2. Superadmin Developer User
        $this->superadmin = DeveloperUser::create([
            'name' => 'Superadmin Fleet Manager',
            'email' => 'superadmin@dxclinic.com',
            'password' => Hash::make('SuperSecret123!'),
            'role' => 'DEVELOPER',
            'is_active' => true,
        ]);

        // 3. Seed Demo Clinic (with 2 doctors)
        $this->demoClinic = DemoService::seedDemoClinic();

        // 4. Seed Clinic NX0001 (Sharma Care Clinic)
        $this->clinic1 = Clinic::create([
            'clinic_code' => 'NX0001',
            'name' => 'Sharma Care Clinic',
            'is_demo' => false,
            'address' => '12, Medical Enclave, Lanka',
            'pincode' => '221005',
            'city' => 'Varanasi',
            'phone' => '9876543210',
            'email' => 'info@sharmaclinic.com',
            'activation_status' => 'ACTIVATED',
            'onboarding_status' => 'COMPLETED',
            'is_onboarded' => true,
            'max_advance_booking_days' => 7,
        ]);

        // Authoritative 1-year License for NX0001
        $key1 = 'NX0001-TEST-KEY-2026';
        $this->license1 = License::create([
            'clinic_id' => $this->clinic1->id,
            'plan_id' => $this->yearlyPlan->id,
            'license_key_hash' => hash('sha256', $key1),
            'license_key_encrypted' => Crypt::encryptString($key1),
            'license_key_prefix' => 'NX0001',
            'start_date' => Carbon::parse('2026-08-29'),
            'end_date' => Carbon::parse('2027-08-29'),
            'amount' => 14999.00,
            'net_amount' => 14999.00,
            'included_doctor_seats' => 5,
            'additional_doctor_seats' => 0,
            'status' => 'ACTIVE',
            'issued_by' => $this->superadmin->id,
        ]);

        // Users for NX0001
        $this->adminUser = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Dr. Rajesh Sharma Admin',
            'email' => 'admin@sharmaclinic.com',
            'mobile' => '9876543210',
            'password' => Hash::make('Admin123!'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->operatorUser = User::create([
            'clinic_id' => $this->clinic1->id,
            'name' => 'Sunil Verma Operator',
            'email' => 'operator@sharmaclinic.com',
            'mobile' => '9876543211',
            'password' => Hash::make('Operator123!'),
            'role' => 'OPERATOR',
            'is_active' => true,
        ]);

        // 4 Doctors for NX0001 (under 5 seat capacity)
        for ($i = 1; $i <= 4; $i++) {
            Doctor::create([
                'clinic_id' => $this->clinic1->id,
                'name' => "Doctor Number {$i}",
                'qualification' => 'MBBS, MD',
                'registration_number' => "REG-NX01-0{$i}",
                'specialization' => 'General Medicine',
                'is_active' => true,
            ]);
        }
    }

    /**
     * Test 1: Demo PWA Auto-Advances Date when Today's Sessions Expire and Options are Not Empty
     */
    public function test_demo_pwa_auto_advances_date_and_populates_sessions(): void
    {
        TenantContext::setClinic($this->demoClinic);

        // Test Livewire component mount
        $component = Livewire::test(PatientBooking::class, ['clinic_code' => 'democlinic']);

        $availableSessions = $component->get('available_sessions');
        $selectedDoctorId = $component->get('selected_doctor_id');

        $this->assertNotEmpty($availableSessions, 'Available sessions must not be empty on mount');
        $this->assertArrayHasKey('MORNING', $availableSessions);
        $this->assertArrayHasKey('EVENING', $availableSessions);
        $this->assertNotNull($selectedDoctorId, 'A default active doctor must be preselected');

        // Test HTTP response
        $response = $this->get('/book/democlinic');
        $response->assertStatus(200);
        $response->assertSee('Morning Session');
        $response->assertSee('Evening Session');
        $response->assertDontSee('-- No sessions available --');
    }

    /**
     * Test 2: Superadmin Demo Card Displays 2 Doctor Seats and Real Doctor Usernames
     */
    public function test_superadmin_demo_card_displays_two_doctor_capacity(): void
    {
        $response = $this->actingAs($this->superadmin, 'developer')->get('/superadmin/dashboard');
        $response->assertStatus(200);

        // Must display hard limit 2 doctor seats
        $response->assertSee('Hard limit: 2 Doctor seats • Doctors: 2 / 2 (demodoctor01, demodoctor02)', false);
        $response->assertDontSee('Hard limit: 1 Doctor seat');
    }

    /**
     * Test 3: NX0001 Authoritative License Dates and True Mathematical Remaining Days
     */
    /**
     * Test 3: NX0001 Authoritative License Dates and True Mathematical Remaining Days
     */
    public function test_nx0001_authoritative_license_and_mathematical_remaining_days(): void
    {
        $license = $this->clinic1->activeLicense;

        $this->assertNotNull($license, 'NX0001 must have an active license');
        $this->assertStringContainsString('Yearly', $license->plan->name);
        $this->assertEquals(5, $license->totalDoctorCapacity());

        // Mathematical difference without faking
        $today = Carbon::today('Asia/Kolkata');
        $expectedDays = (int) $today->diffInDays($license->end_date->copy()->startOfDay(), false);
        $this->assertEquals($expectedDays, $license->remainingDays());

        // Verify remaining days is around 335 days (not 1796 or 2031)
        $this->assertGreaterThan(300, $license->remainingDays());
        $this->assertLessThan(370, $license->remainingDays());
        $this->assertEquals('2027-08-29', $license->end_date->format('Y-m-d'));
    }

    /**
     * Test 4a: Local Hard-Drive Backup Facility strictly blocks non-admin operators with 403
     */
    public function test_backup_facility_role_authorization_blocks_operators(): void
    {
        TenantContext::setClinic($this->clinic1);

        $this->actingAs($this->operatorUser);
        Livewire::test(BackupManager::class)
            ->assertForbidden();
    }

    /**
     * Test 4b: Admin creates backup with companion manifest and custom folder
     */
    public function test_backup_facility_admin_creation_and_companion_manifest(): void
    {
        TenantContext::setClinic($this->clinic1);
        $this->actingAs($this->adminUser);

        $customFolder = base_path('storage/app/test_backups');
        File::ensureDirectoryExists($customFolder);

        $backupService = app(BackupService::class);
        $backup = $backupService->createBackup('AdminPass123!', 'MANUAL', $customFolder);

        $this->assertEquals('SUCCESS', $backup->status);
        $this->assertFileExists($backup->file_path);

        $metaPath = "{$backup->file_path}.meta.json";
        $this->assertFileExists($metaPath);

        // Verify companion manifest contents
        $manifestContent = json_decode(File::get($metaPath), true);
        $this->assertIsArray($manifestContent);
        $this->assertArrayHasKey('sha256_checksum', $manifestContent);
        $this->assertArrayHasKey('size_bytes', $manifestContent);
        $this->assertArrayHasKey('schema_version', $manifestContent);
        $this->assertEquals($backup->checksum, $manifestContent['sha256_checksum']);

        // Clean up test files
        File::delete($backup->file_path);
        File::delete($metaPath);
        if (File::isDirectory($customFolder) && count(File::files($customFolder)) === 0) {
            File::deleteDirectory($customFolder);
        }
    }

    /**
     * Test 5: Professional Manuals and Documentation Hub Routes
     */
    public function test_professional_manuals_and_documentation_hub_routes(): void
    {
        // Hub
        $response = $this->get('/manuals');
        $response->assertStatus(200);
        $response->assertSee('DxClinic Documentation & Manuals Hub', false);

        // Admin manual
        $adminResp = $this->get('/manuals/admin_manual');
        $adminResp->assertStatus(200);
        $adminResp->assertSee('@media print');
        $adminResp->assertSee('DxClinic Administrator');
        $adminResp->assertSee('Clinical Practice Manual');

        // FAQ manual
        $faqResp = $this->get('/manuals/faq');
        $faqResp->assertStatus(200);
        $faqResp->assertSee('Frequently Asked Questions');
        $faqResp->assertSee('Q137:');
        $faqResp->assertSee('Q140:');
    }

    /**
     * Test 6: Chatbot Widget Knowledge Retrieval and Attending Doctor Listing
     */
    public function test_chatbot_widget_knowledge_and_doctor_inquiry(): void
    {
        TenantContext::setClinic($this->demoClinic);

        // 1. Doctor inquiry
        $component = Livewire::test(ChatbotWidget::class)
            ->set('userMessage', 'Who are the available doctors?')
            ->call('sendMessage');

        $messages = $component->get('messages');
        $lastMsg = end($messages);
        $this->assertStringContainsString('Dr. Amit Verma', $lastMsg['text']);
        $this->assertStringContainsString('Dr. Neha Kapoor', $lastMsg['text']);

        // 2. Knowledge inquiry
        $component->set('userMessage', 'What keyboard shortcuts are available?')
            ->call('sendMessage');

        $messages = $component->get('messages');
        $lastMsg = end($messages);
        $this->assertStringContainsString('F1', $lastMsg['text']);
        $this->assertStringContainsString('FAQ Knowledge Base', $lastMsg['source']);
    }
}
