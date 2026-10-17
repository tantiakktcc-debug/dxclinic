<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicDoctorSeatAddon;
use App\Models\Doctor;
use App\Models\License;
use App\Models\Patient;
use App\Models\Visit;
use Carbon\Carbon;
use App\Services\ClinicClock;
use Illuminate\Support\Facades\DB;

$action = $argv[1] ?? 'status';

header('Content-Type: application/json');

switch ($action) {
    case 'seed_live_queue_appointment':
        // Clinic 6 (democlinic)
        $clinic = Clinic::where('public_slug', 'democlinic')->first();
        if (!$clinic) {
            echo json_encode(['success' => false, 'error' => 'democlinic not found']);
            exit(1);
        }

        $doctor1 = Doctor::where('clinic_id', $clinic->id)->where('name', 'like', '%Amit Verma%')->first();
        $doctor2 = Doctor::where('clinic_id', $clinic->id)->where('name', 'like', '%Neha Kapoor%')->first();

        $patient = Patient::where('clinic_id', $clinic->id)->first();
        if (!$patient) {
            $patient = Patient::create([
                'clinic_id' => $clinic->id,
                'patient_id' => 'DEMO-P1',
                'name' => 'Ramesh Test Patient',
                'mobile' => '9888877777',
                'sex' => 'MALE',
                'age' => 40,
                'city' => 'Demo City',
                'address' => 'Demo Street',
            ]);
        }

        // Clean up any test appointment
        $oldApp = Appointment::where('clinic_id', $clinic->id)
            ->where('token_number', 888)
            ->first();
        if ($oldApp) {
            \App\Models\Payment::whereHas('consultationBill', fn($q) => $q->where('appointment_id', $oldApp->id))->delete();
            \App\Models\ConsultationBill::where('appointment_id', $oldApp->id)->delete();
            Visit::where('appointment_id', $oldApp->id)->delete();
            $oldApp->delete();
        }

        // Create ARRIVED appointment for Doctor 1 with PAID fee
        $token = 888;
        $session = ClinicClock::today()->isSunday() ? 'SUNDAY_SINGLE' : 'MORNING';
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor1?->id,
            'appointment_date' => ClinicClock::today()->toDateString(),
            'session_type' => $session,
            'session' => $session,
            'token_number' => $token,
            'formatted_token' => sprintf('%03d', $token),
            'secure_token' => bin2hex(random_bytes(16)),
            'status' => 'ARRIVED',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        $visit = Visit::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor1?->id,
            'appointment_id' => $appointment->id,
            'visit_number' => 'V-' . uniqid(),
            'visit_date' => $appointment->appointment_date,
            'session_type' => $appointment->session_type,
            'status' => 'WAITING',
        ]);

        $bill = \App\Models\ConsultationBill::create([
            'clinic_id' => $clinic->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor1?->id,
            'appointment_id' => $appointment->id,
            'bill_number' => 'BILL-' . uniqid(),
            'bill_date' => now()->toDateString(),
            'total_amount' => 500.00,
            'discount_amount' => 0.00,
            'net_amount' => 500.00,
            'status' => 'PAID',
        ]);

        \App\Models\Payment::create([
            'clinic_id' => $clinic->id,
            'payment_number' => 'PAY-' . uniqid(),
            'consultation_bill_id' => $bill->id,
            'amount' => 500.00,
            'payment_method' => 'CASH',
            'status' => 'SETTLED',
            'settled_at' => now(),
        ]);

        echo json_encode([
            'success' => true,
            'appointment_id' => $appointment->id,
            'token' => $appointment->formatted_token,
            'doctor_name' => $doctor1?->name,
            'status' => $appointment->status,
        ]);
        break;

    case 'check_appointment_visit':
        $appointmentId = (int) ($argv[2] ?? 0);
        $appointment = Appointment::find($appointmentId);
        $visit = Visit::where('appointment_id', $appointmentId)->first();

        echo json_encode([
            'success' => true,
            'appointment' => $appointment ? [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'token' => $appointment->formatted_token,
                'doctor_id' => $appointment->doctor_id,
            ] : null,
            'visit' => $visit ? [
                'id' => $visit->id,
                'status' => $visit->status,
                'started_at' => $visit->started_at?->toDateTimeString(),
                'doctor_id' => $visit->doctor_id,
                'clinic_id' => $visit->clinic_id,
            ] : null,
        ]);
        break;

    case 'get_clinic_seats':
        $clinic = Clinic::where('public_slug', 'democlinic')->first();
        echo json_encode([
            'success' => true,
            'capacity' => $clinic?->totalDoctorCapacity(),
            'active_doctors' => $clinic?->activeDoctorCount(),
            'available_seats' => $clinic?->availableDoctorSeats(),
            'can_add_doctor' => $clinic?->canAddDoctor(),
        ]);
        break;

    case 'seed_fee_gate_appointment':
        $slug = $argv[2] ?? 'democlinic';
        $clinic = Clinic::where('public_slug', $slug)->first();
        if (!$clinic) {
            echo json_encode(['success' => false, 'error' => "Clinic {$slug} not found"]);
            exit(1);
        }

        $doctor = Doctor::where('clinic_id', $clinic->id)->first();
        $patient = Patient::where('clinic_id', $clinic->id)->first();
        if (!$patient) {
            $patient = Patient::create([
                'clinic_id' => $clinic->id,
                'patient_id' => 'FG-P1',
                'name' => 'Fee Gate Patient',
                'mobile' => '9888899999',
                'sex' => 'MALE',
                'age' => 35,
                'city' => 'Varanasi',
                'address' => 'Assi Ghat',
            ]);
        }

        // Clean up previous token 777
        $oldApp = Appointment::where('clinic_id', $clinic->id)->where('token_number', 777)->first();
        if ($oldApp) {
            \App\Models\Payment::whereHas('consultationBill', fn($q) => $q->where('appointment_id', $oldApp->id))->delete();
            \App\Models\ConsultationBill::where('appointment_id', $oldApp->id)->delete();
            Visit::where('appointment_id', $oldApp->id)->delete();
            $oldApp->delete();
        }

        $token = 777;
        $session = ClinicClock::today()->isSunday() ? 'SUNDAY_SINGLE' : 'MORNING';
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor?->id,
            'appointment_date' => ClinicClock::today()->toDateString(),
            'session_type' => $session,
            'session' => $session,
            'token_number' => $token,
            'formatted_token' => sprintf('%03d', $token),
            'secure_token' => bin2hex(random_bytes(16)),
            'status' => 'ARRIVED',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        echo json_encode([
            'success' => true,
            'appointment_id' => $appointment->id,
            'token' => $appointment->formatted_token,
            'clinic_slug' => $clinic->public_slug,
            'doctor_name' => $doctor?->name,
            'fee_status' => 'UNPAID',
        ]);
        break;

    case 'pay_appointment_fee':
        $appointmentId = (int) ($argv[2] ?? 0);
        $appointment = Appointment::find($appointmentId);
        if (!$appointment) {
            echo json_encode(['success' => false, 'error' => "Appointment {$appointmentId} not found"]);
            exit(1);
        }

        $visit = Visit::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'clinic_id' => $appointment->clinic_id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
                'visit_number' => 'V-' . uniqid(),
                'visit_date' => $appointment->appointment_date,
                'session_type' => $appointment->session_type ?? 'MORNING',
                'status' => 'WAITING',
            ]
        );

        $bill = \App\Models\ConsultationBill::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'clinic_id' => $appointment->clinic_id,
                'visit_id' => $visit->id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
                'bill_number' => 'BILL-' . uniqid(),
                'bill_date' => now()->toDateString(),
                'total_amount' => 500.00,
                'discount_amount' => 0.00,
                'net_amount' => 500.00,
                'status' => 'PAID',
            ]
        );

        \App\Models\Payment::firstOrCreate(
            ['consultation_bill_id' => $bill->id],
            [
                'clinic_id' => $appointment->clinic_id,
                'payment_number' => 'PAY-' . uniqid(),
                'amount' => 500.00,
                'payment_method' => 'CASH',
                'status' => 'SETTLED',
                'settled_at' => now(),
            ]
        );

        echo json_encode([
            'success' => true,
            'appointment_id' => $appointment->id,
            'fee_status' => 'PAID',
            'bill_id' => $bill->id,
        ]);
        break;

    case 'setup_seat_limit_clinic':
        // Ensure a clinic has reached its limit
        $clinic = Clinic::where('clinic_code', 'NX0004')->first();
        if (!$clinic) {
            $clinic = Clinic::create([
                'clinic_code' => 'NX0004',
                'public_slug' => 'new-life',
                'name' => 'New Life Clinic',
                'phone' => '9876543999',
                'email' => 'newlife@dxclinic.local',
                'address' => 'Sigra',
                'city' => 'Varanasi',
                'is_active' => true,
                'activation_status' => 'ACTIVATED',
                'activated_at' => now(),
                'onboarding_step' => 'COMPLETED',
            ]);
        } else {
            $clinic->update([
                'activation_status' => 'ACTIVATED',
                'activated_at' => now(),
                'onboarding_step' => 'COMPLETED',
                'is_active' => true,
            ]);
        }

        // Clean up pending requests
        \App\Models\LicenseRequest::where('clinic_id', $clinic->id)
            ->where('request_type', 'DOCTOR_SEAT_ADDON')
            ->delete();

        // Ensure user exists
        $user = \App\Models\User::where('email', 'newlifeadmin@dxclinic.local')->first();
        if (!$user) {
            $user = \App\Models\User::create([
                'name' => 'New Life Admin',
                'email' => 'newlifeadmin@dxclinic.local',
                'password' => bcrypt('Password123!'),
                'clinic_id' => $clinic->id,
                'role' => 'ADMIN',
            ]);
        }

        // Ensure license has capacity 1 and 1 active doctor
        $license = $clinic->activeLicense ?? $clinic->latestLicense;
        if (!$license) {
            $plan = \App\Models\SubscriptionPlan::first() ?? \App\Models\SubscriptionPlan::create([
                'name' => 'Single Doctor',
                'code' => 'SINGLE',
                'duration_months' => 12,
                'price' => 4999,
                'price_per_year' => 4999,
                'max_doctors' => 1,
                'additional_doctor_seat_price' => 2999,
                'features' => ['all'],
                'is_active' => true,
            ]);
            $license = License::create([
                'clinic_id' => $clinic->id,
                'plan_id' => $plan->id,
                'license_key_hash' => hash('sha256', 'KEY-NL-1'),
                'license_key_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString('KEY-NL-1'),
                'license_key_prefix' => 'NXCL-NL1',
                'status' => 'ACTIVE',
                'start_date' => now()->subMonth()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'max_doctors' => 1,
                'included_doctor_seats' => 1,
                'additional_doctor_seats' => 0,
            ]);
        } else {
            $license->update([
                'status' => 'ACTIVE',
                'included_doctor_seats' => 1,
                'additional_doctor_seats' => 0,
            ]);
            \App\Models\ClinicDoctorSeatAddon::where('clinic_id', $clinic->id)->delete();
        }

        if ($clinic->doctors()->count() === 0) {
            Doctor::create([
                'clinic_id' => $clinic->id,
                'name' => 'Dr. Narendra Pathak',
                'qualification' => 'MBBS',
                'registration_number' => 'NL-DOC-1',
                'is_active' => true,
            ]);
        }

        echo json_encode([
            'success' => true,
            'clinic_id' => $clinic->id,
            'clinic_code' => $clinic->clinic_code,
            'public_slug' => $clinic->public_slug,
            'email' => 'newlifeadmin@dxclinic.local',
            'password' => 'Password123!',
            'capacity' => $clinic->fresh()->totalDoctorCapacity(),
            'used' => $clinic->activeDoctorCount(),
            'available' => $clinic->availableDoctorSeats(),
        ]);
        break;

    case 'fulfill_latest_seat_request':
        $req = \App\Models\LicenseRequest::where('request_type', 'DOCTOR_SEAT_ADDON')
            ->whereIn('status', ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING'])
            ->latest()
            ->first();

        if (!$req) {
            echo json_encode(['success' => false, 'error' => 'No pending DOCTOR_SEAT_ADDON request found']);
            exit(1);
        }

        $superadmin = \App\Models\DeveloperUser::first();
        $res = \App\Services\LicenseRequestService::fulfillDoctorSeatAddonRequest(
            $req->id,
            $superadmin?->id ?? 1,
            ['payment_method' => 'BANK_TRANSFER', 'payment_reference' => 'TXN-DEV-ADDON-01']
        );

        $clinic = Clinic::find($req->clinic_id);

        echo json_encode([
            'success' => true,
            'request_number' => $req->request_number,
            'new_status' => $req->fresh()->status,
            'new_capacity' => $clinic->fresh()->totalDoctorCapacity(),
            'available_seats' => $clinic->availableDoctorSeats(),
        ]);
        break;

    case 'seed_care_health_appointment':
        $clinic = Clinic::where('clinic_code', 'NX0003')->first();
        if (!$clinic) {
            echo json_encode(['success' => false, 'error' => 'Care Health NX0003 not found']);
            exit(1);
        }

        $doctor = Doctor::where('clinic_id', $clinic->id)->first() ?? Doctor::create([
            'clinic_id' => $clinic->id,
            'name' => 'Binod Das',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'CARE-DOC-1',
            'is_active' => true,
        ]);

        $patient = Patient::firstOrCreate(
            ['clinic_id' => $clinic->id, 'mobile' => '9111122222'],
            ['patient_id' => 'CARE-P' . rand(100, 999), 'name' => 'Care Patient ' . rand(10, 99), 'sex' => 'FEMALE', 'age' => 30, 'city' => 'Kolkata', 'address' => 'Park Street, Kolkata']
        );

        // Delete any existing token 555 for clean test
        Appointment::where('clinic_id', $clinic->id)->where('token_number', 555)->delete();

        $token = 555;
        $session = ClinicClock::today()->isSunday() ? 'SUNDAY_SINGLE' : 'MORNING';
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => ClinicClock::today()->toDateString(),
            'session_type' => $session,
            'session' => $session,
            'token_number' => $token,
            'formatted_token' => sprintf('%03d', $token),
            'secure_token' => bin2hex(random_bytes(16)),
            'status' => 'ARRIVED',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        echo json_encode([
            'success' => true,
            'appointment_id' => $appointment->id,
            'clinic_id' => $clinic->id,
            'clinic_name' => $clinic->name,
            'public_slug' => $clinic->public_slug,
            'token' => $appointment->formatted_token,
            'patient_name' => $patient->name,
        ]);
        break;

    case 'seed_sharma_clinic_appointment':
        $clinic = Clinic::where('clinic_code', 'NX0001')->first();
        if (!$clinic) {
            echo json_encode(['success' => false, 'error' => 'Sharma Clinic NX0001 not found']);
            exit(1);
        }

        $doctor = Doctor::where('clinic_id', $clinic->id)->first() ?? Doctor::create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr. S.K. Sharma',
            'qualification' => 'MBBS, MS',
            'registration_number' => 'SH-DOC-1',
            'is_active' => true,
        ]);

        $patient = Patient::where('clinic_id', $clinic->id)->first() ?? Patient::create([
            'clinic_id' => $clinic->id,
            'patient_id' => 'SH-P' . rand(100, 999),
            'name' => 'Sharma Patient ' . rand(10, 99),
            'sex' => 'MALE',
            'age' => 45,
            'city' => 'Varanasi',
            'address' => 'Lanka, Varanasi',
            'mobile' => '9876543111',
        ]);

        // Token 555 in Sharma Clinic to verify identical tokens in different clinics are allowed
        Appointment::where('clinic_id', $clinic->id)->where('token_number', 555)->delete();

        $token = 555;
        $session = ClinicClock::today()->isSunday() ? 'SUNDAY_SINGLE' : 'MORNING';
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => ClinicClock::today()->toDateString(),
            'session_type' => $session,
            'session' => $session,
            'token_number' => $token,
            'formatted_token' => sprintf('%03d', $token),
            'secure_token' => bin2hex(random_bytes(16)),
            'status' => 'ARRIVED',
            'booking_source' => 'CLINIC',
            'consultation_mode' => 'CLINIC_PHYSICAL',
        ]);

        echo json_encode([
            'success' => true,
            'appointment_id' => $appointment->id,
            'clinic_id' => $clinic->id,
            'clinic_name' => $clinic->name,
            'public_slug' => $clinic->public_slug,
            'token' => $appointment->formatted_token,
            'patient_name' => $patient->name,
        ]);
        break;

    case 'setup_license_history_data':
        $plan = \App\Models\SubscriptionPlan::firstOrCreate(
            ['code' => '5-year-plan'],
            [
                'name' => '5-Year Plan',
                'duration_months' => 60,
                'price' => 49999.00,
                'included_doctor_seats' => 1,
                'additional_doctor_seat_price' => 2999.00,
                'is_active' => true,
            ]
        );

        $clinic = Clinic::where('clinic_code', 'NX0004')->first();
        if (!$clinic) {
            $clinic = Clinic::create([
                'clinic_code' => 'NX0004',
                'public_slug' => 'newlife',
                'name' => 'New Life Clinic',
                'doctor_name' => 'Dr. Narendra Pathak',
                'qualification' => 'MBBS, MD',
                'experience' => '15 years',
                'address' => 'Abc Tower,, Lanka,',
                'city' => 'Varanasi',
                'pincode' => '221005',
                'phone' => '4444444444',
                'email' => 'dr.narendra@example.test',
                'is_active' => true,
                'status' => 'ACTIVE',
            ]);
        }

        $rawKey = 'NXCL-24LG-8DDW-WJSV-PYQC';
        $license = License::where('clinic_id', $clinic->id)->first();
        if (!$license) {
            $license = License::create([
                'clinic_id' => $clinic->id,
                'plan_id' => $plan->id,
                'license_key_hash' => hash('sha256', strtoupper($rawKey)),
                'license_key_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString($rawKey),
                'license_key_prefix' => 'NXCL',
                'start_date' => Carbon::parse('2026-10-04'),
                'end_date' => Carbon::parse('2031-11-04'),
                'amount' => 49999.00,
                'net_amount' => 49999.00,
                'status' => 'ACTIVE',
            ]);
        }

        // 1. Initial License (LR-000002) - FULFILLED
        $lr2 = \App\Models\LicenseRequest::where('request_number', 'LR-000002')->first();
        if (!$lr2) {
            $lr2 = new \App\Models\LicenseRequest();
            $lr2->request_number = 'LR-000002';
        }
        $lr2->fill([
            'request_type' => 'INITIAL',
            'status' => 'FULFILLED',
            'clinic_name' => 'New Life Clinic',
            'clinic_code' => 'NX0004',
            'owner_name' => 'Narendra Pathak',
            'mobile' => '4444444444',
            'email' => 'dr.narendra@example.test',
            'address' => 'Abc Tower,, Lanka,',
            'city' => 'Varanasi',
            'pincode' => '221005',
            'plan_id' => $plan->id,
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
            'payment_confirmed_at' => Carbon::parse('2026-10-04 10:00:00'),
            'fulfilled_clinic_id' => $clinic->id,
            'fulfilled_license_id' => $license->id,
            'fulfilled_at' => Carbon::parse('2026-10-04 10:05:00'),
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
                    'name' => 'Dr. Narendra Pathak',
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
                'address' => 'Abc Tower,, Lanka,',
                'plan_name' => '5-Year Plan',
                'plan_duration_months' => 60,
                'trial_months' => 1,
                'included_doctor_count' => 1,
                'additional_doctor_count' => 0,
                'base_amount' => 49999.00,
                'net_amount' => 49999.00,
                'license_key_prefix' => 'NXCL',
                'license_key' => 'NXCL-24LG-8DDW-WJSV-PYQC',
                'start_date' => '2026-10-04',
                'end_date' => '2031-11-04',
            ],
        ]);
        $lr2->save();

        \App\Models\LicenseRequestEvent::updateOrCreate(
            ['license_request_id' => $lr2->id, 'event_type' => 'SUBMITTED'],
            [
                'performed_by_type' => 'public',
                'old_status' => null,
                'new_status' => 'SUBMITTED',
                'notes' => 'Initial license request submitted by Narendra Pathak for New Life Clinic',
                'created_at' => Carbon::parse('2026-10-04 09:00:00'),
            ]
        );

        \App\Models\LicenseRequestEvent::updateOrCreate(
            ['license_request_id' => $lr2->id, 'event_type' => 'FULFILLED'],
            [
                'performed_by_type' => 'developer_user',
                'old_status' => 'SUBMITTED',
                'new_status' => 'FULFILLED',
                'notes' => 'License generated: Clinic NX0004, Key NXCL-24LG-8DDW-WJSV-PYQC',
                'created_at' => Carbon::parse('2026-10-04 10:05:00'),
            ]
        );

        // 2. Renewal Request (LR-000004) - FULFILLED
        $lr4 = \App\Models\LicenseRequest::where('request_number', 'LR-000004')->first();
        if (!$lr4) {
            $lr4 = new \App\Models\LicenseRequest();
            $lr4->request_number = 'LR-000004';
        }
        $lr4->fill([
            'request_type' => 'RENEWAL',
            'status' => 'FULFILLED',
            'clinic_name' => 'Care Health Clinic',
            'clinic_code' => 'NX0002',
            'owner_name' => 'Dr. Rajesh Care',
            'mobile' => '9888812345',
            'email' => 'care@dxclinic.local',
            'city' => 'Varanasi',
            'pincode' => '221002',
            'address' => 'Care Health Campus',
            'plan_name_snapshot' => 'Annual Plan',
            'plan_duration_months' => 12,
            'base_amount' => 14999.00,
            'net_amount' => 14999.00,
            'payment_method' => 'UPI',
            'payment_reference' => 'upi-ref-renewal-444',
            'payment_date' => '2026-10-04',
            'fulfilled_clinic_id' => Clinic::where('clinic_code', 'NX0002')->value('id') ?? $clinic->id,
            'fulfilled_at' => Carbon::parse('2026-10-04 11:00:00'),
        ]);
        $lr4->save();

        // 3. Doctor Seat Addon Request (LR-000003) - PENDING
        $lr3 = \App\Models\LicenseRequest::where('request_number', 'LR-000003')->first();
        if (!$lr3) {
            $lr3 = new \App\Models\LicenseRequest();
            $lr3->request_number = 'LR-000003';
        }
        $lr3->fill([
            'request_type' => 'DOCTOR_SEAT_ADDON',
            'status' => 'PENDING',
            'clinic_name' => 'Demo Clinic',
            'clinic_code' => 'NX0003',
            'owner_name' => 'Demo Doctor',
            'mobile' => '9876543210',
            'email' => 'demo@dxclinic.local',
            'additional_doctor_count' => 1,
            'additional_doctor_amount' => 2999.00,
            'net_amount' => 2999.00,
            'admin_notes' => 'Existing Capacity: 2 | Requested: +1 | New Capacity: 3',
        ]);
        $lr3->save();

        // LR-000013: Fulfilled Doctor Seat Addon
        $lr13 = \App\Models\LicenseRequest::where('request_number', 'LR-000013')->first();
        if (!$lr13) {
            $lr13 = new \App\Models\LicenseRequest();
            $lr13->request_number = 'LR-000013';
        }
        $lr13->fill([
            'request_type' => 'DOCTOR_SEAT_ADDON',
            'status' => 'FULFILLED',
            'clinic_name' => 'Demo Clinic',
            'clinic_code' => 'NX0003',
            'owner_name' => 'Demo Doctor',
            'mobile' => '9876543210',
            'email' => 'demo@dxclinic.local',
            'additional_doctor_count' => 1,
            'additional_doctor_amount' => 2999.00,
            'net_amount' => 2999.00,
            'admin_notes' => 'Existing Capacity: 2 | Requested: +1 | Resulting Capacity: 3',
            'fulfilled_at' => Carbon::parse('2026-10-04 12:00:00'),
        ]);
        $lr13->save();

        // 4. Rejected Request (LR-000010) - REJECTED
        $lr10 = \App\Models\LicenseRequest::where('request_number', 'LR-000010')->first();
        if (!$lr10) {
            $lr10 = new \App\Models\LicenseRequest();
            $lr10->request_number = 'LR-000010';
        }
        $lr10->fill([
            'request_type' => 'INITIAL',
            'status' => 'REJECTED',
            'clinic_name' => 'Rejected Medical Center',
            'owner_name' => 'Rejected Applicant',
            'mobile' => '9111111111',
            'email' => 'rejected@dxclinic.local',
            'city' => 'Kanpur',
            'pincode' => '208001',
            'rejection_reason' => 'Invalid medical registration certificate provided',
            'rejected_at' => Carbon::parse('2026-10-04 13:00:00'),
            'net_amount' => 49999.00,
        ]);
        $lr10->save();

        \App\Models\LicenseRequestEvent::firstOrCreate(
            ['license_request_id' => $lr10->id, 'event_type' => 'REJECTED'],
            [
                'performed_by_type' => 'developer_user',
                'old_status' => 'SUBMITTED',
                'new_status' => 'REJECTED',
                'notes' => 'Rejection Reason: Invalid medical registration certificate provided',
                'created_at' => Carbon::parse('2026-10-04 13:00:00'),
            ]
        );

        // 5. Cancelled Request (LR-000011) - CANCELLED
        $lr11 = \App\Models\LicenseRequest::where('request_number', 'LR-000011')->first();
        if (!$lr11) {
            $lr11 = new \App\Models\LicenseRequest();
            $lr11->request_number = 'LR-000011';
        }
        $lr11->fill([
            'request_type' => 'DOCTOR_SEAT_ADDON',
            'status' => 'CANCELLED',
            'clinic_name' => 'Cancelled Clinic',
            'owner_name' => 'Cancelled Doctor',
            'mobile' => '9222222222',
            'email' => 'cancelled@dxclinic.local',
            'cancellation_reason' => 'Operator submitted duplicate request by error',
            'cancelled_at' => Carbon::parse('2026-10-04 14:00:00'),
            'net_amount' => 2999.00,
        ]);
        $lr11->save();

        \App\Models\LicenseRequestEvent::firstOrCreate(
            ['license_request_id' => $lr11->id, 'event_type' => 'CANCELLED'],
            [
                'performed_by_type' => 'public',
                'old_status' => 'SUBMITTED',
                'new_status' => 'CANCELLED',
                'notes' => 'Cancellation Reason: Operator submitted duplicate request by error',
                'created_at' => Carbon::parse('2026-10-04 14:00:00'),
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => 'License history test records seeded and verified successfully',
            'raw_key' => $rawKey,
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => "Unknown action {$action}"]);
        break;
}
