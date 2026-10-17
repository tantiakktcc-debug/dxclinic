<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Models\DiagnosticBill;
use App\Models\DiagnosticItem;
use App\Models\DiagnosticOrder;
use App\Models\DiagnosticOrderItem;
use App\Models\DiagnosticReport;
use App\Models\DiagnosticResult;
use App\Models\DiagnosticSpecimen;
use App\Models\Doctor;
use App\Models\Journal;
use App\Models\LedgerAccount;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Services\CashSessionService;
use App\Services\DiagnosticBillingService;
use App\Services\DiagnosticLabService;
use App\Services\PaymentService;
use App\Services\PdfGeneratorService;
use App\Services\RefundService;
use Database\Seeders\SystemLedgerSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Revision36DiagnosticLabFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Clinic $otherClinic;
    private Doctor $doctor;
    private User $adminUser;
    private User $doctorUser;
    private User $operatorUser;
    private User $otherClinicUser;
    private Patient $patient;
    private DiagnosticItem $itemCbc;
    private DiagnosticItem $itemTsh;
    private DiagnosticItem $itemHiv;
    private DiagnosticBillingService $billingService;
    private DiagnosticLabService $labService;
    private PaymentService $paymentService;

    private CashSession $cashSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemLedgerSeeder::class);

        $this->clinic = Clinic::create([
            'name' => 'Apex Healthcare',
            'phone' => '9876543210',
            'address' => 'Mall Road',
            'city' => 'Kanpur',
            'public_slug' => 'apexcare',
            'is_onboarded' => true,
        ]);

        $this->otherClinic = Clinic::create([
            'name' => 'Metro Care',
            'phone' => '9876500000',
            'address' => 'Civil Lines',
            'city' => 'Lucknow',
            'public_slug' => 'metrocare',
            'is_onboarded' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. R. K. Mishra',
            'qualification' => 'MBBS, MD (Pathology)',
            'registration_number' => 'MCI-88990',
        ]);

        $this->adminUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Admin User',
            'email' => 'admin@apexcare.com',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'name' => 'Dr. Mishra User',
            'email' => 'doctor@apexcare.com',
            'password' => bcrypt('DoctorPassword123!'),
            'role' => 'DOCTOR',
            'is_active' => true,
        ]);

        $this->operatorUser = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Lab Operator',
            'email' => 'operator@apexcare.com',
            'password' => bcrypt('OperatorPassword123!'),
            'role' => 'OPERATOR',
            'is_active' => true,
        ]);

        $this->otherClinicUser = User::create([
            'clinic_id' => $this->otherClinic->id,
            'name' => 'Other Clinic Admin',
            'email' => 'admin@metrocare.com',
            'password' => bcrypt('OtherAdmin123!'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'P-2026-0001',
            'name' => 'Vikram Malhotra',
            'age' => 45,
            'sex' => 'MALE',
            'mobile' => '9888877777',
            'address' => '12/45 Mall Road',
            'city' => 'Kanpur',
        ]);

        // Diagnostic Tests (Numeric, Text, Qualitative)
        $this->itemCbc = DiagnosticItem::create([
            'clinic_id' => $this->clinic->id,
            'code' => 'CBC01',
            'name' => 'Hemoglobin (Hb)',
            'type' => 'TEST',
            'rate' => 200.00,
            'is_active' => true,
            'result_type' => 'NUMERIC',
            'specimen_type' => 'EDTA Whole Blood',
            'unit' => 'g/dL',
            'reference_range' => '13.0 - 17.0',
            'decimal_precision' => 1,
            'critical_low' => 7.0,
            'critical_high' => 20.0,
        ]);

        $this->itemTsh = DiagnosticItem::create([
            'clinic_id' => $this->clinic->id,
            'code' => 'TSH01',
            'name' => 'Thyroid Stimulating Hormone (TSH)',
            'type' => 'TEST',
            'rate' => 350.00,
            'is_active' => true,
            'result_type' => 'NUMERIC',
            'specimen_type' => 'Serum',
            'unit' => 'uIU/mL',
            'reference_range' => '0.4 - 4.2',
            'decimal_precision' => 2,
        ]);

        $this->itemHiv = DiagnosticItem::create([
            'clinic_id' => $this->clinic->id,
            'code' => 'HIV01',
            'name' => 'HIV 1 & 2 Antibody Rapid Test',
            'type' => 'TEST',
            'rate' => 500.00,
            'is_active' => true,
            'result_type' => 'QUALITATIVE',
            'specimen_type' => 'Serum',
            'reference_text' => 'Non-Reactive (Negative)',
        ]);

        $this->billingService = app(DiagnosticBillingService::class);
        $this->labService = app(DiagnosticLabService::class);
        $this->paymentService = app(PaymentService::class);

        // Open Cash session
        $this->cashSession = app(CashSessionService::class)->openSession($this->clinic, '2026-09-30', 'MORNING', 1000.00);
    }

    /**
     * Helper to create a paid diagnostic order
     */
    private function createPaidDiagnosticOrder(): DiagnosticOrder
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [
                ['item_id' => $this->itemCbc->id, 'quantity' => 1],
                ['item_id' => $this->itemHiv->id, 'quantity' => 1],
            ],
            0.00,
            0.00
        );

        $this->paymentService->settleDiagnosticPayment(
            $bill,
            [
                'payment_method' => 'CASH',
                'amount' => 700.00,
                'cash_session_id' => $this->cashSession->id,
            ]
        );

        $order = DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->first();
        $this->assertNotNull($order);
        return $order;
    }

    /**
     * A. Test diagnostic order discovery
     */
    public function test_a_diagnostic_order_discovery(): void
    {
        $order = $this->createPaidDiagnosticOrder();

        $this->assertEquals($this->clinic->id, $order->clinic_id);
        $this->assertEquals($this->patient->id, $order->patient_id);
        $this->assertEquals('SAMPLE_PENDING', $order->order_status);
        $this->assertCount(2, $order->items);
        $this->assertStringStartsWith('ORD-D-', $order->order_number);
    }

    /**
     * B & C & D. Sample creation, unique specimen identifier, and sample collection
     */
    public function test_b_c_d_sample_creation_unique_id_and_collection(): void
    {
        $order = $this->createPaidDiagnosticOrder();

        $specimen = $this->labService->collectSpecimen(
            $order,
            $this->operatorUser,
            'Collected via venipuncture in standard vacutainer.'
        );

        $this->assertNotNull($specimen);
        $this->assertEquals($order->id, $specimen->diagnostic_order_id);
        $this->assertEquals('COLLECTED', $specimen->status);
        $this->assertEquals($this->operatorUser->id, $specimen->collected_by_user_id);
        $this->assertNotNull($specimen->collected_at);
        $this->assertStringStartsWith('SMP-', $specimen->specimen_identifier);

        // Verify specimen identifier is unique and persistent
        $order->refresh();
        $this->assertEquals('COLLECTED', $order->order_status);
    }

    /**
     * E. Duplicate collection prevention (idempotency)
     */
    public function test_e_duplicate_collection_prevention(): void
    {
        $order = $this->createPaidDiagnosticOrder();

        $specimen1 = $this->labService->collectSpecimen($order, $this->operatorUser);
        $specimen2 = $this->labService->collectSpecimen($order, $this->operatorUser);

        $this->assertEquals($specimen1->id, $specimen2->id);
        $this->assertEquals($specimen1->specimen_identifier, $specimen2->specimen_identifier);
        $this->assertEquals(1, $order->specimens()->count());
    }

    /**
     * F. Sample status transition
     */
    public function test_f_sample_status_transition(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $specimen = $this->labService->collectSpecimen($order, $this->operatorUser);

        $this->assertEquals('COLLECTED', $specimen->status);

        // Transition to RECEIVED / PROCESSING
        $specimen->update([
            'status' => 'RECEIVED',
            'received_at' => now(),
        ]);
        $order->update(['status' => 'PROCESSING']);

        $specimen->refresh();
        $order->refresh();
        $this->assertEquals('RECEIVED', $specimen->status);
        $this->assertEquals('PROCESSING', $order->order_status);
    }

    /**
     * G & H. Rejected sample and recollection workflow
     */
    public function test_g_h_rejected_sample_and_recollection(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $specimen1 = $this->labService->collectSpecimen($order, $this->operatorUser);

        // Reject specimen with mandatory reason
        $this->labService->rejectSpecimen(
            $specimen1,
            'Specimen hemolyzed during transit. Recollection required.',
            $this->operatorUser
        );

        $specimen1->refresh();
        $order->refresh();
        $this->assertEquals('REJECTED', $specimen1->status);
        $this->assertEquals('SAMPLE_PENDING', $order->order_status);

        // Recollect specimen
        $specimen2 = $this->labService->recollectSpecimen(
            $order,
            $this->operatorUser,
            'Repeat collection from left antecubital vein.'
        );

        $this->assertNotEquals($specimen1->id, $specimen2->id);
        $this->assertNotEquals($specimen1->specimen_identifier, $specimen2->specimen_identifier);
        $this->assertEquals($specimen1->id, $specimen2->parent_specimen_id);
        $this->assertEquals('COLLECTED', $specimen2->status);

        // Historical rejected specimen preserved
        $this->assertEquals(2, $order->specimens()->count());
        $this->assertDatabaseHas('diagnostic_specimens', ['id' => $specimen1->id, 'status' => 'REJECTED']);
    }

    /**
     * I, J, K, L. Result entry: numeric, text, qualitative, and abnormal flag detection
     */
    public function test_i_j_k_l_result_entry_numeric_qualitative_and_abnormal(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $specimen = $this->labService->collectSpecimen($order, $this->operatorUser);

        $orderItems = $order->items;
        $cbcOrderItem = $orderItems->where('diagnostic_item_id', $this->itemCbc->id)->first();
        $hivOrderItem = $orderItems->where('diagnostic_item_id', $this->itemHiv->id)->first();

        // 10.5 g/dL is below normal reference range 13.0 - 17.0 (Abnormal: LOW)
        $resultsPayload = [
            $cbcOrderItem->id => [
                'value' => '10.5',
                'remarks' => 'Mild microcytic hypochromic anemia',
            ],
            $hivOrderItem->id => [
                'value' => 'NEGATIVE',
                'remarks' => 'Non-reactive by rapid immunochromatography',
            ],
        ];

        $results = $this->labService->enterResults($order, $resultsPayload, $this->operatorUser);

        $this->assertCount(2, $results);

        $cbcResult = $results->where('order_item_id', $cbcOrderItem->id)->first();
        $this->assertEquals(10.5, (float)$cbcResult->numeric_value);
        $this->assertEquals('NUMERIC', $cbcResult->result_type);
        $this->assertTrue((bool)$cbcResult->is_abnormal);
        $this->assertEquals('LOW', $cbcResult->abnormal_flag);
        $this->assertEquals('ENTERED', $cbcResult->status);

        $hivResult = $results->where('order_item_id', $hivOrderItem->id)->first();
        $this->assertEquals('NEGATIVE', $hivResult->result_value);
        $this->assertEquals('QUALITATIVE', $hivResult->result_type);
        $this->assertFalse((bool)$hivResult->is_abnormal);
    }

    /**
     * M. Wrong-order result rejection
     */
    public function test_m_wrong_order_result_rejection(): void
    {
        $order1 = $this->createPaidDiagnosticOrder();
        $this->labService->collectSpecimen($order1, $this->operatorUser);

        $order2 = $this->createPaidDiagnosticOrder();
        $this->labService->collectSpecimen($order2, $this->operatorUser);

        $order2Item = $order2->items->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order item does not belong to the specified diagnostic order');

        // Attempt to pass order 2's item to order 1
        $this->labService->enterResults($order1, [
            $order2Item->id => ['value' => '14.0'],
        ], $this->operatorUser);
    }

    /**
     * N. Cross-clinic result rejection (IDOR security)
     */
    public function test_n_cross_clinic_result_rejection(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $this->labService->collectSpecimen($order, $this->operatorUser);
        $item = $order->items->first();

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->expectExceptionMessage('Unauthorized access to clinical resources belonging to another clinic.');

        // User from other clinic attempts to enter result
        $this->labService->enterResults($order, [
            $item->id => ['value' => '12.0'],
        ], $this->otherClinicUser);
    }

    /**
     * O. Result verification RBAC: Operator is blocked; Doctor/Admin succeeds
     */
    public function test_o_result_verification_rbac(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $this->labService->collectSpecimen($order, $this->operatorUser);

        $payload = [];
        foreach ($order->items as $item) {
            $payload[$item->id] = ['value' => '14.0'];
        }
        $this->labService->enterResults($order, $payload, $this->operatorUser);

        // Operator cannot verify
        try {
            $this->labService->verifyResults($order, $this->operatorUser);
            $this->fail('Operator should not be allowed to verify diagnostic results');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertStringContainsString('Doctor or Administrator', $e->getMessage());
        }

        // Doctor verifies successfully
        $verified = $this->labService->verifyResults($order, $this->doctorUser, 'Clinically correlated.');
        $this->assertTrue($verified);

        $verifiedResults = $order->results()->where('status', 'VERIFIED')->get();
        $this->assertCount(2, $verifiedResults);
        foreach ($verifiedResults as $res) {
            $this->assertEquals('VERIFIED', $res->status);
            $this->assertEquals($this->doctorUser->id, $res->verified_by_user_id);
            $this->assertNotNull($res->verified_at);
        }
    }

    /**
     * P. Result release generates report
     */
    public function test_p_result_release(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $this->labService->collectSpecimen($order, $this->operatorUser);

        $payload = [];
        foreach ($order->items as $item) {
            $payload[$item->id] = ['value' => '14.0'];
        }
        $this->labService->enterResults($order, $payload, $this->operatorUser);
        $this->labService->verifyResults($order, $this->doctorUser);

        $report = $this->labService->releaseReport($order, $this->doctorUser, 'Electronic release authorized.');

        $this->assertNotNull($report);
        $this->assertEquals('FINAL', $report->status);
        $this->assertStringStartsWith('REP-D-', $report->report_number);
        $this->assertNotNull($report->verification_hash);
        $this->assertEquals($this->doctorUser->id, $report->released_by_user_id);
        $this->assertEquals('RELEASED', $order->fresh()->order_status);
    }

    /**
     * Q. Post-verification modification rejection (direct update blocked)
     */
    public function test_q_post_verification_modification_rejection(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $this->labService->collectSpecimen($order, $this->operatorUser);

        $payload = [];
        foreach ($order->items as $item) {
            $payload[$item->id] = ['value' => '14.0'];
        }
        $this->labService->enterResults($order, $payload, $this->operatorUser);
        $this->labService->verifyResults($order, $this->doctorUser);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot silently overwrite a VERIFIED result');

        // Attempting normal enterResults on verified order fails
        $this->labService->enterResults($order, $payload, $this->operatorUser);
    }

    /**
     * R. Controlled result correction with mandatory audit reason and version bump
     */
    public function test_r_controlled_result_correction(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $this->labService->collectSpecimen($order, $this->operatorUser);

        $payload = [];
        foreach ($order->items as $item) {
            $payload[$item->id] = ['value' => '14.0'];
        }
        $this->labService->enterResults($order, $payload, $this->operatorUser);
        $this->labService->verifyResults($order, $this->doctorUser);

        $originalResult = $order->results()->first();

        // Perform controlled correction
        $newResult = $this->labService->correctResult(
            $originalResult,
            '13.5',
            'Analytical re-run after instrument calibration.',
            $this->doctorUser
        );

        $originalResult->refresh();
        $this->assertEquals('CORRECTED', $originalResult->status);
        $this->assertEquals('13.5', $newResult->result_value);
        $this->assertEquals(2, $newResult->version);
        $this->assertEquals($originalResult->id, $newResult->previous_result_id);
        $this->assertEquals('VERIFIED', $newResult->status);
    }

    /**
     * S. Audit trail verification for all clinical transitions
     */
    public function test_s_audit_trail_verification(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $specimen = $this->labService->collectSpecimen($order, $this->operatorUser);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'SPECIMEN_COLLECTED',
            'entity_type' => DiagnosticSpecimen::class,
            'entity_id' => $specimen->id,
        ]);

        $payload = [];
        foreach ($order->items as $item) {
            $payload[$item->id] = ['value' => '14.0'];
        }
        $this->labService->enterResults($order, $payload, $this->operatorUser);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'DIAGNOSTIC_RESULTS_ENTERED',
            'entity_type' => DiagnosticOrder::class,
            'entity_id' => $order->id,
        ]);

        $this->labService->verifyResults($order, $this->doctorUser);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'DIAGNOSTIC_RESULTS_VERIFIED',
            'entity_type' => DiagnosticOrder::class,
            'entity_id' => $order->id,
        ]);

        $report = $this->labService->releaseReport($order, $this->doctorUser);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $this->clinic->id,
            'action' => 'DIAGNOSTIC_REPORT_RELEASED',
            'entity_type' => DiagnosticReport::class,
            'entity_id' => $report->id,
        ]);
    }

    /**
     * T & U. Report generation and reprint idempotency
     */
    public function test_t_u_report_generation_and_reprint_idempotency(): void
    {
        $order = $this->createPaidDiagnosticOrder();
        $this->labService->collectSpecimen($order, $this->operatorUser);

        $payload = [];
        foreach ($order->items as $item) {
            $payload[$item->id] = ['value' => '14.0'];
        }
        $this->labService->enterResults($order, $payload, $this->operatorUser);
        $this->labService->verifyResults($order, $this->doctorUser);
        $report = $this->labService->releaseReport($order, $this->doctorUser);

        $pdfService = app(PdfGeneratorService::class);
        $data = [
            'report' => $report,
            'order' => $order,
            'specimen' => $order->specimens->first(),
            'results' => $order->results()->where('status', '!=', 'CORRECTED')->get(),
            'qrBase64' => null,
        ];

        // Generate PDF
        $response1 = $pdfService->generateDiagnosticResultReportPdf($data);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals('application/pdf', $response1->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response1->getContent());

        // Snapshot counts before reprint
        $ordersCount = DiagnosticOrder::count();
        $specimensCount = DiagnosticSpecimen::count();
        $resultsCount = DiagnosticResult::count();
        $reportsCount = DiagnosticReport::count();
        $paymentsCount = Payment::count();
        $journalsCount = Journal::count();

        // Reprint PDF
        $response2 = $pdfService->generateDiagnosticResultReportPdf($data);
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertStringStartsWith('%PDF-', $response2->getContent());

        // Assert zero new records created by reprinting
        $this->assertEquals($ordersCount, DiagnosticOrder::count());
        $this->assertEquals($specimensCount, DiagnosticSpecimen::count());
        $this->assertEquals($resultsCount, DiagnosticResult::count());
        $this->assertEquals($reportsCount, DiagnosticReport::count());
        $this->assertEquals($paymentsCount, Payment::count());
        $this->assertEquals($journalsCount, Journal::count());
    }

    /**
     * V. Zero financial side effects (No journals, no payments created during lab operations)
     */
    public function test_v_zero_accounting_side_effects(): void
    {
        $order = $this->createPaidDiagnosticOrder();

        $initialJournalsCount = Journal::count();
        $initialPaymentsCount = Payment::count();

        // Collect specimen
        $this->labService->collectSpecimen($order, $this->operatorUser);

        // Enter results
        $payload = [];
        foreach ($order->items as $item) {
            $payload[$item->id] = ['value' => '14.0'];
        }
        $this->labService->enterResults($order, $payload, $this->operatorUser);

        // Verify results
        $this->labService->verifyResults($order, $this->doctorUser);

        // Correct result
        $firstResult = $order->results()->first();
        $this->labService->correctResult($firstResult, '13.8', 'Instrument check', $this->doctorUser);

        // Release report
        $this->labService->releaseReport($order, $this->doctorUser);

        // Strict assertion: Not a single accounting journal or payment was created
        $this->assertEquals($initialJournalsCount, Journal::count());
        $this->assertEquals($initialPaymentsCount, Payment::count());
    }

    /**
     * W. Existing diagnostic billing regression intact
     */
    public function test_w_existing_diagnostic_billing_regression(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [
                ['item_id' => $this->itemCbc->id, 'quantity' => 1],
            ],
            0.00,
            20.00
        );

        $this->assertEquals(200.00, (float)$bill->gross_amount);
        $this->assertEquals(20.00, (float)$bill->waiver_amount);
        $this->assertEquals(180.00, (float)$bill->net_amount);
        $this->assertEquals('PENDING', $bill->status);

        $payment = $this->paymentService->settleDiagnosticPayment(
            $bill,
            [
                'payment_method' => 'CASH',
                'amount' => 180.00,
                'cash_session_id' => $this->cashSession->id,
            ]
        );

        $this->assertEquals('PAID', $bill->fresh()->status);
        $this->assertEquals('SETTLED', $payment->status);
        $this->assertEquals(180.00, (float)$payment->amount);
    }

    /**
     * 9.A: Existing diagnostic bill calculation remains unchanged
     */
    public function test_section_9_a_existing_diagnostic_bill_calculation_remains_unchanged(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [
                ['item_id' => $this->itemCbc->id, 'quantity' => 2], // 2 * 200 = 400
                ['item_id' => $this->itemTsh->id, 'quantity' => 1], // 1 * 350 = 350
            ],
            50.00, // discount
            25.00  // waiver
        );

        $this->assertEquals(750.00, (float) $bill->gross_amount);
        $this->assertEquals(50.00, (float) $bill->discount_amount);
        $this->assertEquals(25.00, (float) $bill->waiver_amount);
        $this->assertEquals(675.00, (float) $bill->net_amount);
        $this->assertEquals('PENDING', $bill->status);

        $this->assertCount(2, $bill->items);
        $cbcItem = $bill->items->where('diagnostic_item_id', $this->itemCbc->id)->first();
        $this->assertNotNull($cbcItem);
        $this->assertEquals(200.00, (float) $cbcItem->rate);
        $this->assertEquals(2, $cbcItem->quantity);
        $this->assertEquals(400.00, (float) $cbcItem->amount);
    }

    /**
     * 9.B: Existing payment settlement remains unchanged
     */
    public function test_section_9_b_existing_payment_settlement_remains_unchanged(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [['item_id' => $this->itemTsh->id, 'quantity' => 1]],
            0.00,
            0.00
        );

        $payment = $this->paymentService->settleDiagnosticPayment(
            $bill,
            [
                'payment_method' => 'CASH',
                'amount' => 350.00,
                'cash_session_id' => $this->cashSession->id,
            ]
        );

        $this->assertEquals('PAID', $bill->fresh()->status);
        $this->assertEquals('SETTLED', $payment->status);
        $this->assertEquals(350.00, (float) $payment->amount);
        $this->assertEquals(350.00, (float) $bill->fresh()->payment->amount);

        // Verify balanced journal entry created
        $journal = Journal::where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->first();
        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());
    }

    /**
     * 9.C: Successful settlement creates exactly one diagnostic order
     */
    public function test_section_9_c_successful_settlement_creates_exactly_one_diagnostic_order(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [['item_id' => $this->itemCbc->id, 'quantity' => 1]],
            0.00,
            0.00
        );

        $ordersBefore = DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->count();
        // Since bill creation auto-initiates in ORDERED status:
        $this->assertEquals(1, $ordersBefore);

        $this->paymentService->settleDiagnosticPayment(
            $bill,
            [
                'payment_method' => 'CASH',
                'amount' => 200.00,
                'cash_session_id' => $this->cashSession->id,
            ]
        );

        $ordersAfter = DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->get();
        // Strict invariant: exactly one order
        $this->assertCount(1, $ordersAfter);
        $this->assertEquals('SAMPLE_PENDING', $ordersAfter->first()->order_status);
        $this->assertEquals($bill->id, $ordersAfter->first()->diagnostic_bill_id);
    }

    /**
     * 9.D: Repeated settlement/retry cannot create duplicate diagnostic orders
     */
    public function test_section_9_d_repeated_settlement_cannot_create_duplicate_diagnostic_orders(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [['item_id' => $this->itemCbc->id, 'quantity' => 1]],
            0.00,
            0.00
        );

        $this->paymentService->settleDiagnosticPayment(
            $bill,
            [
                'payment_method' => 'CASH',
                'amount' => 200.00,
                'cash_session_id' => $this->cashSession->id,
            ]
        );

        $this->assertEquals(1, DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->count());

        // Repeated attempt via lab service to sync / create from bill
        $order2 = $this->labService->createOrderFromBill($bill);
        $this->assertEquals(1, DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->count());

        // Database unique constraint level check: Direct duplicate insert must fail
        $this->expectException(QueryException::class);
        DiagnosticOrder::create([
            'clinic_id' => $this->clinic->id,
            'diagnostic_bill_id' => $bill->id,
            'patient_id' => $this->patient->id,
            'order_number' => 'ORD-D-COLLISION-TEST',
            'order_date' => now()->toDateString(),
            'order_status' => 'ORDERED',
        ]);
    }

    /**
     * 9.E: Repeated order synchronization is idempotent
     */
    public function test_section_9_e_repeated_order_synchronization_is_idempotent(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [['item_id' => $this->itemCbc->id, 'quantity' => 1]],
            0.00,
            0.00
        );

        $order1 = $this->labService->createOrderFromBill($bill);
        $initialItemCount = $order1->items()->count();

        // Call synchronization 5 times
        for ($i = 0; $i < 5; $i++) {
            $orderN = $this->labService->createOrderFromBill($bill);
            $this->assertEquals($order1->id, $orderN->id);
            $this->assertEquals($order1->order_number, $orderN->order_number);
        }

        $this->assertEquals(1, DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->count());
        $this->assertEquals($initialItemCount, $order1->items()->count());
    }

    /**
     * 9.F: Diagnostic result metadata does not alter bill price
     */
    public function test_section_9_f_diagnostic_result_metadata_does_not_alter_bill_price(): void
    {
        // Modify metadata to various edge cases
        $this->itemCbc->update([
            'result_type' => 'TEXT',
            'unit' => 'mg/dL',
            'reference_range' => '50 - 100',
            'critical_low' => 10.0,
            'critical_high' => 500.0,
            'decimal_precision' => 3,
        ]);

        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [['item_id' => $this->itemCbc->id, 'quantity' => 3]],
            0.00,
            0.00
        );

        // Price must strictly remain 3 * 200.00 = 600.00
        $this->assertEquals(600.00, (float) $bill->gross_amount);
        $this->assertEquals(600.00, (float) $bill->net_amount);
        $this->assertEquals(200.00, (float) $bill->items->first()->rate);
    }

    /**
     * 9.G: Historical diagnostic bills remain immutable
     */
    public function test_section_9_g_historical_diagnostic_bills_remain_immutable(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [
                ['item_id' => $this->itemCbc->id, 'quantity' => 1],
                ['item_id' => $this->itemHiv->id, 'quantity' => 1],
            ],
            20.00,
            10.00
        );

        $payment = $this->paymentService->settleDiagnosticPayment(
            $bill,
            [
                'payment_method' => 'CASH',
                'amount' => 670.00,
                'cash_session_id' => $this->cashSession->id,
            ]
        );

        $originalBillData = $bill->fresh()->toArray();
        $originalItemsData = $bill->items()->get()->toArray();
        $originalPaymentData = $payment->fresh()->toArray();

        // Perform full downstream lab workflow
        $order = DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->firstOrFail();
        $this->labService->collectSpecimen($order, $this->operatorUser);
        $this->labService->enterResults($order, [
            $order->items[0]->id => ['value' => '15.5'],
            $order->items[1]->id => ['value' => 'Non-Reactive'],
        ], $this->operatorUser);
        $this->labService->verifyResults($order, $this->doctorUser);
        $this->labService->releaseReport($order, $this->doctorUser);

        // Verify bill snapshot immutability
        $refreshedBill = $bill->fresh();
        $this->assertEquals($originalBillData['gross_amount'], $refreshedBill->gross_amount);
        $this->assertEquals($originalBillData['discount_amount'], $refreshedBill->discount_amount);
        $this->assertEquals($originalBillData['waiver_amount'], $refreshedBill->waiver_amount);
        $this->assertEquals($originalBillData['net_amount'], $refreshedBill->net_amount);
        $this->assertEquals($originalBillData['status'], $refreshedBill->status);

        // Verify bill items immutability
        $refreshedItems = $refreshedBill->items()->get()->toArray();
        foreach ($refreshedItems as $idx => $item) {
            $this->assertEquals($originalItemsData[$idx]['rate'], $item['rate']);
            $this->assertEquals($originalItemsData[$idx]['quantity'], $item['quantity']);
            $this->assertEquals($originalItemsData[$idx]['amount'], $item['amount']);
        }

        // Verify payment immutability
        $this->assertEquals($originalPaymentData['amount'], $payment->fresh()->amount);
        $this->assertEquals($originalPaymentData['status'], $payment->fresh()->status);
    }

    /**
     * 9.H: Refund workflow remains unchanged
     */
    public function test_section_9_h_refund_workflow_remains_unchanged(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [['item_id' => $this->itemCbc->id, 'quantity' => 1]],
            0.00,
            0.00
        );

        $payment = $this->paymentService->settleDiagnosticPayment(
            $bill,
            [
                'payment_method' => 'CASH',
                'amount' => 200.00,
                'cash_session_id' => $this->cashSession->id,
            ]
        );

        $refundService = app(RefundService::class);
        $refund = $refundService->requestRefund([
            'payment_id' => $payment->id,
            'diagnostic_bill_id' => $bill->id,
            'refund_amount' => 100.00,
            'refund_method' => 'CASH',
            'reason' => 'Patient requested partial cancellation of diagnostic test',
            'cash_session_id' => $this->cashSession->id,
        ], $this->operatorUser);

        $this->assertEquals('REQUESTED', $refund->status);
        $this->assertEquals(100.00, (float) $refund->refund_amount);

        $approvedRefund = $refundService->approveRefund($refund, $this->adminUser);
        $this->assertEquals('APPROVED', $approvedRefund->status);

        $processedRefund = $refundService->processRefund($approvedRefund, $this->adminUser, [
            'cash_session_id' => $this->cashSession->id,
            'refund_method' => 'CASH',
        ]);

        $this->assertEquals('PROCESSED', $processedRefund->status);
        $this->assertEquals(100.00, (float) $refundService->getRemainingRefundableAmount($payment));
    }

    /**
     * 9.I: No accounting entry is generated by lab order creation
     */
    public function test_section_9_i_no_accounting_entry_is_generated_by_lab_order_creation(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [['item_id' => $this->itemCbc->id, 'quantity' => 1]],
            0.00,
            0.00
        );

        $journalsBeforeSettlement = Journal::count();

        $this->paymentService->settleDiagnosticPayment(
            $bill,
            [
                'payment_method' => 'CASH',
                'amount' => 200.00,
                'cash_session_id' => $this->cashSession->id,
            ]
        );

        // Exactly one journal entry for payment settlement (RV-DIAG)
        $journalsAfterSettlement = Journal::count();
        $this->assertEquals($journalsBeforeSettlement + 1, $journalsAfterSettlement);

        // Now run lab order creation and lifecycle
        $order = DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->firstOrFail();
        $this->labService->collectSpecimen($order, $this->operatorUser);
        $this->labService->enterResults($order, [
            $order->items[0]->id => ['value' => '14.2'],
        ], $this->operatorUser);
        $this->labService->verifyResults($order, $this->doctorUser);
        $this->labService->releaseReport($order, $this->doctorUser);

        // Post-condition: Zero new journals created by lab operations
        $this->assertEquals($journalsAfterSettlement, Journal::count());
    }

    /**
     * 9.J: Lab workflow failure cannot silently corrupt financial state
     */
    public function test_section_9_j_lab_workflow_failure_cannot_silently_corrupt_financial_state(): void
    {
        $bill = $this->billingService->createBill(
            $this->patient,
            null,
            [['item_id' => $this->itemCbc->id, 'quantity' => 1]],
            0.00,
            0.00
        );

        $payment = $this->paymentService->settleDiagnosticPayment(
            $bill,
            [
                'payment_method' => 'CASH',
                'amount' => 200.00,
                'cash_session_id' => $this->cashSession->id,
            ]
        );

        $this->assertEquals('PAID', $bill->fresh()->status);
        $this->assertEquals('SETTLED', $payment->fresh()->status);

        // Now suppose downstream lab order processing throws an exception (e.g. invalid specimen collection attempt)
        $order = DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->firstOrFail();

        try {
            // Attempt an unauthorized result verification by OPERATOR
            $this->labService->verifyResults($order, $this->operatorUser);
            $this->fail('Expected AuthorizationException was not thrown');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Exception caught as expected
        }

        // Verify financial state is completely untouched and intact
        $this->assertEquals('PAID', $bill->fresh()->status);
        $this->assertEquals('SETTLED', $payment->fresh()->status);
        $this->assertEquals(200.00, (float) $payment->fresh()->amount);

        // The lab order remains in a clear, recoverable state
        $this->assertEquals('SAMPLE_PENDING', $order->fresh()->order_status);
    }
}
