<?php

namespace Tests\Feature;

use App\Livewire\Pwa\ChatbotWidget;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChatbotWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'Arogya Clinic',
            'phone' => '9876543210',
            'address' => 'Lanka Road',
            'city' => 'Varanasi',
            'schedule_config' => [
                'monday' => ['closed' => false, 'sessions' => [
                    ['type' => 'MORNING', 'start' => '09:00', 'end' => '13:00', 'max_tokens' => 30],
                ]],
            ],
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-12345',
        ]);
    }

    public function test_chatbot_identifies_existing_patient_and_books_token(): void
    {
        $existingPatient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00001',
            'name' => 'Rahul Sharma',
            'mobile' => '9812345678',
            'age' => 32,
            'sex' => 'MALE',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
        ]);

        Livewire::test(ChatbotWidget::class)
            // 1. User says "Hi"
            ->set('userMessage', 'Hi')
            ->call('sendMessage')
            ->assertSet('botState', 'ASK_BOOKING')

            // 2. Selects "Yes" for appointment booking
            ->call('handleOption', 'yes_booking')
            ->assertSet('botState', 'COLLECT_NAME_MOBILE')

            // 3. Submits Name and Mobile
            ->set('chatName', 'Rahul Sharma')
            ->set('chatMobile', '9812345678')
            ->call('submitNameMobile')
            ->assertSet('botState', 'CONFIRM_EXISTING')
            ->assertSet('matchedPatient.id', $existingPatient->id)

            // 4. Confirms existing patient ID
            ->call('handleOption', 'continue_existing')
            ->assertSet('botState', 'SELECT_SESSION')

            // 5. Selects Date and Books Appointment
            ->set('chatDate', '2026-09-28')
            ->set('chatSession', 'MORNING')
            ->call('submitAppointmentBooking')
            ->assertSet('botState', 'BOOKED');

        // Verify Appointment created in database
        $appointment = Appointment::where('patient_id', $existingPatient->id)->first();
        $this->assertNotNull($appointment);
        $this->assertEquals(1, $appointment->token_number);
        $this->assertEquals('001', $appointment->formatted_token);
        $this->assertEquals('ONLINE', $appointment->booking_source);
    }

    public function test_chatbot_registers_new_patient_and_allocates_token(): void
    {
        Livewire::test(ChatbotWidget::class)
            // 1. Starts booking directly
            ->call('handleOption', 'start_booking')
            ->assertSet('botState', 'COLLECT_NAME_MOBILE')

            // 2. Enters unregistered Name and Mobile
            ->set('chatName', 'Priya Singh')
            ->set('chatMobile', '9899887766')
            ->call('submitNameMobile')
            ->assertSet('botState', 'COLLECT_NEW_PATIENT_DETAILS')

            // 3. Fills in demographic and address details
            ->set('chatAge', '27')
            ->set('chatSex', 'FEMALE')
            ->set('chatAddress', 'Lane 4, Sigra')
            ->set('chatCity', 'Varanasi')
            ->call('submitNewPatientDetails')
            ->assertSet('botState', 'SELECT_SESSION')

            // 4. Selects Date & Session and Books
            ->set('chatDate', '2026-09-28')
            ->set('chatSession', 'MORNING')
            ->call('submitAppointmentBooking')
            ->assertSet('botState', 'BOOKED');

        // Verify Patient created in database
        $newPatient = Patient::where('mobile', 'LIKE', '%9899887766')->first();
        $this->assertNotNull($newPatient);
        $this->assertEquals('Priya Singh', $newPatient->name);
        $this->assertEquals(27, $newPatient->age);
        $this->assertEquals('FEMALE', $newPatient->sex);
        $this->assertEquals('Lane 4, Sigra', $newPatient->address);
        $this->assertEquals('Varanasi', $newPatient->city);

        // Verify Appointment linked
        $appointment = Appointment::where('patient_id', $newPatient->id)->first();
        $this->assertNotNull($appointment);
        $this->assertEquals(1, $appointment->token_number);
        $this->assertEquals('001', $appointment->formatted_token);
        $this->assertNotNull($appointment->secure_token);
    }
}
