<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    /**
     * Generate or retrieve existing receipt for a payment (Section 60, 69, 79)
     * Reprinting does NOT create a new payment or receipt entity.
     */
    public function generateReceipt(Payment $payment, string $receiptType, int $patientId): Receipt
    {
        return DB::transaction(function () use ($payment, $receiptType, $patientId) {
            $existing = Receipt::where('payment_id', $payment->id)->first();
            if ($existing) {
                return $existing;
            }

            $prefix = $receiptType === 'CONSULTATION' ? 'RCP-C-' : 'RCP-D-';
            $receiptNumber = $prefix . date('Ymd') . '-' . str_pad((string) rand(100, 9999), 4, '0', STR_PAD_LEFT);

            return Receipt::create([
                'clinic_id' => $payment->clinic_id,
                'receipt_number' => $receiptNumber,
                'payment_id' => $payment->id,
                'receipt_type' => $receiptType,
                'patient_id' => $patientId,
                'amount' => $payment->amount,
                'issued_at' => Carbon::now(),
            ]);
        });
    }
}
