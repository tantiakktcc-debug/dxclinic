<?php

namespace Tests\Feature;

use App\Livewire\Pwa\PatientBooking;
use App\Models\Appointment;
use App\Models\BookingType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\License;
use App\Models\Patient;
use App\Models\SubscriptionPlan;
use App\Services\PhoneNumberNormalizer;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class PatientPwaRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected BookingType $bookingType;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();

        $plan = SubscriptionPlan::create([
            'name' => 'Professional Plan',
            'code' => 'PROFESSIONAL',
            'duration_months' => 12,
            'price' => 9999.00,
            'price_per_year' => 9999.00,
            'max_doctors' => 5,
            'features' => ['all'],
            'is_active' => true,
        ]);

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'sharmacare',
            'name' => 'Sharma Care Clinic',
            'address' => 'Assi Ghat',
            'city' => 'Varanasi',
            'phone' => '9876543001',
            'is_onboarded' => true,
            'onboarding_status' => 'COMPLETED',
            'activation_status' => 'ACTIVATED',
            'max_advance_booking_days' => 7,
            'max_patients_per_mobile' => 4,
        ]);

        License::create([
            'clinic_id' => $this->clinic->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'KEY-SHARMA'),
            'license_key_encrypted' => Crypt::encryptString('KEY-SHARMA'),
            'license_key_prefix' => 'NXCL-SHAR',
            'status' => 'ACTIVE',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'max_doctors' => 5,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'specialization' => 'General Medicine',
            'registration_number' => 'DOC-SHARMA-01',
            'is_active' => true,
            'consultation_fee' => 600,
        ]);

        $this->bookingType = BookingType::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Clinic Consultancy',
            'code' => 'CLINIC_PHYSICAL',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'is_active' => true,
            'is_system' => true,
            'is_public' => true,
        ]);

        \App\Models\DoctorBookingTypeAvailability::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'booking_type_id' => $this->bookingType->id,
            'is_enabled' => true,
        ]);

        FeeMaster::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'booking_type_id' => $this->bookingType->id,
            'code' => 'OPD_FEE_DOC_SHARMA',
            'name' => 'Consultation Fee',
            'fee_type' => 'OPD',
            'amount' => 600.00,
            'effective_from' => '2020-01-01',
        ]);
    }

    /**
     * 1. Valid new patient registration succeeds and creates patient, appointment, token.
     */
    public function test_valid_new_patient_registration_succeeds_without_500(): void
    {
        $tomorrow = Carbon::tomorrow('Asia/Kolkata')->toDateString();

        $component = Livewire::test(PatientBooking::class, ['public_slug' => 'sharmacare'])
            ->set('name', 'Amitabh Bachchan')
            ->set('age', 82)
            ->set('sex', 'MALE')
            ->set('mobile', '9876543210')
            ->set('address', 'Prateeksha, Juhu')
            ->set('city', 'Varanasi')
            ->set('appointment_date', $tomorrow)
            ->set('session_type', 'MORNING')
            ->set('consultation_mode', 'CLINIC_PHYSICAL')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('selected_booking_type_id', $this->bookingType->id)
            ->call('bookToken');

        $component->assertHasNoErrors();
        $this->assertTrue($component->get('bookingSuccess'));
        $appointment = $component->get('bookedAppointment');
        $this->assertNotNull($appointment);
        $this->assertEquals(1, $appointment->token_number);
        $this->assertEquals('ONLINE', $appointment->booking_source);
        $this->assertEquals($this->clinic->id, $appointment->clinic_id);

        // Verify patient created
        $patient = Patient::where('clinic_id', $this->clinic->id)->where('name', 'Amitabh Bachchan')->first();
        $this->assertNotNull($patient);
        $this->assertEquals('+91 9876543210', $patient->mobile);
        $this->assertStringStartsWith('DXP-', $patient->patient_id);
    }

    /**
     * 2. Invalid phone inputs must return validation errors, never HTTP 500.
     */
    public function test_invalid_phone_formats_produce_validation_errors_not_500(): void
    {
        $invalidNumbers = [
            '98765 43210',
            '98765-43210',
            '987654321',
            '98765432100',
            '+91 98765 43210',
            'abcdefghij',
            '9876543210a',
        ];

        $tomorrow = Carbon::tomorrow('Asia/Kolkata')->toDateString();

        foreach ($invalidNumbers as $phone) {
            $component = Livewire::test(PatientBooking::class, ['public_slug' => 'sharmacare'])
                ->set('name', 'Valid Patient Name')
                ->set('age', 35)
                ->set('sex', 'FEMALE')
                ->set('mobile', $phone)
                ->set('address', 'Test Address')
                ->set('city', 'Varanasi')
                ->set('appointment_date', $tomorrow)
                ->set('session_type', 'MORNING')
                ->set('selected_doctor_id', $this->doctor->id)
                ->set('selected_booking_type_id', $this->bookingType->id)
                ->call('bookToken');

            $component->assertHasErrors(['mobile']);
            $this->assertFalse($component->get('bookingSuccess'));
        }

        // Verify no patient created
        $this->assertEquals(0, Patient::count());
        $this->assertEquals(0, Appointment::count());
    }

    /**
     * 3. Invalid name produces validation error, not 500.
     */
    public function test_invalid_name_produces_validation_error(): void
    {
        $tomorrow = Carbon::tomorrow('Asia/Kolkata')->toDateString();

        $component = Livewire::test(PatientBooking::class, ['public_slug' => 'sharmacare'])
            ->set('name', '12345')
            ->set('age', 35)
            ->set('sex', 'MALE')
            ->set('mobile', '9876543210')
            ->set('address', 'Test Address')
            ->set('city', 'Varanasi')
            ->set('appointment_date', $tomorrow)
            ->set('session_type', 'MORNING')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('selected_booking_type_id', $this->bookingType->id)
            ->call('bookToken');

        $component->assertHasErrors(['name']);
        $this->assertFalse($component->get('bookingSuccess'));
    }

    /**
     * 4. Missing required fields produce validation errors, not 500.
     */
    public function test_missing_fields_produce_validation_errors(): void
    {
        $component = Livewire::test(PatientBooking::class, ['public_slug' => 'sharmacare'])
            ->set('name', '')
            ->set('age', '')
            ->set('mobile', '')
            ->set('address', '')
            ->set('city', '')
            ->call('bookToken');

        $component->assertHasErrors(['name', 'age', 'mobile', 'address', 'city']);
        $this->assertFalse($component->get('bookingSuccess'));
    }

    /**
     * 5. Double submission / idempotency: submitting twice rapidly reuses the booking and does not duplicate.
     */
    public function test_idempotent_double_submission_does_not_create_duplicates(): void
    {
        $tomorrow = Carbon::tomorrow('Asia/Kolkata')->toDateString();

        $component = Livewire::test(PatientBooking::class, ['public_slug' => 'sharmacare'])
            ->set('name', 'Rohan Gupta')
            ->set('age', 30)
            ->set('sex', 'MALE')
            ->set('mobile', '9876543215')
            ->set('address', 'Godowlia')
            ->set('city', 'Varanasi')
            ->set('appointment_date', $tomorrow)
            ->set('session_type', 'MORNING')
            ->set('consultation_mode', 'CLINIC_PHYSICAL')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('selected_booking_type_id', $this->bookingType->id)
            ->call('bookToken');

        $this->assertTrue($component->get('bookingSuccess'));
        $firstAppointmentId = $component->get('bookedAppointment')?->id;

        // Submitting again on the same component instance
        $component->call('bookToken');
        $this->assertEquals($firstAppointmentId, $component->get('bookedAppointment')?->id);

        // Submitting from a second component instance with the exact same data
        $secondComponent = Livewire::test(PatientBooking::class, ['public_slug' => 'sharmacare'])
            ->set('name', 'Rohan Gupta')
            ->set('age', 30)
            ->set('sex', 'MALE')
            ->set('mobile', '9876543215')
            ->set('address', 'Godowlia')
            ->set('city', 'Varanasi')
            ->set('appointment_date', $tomorrow)
            ->set('session_type', 'MORNING')
            ->set('consultation_mode', 'CLINIC_PHYSICAL')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('selected_booking_type_id', $this->bookingType->id)
            ->call('bookToken');

        $this->assertTrue($secondComponent->get('bookingSuccess'));
        $this->assertEquals($firstAppointmentId, $secondComponent->get('bookedAppointment')?->id);

        // Assert exactly 1 patient and 1 appointment exist
        $this->assertEquals(1, Patient::count());
        $this->assertEquals(1, Appointment::count());
    }

    /**
     * 6. Existing patient reuse: patient with same name and mobile is reused, not duplicated.
     */
    public function test_existing_patient_is_reused_without_duplication(): void
    {
        $existingPatient = Patient::create([
            'patient_id' => 'DXP-00001',
            'clinic_id' => $this->clinic->id,
            'name' => 'Kavita Sharma',
            'age' => 45,
            'sex' => 'FEMALE',
            'mobile' => '+91 9876543216',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
            'profile_status' => 'ACTIVE',
        ]);

        $tomorrow = Carbon::tomorrow('Asia/Kolkata')->toDateString();

        $component = Livewire::test(PatientBooking::class, ['public_slug' => 'sharmacare'])
            ->set('name', 'kavita sharma')
            ->set('age', 45)
            ->set('sex', 'FEMALE')
            ->set('mobile', '9876543216')
            ->set('address', 'Bhelupur')
            ->set('city', 'Varanasi')
            ->set('appointment_date', $tomorrow)
            ->set('session_type', 'MORNING')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('selected_booking_type_id', $this->bookingType->id)
            ->call('bookToken');

        $this->assertTrue($component->get('bookingSuccess'));
        $this->assertEquals($existingPatient->id, $component->get('bookedAppointment')?->patient_id);
        $this->assertEquals(1, Patient::count());
    }

    /**
     * 7. Tenant isolation: booking belongs strictly to Sharma Care clinic.
     */
    public function test_tenant_isolation_is_strictly_enforced(): void
    {
        $tomorrow = Carbon::tomorrow('Asia/Kolkata')->toDateString();

        $component = Livewire::test(PatientBooking::class, ['public_slug' => 'sharmacare'])
            ->set('name', 'Deepak Verma')
            ->set('age', 29)
            ->set('sex', 'MALE')
            ->set('mobile', '9876543217')
            ->set('address', 'Lanka')
            ->set('city', 'Varanasi')
            ->set('appointment_date', $tomorrow)
            ->set('session_type', 'MORNING')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('selected_booking_type_id', $this->bookingType->id)
            ->call('bookToken');

        $this->assertTrue($component->get('bookingSuccess'));
        $appt = $component->get('bookedAppointment');
        $this->assertEquals($this->clinic->id, $appt->clinic_id);
        $this->assertEquals($this->clinic->id, $appt->patient->clinic_id);
        $this->assertEquals($this->clinic->id, $appt->doctor->clinic_id);
    }
}
