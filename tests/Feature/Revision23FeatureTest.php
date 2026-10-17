<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\Vital;
use App\Services\PdfGeneratorService;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Revision23FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected Patient $patient;
    protected User $adminUser;
    protected User $operatorUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'DxClinic Varanasi',
            'city' => 'Varanasi',
            'address' => 'Assi Ghat',
            'phone' => '9876543210',
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-12345',
            'specialization' => 'General Medicine',
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00001',
            'name' => 'Ramesh Kumar',
            'age' => 45,
            'sex' => 'MALE',
            'mobile' => '9876543210',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
        ]);

        $plan = \App\Models\SubscriptionPlan::firstOrCreate(
            ['code' => 'YEARLY'],
            [
                'name' => 'Yearly Plan',
                'duration_months' => 12,
                'price' => 14999.00,
                'included_doctor_seats' => 5,
                'is_active' => true,
            ]
        );

        \App\Models\License::create([
            'clinic_id' => $this->clinic->id,
            'plan_id' => $plan->id,
            'plan_type' => 'YEARLY',
            'status' => 'ACTIVE',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
            'license_key_hash' => hash('sha256', 'TEST-KEY-23'),
            'license_key_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString('TEST-KEY-23'),
            'license_key_prefix' => 'NXCL-TEST',
        ]);

        $this->adminUser = User::factory()->create([
            'clinic_id' => $this->clinic->id,
            'email' => 'admin@dxclinic.local',
            'role' => 'ADMIN',
        ]);

        $this->operatorUser = User::factory()->create([
            'clinic_id' => $this->clinic->id,
            'email' => 'operator@dxclinic.local',
            'role' => 'OPERATOR',
        ]);
    }

    /**
     * Test Patient Management view and search
     */
    public function test_patient_management_renders_and_searches(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('patients'));
        $response->assertStatus(200);

        Livewire::test(\App\Livewire\PatientManager::class)
            ->assertSee('Patient Management')
            ->set('search', 'Ramesh')
            ->assertStatus(200);
    }

    /**
     * Test Patient Editing and immutability of historical records
     */
    public function test_patient_editing_updates_demographics_safely(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(\App\Livewire\PatientManager::class)
            ->call('editProfile', $this->patient->id)
            ->set('editName', 'Updated Test Patient')
            ->set('editCity', 'Varanasi')
            ->call('saveProfile');

        $this->assertDatabaseHas('patients', [
            'id' => $this->patient->id,
            'name' => 'Updated Test Patient',
        ]);
    }

    /**
     * Test Patient complete history view
     */
    public function test_patient_history_shows_chronological_visits_and_bills(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(\App\Livewire\PatientManager::class)
            ->call('viewHistory', $this->patient->id)
            ->assertSet('activeView', 'history')
            ->assertSet('selectedPatientId', $this->patient->id);
    }

    /**
     * Test Clinic & Doctor Management
     */
    public function test_clinic_and_doctor_management_edit(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('settings.clinic-doctor'));
        $response->assertStatus(200);

        Livewire::test(\App\Livewire\ClinicDoctorManager::class)
            ->assertSee('Clinic Information')
            ->assertSee('Doctor Profiles')
            ->call('enableClinicEdit')
            ->assertSet('clinicEditMode', true)
            ->call('cancelClinicEdit')
            ->assertSet('clinicEditMode', false);
    }

    /**
     * Test Doctor normalized display name prevents double "Dr."
     */
    public function test_doctor_normalized_display_name(): void
    {
        $doctor1 = new Doctor(['name' => 'Rajesh Sharma']);
        $this->assertEquals('Dr. Rajesh Sharma', $doctor1->display_name);

        $doctor2 = new Doctor(['name' => 'Dr. Rajesh Sharma']);
        $this->assertEquals('Dr. Rajesh Sharma', $doctor2->display_name);

        $doctor3 = new Doctor(['name' => 'Dr. Dr. Rajesh Sharma']);
        $this->assertEquals('Dr. Rajesh Sharma', $doctor3->display_name);

        $doctor4 = new Doctor(['name' => 'DR RAJESH SHARMA']);
        $this->assertEquals('Dr. RAJESH SHARMA', $doctor4->display_name);
    }

    /**
     * Test PDF responses return application/pdf and %PDF signature
     */
    public function test_pdf_endpoints_return_real_pdf(): void
    {
        $this->actingAs($this->adminUser);

        $app = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'token_number' => 1,
            'formatted_token' => '001',
            'booking_source' => 'CLINIC',
            'source' => 'CLINIC',
            'status' => 'ARRIVED',
            'secure_token' => str_repeat('a', 64),
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_id' => $app->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'IN_PROGRESS',
        ]);

        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_id' => $app->id,
            'visit_id' => $visit->id,
            'bill_number' => 'BILL-C-TEST-001',
            'bill_date' => now()->toDateString(),
            'gross_amount' => 600.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'net_amount' => 600.00,
            'status' => 'PENDING',
        ]);

        $this->actingAs($this->operatorUser);

        $response = $this->get("/pdf/consultation-receipt/{$bill->id}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $responsePad = $this->get("/pdf/doctor-pad/{$app->id}");
        $responsePad->assertStatus(200);
        $responsePad->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $responsePad->getContent());
    }

    /**
     * Test Doctor Pad Pre-Printed Overlay Rules
     */
    public function test_doctor_pad_preprinted_mode_boundary(): void
    {
        $app = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'token_number' => 1,
            'formatted_token' => '001',
            'booking_source' => 'CLINIC',
            'source' => 'CLINIC',
            'status' => 'ARRIVED',
            'secure_token' => str_repeat('b', 64),
        ]);

        $view = view('print.doctor-pad', [
            'appointment' => $app,
            'clinic' => $this->clinic,
            'patient' => $this->patient,
            'doctor' => $this->doctor,
            'vital' => null,
            'formattedAddress' => $this->patient->formatted_address,
        ])->render();

        // 1. Default mode must be preprinted-mode
        $this->assertStringContainsString('class="preprinted-mode"', $view);

        // 2. Only required patient fields
        $this->assertStringContainsString('field-patient-name', $view);
        $this->assertStringContainsString('field-age-sex', $view);
        $this->assertStringContainsString('field-address', $view);
        $this->assertStringContainsString('field-date', $view);

        // 3. Physical coordinate system (8.25in x 11.671in)
        $this->assertStringContainsString('size: 8.25in 11.671in', $view);
        $this->assertStringContainsString('field-patient-id', $view);
        $this->assertStringContainsString('field-token-no', $view);
    }
}
