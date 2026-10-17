<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\Doctor;
use App\Models\DoctorConsultationType;
use App\Models\Expense;
use App\Models\FeeMaster;
use App\Models\Journal;
use App\Models\License;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\SubscriptionPlan;
use App\Models\TokenSequence;
use App\Models\User;
use App\Models\Visit;
use App\Models\Vital;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoService
{
    public const DEMO_CLINIC_CODE = 'democlinic';
    public const DEMO_ADMIN_LOGIN = 'demoadmin';
    public const DEMO_OPERATOR_LOGIN = 'demooperator';
    public const DEMO_DOCTOR1_LOGIN = 'demodoctor01';
    public const DEMO_DOCTOR2_LOGIN = 'demodoctor02';
    public const DEMO_DOCTOR_LOGIN = 'demodoctor'; // Backward-compatible alias

    public const DEMO_ADMIN_PASSWORD = 'DemoAdmin@2026!';
    public const DEMO_OPERATOR_PASSWORD = 'DemoOp@2026!';
    public const DEMO_DOCTOR1_PASSWORD = 'DemoDoc01@2026!';
    public const DEMO_DOCTOR2_PASSWORD = 'DemoDoc02@2026!';
    public const DEMO_DOCTOR_PASSWORD = 'DemoDoc01@2026!';

    /**
     * Get the permanent system Demo Clinic tenant
     */
    public static function getDemoClinic(): ?Clinic
    {
        return Clinic::where('clinic_code', self::DEMO_CLINIC_CODE)->first();
    }

    /**
     * Check if a clinic is the demo clinic
     */
    public static function isDemo(?Clinic $clinic): bool
    {
        if (!$clinic) {
            return false;
        }
        return $clinic->isDemo();
    }

    /**
     * Check if the demo clinic is currently paused
     */
    public static function isDemoPaused(): bool
    {
        $demo = self::getDemoClinic();
        return $demo ? $demo->isDemoPaused() : false;
    }

    /**
     * Pause the demo environment
     */
    public static function pauseDemo(): bool
    {
        $demo = self::getDemoClinic();
        if (!$demo) {
            $demo = self::seedDemoClinic();
        }

        $demo->demo_status = 'PAUSED';
        return $demo->save();
    }

    /**
     * Resume the demo environment
     */
    public static function resumeDemo(): bool
    {
        $demo = self::getDemoClinic();
        if (!$demo) {
            $demo = self::seedDemoClinic();
        }

        $demo->demo_status = 'ACTIVE';
        return $demo->save();
    }

    /**
     * Seed or re-seed the complete permanent system Demo Tenant
     */
    public static function seedDemoClinic(): Clinic
    {
        return DB::transaction(function () {
            // 1. Clinic Record
            $clinic = Clinic::firstOrNew(['clinic_code' => self::DEMO_CLINIC_CODE]);
            $clinic->name = 'Demo Clinic';
            $clinic->is_demo = true;
            $clinic->demo_status = $clinic->demo_status ?? 'ACTIVE';
            $clinic->address = '108, Healthcare Boulevard, Cyber City';
            $clinic->city = 'Varanasi';
            $clinic->pincode = '221005';
            $clinic->phone = '9876543210';
            $clinic->email = 'contact@democlinic.dxclinic.local';
            $clinic->activation_status = 'ACTIVATED';
            $clinic->onboarding_status = 'COMPLETED';
            $clinic->is_onboarded = true;
            $clinic->activated_at = $clinic->activated_at ?? now();
            $clinic->max_advance_booking_days = 2;
            $clinic->max_patients_per_mobile = 4;
            $clinic->schedule_config = [
                'monday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '21:00']],
                'tuesday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '21:00']],
                'wednesday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '21:00']],
                'thursday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '21:00']],
                'friday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '21:00']],
                'saturday' => ['morning' => ['09:00', '13:00'], 'evening' => ['17:00', '20:00']],
                'sunday' => ['single' => ['10:00', '13:00']],
            ];
            $clinic->print_branding_config = [
                'header_title' => 'DEMO CLINIC & DIAGNOSTICS',
                'sub_header' => 'A Modern Multi-Speciality Outpatient Facility',
                'footer_note' => 'System Demonstration Environment — All Data is Fictional',
            ];
            $clinic->public_slug = $clinic->public_slug ?: 'democlinic';
            $clinic->booking_qr_prod_url = rtrim((string) config('app.public_booking_url', 'https://dxclinic.example.local/book'), '/') . "/{$clinic->public_slug}";
            $clinic->booking_qr_local_url = url("/book/{$clinic->public_slug}");
            $clinic->save();

            // 2. Demo Doctors (Hard rule: exactly 2 fictional demo doctors)
            // Doctor #1: Amit Verma (General Medicine)
            $doctor1 = Doctor::updateOrCreate(
                ['clinic_id' => $clinic->id, 'registration_number' => 'REG-DEMO01'],
                [
                    'name' => 'Amit Verma',
                    'qualification' => 'MBBS, MD (General Medicine)',
                    'specialization' => 'General Medicine',
                    'contact_number' => '9876543210',
                    'is_active' => true,
                ]
            );

            // Doctor #2: Neha Kapoor (Family Medicine)
            $doctor2 = Doctor::updateOrCreate(
                ['clinic_id' => $clinic->id, 'registration_number' => 'REG-DEMO02'],
                [
                    'name' => 'Neha Kapoor',
                    'qualification' => 'MBBS, MS (Family Medicine)',
                    'specialization' => 'Family Medicine',
                    'contact_number' => '9876543211',
                    'is_active' => true,
                ]
            );

            // Clean up any old demo doctors with old registration numbers or names
            Doctor::where('clinic_id', $clinic->id)
                ->whereNotIn('registration_number', ['REG-DEMO01', 'REG-DEMO02'])
                ->delete();

            $clinic->default_doctor_id = $doctor1->id;
            $clinic->save();

            // 3. Demo License (5-Year Plan, 60 months duration, hard limit: 2 doctor seats)
            $plan = SubscriptionPlan::where('code', 'FIVE_YEAR')->first()
                ?? SubscriptionPlan::where('duration_months', 60)->first()
                ?? SubscriptionPlan::first();
            $rawLicenseKey = 'NXCL-DEMO-0000-0000-DEMO';

            $startDate = Carbon::today('Asia/Kolkata');
            $endDate = (clone $startDate)->addMonths(60);

            $license = License::updateOrCreate(
                ['clinic_id' => $clinic->id],
                [
                    'plan_id' => $plan?->id ?? 6,
                    'license_key_hash' => hash('sha256', $rawLicenseKey),
                    'license_key_encrypted' => Crypt::encryptString($rawLicenseKey),
                    'license_key_prefix' => 'NXCL',
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'amount' => 0.00,
                    'net_amount' => 0.00,
                    'included_doctor_seats' => 2,
                    'additional_doctor_seats' => 0,
                    'additional_doctor_fee_per_year' => 2999.00,
                    'additional_doctor_amount' => 0.00,
                    'status' => 'ACTIVE',
                    'issued_at' => now(),
                    'notes' => 'Permanent system demo license (5-Year Plan)',
                ]
            );

            // 4. Demo Users
            // 4a. Admin User
            User::updateOrCreate(
                ['clinic_id' => $clinic->id, 'login_id' => self::DEMO_ADMIN_LOGIN],
                [
                    'name' => 'Demo Administrator',
                    'email' => 'demoadmin@dxclinic.local',
                    'phone' => '9876543210',
                    'role' => 'ADMIN',
                    'password' => Hash::make(self::DEMO_ADMIN_PASSWORD),
                    'must_change_password' => false,
                    'status' => 'ACTIVE',
                ]
            );

            // 4b. Operator User
            User::updateOrCreate(
                ['clinic_id' => $clinic->id, 'login_id' => self::DEMO_OPERATOR_LOGIN],
                [
                    'name' => 'Demo Frontdesk Operator',
                    'email' => 'demooperator@dxclinic.local',
                    'phone' => '9876543211',
                    'role' => 'OPERATOR',
                    'password' => Hash::make(self::DEMO_OPERATOR_PASSWORD),
                    'must_change_password' => false,
                    'status' => 'ACTIVE',
                ]
            );

            // 4c. Doctor User #1 (demodoctor01 -> Dr. Amit Verma)
            User::updateOrCreate(
                ['clinic_id' => $clinic->id, 'login_id' => self::DEMO_DOCTOR1_LOGIN],
                [
                    'name' => 'Amit Verma',
                    'email' => 'demodoctor01@dxclinic.local',
                    'phone' => '9876543212',
                    'role' => 'DOCTOR',
                    'doctor_id' => $doctor1->id,
                    'password' => Hash::make(self::DEMO_DOCTOR1_PASSWORD),
                    'must_change_password' => false,
                    'status' => 'ACTIVE',
                ]
            );

            // 4d. Doctor User #2 (demodoctor02 -> Dr. Neha Kapoor)
            User::updateOrCreate(
                ['clinic_id' => $clinic->id, 'login_id' => self::DEMO_DOCTOR2_LOGIN],
                [
                    'name' => 'Neha Kapoor',
                    'email' => 'demodoctor02@dxclinic.local',
                    'phone' => '9876543213',
                    'role' => 'DOCTOR',
                    'doctor_id' => $doctor2->id,
                    'password' => Hash::make(self::DEMO_DOCTOR2_PASSWORD),
                    'must_change_password' => false,
                    'status' => 'ACTIVE',
                ]
            );

            // 4e. Backward-compatible alias: demodoctor -> Dr. Amit Verma
            User::updateOrCreate(
                ['clinic_id' => $clinic->id, 'login_id' => self::DEMO_DOCTOR_LOGIN],
                [
                    'name' => 'Amit Verma',
                    'email' => 'demodoctor@dxclinic.local',
                    'phone' => '9876543212',
                    'role' => 'DOCTOR',
                    'doctor_id' => $doctor1->id,
                    'password' => Hash::make(self::DEMO_DOCTOR_PASSWORD),
                    'must_change_password' => false,
                    'status' => 'ACTIVE',
                ]
            );

            // 5. Consultation Types & Fees
            self::seedConsultationTypesAndFees($clinic, $doctor1, $doctor2);

            // 6. Seed Fictional Transactional Data
            self::seedTransactionalData($clinic, $doctor1, $doctor2);

            // 7. Demo WhatsApp Connection (Section 8)
            \App\Models\WhatsAppConnection::firstOrCreate(
                ['clinic_id' => $clinic->id],
                [
                    'provider' => 'meta',
                    'phone_number_id' => '109876543210001',
                    'business_account_id' => 'waba_democlinic_001',
                    'display_phone_number' => '+91 98765 43210',
                    'business_display_name' => 'Demo Clinic Assistant',
                    'status' => 'CONNECTED',
                    'connection_type' => 'EMBEDDED_SIGNUP',
                    'ownership_type' => 'CLINIC_OWNED',
                    'webhook_verify_token' => 'dxclinic_verify_token_secure',
                    'settings' => [
                        'assistant_enabled' => true,
                        'booking_enabled' => true,
                        'faq_enabled' => true,
                        'hours_enabled' => true,
                        'price_info_enabled' => true,
                        'location_enabled' => true,
                        'human_handoff_enabled' => true,
                        'booking_today' => true,
                        'booking_tomorrow' => true,
                        'morning_enabled' => true,
                        'evening_enabled' => true,
                        'sunday_enabled' => true,
                    ],
                    'connected_at' => now(),
                ]
            );

            return $clinic->fresh();
        });
    }

    /**
     * Reset Demo Tenant data atomically
     * Strictly affects ONLY the demo clinic. Real clinics are NEVER touched.
     */
    public static function resetDemoData(): array
    {
        return DB::transaction(function () {
            $clinic = self::getDemoClinic();
            if (!$clinic) {
                $clinic = self::seedDemoClinic();
                return ['success' => true, 'message' => 'Demo Clinic initialized.'];
            }

            // Safety guard: Must be demo clinic
            if (!$clinic->isDemo()) {
                throw new \InvalidArgumentException('Reset operation is only allowed for the Demo Clinic.');
            }

            $clinicId = $clinic->id;

            $visitIds = Visit::where('clinic_id', $clinicId)->pluck('id');
            Vital::whereIn('visit_id', $visitIds)->delete();
            Appointment::where('clinic_id', $clinicId)->delete();
            ConsultationBill::where('clinic_id', $clinicId)->delete();
            DiagnosticBill::where('clinic_id', $clinicId)->delete();
            Payment::where('clinic_id', $clinicId)->delete();
            Receipt::where('clinic_id', $clinicId)->delete();
            Expense::where('clinic_id', $clinicId)->delete();
            Journal::where('clinic_id', $clinicId)->delete();
            Visit::where('clinic_id', $clinicId)->delete();
            Patient::where('clinic_id', $clinicId)->delete();
            TokenSequence::where('clinic_id', $clinicId)->delete();
            CashSession::where('clinic_id', $clinicId)->delete();

            // 2. Restore active status
            $clinic->demo_status = 'ACTIVE';
            $clinic->save();

            // 3. Ensure both demo doctors exist
            $doctor1 = Doctor::updateOrCreate(
                ['clinic_id' => $clinic->id, 'registration_number' => 'REG-DEMO01'],
                [
                    'name' => 'Amit Verma',
                    'qualification' => 'MBBS, MD (General Medicine)',
                    'specialization' => 'General Medicine',
                    'contact_number' => '9876543210',
                    'is_active' => true,
                ]
            );

            $doctor2 = Doctor::updateOrCreate(
                ['clinic_id' => $clinic->id, 'registration_number' => 'REG-DEMO02'],
                [
                    'name' => 'Neha Kapoor',
                    'qualification' => 'MBBS, MS (Family Medicine)',
                    'specialization' => 'Family Medicine',
                    'contact_number' => '9876543211',
                    'is_active' => true,
                ]
            );

            // Delete any unexpected doctors in demo clinic
            Doctor::where('clinic_id', $clinic->id)
                ->whereNotIn('id', [$doctor1->id, $doctor2->id])
                ->delete();

            $clinic->default_doctor_id = $doctor1->id;
            $clinic->save();

            // Reset demo users
            User::updateOrCreate(
                ['clinic_id' => $clinic->id, 'login_id' => self::DEMO_DOCTOR1_LOGIN],
                [
                    'name' => 'Amit Verma',
                    'email' => 'demodoctor01@dxclinic.local',
                    'role' => 'DOCTOR',
                    'doctor_id' => $doctor1->id,
                    'password' => Hash::make(self::DEMO_DOCTOR1_PASSWORD),
                    'must_change_password' => false,
                    'status' => 'ACTIVE',
                ]
            );

            User::updateOrCreate(
                ['clinic_id' => $clinic->id, 'login_id' => self::DEMO_DOCTOR2_LOGIN],
                [
                    'name' => 'Neha Kapoor',
                    'email' => 'demodoctor02@dxclinic.local',
                    'role' => 'DOCTOR',
                    'doctor_id' => $doctor2->id,
                    'password' => Hash::make(self::DEMO_DOCTOR2_PASSWORD),
                    'must_change_password' => false,
                    'status' => 'ACTIVE',
                ]
            );

            User::updateOrCreate(
                ['clinic_id' => $clinic->id, 'login_id' => self::DEMO_DOCTOR_LOGIN],
                [
                    'name' => 'Amit Verma',
                    'email' => 'demodoctor@dxclinic.local',
                    'role' => 'DOCTOR',
                    'doctor_id' => $doctor1->id,
                    'password' => Hash::make(self::DEMO_DOCTOR_PASSWORD),
                    'must_change_password' => false,
                    'status' => 'ACTIVE',
                ]
            );

            self::seedConsultationTypesAndFees($clinic, $doctor1, $doctor2);
            self::seedTransactionalData($clinic, $doctor1, $doctor2);

            return [
                'success' => true,
                'clinic_code' => $clinic->clinic_code,
                'status' => $clinic->demo_status,
                'message' => 'Demo Clinic data reset successfully to standard fictional dataset.',
            ];
        });
    }

    /**
     * Seed consultation types and fee configurations for Demo Clinic
     */
    protected static function seedConsultationTypesAndFees(Clinic $clinic, Doctor $doctor1, ?Doctor $doctor2 = null): void
    {
        // Doctor #1 (Amit Verma): Clinic Consultancy + Online Consultancy
        DoctorConsultationType::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => $doctor1->id, 'consultation_mode' => 'CLINIC_PHYSICAL'],
            ['is_enabled' => true]
        );
        DoctorConsultationType::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => $doctor1->id, 'consultation_mode' => 'ONLINE'],
            ['is_enabled' => true]
        );
        DoctorConsultationType::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => $doctor1->id, 'consultation_mode' => 'VISITING_CONSULTANCY'],
            ['is_enabled' => false]
        );

        // Seed / link Revision 44 & 45 Booking Types
        $btClinic = \App\Models\BookingType::firstOrCreate(
            ['clinic_id' => $clinic->id, 'code' => 'CLINIC_CONSULTANCY'],
            ['name' => 'Clinic Consultancy', 'consultation_mode' => 'CLINIC_PHYSICAL', 'is_system' => true, 'is_active' => true, 'is_public_bookable' => true, 'display_order' => 10]
        );
        $btOnline = \App\Models\BookingType::firstOrCreate(
            ['clinic_id' => $clinic->id, 'code' => 'ONLINE_CONSULTANCY'],
            ['name' => 'Online Consultancy', 'consultation_mode' => 'ONLINE', 'is_system' => true, 'is_active' => true, 'is_public_bookable' => true, 'display_order' => 20]
        );
        $btVisiting = \App\Models\BookingType::firstOrCreate(
            ['clinic_id' => $clinic->id, 'code' => 'VISITING_CONSULTANCY'],
            ['name' => 'Visiting Consultancy', 'consultation_mode' => 'VISITING_CONSULTANCY', 'is_system' => true, 'is_active' => true, 'is_public_bookable' => false, 'display_order' => 30]
        );

        \App\Models\DoctorBookingTypeAvailability::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => $doctor1->id, 'booking_type_id' => $btClinic->id],
            ['is_enabled' => true]
        );
        \App\Models\DoctorBookingTypeAvailability::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => $doctor1->id, 'booking_type_id' => $btOnline->id],
            ['is_enabled' => true]
        );

        FeeMaster::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => $doctor1->id, 'booking_type_id' => $btClinic->id],
            ['code' => 'CONSULTATION_PHYSICAL', 'name' => 'Clinic Consultancy', 'amount' => 500.00, 'is_active' => true]
        );
        FeeMaster::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => $doctor1->id, 'booking_type_id' => $btOnline->id],
            ['code' => 'CONSULTATION_ONLINE', 'name' => 'Online Video Consultancy', 'amount' => 400.00, 'is_active' => true]
        );

        // Doctor #2 (Neha Kapoor): Clinic Consultancy + Visiting Consultancy
        if ($doctor2) {
            DoctorConsultationType::updateOrCreate(
                ['clinic_id' => $clinic->id, 'doctor_id' => $doctor2->id, 'consultation_mode' => 'CLINIC_PHYSICAL'],
                ['is_enabled' => true]
            );
            DoctorConsultationType::updateOrCreate(
                ['clinic_id' => $clinic->id, 'doctor_id' => $doctor2->id, 'consultation_mode' => 'ONLINE'],
                ['is_enabled' => false]
            );
            DoctorConsultationType::updateOrCreate(
                ['clinic_id' => $clinic->id, 'doctor_id' => $doctor2->id, 'consultation_mode' => 'VISITING_CONSULTANCY'],
                ['is_enabled' => true]
            );

            \App\Models\DoctorBookingTypeAvailability::updateOrCreate(
                ['clinic_id' => $clinic->id, 'doctor_id' => $doctor2->id, 'booking_type_id' => $btClinic->id],
                ['is_enabled' => true]
            );
            \App\Models\DoctorBookingTypeAvailability::updateOrCreate(
                ['clinic_id' => $clinic->id, 'doctor_id' => $doctor2->id, 'booking_type_id' => $btVisiting->id],
                ['is_enabled' => true]
            );

            FeeMaster::updateOrCreate(
                ['clinic_id' => $clinic->id, 'doctor_id' => $doctor2->id, 'booking_type_id' => $btClinic->id],
                ['code' => 'CONSULTATION_PHYSICAL', 'name' => 'Clinic Consultancy', 'amount' => 500.00, 'is_active' => true]
            );
            FeeMaster::updateOrCreate(
                ['clinic_id' => $clinic->id, 'doctor_id' => $doctor2->id, 'booking_type_id' => $btVisiting->id],
                ['code' => 'CONSULTATION_VISITING', 'name' => 'Visiting Consultancy', 'amount' => 750.00, 'is_active' => true]
            );
        }

        // Clinic-wide baseline fees (without doctor_id) to prevent fallback to platform/other clinics
        FeeMaster::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => null, 'code' => 'CONSULTATION_PHYSICAL'],
            ['name' => 'Clinic Consultancy', 'amount' => 500.00, 'is_active' => true]
        );
        FeeMaster::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => null, 'code' => 'CONSULTATION_ONLINE'],
            ['name' => 'Online Video Consultancy', 'amount' => 400.00, 'is_active' => true]
        );
        FeeMaster::updateOrCreate(
            ['clinic_id' => $clinic->id, 'doctor_id' => null, 'code' => 'CONSULTATION_VISITING'],
            ['name' => 'Visiting Consultancy', 'amount' => 750.00, 'is_active' => true]
        );
    }

    /**
     * Seed realistic deterministic fictional clinical dataset
     */
    protected static function seedTransactionalData(Clinic $clinic, Doctor $doctor1, ?Doctor $doctor2 = null): void
    {
        $clinicId = $clinic->id;
        $visitIds = Visit::where('clinic_id', $clinicId)->pluck('id');
        Vital::whereIn('visit_id', $visitIds)->delete();
        Appointment::where('clinic_id', $clinicId)->delete();
        ConsultationBill::where('clinic_id', $clinicId)->delete();
        DiagnosticBill::where('clinic_id', $clinicId)->delete();
        Payment::where('clinic_id', $clinicId)->delete();
        Receipt::where('clinic_id', $clinicId)->delete();
        Expense::where('clinic_id', $clinicId)->delete();
        Journal::where('clinic_id', $clinicId)->delete();
        Visit::where('clinic_id', $clinicId)->delete();
        Patient::where('clinic_id', $clinicId)->delete();
        TokenSequence::where('clinic_id', $clinicId)->delete();
        CashSession::where('clinic_id', $clinicId)->delete();

        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $tomorrow = Carbon::tomorrow('Asia/Kolkata')->toDateString();

        // 1. Open an active Cash Session for today
        $adminUser = $clinic->users()->where('role', 'ADMIN')->first();
        $cashSession = CashSession::firstOrCreate(
            ['clinic_id' => $clinic->id, 'session_date' => $today, 'session_type' => 'MORNING'],
            [
                'status' => 'OPEN',
                'opened_by' => $adminUser?->id ?? 1,
                'opened_at' => now()->startOfDay()->addHours(8),
                'opening_physical_cash' => 2000.00,
                'expected_closing_cash' => 2000.00,
            ]
        );

        // 2. Fictional Patients
        $fictionalPatients = [
            ['name' => 'Rahul Verma', 'age' => 34, 'sex' => 'MALE', 'mobile' => '9870001001', 'address' => 'Assi Ghat', 'city' => 'Varanasi'],
            ['name' => 'Sunita Devi', 'age' => 48, 'sex' => 'FEMALE', 'mobile' => '9870001002', 'address' => 'Lanka', 'city' => 'Varanasi'],
            ['name' => 'Amit Patel', 'age' => 26, 'sex' => 'MALE', 'mobile' => '9870001003', 'address' => 'Sigra', 'city' => 'Varanasi'],
            ['name' => 'Priya Singh', 'age' => 31, 'sex' => 'FEMALE', 'mobile' => '9870001004', 'address' => 'Bhelupur', 'city' => 'Varanasi'],
            ['name' => 'Mohammed Ali', 'age' => 52, 'sex' => 'MALE', 'mobile' => '9870001005', 'address' => 'Godowlia', 'city' => 'Varanasi'],
            ['name' => 'Vikram Malhotra', 'age' => 41, 'sex' => 'MALE', 'mobile' => '9870001006', 'address' => 'Cantonment', 'city' => 'Varanasi'],
            ['name' => 'Ananya Sen', 'age' => 22, 'sex' => 'FEMALE', 'mobile' => '9870001007', 'address' => 'Mahmoorganj', 'city' => 'Varanasi'],
            ['name' => 'Ramesh Yadav', 'age' => 60, 'sex' => 'MALE', 'mobile' => '9870001008', 'address' => 'Pandeypur', 'city' => 'Varanasi'],
        ];

        $createdPatients = [];
        foreach ($fictionalPatients as $index => $pData) {
            $patientCode = 'DEMO-P' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $patient = Patient::updateOrCreate(
                ['clinic_id' => $clinic->id, 'patient_id' => $patientCode],
                [
                    'name' => $pData['name'],
                    'age' => $pData['age'],
                    'sex' => $pData['sex'],
                    'mobile' => $pData['mobile'],
                    'address' => $pData['address'],
                    'city' => $pData['city'],
                    'profile_status' => 'COMPLETE',
                ]
            );
            $createdPatients[] = $patient;
        }

        // 3. Appointments & Tokens
        $tokenSeqToday = TokenSequence::firstOrCreate(
            ['clinic_id' => $clinic->id, 'token_date' => $today, 'session_type' => 'MORNING'],
            ['current_token' => 5]
        );

        $statuses = ['COMPLETED', 'ARRIVED', 'BOOKED', 'BOOKED', 'BOOKED'];
        for ($i = 0; $i < 5; $i++) {
            $tokenNum = $i + 1;
            $formattedToken = str_pad((string) $tokenNum, 3, '0', STR_PAD_LEFT);
            $patient = $createdPatients[$i];

            // Alternate doctors between doctor1 and doctor2
            $assignedDoctor = ($i % 2 === 0 || !$doctor2) ? $doctor1 : $doctor2;

            $appointment = Appointment::create([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $assignedDoctor->id,
                'appointment_date' => $today,
                'session_type' => 'MORNING',
                'booking_source' => $i === 0 ? 'ONLINE' : 'CLINIC',
                'consultation_mode' => 'CLINIC_PHYSICAL',
                'token_number' => $tokenNum,
                'formatted_token' => $formattedToken,
                'status' => $statuses[$i],
                'secure_token' => Str::random(32),
            ]);

            // For completed appointment: create visit, vitals, bill, payment
            if ($statuses[$i] === 'COMPLETED') {
                $visit = Visit::create([
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patient->id,
                    'doctor_id' => $assignedDoctor->id,
                    'appointment_id' => $appointment->id,
                    'cash_session_id' => $cashSession->id,
                    'visit_date' => $today,
                    'session_type' => 'MORNING',
                    'consultation_mode' => 'CLINIC_PHYSICAL',
                    'visit_type' => 'FIRST_VISIT',
                    'status' => 'COMPLETED',
                    'arrived_at' => now()->subHours(2),
                    'completed_at' => now()->subHours(1),
                ]);

                Vital::create([
                    'visit_id' => $visit->id,
                    'weight' => 68.5,
                    'bp_systolic' => 120,
                    'bp_diastolic' => 80,
                    'pulse' => 74,
                    'temperature' => 98.4,
                    'spo2' => 99,
                ]);

                $bill = ConsultationBill::create([
                    'clinic_id' => $clinic->id,
                    'bill_number' => 'DEMO-CB-0001',
                    'appointment_id' => $appointment->id,
                    'visit_id' => $visit->id,
                    'patient_id' => $patient->id,
                    'doctor_id' => $assignedDoctor->id,
                    'bill_date' => $today,
                    'gross_amount' => 500.00,
                    'discount_amount' => 0.00,
                    'waiver_amount' => 0.00,
                    'net_amount' => 500.00,
                    'consultation_mode' => 'CLINIC_PHYSICAL',
                    'status' => 'PAID',
                ]);

                $payment = Payment::create([
                    'clinic_id' => $clinic->id,
                    'payment_number' => 'DEMO-PAY-0001',
                    'cash_session_id' => $cashSession->id,
                    'consultation_bill_id' => $bill->id,
                    'amount' => 500.00,
                    'payment_method' => 'CASH',
                    'status' => 'SETTLED',
                    'settled_at' => now(),
                ]);

                Receipt::create([
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patient->id,
                    'receipt_number' => 'DEMO-REC-0001',
                    'receipt_type' => 'CONSULTATION',
                    'payment_id' => $payment->id,
                    'amount' => 500.00,
                    'issued_at' => now(),
                ]);
            }
        }

        // Tomorrow appointment with doctor 2
        $tomorrowDoctor = $doctor2 ?? $doctor1;
        Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $createdPatients[5]->id,
            'doctor_id' => $tomorrowDoctor->id,
            'appointment_date' => $tomorrow,
            'session_type' => 'MORNING',
            'booking_source' => 'ONLINE',
            'consultation_mode' => 'CLINIC_PHYSICAL',
            'token_number' => 1,
            'formatted_token' => '001',
            'status' => 'BOOKED',
            'secure_token' => Str::random(32),
        ]);
    }
}
