<?php

namespace App\Services;

use App\Models\DiagnosticBill;
use App\Models\DiagnosticBillItem;
use App\Models\DiagnosticItem;
use App\Models\Patient;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DiagnosticBillingService
{
    /**
     * Create a diagnostic bill with snapshotted items (Section 65, 67, 73)
     * Billed items must reflect doctor's written instructions.
     */
    public function createBill(Patient $patient, ?Visit $visit, array $itemIdsWithQty, float $discount = 0.00, float $waiver = 0.00): DiagnosticBill
    {
        if (empty($itemIdsWithQty)) {
            throw new InvalidArgumentException("At least one diagnostic test or package must be selected.");
        }

        return DB::transaction(function () use ($patient, $visit, $itemIdsWithQty, $discount, $waiver) {
            $clinicId = $patient->clinic_id;
            $billNumber = 'BILL-D-' . date('Ymd') . '-' . str_pad((string) rand(100, 9999), 4, '0', STR_PAD_LEFT);

            // Compute line items with snapshotted rates
            $lineItems = [];
            $grossTotal = 0.0;

            foreach ($itemIdsWithQty as $entry) {
                $itemId = (int) ($entry['item_id'] ?? 0);
                $qty = max(1, (int) ($entry['quantity'] ?? 1));

                $diagnosticItem = DiagnosticItem::findOrFail($itemId);
                $rate = (float) $diagnosticItem->rate;
                $amount = round($rate * $qty, 2);

                $lineItems[] = [
                    'item' => $diagnosticItem,
                    'qty' => $qty,
                    'rate' => $rate,
                    'amount' => $amount,
                ];

                $grossTotal += $amount;

                // Update deterministic usage counter and recency (Section 66)
                $diagnosticItem->increment('usage_count');
                $diagnosticItem->update(['last_used_at' => Carbon::now()]);
            }

            $grossTotal = round($grossTotal, 2);
            $netTotal = round($grossTotal - $discount - $waiver, 2);

            if (!DiagnosticBill::validateInvariants($grossTotal, $discount, $waiver, $netTotal)) {
                throw new InvalidArgumentException("Financial invariant violated: net ({$netTotal}) must equal gross ({$grossTotal}) - discount ({$discount}) - waiver ({$waiver}).");
            }

            $bill = DiagnosticBill::create([
                'clinic_id' => $clinicId,
                'bill_number' => $billNumber,
                'visit_id' => $visit?->id,
                'patient_id' => $patient->id,
                'doctor_id' => $visit?->doctor_id,
                'bill_date' => Carbon::now()->toDateString(),
                'gross_amount' => $grossTotal,
                'discount_amount' => $discount,
                'waiver_amount' => $waiver,
                'net_amount' => $netTotal,
                'status' => 'PENDING',
            ]);

            foreach ($lineItems as $line) {
                DiagnosticBillItem::create([
                    'diagnostic_bill_id' => $bill->id,
                    'diagnostic_item_id' => $line['item']->id,
                    'item_type' => $line['item']->type,
                    'item_name' => $line['item']->name,
                    'rate' => $line['rate'],
                    'quantity' => $line['qty'],
                    'amount' => $line['amount'],
                ]);
            }

            // Initialize operational laboratory order (Revision 36)
            try {
                app(\App\Services\DiagnosticLabService::class)->createOrderFromBill($bill);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("createOrderFromBill error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            }

            return $bill->fresh(['items']);
        });
    }

    /**
     * Get deterministic frequently used diagnostic items for Quick Add (Section 66)
     * Scoped to global platform catalog plus tenant-owned items, strictly excluding foreign clinics.
     */
    public function getFrequentlyUsed(int $limit = 10, ?int $clinicId = null)
    {
        if ($clinicId === null && \Illuminate\Support\Facades\Auth::guard('web')->check()) {
            $clinicId = \Illuminate\Support\Facades\Auth::guard('web')->user()?->clinic_id;
        }

        $query = DiagnosticItem::frequent($limit);

        if ($clinicId) {
            $query->where(function ($q) use ($clinicId) {
                $q->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            });
        } else {
            $query->whereNull('clinic_id');
        }

        return $query->get();
    }
}
