<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\DiagnosticBillItem;
use App\Models\DiagnosticItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillModificationService
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * Modify a PENDING Consultation Bill before payment settlement
     */
    public function modifyPendingConsultationBill(ConsultationBill $bill, array $data, ?User $user = null): ConsultationBill
    {
        $user = $user ?? auth()->user();
        $reason = $this->refundService->validateReason($data['reason'] ?? null);

        return DB::transaction(function () use ($bill, $data, $user, $reason) {
            $bill = ConsultationBill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if ($bill->status !== 'PENDING') {
                throw new InvalidArgumentException("Only PENDING consultation bills can be modified in-place. For settled bills, initiate a controlled refund or adjustment.");
            }

            $oldValues = $bill->only(['gross_amount', 'discount_amount', 'waiver_amount', 'net_amount', 'waiver_type']);

            $gross = isset($data['gross_amount']) ? round((float) $data['gross_amount'], 2) : (float) $bill->gross_amount;
            $discount = isset($data['discount_amount']) ? round((float) $data['discount_amount'], 2) : (float) $bill->discount_amount;
            $waiver = isset($data['waiver_amount']) ? round((float) $data['waiver_amount'], 2) : (float) $bill->waiver_amount;
            $net = round($gross - $discount - $waiver, 2);

            if ($net < 0) {
                throw new InvalidArgumentException("Net amount cannot be negative.");
            }

            $bill->update([
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'waiver_amount' => $waiver,
                'net_amount' => $net,
                'waiver_type' => $data['waiver_type'] ?? $bill->waiver_type,
            ]);

            $newValues = $bill->only(['gross_amount', 'discount_amount', 'waiver_amount', 'net_amount', 'waiver_type']);

            AuditLog::log(
                action: 'BILL_MODIFIED',
                entityType: ConsultationBill::class,
                entityId: $bill->id,
                newValues: $newValues,
                oldValues: $oldValues,
                reason: $reason,
                changeCategory: 'BILL_CORRECTION',
                financialImpact: "Updated pending consultation net from ₹{$oldValues['net_amount']} to ₹{$newValues['net_amount']}"
            );

            return $bill->fresh();
        });
    }

    /**
     * Modify a PENDING Diagnostic Bill before payment settlement
     */
    public function modifyPendingDiagnosticBill(DiagnosticBill $bill, array $itemsData, ?float $discountAmount = null, ?string $reason = null, ?User $user = null): DiagnosticBill
    {
        $user = $user ?? auth()->user();
        $validatedReason = $this->refundService->validateReason($reason);

        return DB::transaction(function () use ($bill, $itemsData, $discountAmount, $validatedReason, $user) {
            $bill = DiagnosticBill::with('items')->where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if ($bill->status !== 'PENDING') {
                throw new InvalidArgumentException("Only PENDING diagnostic bills can be modified in-place. For settled bills, initiate a refund or supplementary bill.");
            }

            $oldValues = [
                'gross_amount' => (float) $bill->gross_amount,
                'discount_amount' => (float) $bill->discount_amount,
                'net_amount' => (float) $bill->net_amount,
                'items_count' => $bill->items->count(),
            ];

            // Reconstruct items
            $bill->items()->delete();

            $grossAmount = 0.00;
            foreach ($itemsData as $itemRow) {
                $itemObj = DiagnosticItem::findOrFail($itemRow['item_id']);
                $qty = max(1, (int) ($itemRow['quantity'] ?? 1));
                $rate = round((float) ($itemRow['rate'] ?? $itemObj->rate), 2);
                $amount = round($qty * $rate, 2);
                $grossAmount += $amount;

                DiagnosticBillItem::create([
                    'diagnostic_bill_id' => $bill->id,
                    'diagnostic_item_id' => $itemObj->id,
                    'item_type' => $itemObj->type,
                    'item_name' => $itemObj->name,
                    'rate' => $rate,
                    'quantity' => $qty,
                    'amount' => $amount,
                ]);
            }

            $discount = $discountAmount !== null ? round((float) $discountAmount, 2) : (float) $bill->discount_amount;
            $netAmount = max(0.00, round($grossAmount - $discount - (float)$bill->waiver_amount, 2));

            $bill->update([
                'gross_amount' => $grossAmount,
                'discount_amount' => $discount,
                'net_amount' => $netAmount,
            ]);

            $newValues = [
                'gross_amount' => $grossAmount,
                'discount_amount' => $discount,
                'net_amount' => $netAmount,
                'items_count' => count($itemsData),
            ];

            AuditLog::log(
                action: 'BILL_MODIFIED',
                entityType: DiagnosticBill::class,
                entityId: $bill->id,
                newValues: $newValues,
                oldValues: $oldValues,
                reason: $validatedReason,
                changeCategory: 'ITEM_CORRECTION',
                financialImpact: "Modified pending diagnostic bill items. Net: ₹{$netAmount}"
            );

            return $bill->fresh(['items']);
        });
    }

    /**
     * Create a Supplementary Diagnostic Bill when additional tests/charges are legitimately required after settlement
     */
    public function createSupplementaryDiagnosticBill(DiagnosticBill $originalBill, array $itemsData, string $reason, ?User $user = null): DiagnosticBill
    {
        $user = $user ?? auth()->user();
        $validatedReason = $this->refundService->validateReason($reason);

        return DB::transaction(function () use ($originalBill, $itemsData, $validatedReason, $user) {
            // Count existing supplements for this bill
            $suppCount = DB::table('diagnostic_bills')
                ->where('bill_number', 'LIKE', "{$originalBill->bill_number}-SUPP-%")
                ->count();
            $suppNumber = $suppCount + 1;
            $supplementaryBillNumber = "{$originalBill->bill_number}-SUPP-{$suppNumber}";

            $grossAmount = 0.00;
            $itemRowsToCreate = [];

            foreach ($itemsData as $row) {
                $itemObj = DiagnosticItem::findOrFail($row['item_id']);
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                $rate = round((float) ($row['rate'] ?? $itemObj->rate), 2);
                $amount = round($qty * $rate, 2);
                $grossAmount += $amount;

                $itemRowsToCreate[] = [
                    'diagnostic_item_id' => $itemObj->id,
                    'item_type' => $itemObj->type,
                    'item_name' => $itemObj->name,
                    'rate' => $rate,
                    'quantity' => $qty,
                    'amount' => $amount,
                ];
            }

            if ($grossAmount <= 0) {
                throw new InvalidArgumentException("Supplementary bill amount must be greater than zero.");
            }

            $supplementaryBill = DiagnosticBill::create([
                'clinic_id' => $originalBill->clinic_id,
                'bill_number' => $supplementaryBillNumber,
                'visit_id' => $originalBill->visit_id,
                'patient_id' => $originalBill->patient_id,
                'doctor_id' => $originalBill->doctor_id,
                'bill_date' => Carbon::now()->toDateString(),
                'gross_amount' => $grossAmount,
                'discount_amount' => 0.00,
                'waiver_amount' => 0.00,
                'net_amount' => $grossAmount,
                'status' => 'PENDING',
            ]);

            foreach ($itemRowsToCreate as $itemData) {
                $itemData['diagnostic_bill_id'] = $supplementaryBill->id;
                DiagnosticBillItem::create($itemData);
            }

            AuditLog::log(
                action: 'SUPPLEMENTARY_BILL_CREATED',
                entityType: DiagnosticBill::class,
                entityId: $supplementaryBill->id,
                newValues: [
                    'bill_number' => $supplementaryBillNumber,
                    'original_bill_id' => $originalBill->id,
                    'original_bill_number' => $originalBill->bill_number,
                    'supplementary_amount' => $grossAmount,
                ],
                oldValues: null,
                reason: $validatedReason,
                changeCategory: 'SUPPLEMENTARY_BILL',
                financialImpact: "Created supplementary diagnostic bill #{$supplementaryBillNumber} for ₹" . number_format($grossAmount, 2)
            );

            return $supplementaryBill->fresh(['items']);
        });
    }
}
