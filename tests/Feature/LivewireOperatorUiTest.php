<?php

namespace Tests\Feature;

use App\Livewire\Operator\ArrivalQueue;
use App\Livewire\Operator\ConsultationBilling;
use App\Livewire\Operator\DiagnosticBilling;
use App\Models\Appointment;
use App\Models\BankAccount;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AppointmentService;
use Database\Seeders\DiagnosticMasterSeeder;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireOperatorUiTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Doctor $doctor;
    private BankAccount $bankAccount;
    private \App\Models\User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Services\ClinicClock::setTestNow('2026-09-28 08:30:00');
        $this->seed(SystemLedgerSeeder::class);
        $this->seed(DiagnosticMasterSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'Sharma Clinic',
            'phone' => '9876543210',
            'address' => '123, MG Road',
            'city' => 'Varanasi',
            'is_onboarded' => true,
        ]);

        $this->operator = \App\Models\User::factory()->create([
            'clinic_id' => $this->clinic->id,
            'role' => 'OPERATOR',
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-12345',
            'specialization' => 'General Physician',
        ]);

        $this->bankAccount = BankAccount::create([
            'clinic_id' => $this->clinic->id,
            'bank_name' => 'State Bank of India',
            'account_holder' => 'Sharma Clinic',
            'account_number' => '123456789012',
            'ifsc' => 'SBIN0001234',
            'branch' => 'Main Branch',
            'upi_id' => 'sharma@sbi',
            'is_active' => true,
            'ledger_account_id' => 2, // Bank ledger
        ]);
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_arrival_queue_search_and_workflow_panel(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P000124',
            'name' => 'Ramesh Kumar',
            'mobile' => '9876543210',
            'age' => 45,
            'sex' => 'MALE',
            'address' => 'House 25, Lanka',
            'city' => 'Varanasi',
        ]);

        $appointment = app(AppointmentService::class)->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
        ]);

        Livewire::test(ArrivalQueue::class)
            ->assertSee('Patient Arrival Check-in')
            // Search by token
            ->set('arrivalSearchQuery', $appointment->formatted_token)
            ->call('searchPatientForArrival')
            ->assertSee('Ramesh Kumar')
            ->assertSee('Confirm Arrival')
            // Confirm arrival
            ->call('markArrived', $appointment->id)
            ->assertSee('Active Workflow')
            ->assertSee('Appointment Verified')
            ->assertSee('Visit Created')
            // Open Vitals modal and record vitals
            ->call('openVitalsModal', $appointment->id)
            ->set('bp_systolic', 120)
            ->set('bp_diastolic', 80)
            ->set('pulse', 78)
            ->set('spo2', 98)
            ->set('weight', 68.5)
            ->set('temperature', 98.4)
            ->set('respiratory_rate', 18)
            ->set('rbs', 110)
            ->call('saveVitals')
            ->assertSee('Vitals recorded for Token');

        $this->assertDatabaseHas('vitals', [
            'bp_systolic' => 120,
            'bp_diastolic' => 80,
            'pulse' => 78,
            'spo2' => 98,
        ]);
    }

    public function test_consultation_billing_cash_and_soundbox_upi(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P000125',
            'name' => 'Anita Devi',
            'mobile' => '9876543211',
            'age' => 52,
            'sex' => 'FEMALE',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
        ]);

        $appointment = app(AppointmentService::class)->bookAppointment([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
        ]);

        // Settle via UPI with Soundbox check
        $component = Livewire::test(ConsultationBilling::class, ['appointmentId' => $appointment->id])
            ->assertSee('Consultation Fee')
            ->set('paymentMethod', 'UPI')
            ->set('selectedBankAccountId', $this->bankAccount->id)
            ->set('soundboxConfirmed', false)
            ->call('settlePayment')
            ->assertSee('Physical Soundbox confirmation is required to settle UPI payment');

        $component->set('soundboxConfirmed', true)
            ->call('settlePayment')
            ->assertSee('Payment Received')
            ->assertSee('PAID');

        // Test print routes respond with 200
        $billId = $component->get('bill')->id;
        $this->actingAs($this->operator);
        $this->get(route('print.consultation.receipt', $billId))->assertStatus(200);
        $this->get(route('print.doctor.pad', $appointment->id))->assertStatus(200);
    }

    public function test_diagnostic_billing_workflow_and_lab_slip(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P000126',
            'name' => 'Mohan Lal',
            'mobile' => '9876543212',
            'age' => 38,
            'sex' => 'MALE',
            'address' => 'Godowlia',
            'city' => 'Varanasi',
        ]);

        $cbc = \App\Models\DiagnosticItem::firstOrCreate(
            ['code' => 'CBC'],
            ['name' => 'Complete Blood Count (CBC)', 'type' => 'TEST', 'rate' => 300.00, 'is_active' => true]
        );
        $lft = \App\Models\DiagnosticItem::firstOrCreate(
            ['code' => 'LFT'],
            ['name' => 'Liver Function Test (LFT)', 'type' => 'TEST', 'rate' => 700.00, 'is_active' => true]
        );

        $component = Livewire::test(DiagnosticBilling::class)
            ->set('patientSearchQuery', 'Mohan')
            ->call('searchPatient')
            ->call('selectPatient', $patient->id)
            ->assertSee('Mohan Lal')
            // Add items
            ->call('addItem', $cbc->id)
            ->call('addItem', $lft->id)
            ->assertSee($cbc->name)
            ->assertSee($lft->name)
            // Assert button before bill creation
            ->assertSee('Save Bill & Continue to Payment', false)
            // Create bill
            ->call('createBill')
            ->assertSee('Confirm Diagnostic Payment', false)
            // Settle payment
            ->set('paymentMethod', 'CASH')
            ->call('settlePayment')
            ->assertSee('Diagnostic Payment Received');

        $billId = $component->get('bill')->id;
        $this->actingAs($this->operator);
        $this->get(route('print.diagnostic.receipt', $billId))->assertStatus(200);
        $this->get(route('print.lab.slip', $billId))->assertStatus(200);
    }
}
