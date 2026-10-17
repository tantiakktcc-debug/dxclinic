<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Visit;
use App\Services\DoctorSeenStatusResolver;
use App\Services\FeeStatusResolver;
use App\Services\QueueViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveQueueFeeAndDoctorSeenTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected Patient $patient;
    protected FeeStatusResolver $feeResolver;
    protected DoctorSeenStatusResolver $doctorSeenResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'sharmacare',
            'name' => 'Sharma Care Clinic',
            'phone' => '9876543210',
            'email' => 'clinic@sharma.com',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
            'is_active' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'UP-MC-12345',
            'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP100001',
            'name' => 'Raj Kumar',
            'sex' => 'MALE',
            'age' => 35,
            'mobile' => '9876543210',
            'city' => 'Varanasi',
            'address' => 'Bhelupur',
        ]);

        $this->feeResolver = new FeeStatusResolver();
        $this->doctorSeenResolver = new DoctorSeenStatusResolver();
    }

    public function test_fee_status_unpaid_when_no_bill(): void
    {
        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 1,
            'formatted_token' => '001',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'BOOKED',
        ]);

        $this->assertEquals('UNPAID', $this->feeResolver->resolve($appointment));
    }

    public function test_fee_status_paid_when_fully_settled(): void
    {
        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 1,
            'formatted_token' => '001',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_id' => $appointment->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'REGISTERED',
        ]);

        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'BILL-001',
            'appointment_id' => $appointment->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => now()->toDateString(),
            'gross_amount' => 500,
            'net_amount' => 500,
            'status' => 'PAID',
        ]);

        Payment::create([
            'clinic_id' => $this->clinic->id,
            'payment_number' => 'PAY-001',
            'consultation_bill_id' => $bill->id,
            'payment_method' => 'CASH',
            'amount' => 500,
            'status' => 'SETTLED',
        ]);

        $this->assertEquals('PAID', $this->feeResolver->resolve($appointment->fresh(['consultationBill.payments', 'visit.consultationBill.payments'])));
    }

    public function test_fee_status_partial_when_underpaid_or_partially_refunded(): void
    {
        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 1,
            'formatted_token' => '001',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_id' => $appointment->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'REGISTERED',
        ]);

        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'BILL-002',
            'appointment_id' => $appointment->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => now()->toDateString(),
            'gross_amount' => 500,
            'net_amount' => 500,
            'status' => 'PENDING',
        ]);

        Payment::create([
            'clinic_id' => $this->clinic->id,
            'payment_number' => 'PAY-002',
            'consultation_bill_id' => $bill->id,
            'payment_method' => 'CASH',
            'amount' => 200,
            'status' => 'SETTLED',
        ]);

        $this->assertEquals('PARTIAL', $this->feeResolver->resolve($appointment->fresh(['consultationBill.payments', 'visit.consultationBill.payments'])));
    }

    public function test_fee_status_refunded_when_refund_processed(): void
    {
        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 1,
            'formatted_token' => '001',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_id' => $appointment->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'REGISTERED',
        ]);

        $bill = ConsultationBill::create([
            'clinic_id' => $this->clinic->id,
            'bill_number' => 'BILL-003',
            'appointment_id' => $appointment->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'bill_date' => now()->toDateString(),
            'gross_amount' => 500,
            'net_amount' => 500,
            'status' => 'REFUNDED',
        ]);

        $payment = Payment::create([
            'clinic_id' => $this->clinic->id,
            'payment_number' => 'PAY-003',
            'consultation_bill_id' => $bill->id,
            'payment_method' => 'CASH',
            'amount' => 500,
            'status' => 'SETTLED',
        ]);

        Refund::create([
            'clinic_id' => $this->clinic->id,
            'refund_number' => 'REF-001',
            'original_payment_id' => $payment->id,
            'consultation_bill_id' => $bill->id,
            'patient_id' => $this->patient->id,
            'refund_type' => 'FULL',
            'refund_amount' => 500,
            'refund_method' => 'CASH',
            'reason' => 'Patient requested refund',
            'status' => 'PROCESSED',
        ]);

        $this->assertEquals('REFUNDED', $this->feeResolver->resolve($appointment->fresh(['consultationBill.refunds', 'visit.consultationBill.refunds'])));
    }

    public function test_doctor_seen_status_not_seen_when_waiting(): void
    {
        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 1,
            'formatted_token' => '001',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        Visit::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_id' => $appointment->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'REGISTERED',
        ]);

        $this->assertEquals('NOT SEEN', $this->doctorSeenResolver->resolve($appointment->fresh(['visit'])));
    }

    public function test_doctor_seen_status_seen_when_in_consultation_or_completed(): void
    {
        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'CLINIC',
            'token_number' => 1,
            'formatted_token' => '001',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'ARRIVED',
        ]);

        $visit = Visit::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_id' => $appointment->id,
            'visit_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'status' => 'IN_PROGRESS',
        ]);

        $this->assertEquals('SEEN', $this->doctorSeenResolver->resolve($appointment->fresh(['visit'])));

        // When completed
        $visit->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        $this->assertEquals('SEEN', $this->doctorSeenResolver->resolve($appointment->fresh(['visit'])));
    }

    public function test_queue_view_service_returns_fee_and_doctor_seen_in_items(): void
    {
        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->toDateString(),
            'session_type' => 'MORNING',
            'booking_source' => 'WHATSAPP',
            'token_number' => 2,
            'formatted_token' => '002',
            'secure_token' => \Illuminate\Support\Str::random(32),
            'status' => 'BOOKED',
        ]);

        $service = app(QueueViewService::class);
        $data = $service->getQueueData($this->clinic, now()->toDateString());

        $this->assertNotEmpty($data['items']);
        $item = collect($data['items'])->firstWhere('token_number', 2);
        $this->assertNotNull($item);
        $this->assertEquals('UNPAID', $item['fee_status']);
        $this->assertEquals('NOT SEEN', $item['doctor_seen_status']);
        $this->assertEquals('WHATSAPP', $item['booking_source']);
    }
}
