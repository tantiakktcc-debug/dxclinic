<?php

namespace Tests\Feature;

use App\Livewire\Operator\OperatorBooking;
use App\Livewire\Pwa\PatientBooking;
use App\Livewire\Settings\BookingTypeManager;
use App\Models\Appointment;
use App\Models\BookingType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorBookingTypeAvailability;
use App\Models\Patient;
use App\Models\PrescriptionPadTemplate;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\BookingTypeService;
use App\Services\ClinicClock;
use App\Services\ClinicScheduleService;
use App\Services\PatientIdentityService;
use App\Services\PrescriptionTemplateResolver;
use App\Services\TenantContext;
use Carbon\Carbon;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class Revision44BookingTypeFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected User $adminUser;
    protected User $operatorUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        // Monday 2026-09-28 07:30 IST
        ClinicClock::setTestNow('2026-09-28 07:30:00');

        $this->clinic = Clinic::create([
            'name' => 'City Care Clinic',
            'phone' => '9876543210',
            'address' => 'Assi Ghat Road',
            'city' => 'Varanasi',
            'pincode' => '221005',
            'is_onboarded' => true,
        ]);

        TenantContext::setClinic($this->clinic);
        $this->seed(\Database\Seeders\FeeMasterSeeder::class);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Shailesh Verma',
            'qualification' => 'MBBS, MD (Medicine)',
            'registration_number' => 'UP-MED-2026-4401',
            'specialization' => 'General Physician',
            'contact_number' => '9876543211',
            'is_active' => true,
            'schedule_config' => [
                'monday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'tuesday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'wednesday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'thursday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'friday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'saturday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'sunday' => ['single' => ['09:00', '12:00']],
            ],
        ]);

        $this->clinic->update(['default_doctor_id' => $this->doctor->id]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@citycare.com',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'clinic_id' => $this->clinic->id,
            'is_active' => true,
        ]);

        $this->operatorUser = User::create([
            'name' => 'Operator User',
            'email' => 'operator@citycare.com',
            'password' => bcrypt('Password123!'),
            'role' => 'OPERATOR',
            'clinic_id' => $this->clinic->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: System booking types are seeded on clinic creation.
     */
    public function test_system_booking_types_are_automatically_seeded_for_clinic(): void
    {
        $types = BookingType::forClinic($this->clinic->id)->get();
        $this->assertCount(3, $types);

        $codes = $types->pluck('code')->all();
        $this->assertContains(BookingType::CLINIC_CONSULTANCY, $codes);
        $this->assertContains(BookingType::ONLINE_CONSULTANCY, $codes);
        $this->assertContains(BookingType::EMERGENCY_CONSULTANCY, $codes);

        $clinicType = $types->firstWhere('code', BookingType::CLINIC_CONSULTANCY);
        $this->assertEquals('Clinic Consultancy', $clinicType->name);
        $this->assertEquals('CLINIC_PHYSICAL', $clinicType->consultation_mode);
        $this->assertTrue($clinicType->is_system);
        $this->assertTrue($clinicType->is_public_bookable);

        $onlineType = $types->firstWhere('code', BookingType::ONLINE_CONSULTANCY);
        $this->assertEquals('Online Consultancy', $onlineType->name);
        $this->assertEquals('ONLINE', $onlineType->consultation_mode);
        $this->assertTrue($onlineType->is_system);
        $this->assertTrue($onlineType->is_public_bookable);

        $emergencyType = $types->firstWhere('code', BookingType::EMERGENCY_CONSULTANCY);
        $this->assertEquals('Emergency Consultancy', $emergencyType->name);
        $this->assertTrue($emergencyType->is_system);
        $this->assertFalse($emergencyType->is_public_bookable);
    }

    /**
     * Test 2: System types cannot be deleted.
     */
    public function test_system_booking_types_cannot_be_deleted(): void
    {
        $systemType = BookingType::forClinic($this->clinic->id)->where('code', BookingType::CLINIC_CONSULTANCY)->firstOrFail();
        $this->assertFalse($systemType->canBeDeleted());

        $service = app(BookingTypeService::class);
        $this->expectException(InvalidArgumentException::class);
        $service->deleteType($systemType);
    }

    /**
     * Test 3: Doctor created automatically gets DoctorBookingTypeAvailability seeded.
     */
    public function test_new_doctor_automatically_has_booking_type_availability_seeded(): void
    {
        $availabilities = DoctorBookingTypeAvailability::where('clinic_id', $this->clinic->id)
            ->where('doctor_id', $this->doctor->id)
            ->get();

        $this->assertCount(3, $availabilities);
        foreach ($availabilities as $av) {
            $this->assertTrue((bool) $av->is_enabled);
        }
    }

    /**
     * Test 4: Custom booking type creation via BookingTypeService succeeds.
     */
    public function test_custom_booking_type_creation_and_deletion(): void
    {
        $service = app(BookingTypeService::class);

        $custom = $service->createCustomType([
            'code'               => 'VIP_HOME_VISIT',
            'name'               => 'VIP Home Visit',
            'consultation_mode'  => 'VISITING_CONSULTANCY',
            'is_public_bookable' => false,
            'display_order'      => 40,
        ], $this->clinic->id);

        $this->assertNotNull($custom);
        $this->assertEquals('VIP_HOME_VISIT', $custom->code);
        $this->assertEquals('VIP Home Visit', $custom->name);
        $this->assertFalse($custom->is_system);
        $this->assertTrue($custom->canBeDeleted());

        // Deleting when no appointments reference it succeeds
        $service->deleteType($custom);
        $this->assertDatabaseMissing('booking_types', ['id' => $custom->id]);
    }

    /**
     * Test 5: Custom booking type cannot use system reserved codes.
     */
    public function test_cannot_create_custom_type_with_system_code(): void
    {
        $service = app(BookingTypeService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->createCustomType([
            'code'               => 'CLINIC_CONSULTANCY',
            'name'               => 'Another Clinic Consultancy',
            'consultation_mode'  => 'CLINIC_PHYSICAL',
        ], $this->clinic->id);
    }

    /**
     * Test 6: Doctor availability matrix toggles and sync.
     */
    public function test_doctor_availability_matrix_toggle_and_sync(): void
    {
        $service = app(BookingTypeService::class);
        $onlineType = BookingType::forClinic($this->clinic->id)->where('code', BookingType::ONLINE_CONSULTANCY)->firstOrFail();

        // Toggle doctor availability off for ONLINE
        $service->toggleDoctorAvailability($this->clinic->id, $this->doctor->id, $onlineType->id, false);

        $enabledTypes = $service->getEnabledForDoctor($this->doctor->id, $this->clinic->id);
        $this->assertFalse($enabledTypes->contains('id', $onlineType->id));

        // Sync doctor availability back on
        $service->syncDoctorAvailabilities($this->clinic->id, $this->doctor->id, [
            $onlineType->id => true,
        ]);

        $enabledTypesAfter = $service->getEnabledForDoctor($this->doctor->id, $this->clinic->id);
        $this->assertTrue($enabledTypesAfter->contains('id', $onlineType->id));
    }

    /**
     * Test 7: Public bookable filtering on PWA.
     */
    public function test_public_bookable_types_filter_correctly(): void
    {
        $service = app(BookingTypeService::class);
        $publicTypes = $service->getPublicBookableForClinic($this->clinic->id, $this->doctor->id);

        $codes = $publicTypes->pluck('code')->all();
        $this->assertContains(BookingType::CLINIC_CONSULTANCY, $codes);
        $this->assertContains(BookingType::ONLINE_CONSULTANCY, $codes);
        $this->assertNotContains(BookingType::EMERGENCY_CONSULTANCY, $codes, 'Emergency must not be public bookable');
    }

    /**
     * Test 8: Booking with booking_type_code sets booking_type_id and aligns consultation_mode.
     */
    public function test_booking_with_booking_type_code_sets_foreign_key_and_mode(): void
    {
        $patientService = app(PatientIdentityService::class);
        $patient = $patientService->registerPatient([
            'clinic_id' => $this->clinic->id,
            'name' => 'Arun Pandey',
            'age' => 38,
            'sex' => 'MALE',
            'mobile' => '9876543215',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
        ]);

        $appointmentService = app(AppointmentService::class);
        $appointment = $appointmentService->bookAppointment([
            'clinic_id'         => $this->clinic->id,
            'patient_id'        => $patient->id,
            'doctor_id'         => $this->doctor->id,
            'appointment_date'  => '2026-09-28',
            'session_type'      => 'MORNING',
            'booking_source'    => 'CLINIC',
            'booking_type_code' => BookingType::ONLINE_CONSULTANCY,
        ]);

        $this->assertNotNull($appointment->booking_type_id);
        $this->assertEquals('ONLINE', $appointment->consultation_mode);
        $this->assertEquals('Online Consultancy', $appointment->bookingType->name);
        $this->assertEquals(1, $appointment->token_number);
    }

    /**
     * Test 9: Booking with booking_type_id works directly.
     */
    public function test_booking_with_booking_type_id_works_directly(): void
    {
        $emergencyType = BookingType::forClinic($this->clinic->id)->where('code', BookingType::EMERGENCY_CONSULTANCY)->firstOrFail();

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-44001',
            'name' => 'Meena Gupta',
            'age' => 42,
            'sex' => 'FEMALE',
            'mobile' => '9876543216',
            'address' => 'Sigra',
            'city' => 'Varanasi',
        ]);

        $appointmentService = app(AppointmentService::class);
        $appointment = $appointmentService->bookAppointment([
            'clinic_id'         => $this->clinic->id,
            'patient_id'        => $patient->id,
            'doctor_id'         => $this->doctor->id,
            'appointment_date'  => '2026-09-28',
            'session_type'      => 'MORNING',
            'booking_source'    => 'CLINIC',
            'booking_type_id'   => $emergencyType->id,
        ]);

        $this->assertEquals($emergencyType->id, $appointment->booking_type_id);
        $this->assertEquals('CLINIC_PHYSICAL', $appointment->consultation_mode);
        $this->assertEquals(1, $appointment->token_number);
    }

    /**
     * Test 10: Legacy booking without booking_type_id remains fully supported.
     */
    public function test_legacy_booking_without_booking_type_remains_supported(): void
    {
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-44002',
            'name' => 'Ganesh Prasad',
            'age' => 55,
            'sex' => 'MALE',
            'mobile' => '9876543217',
            'address' => 'Godowlia',
            'city' => 'Varanasi',
        ]);

        $appointmentService = app(AppointmentService::class);
        $appointment = $appointmentService->bookAppointment([
            'clinic_id'         => $this->clinic->id,
            'patient_id'        => $patient->id,
            'doctor_id'         => $this->doctor->id,
            'appointment_date'  => '2026-09-28',
            'session_type'      => 'MORNING',
            'booking_source'    => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        $this->assertNull($appointment->booking_type_id);
        $this->assertEquals('CLINIC_PHYSICAL', $appointment->consultation_mode);
        $this->assertEquals(1, $appointment->token_number);
    }

    /**
     * Test 11: BookingTypeManager Livewire component.
     */
    public function test_booking_type_manager_livewire_component(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(BookingTypeManager::class)
            ->assertSee('Booking Types')
            ->assertSee('Clinic Consultancy')
            ->assertSee('Online Consultancy')
            ->assertSee('Emergency Consultancy')
            ->call('openCreateModal')
            ->set('newCode', 'SPECIALTY_CLINIC')
            ->set('newName', 'Specialty Clinic')
            ->set('newConsultationMode', 'CLINIC_PHYSICAL')
            ->call('createBookingType')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('booking_types', [
            'clinic_id' => $this->clinic->id,
            'code' => 'SPECIALTY_CLINIC',
            'name' => 'Specialty Clinic',
        ]);
    }

    /**
     * Test 12: OperatorBooking Livewire component with Booking Types.
     */
    public function test_operator_booking_renders_canonical_clinic_consultancy_and_sets_type(): void
    {
        $this->actingAs($this->operatorUser);

        Livewire::test(OperatorBooking::class)
            ->assertSee('Clinic Consultancy')
            ->assertDontSee('Clinic Consultation')
            ->assertDontSee('Clinic Physical Consultation')
            ->set('name', 'Sunita Devi')
            ->set('age', '34')
            ->set('sex', 'FEMALE')
            ->set('mobile', '9876543220')
            ->set('address', 'Lahurabir')
            ->set('city', 'Varanasi')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('appointment_date', '2026-09-28')
            ->set('session_type', 'MORNING')
            ->call('bookOperatorToken')
            ->assertSet('bookingSuccess', true)
            ->assertHasNoErrors();

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);
        $this->assertNotNull($appointment->booking_type_id);
    }

    /**
     * Test 13: PatientBooking PWA Livewire component with public booking types.
     */
    public function test_patient_booking_pwa_renders_canonical_clinic_consultancy_and_excludes_emergency(): void
    {
        Livewire::test(PatientBooking::class, ['clinic_code' => $this->clinic->clinic_code])
            ->assertSee('Clinic Consultancy')
            ->assertDontSee('Emergency Consultancy')
            ->assertDontSee('Clinic Consultation')
            ->set('name', 'Pooja Singh')
            ->set('age', '28')
            ->set('sex', 'FEMALE')
            ->set('mobile', '9876543225')
            ->set('address', 'Cantonment')
            ->set('city', 'Varanasi')
            ->set('selected_doctor_id', $this->doctor->id)
            ->set('appointment_date', '2026-09-28')
            ->set('session_type', 'MORNING')
            ->call('bookToken')
            ->assertSet('bookingSuccess', true)
            ->assertHasNoErrors();

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);
        $this->assertNotNull($appointment->booking_type_id);
    }

    /**
     * Test 14: Historical prescription pad template immutability and template name snapshot.
     */
    public function test_prescription_template_resolver_snapshots_template_name(): void
    {
        $template = PrescriptionPadTemplate::create([
            'clinic_id'   => $this->clinic->id,
            'doctor_id'   => $this->doctor->id,
            'name'        => 'Dr. Shailesh Standard Prescription v1',
            'version'     => 1,
            'status'      => 'ACTIVE',
            'is_active'   => true,
            'page_margin' => 'DEFAULT',
            'font_family' => 'sans-serif',
            'font_size'   => 'MEDIUM',
        ]);

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-44010',
            'name' => 'Vikram Roy',
            'age' => 50,
            'sex' => 'MALE',
            'mobile' => '9876543230',
            'address' => 'Naria',
            'city' => 'Varanasi',
        ]);

        $appointmentService = app(AppointmentService::class);
        $appointment = $appointmentService->bookAppointment([
            'clinic_id'         => $this->clinic->id,
            'patient_id'        => $patient->id,
            'doctor_id'         => $this->doctor->id,
            'appointment_date'  => '2026-09-28',
            'session_type'      => 'MORNING',
            'booking_source'    => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        $resolver = app(PrescriptionTemplateResolver::class);
        $resolved = $resolver->resolveForAppointment($appointment, $this->clinic->id);

        $this->assertNotNull($resolved);
        $this->assertEquals($template->id, $resolved->id);

        $appointment->refresh();
        $this->assertEquals($template->id, $appointment->prescription_pad_template_id);
        $this->assertEquals('Dr. Shailesh Standard Prescription v1', $appointment->prescription_template_name);

        // Even if template is archived later, resolution returns original template
        $template->update(['status' => 'ARCHIVED', 'is_active' => false]);
        $resolvedArchived = $resolver->resolveForAppointment($appointment, $this->clinic->id);
        $this->assertNotNull($resolvedArchived);
        $this->assertEquals($template->id, $resolvedArchived->id);
    }
}
