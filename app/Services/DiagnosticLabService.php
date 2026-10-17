<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\DiagnosticBill;
use App\Models\DiagnosticItem;
use App\Models\DiagnosticOrder;
use App\Models\DiagnosticOrderItem;
use App\Models\DiagnosticReport;
use App\Models\DiagnosticResult;
use App\Models\DiagnosticSequence;
use App\Models\DiagnosticSpecimen;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DiagnosticLabService
{
    /**
     * Create a new Diagnostic Order
     */
    public function createOrder(
        Patient $patient,
        array $testItemIds,
        ?Doctor $doctor = null,
        ?Visit $visit = null,
        ?Appointment $appointment = null,
        ?DiagnosticBill $bill = null,
        string $priority = 'ROUTINE',
        ?string $clinicalNotes = null,
        ?User $creator = null
    ): DiagnosticOrder {
        if (empty($testItemIds)) {
            throw new InvalidArgumentException("At least one diagnostic investigation must be selected.");
        }

        return DB::transaction(function () use (
            $patient, $testItemIds, $doctor, $visit, $appointment, $bill, $priority, $clinicalNotes, $creator
        ) {
            $clinic = $patient->clinic;
            $orderNumber = DiagnosticSequence::nextOrderNumber($clinic);

            // Determine initial status based on billing settlement
            $initialStatus = ($bill && $bill->status === 'PAID') ? 'SAMPLE_PENDING' : 'ORDERED';

            $order = DiagnosticOrder::create([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor?->id ?? $visit?->doctor_id ?? $bill?->doctor_id,
                'visit_id' => $visit?->id ?? $bill?->visit_id,
                'appointment_id' => $appointment?->id,
                'diagnostic_bill_id' => $bill?->id,
                'order_number' => $orderNumber,
                'order_date' => Carbon::now(),
                'status' => $initialStatus,
                'priority' => in_array($priority, ['ROUTINE', 'URGENT', 'STAT']) ? $priority : 'ROUTINE',
                'clinical_notes' => $clinicalNotes,
                'created_by' => $creator?->id,
            ]);

            foreach ($testItemIds as $itemEntry) {
                $itemId = is_array($itemEntry) ? ($itemEntry['item_id'] ?? 0) : (int) $itemEntry;
                $qty = is_array($itemEntry) ? max(1, (int) ($itemEntry['quantity'] ?? 1)) : 1;

                $diagItem = DiagnosticItem::findOrFail($itemId);

                DiagnosticOrderItem::create([
                    'diagnostic_order_id' => $order->id,
                    'diagnostic_item_id' => $diagItem->id,
                    'test_name' => $diagItem->name,
                    'item_type' => $diagItem->type,
                    'quantity' => $qty,
                    'specimen_type' => $diagItem->specimen_type ?? 'Blood',
                    'status' => 'PENDING',
                ]);
            }

            // Record audit trail
            AuditLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => $creator?->id,
                'action' => 'DIAGNOSTIC_ORDER_CREATED',
                'entity_type' => DiagnosticOrder::class,
                'entity_id' => $order->id,
                'new_values' => ['order_number' => $order->order_number, 'status' => $order->status],
                'reason' => 'Diagnostic investigation order registered',
                'change_category' => 'CLINICAL_WORKFLOW',
            ]);

            return $order->fresh(['items', 'patient', 'doctor', 'clinic']);
        });
    }

    /**
     * Create or retrieve an Order corresponding to a Diagnostic Bill
     */
    public function createOrderFromBill(DiagnosticBill $bill, ?User $creator = null): DiagnosticOrder
    {
        $existing = DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->first();
        if ($existing) {
            // If bill is paid and order is still ORDERED, advance to SAMPLE_PENDING
            if ($bill->status === 'PAID' && $existing->status === 'ORDERED') {
                $existing->update(['status' => 'SAMPLE_PENDING']);
            }
            return $existing->fresh(['items', 'patient', 'doctor', 'specimens']);
        }

        $bill->loadMissing(['items', 'patient.clinic', 'doctor', 'visit']);

        $items = $bill->items->map(fn($bi) => [
            'item_id' => $bi->diagnostic_item_id,
            'quantity' => $bi->quantity,
        ])->toArray();

        try {
            return $this->createOrder(
                $bill->patient,
                $items,
                $bill->doctor,
                $bill->visit,
                null,
                $bill,
                'ROUTINE',
                "Generated from diagnostic bill #{$bill->bill_number}",
                $creator
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            // Concurrency-safe fallback: another process created the order concurrently
            $collisionOrder = DiagnosticOrder::where('diagnostic_bill_id', $bill->id)->first();
            if ($collisionOrder) {
                if ($bill->status === 'PAID' && $collisionOrder->status === 'ORDERED') {
                    $collisionOrder->update(['status' => 'SAMPLE_PENDING']);
                }
                return $collisionOrder->fresh(['items', 'patient', 'doctor', 'specimens']);
            }
            throw $e;
        }
    }

    /**
     * Collect sample / specimen for an order (Idempotent & Concurrency Safe)
     */
    public function collectSpecimen(
        DiagnosticOrder $order,
        mixed $dataOrCollector = [],
        mixed $collectorOrNotes = null
    ): DiagnosticSpecimen {
        if ($dataOrCollector instanceof User) {
            $collector = $dataOrCollector;
            $data = is_array($collectorOrNotes) ? $collectorOrNotes : (is_string($collectorOrNotes) ? ['notes' => $collectorOrNotes] : []);
        } elseif ($collectorOrNotes instanceof User) {
            $collector = $collectorOrNotes;
            $data = is_array($dataOrCollector) ? $dataOrCollector : (is_string($dataOrCollector) ? ['notes' => $dataOrCollector] : []);
        } else {
            $collector = auth()->user() ?? throw new InvalidArgumentException("Collector user is required.");
            $data = is_array($dataOrCollector) ? $dataOrCollector : [];
        }

        $this->authorizeClinicScope($order->clinic_id, $collector);

        return DB::transaction(function () use ($order, $data, $collector) {
            // Lock order for update to prevent concurrent duplicate collection
            $order = DiagnosticOrder::where('id', $order->id)->lockForUpdate()->first();

            // Idempotency check: if specimen already exists and is COLLECTED or beyond, return existing
            $existingSpecimen = DiagnosticSpecimen::where('diagnostic_order_id', $order->id)
                ->whereNotIn('status', ['REJECTED', 'CANCELLED'])
                ->first();

            if ($existingSpecimen && in_array($existingSpecimen->status, ['COLLECTED', 'RECEIVED', 'PROCESSING', 'RESULT_ENTERED', 'VERIFIED', 'RELEASED'])) {
                return $existingSpecimen;
            }

            $clinic = Clinic::findOrFail($order->clinic_id);
            $specimenIdentifier = DiagnosticSequence::nextSpecimenIdentifier($clinic);

            // Determine specimen type
            $primaryItem = $order->items()->first();
            $specimenType = $data['specimen_type'] ?? ($primaryItem?->specimen_type ?? 'Blood');
            $notes = $data['notes'] ?? null;

            $specimen = DiagnosticSpecimen::create([
                'clinic_id' => $clinic->id,
                'diagnostic_order_id' => $order->id,
                'order_item_id' => null, // Order-level specimen
                'specimen_identifier' => $specimenIdentifier,
                'specimen_type' => $specimenType,
                'status' => 'COLLECTED',
                'collected_at' => Carbon::now(),
                'collected_by' => $collector->id,
                'notes' => $notes,
            ]);

            $order->update(['status' => 'COLLECTED']);
            $order->items()->update(['status' => 'COLLECTED']);

            // Audit
            AuditLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => $collector->id,
                'action' => 'SPECIMEN_COLLECTED',
                'entity_type' => DiagnosticSpecimen::class,
                'entity_id' => $specimen->id,
                'new_values' => [
                    'specimen_identifier' => $specimen->specimen_identifier,
                    'status' => 'COLLECTED',
                    'collected_at' => $specimen->collected_at->toDateTimeString(),
                ],
                'reason' => 'Specimen successfully collected from patient',
                'change_category' => 'CLINICAL_WORKFLOW',
            ]);

            return $specimen;
        });
    }

    /**
     * Reject a specimen (e.g. hemolyzed, clotted, insufficient quantity)
     */
    public function rejectSpecimen(
        DiagnosticSpecimen $specimen,
        string $reason,
        User $user
    ): DiagnosticSpecimen {
        $this->authorizeClinicScope($specimen->clinic_id, $user);

        if (strlen(trim($reason)) < 5) {
            throw new InvalidArgumentException("A valid rejection reason must be provided (minimum 5 characters).");
        }

        if ($specimen->isReleased()) {
            throw new InvalidArgumentException("Cannot reject a specimen whose report has already been released.");
        }

        return DB::transaction(function () use ($specimen, $reason, $user) {
            $specimen = DiagnosticSpecimen::where('id', $specimen->id)->lockForUpdate()->first();

            $specimen->update([
                'status' => 'REJECTED',
                'rejection_reason' => trim($reason),
                'rejected_at' => Carbon::now(),
                'rejected_by' => $user->id,
            ]);

            // Revert order back to SAMPLE_PENDING for recollection
            $order = $specimen->order;
            $order->update(['status' => 'SAMPLE_PENDING']);
            $order->items()->update(['status' => 'PENDING']);

            AuditLog::create([
                'clinic_id' => $specimen->clinic_id,
                'user_id' => $user->id,
                'action' => 'SPECIMEN_REJECTED',
                'entity_type' => DiagnosticSpecimen::class,
                'entity_id' => $specimen->id,
                'new_values' => ['status' => 'REJECTED', 'reason' => $reason],
                'reason' => 'Specimen rejected: ' . $reason,
                'change_category' => 'CLINICAL_WORKFLOW',
            ]);

            return $specimen->fresh();
        });
    }

    /**
     * Recollect sample after rejection (Preserves original rejected specimen)
     */
    public function recollectSpecimen(
        DiagnosticSpecimen|DiagnosticOrder $specimenOrOrder,
        mixed $notesOrCollector = '',
        mixed $collectorOrNotes = null
    ): DiagnosticSpecimen {
        if ($specimenOrOrder instanceof DiagnosticOrder) {
            $rejectedSpecimen = $specimenOrOrder->specimens()->where('status', 'REJECTED')->latest()->first();
            if (!$rejectedSpecimen) {
                throw new InvalidArgumentException("No rejected specimen found for this order to recollect.");
            }
        } else {
            $rejectedSpecimen = $specimenOrOrder;
        }

        if ($notesOrCollector instanceof User) {
            $collector = $notesOrCollector;
            $notes = is_string($collectorOrNotes) ? $collectorOrNotes : '';
        } elseif ($collectorOrNotes instanceof User) {
            $collector = $collectorOrNotes;
            $notes = is_string($notesOrCollector) ? $notesOrCollector : '';
        } else {
            $collector = auth()->user() ?? throw new InvalidArgumentException("Collector user is required.");
            $notes = is_string($notesOrCollector) ? $notesOrCollector : '';
        }

        $this->authorizeClinicScope($rejectedSpecimen->clinic_id, $collector);

        if ($rejectedSpecimen->status !== 'REJECTED') {
            throw new InvalidArgumentException("Recollection can only be initiated on a rejected specimen.");
        }

        return DB::transaction(function () use ($rejectedSpecimen, $notes, $collector) {
            $clinic = Clinic::findOrFail($rejectedSpecimen->clinic_id);
            $newIdentifier = DiagnosticSequence::nextSpecimenIdentifier($clinic);

            $newSpecimen = DiagnosticSpecimen::create([
                'clinic_id' => $clinic->id,
                'diagnostic_order_id' => $rejectedSpecimen->diagnostic_order_id,
                'order_item_id' => $rejectedSpecimen->order_item_id,
                'specimen_identifier' => $newIdentifier,
                'specimen_type' => $rejectedSpecimen->specimen_type,
                'status' => 'COLLECTED',
                'collected_at' => Carbon::now(),
                'collected_by' => $collector->id,
                'notes' => $notes,
                'is_recollection' => true,
                'parent_specimen_id' => $rejectedSpecimen->id,
            ]);

            $order = $rejectedSpecimen->order;
            $order->update(['status' => 'COLLECTED']);
            $order->items()->update(['status' => 'COLLECTED']);

            AuditLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => $collector->id,
                'action' => 'SPECIMEN_RECOLLECTED',
                'entity_type' => DiagnosticSpecimen::class,
                'entity_id' => $newSpecimen->id,
                'new_values' => [
                    'specimen_identifier' => $newIdentifier,
                    'parent_specimen_id' => $rejectedSpecimen->id,
                ],
                'reason' => 'Recollection recorded following specimen rejection',
                'change_category' => 'CLINICAL_WORKFLOW',
            ]);

            return $newSpecimen;
        });
    }

    /**
     * Structured Result Entry for Diagnostic Order investigations
     */
    public function enterResults(
        DiagnosticOrder $order,
        array $resultsData,
        User $user
    ): Collection {
        $this->authorizeClinicScope($order->clinic_id, $user);

        if (empty($resultsData)) {
            throw new InvalidArgumentException("Results data cannot be empty.");
        }

        return DB::transaction(function () use ($order, $resultsData, $user) {
            $order = DiagnosticOrder::where('id', $order->id)->lockForUpdate()->first();
            $specimen = $order->specimens()->whereNotIn('status', ['REJECTED', 'CANCELLED'])->latest()->first();

            $savedResults = collect();

            foreach ($resultsData as $key => $entry) {
                $orderItemId = (int) ($entry['order_item_id'] ?? $key);
                $orderItem = DiagnosticOrderItem::where('diagnostic_order_id', $order->id)->find($orderItemId);
                if (!$orderItem) {
                    throw new InvalidArgumentException("Order item does not belong to the specified diagnostic order.");
                }
                $diagItem = $orderItem->diagnosticItem;

                $resultType = $entry['result_type'] ?? ($diagItem?->result_type ?? 'NUMERIC');
                $resultVal = trim((string) ($entry['result_value'] ?? $entry['value'] ?? ''));
                $unit = $entry['unit'] ?? ($diagItem?->unit ?? null);
                $refRange = $entry['reference_range'] ?? ($diagItem?->reference_range ?? null);
                $remarks = $entry['remarks'] ?? null;

                $numericVal = is_numeric($resultVal) ? (float) $resultVal : null;
                $abnormalFlag = $this->determineAbnormalFlag($resultType, $numericVal, $resultVal, $refRange, $diagItem);

                // Check if result already exists for this order item
                $existing = DiagnosticResult::where('diagnostic_order_id', $order->id)
                    ->where('diagnostic_order_item_id', $orderItem->id)
                    ->where('status', '!=', 'CORRECTED')
                    ->first();

                if ($existing) {
                    if (in_array($existing->status, ['VERIFIED', 'RELEASED'])) {
                        throw new InvalidArgumentException("Cannot silently overwrite a {$existing->status} result. Use controlled correction.");
                    }

                    $existing->update([
                        'diagnostic_specimen_id' => $specimen?->id,
                        'parameter_name' => $entry['parameter_name'] ?? $orderItem->test_name,
                        'result_type' => $resultType,
                        'result_value' => $resultVal,
                        'numeric_value' => $numericVal,
                        'unit' => $unit,
                        'reference_range' => $refRange,
                        'abnormal_flag' => $abnormalFlag,
                        'remarks' => $remarks,
                        'status' => 'ENTERED',
                        'entered_at' => Carbon::now(),
                        'entered_by' => $user->id,
                    ]);

                    $savedResults->push($existing->fresh());
                } else {
                    $result = DiagnosticResult::create([
                        'clinic_id' => $order->clinic_id,
                        'diagnostic_order_id' => $order->id,
                        'diagnostic_order_item_id' => $orderItem->id,
                        'diagnostic_specimen_id' => $specimen?->id,
                        'diagnostic_item_id' => $diagItem?->id,
                        'patient_id' => $order->patient_id,
                        'parameter_name' => $entry['parameter_name'] ?? $orderItem->test_name,
                        'result_type' => $resultType,
                        'result_value' => $resultVal,
                        'numeric_value' => $numericVal,
                        'unit' => $unit,
                        'reference_range' => $refRange,
                        'abnormal_flag' => $abnormalFlag,
                        'remarks' => $remarks,
                        'status' => 'ENTERED',
                        'version' => 1,
                        'is_corrected' => false,
                        'entered_at' => Carbon::now(),
                        'entered_by' => $user->id,
                    ]);

                    $savedResults->push($result);
                }

                $orderItem->update(['status' => 'RESULT_ENTERED']);
            }

            $order->update(['status' => 'RESULT_ENTERED']);
            if ($specimen && $specimen->status !== 'RESULT_ENTERED') {
                $specimen->update(['status' => 'RESULT_ENTERED']);
            }

            AuditLog::create([
                'clinic_id' => $order->clinic_id,
                'user_id' => $user->id,
                'action' => 'DIAGNOSTIC_RESULTS_ENTERED',
                'entity_type' => DiagnosticOrder::class,
                'entity_id' => $order->id,
                'new_values' => ['results_count' => $savedResults->count()],
                'reason' => 'Laboratory test results entered',
                'change_category' => 'CLINICAL_WORKFLOW',
            ]);

            return $savedResults;
        });
    }

    /**
     * Result Verification (Doctor or Admin Only)
     */
    public function verifyResults(DiagnosticOrder $order, User $verifier): bool
    {
        $this->authorizeClinicScope($order->clinic_id, $verifier);

        // RBAC enforcement: Only DOCTOR or ADMIN can verify clinical results
        if (!$verifier->isAdmin() && !$verifier->isDoctor()) {
            throw new AuthorizationException("Only a qualified Doctor or Administrator can verify clinical laboratory results.");
        }

        return DB::transaction(function () use ($order, $verifier) {
            $order = DiagnosticOrder::where('id', $order->id)->lockForUpdate()->first();

            $results = $order->results()->where('status', 'ENTERED')->get();
            if ($results->isEmpty() && !$order->results()->exists()) {
                throw new InvalidArgumentException("No entered results found to verify.");
            }

            $now = Carbon::now();

            $order->results()->where('status', 'ENTERED')->update([
                'status' => 'VERIFIED',
                'verified_at' => $now,
                'verified_by' => $verifier->id,
            ]);

            $order->update(['status' => 'VERIFIED']);
            $order->items()->update(['status' => 'VERIFIED']);

            $specimen = $order->specimens()->whereNotIn('status', ['REJECTED', 'CANCELLED'])->latest()->first();
            if ($specimen) {
                $specimen->update(['status' => 'VERIFIED']);
            }

            AuditLog::create([
                'clinic_id' => $order->clinic_id,
                'user_id' => $verifier->id,
                'action' => 'DIAGNOSTIC_RESULTS_VERIFIED',
                'entity_type' => DiagnosticOrder::class,
                'entity_id' => $order->id,
                'new_values' => ['status' => 'VERIFIED', 'verified_by' => $verifier->id],
                'reason' => 'Diagnostic results verified by authorized clinician',
                'change_category' => 'CLINICAL_WORKFLOW',
            ]);

            return true;
        });
    }

    /**
     * Release Diagnostic Report (Doctor or Admin Only)
     */
    public function releaseReport(
        DiagnosticOrder $order,
        User $releaser,
        ?string $reportNotes = null
    ): DiagnosticReport {
        $this->authorizeClinicScope($order->clinic_id, $releaser);

        // RBAC enforcement: Only DOCTOR or ADMIN can release diagnostic reports
        if (!$releaser->isAdmin() && !$releaser->isDoctor()) {
            throw new AuthorizationException("Only a qualified Doctor or Administrator can release final diagnostic reports.");
        }

        return DB::transaction(function () use ($order, $releaser, $reportNotes) {
            $order = DiagnosticOrder::where('id', $order->id)->lockForUpdate()->first();

            // Strict gate: Results MUST be verified before report release
            $verifiedResultsCount = $order->results()->whereIn('status', ['VERIFIED', 'RELEASED'])->count();
            if ($verifiedResultsCount === 0) {
                throw new InvalidArgumentException("Cannot release an unverified report. All results must be clinically verified prior to release.");
            }

            $clinic = Clinic::findOrFail($order->clinic_id);
            $reportNumber = DiagnosticSequence::nextReportNumber($clinic);
            $verificationHash = hash('sha256', $order->id . $reportNumber . now()->timestamp);
            $now = Carbon::now();

            $report = DiagnosticReport::updateOrCreate(
                ['diagnostic_order_id' => $order->id],
                [
                    'clinic_id' => $clinic->id,
                    'patient_id' => $order->patient_id,
                    'doctor_id' => $order->doctor_id,
                    'report_number' => $reportNumber,
                    'status' => 'FINAL',
                    'finalized_at' => $now,
                    'finalized_by' => $releaser->id,
                    'released_at' => $now,
                    'released_by' => $releaser->id,
                    'report_notes' => $reportNotes,
                    'verification_hash' => $verificationHash,
                ]
            );

            // Update all verified results to RELEASED
            $order->results()->where('status', 'VERIFIED')->update([
                'status' => 'RELEASED',
                'released_at' => $now,
                'released_by' => $releaser->id,
            ]);

            $order->update(['status' => 'RELEASED']);
            $order->items()->update(['status' => 'RELEASED']);

            $specimen = $order->specimens()->whereNotIn('status', ['REJECTED', 'CANCELLED'])->latest()->first();
            if ($specimen) {
                $specimen->update(['status' => 'RELEASED']);
            }

            AuditLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => $releaser->id,
                'action' => 'DIAGNOSTIC_REPORT_RELEASED',
                'entity_type' => DiagnosticReport::class,
                'entity_id' => $report->id,
                'new_values' => [
                    'report_number' => $report->report_number,
                    'status' => 'FINAL',
                    'verification_hash' => $verificationHash,
                ],
                'reason' => 'Diagnostic report authorized and released',
                'change_category' => 'CLINICAL_WORKFLOW',
            ]);

            return $report->fresh(['clinic', 'patient', 'doctor', 'order.results']);
        });
    }

    /**
     * Controlled Result Correction (Audit Preserved & Versioned)
     */
    public function correctResult(
        DiagnosticResult $currentResult,
        string $newValue,
        string $reason,
        User $user
    ): DiagnosticResult {
        $this->authorizeClinicScope($currentResult->clinic_id, $user);

        if (!$user->isAdmin() && !$user->isDoctor()) {
            throw new AuthorizationException("Only an Administrator or Doctor can perform controlled clinical result corrections.");
        }

        if (strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException("A comprehensive correction reason must be recorded (minimum 10 characters).");
        }

        if (!in_array($currentResult->status, ['VERIFIED', 'RELEASED'])) {
            throw new InvalidArgumentException("Controlled correction applies only to VERIFIED or RELEASED results.");
        }

        return DB::transaction(function () use ($currentResult, $newValue, $reason, $user) {
            $currentResult = DiagnosticResult::where('id', $currentResult->id)->lockForUpdate()->first();
            $oldValue = $currentResult->result_value;

            // Mark old result as superseded/corrected
            $currentResult->update(['status' => 'CORRECTED']);

            $numericVal = is_numeric($newValue) ? (float) $newValue : null;
            $diagItem = $currentResult->diagnosticItem;
            $abnormalFlag = $this->determineAbnormalFlag(
                $currentResult->result_type,
                $numericVal,
                $newValue,
                $currentResult->reference_range,
                $diagItem
            );

            // Create new version
            $newResult = DiagnosticResult::create([
                'clinic_id' => $currentResult->clinic_id,
                'diagnostic_order_id' => $currentResult->diagnostic_order_id,
                'diagnostic_order_item_id' => $currentResult->diagnostic_order_item_id,
                'diagnostic_specimen_id' => $currentResult->diagnostic_specimen_id,
                'diagnostic_item_id' => $currentResult->diagnostic_item_id,
                'patient_id' => $currentResult->patient_id,
                'parameter_name' => $currentResult->parameter_name,
                'result_type' => $currentResult->result_type,
                'result_value' => $newValue,
                'numeric_value' => $numericVal,
                'unit' => $currentResult->unit,
                'reference_range' => $currentResult->reference_range,
                'abnormal_flag' => $abnormalFlag,
                'remarks' => $currentResult->remarks,
                'status' => $currentResult->status === 'RELEASED' ? 'RELEASED' : 'VERIFIED',
                'version' => $currentResult->version + 1,
                'is_corrected' => true,
                'correction_reason' => trim($reason),
                'previous_result_id' => $currentResult->id,
                'entered_at' => $currentResult->entered_at,
                'entered_by' => $currentResult->entered_by,
                'verified_at' => Carbon::now(),
                'verified_by' => $user->id,
                'released_at' => $currentResult->status === 'RELEASED' ? Carbon::now() : null,
                'released_by' => $currentResult->status === 'RELEASED' ? $user->id : null,
                'corrected_at' => Carbon::now(),
                'corrected_by' => $user->id,
            ]);

            AuditLog::create([
                'clinic_id' => $currentResult->clinic_id,
                'user_id' => $user->id,
                'action' => 'DIAGNOSTIC_RESULT_CORRECTED',
                'entity_type' => DiagnosticResult::class,
                'entity_id' => $newResult->id,
                'old_values' => ['value' => $oldValue, 'version' => $currentResult->version],
                'new_values' => ['value' => $newValue, 'version' => $newResult->version],
                'reason' => trim($reason),
                'change_category' => 'CLINICAL_WORKFLOW',
            ]);

            return $newResult;
        });
    }

    /**
     * Automatic Abnormal Flag Evaluator
     */
    protected function determineAbnormalFlag(
        string $resultType,
        ?float $numericVal,
        string $rawVal,
        ?string $refRange,
        ?DiagnosticItem $diagItem
    ): string {
        if ($resultType === 'NUMERIC' && $numericVal !== null) {
            // Check critical bounds first
            if ($diagItem?->critical_low !== null && $numericVal < (float) $diagItem->critical_low) {
                return 'CRITICAL';
            }
            if ($diagItem?->critical_high !== null && $numericVal > (float) $diagItem->critical_high) {
                return 'CRITICAL';
            }

            // Check reference range format: "min - max"
            if ($refRange && preg_match('/([\d\.]+)\s*-\s*([\d\.]+)/', $refRange, $matches)) {
                $min = (float) $matches[1];
                $max = (float) $matches[2];
                if ($numericVal < $min) return 'LOW';
                if ($numericVal > $max) return 'HIGH';
                return 'NORMAL';
            }
        }

        // Qualitative check
        $lower = strtolower($rawVal);
        if (str_contains($lower, 'positive') || str_contains($lower, 'reactive') || str_contains($lower, 'abnormal') || str_contains($lower, 'present')) {
            return 'ABNORMAL';
        }

        return 'NORMAL';
    }

    /**
     * Enforce tenant isolation strictly
     */
    protected function authorizeClinicScope(int $clinicId, User $user): void
    {
        if ($user->role === 'SUPERADMIN') {
            return;
        }

        if ((int) $user->clinic_id !== (int) $clinicId) {
            throw new AuthorizationException("Unauthorized access to clinical resources belonging to another clinic.");
        }
    }
}
