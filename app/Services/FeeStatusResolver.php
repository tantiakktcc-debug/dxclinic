<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ConsultationBill;

class FeeStatusResolver
{
    /**
     * Resolves authoritative Fee status for an appointment based ONLY
     * on existing authoritative billing and payment records.
     *
     * Returns: 'PAID' | 'UNPAID' | 'PARTIAL' | 'REFUNDED'
     */
    public function resolve(Appointment $appointment): string
    {
        // Check direct consultation bill or visit consultation bill
        /** @var ConsultationBill|null $bill */
        $bill = $appointment->consultationBill ?? $appointment->visit?->consultationBill;

        if (!$bill) {
            return 'UNPAID';
        }

        // Check if bill itself is marked REFUNDED
        if ($bill->status === 'REFUNDED') {
            return 'REFUNDED';
        }

        // Calculate settled payments and processed refunds
        // Use loaded relationships if present to prevent N+1 queries
        $payments = $bill->relationLoaded('payments')
            ? $bill->payments
            : ($bill->relationLoaded('payment') && $bill->payment ? collect([$bill->payment]) : $bill->payments()->get());

        $refunds = $bill->relationLoaded('refunds')
            ? $bill->refunds
            : $bill->refunds()->get();

        $settledPaid = (float) $payments->where('status', 'SETTLED')->sum('amount');
        $processedRefunded = (float) $refunds->where('status', 'PROCESSED')->sum('refund_amount');

        // If refunds cover the settled payment amount
        if ($settledPaid > 0 && $processedRefunded >= $settledPaid) {
            return 'REFUNDED';
        }

        // If partially refunded
        if ($processedRefunded > 0 && $processedRefunded < $settledPaid) {
            return 'PARTIAL';
        }

        // If no payment was made yet
        if ($settledPaid <= 0) {
            // Waived bill or 0-amount bill marked PAID
            if ($bill->status === 'PAID' || ($bill->status === 'WAIVED' && (float) $bill->net_amount <= 0.0)) {
                return 'PAID';
            }
            return 'UNPAID';
        }

        // Payment was made: check against net_amount
        $netAmount = (float) $bill->net_amount;
        if ($settledPaid >= $netAmount || $bill->status === 'PAID') {
            return 'PAID';
        }

        if ($settledPaid < $netAmount) {
            return 'PARTIAL';
        }

        return 'UNPAID';
    }
}
