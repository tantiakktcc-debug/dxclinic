<?php

namespace App\Services;

use App\Models\ClinicDoctorSeatAddon;
use App\Models\License;
use App\Models\LicenseRenewal;
use App\Models\PlatformInvoiceItem;
use App\Models\PlatformInvoiceSequence;
use App\Models\PlatformSalesInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformInvoiceService
{
    /**
     * Generate or return existing invoice for an Initial License
     */
    public static function createForInitialLicense(License $license, ?int $createdBy = null): PlatformSalesInvoice
    {
        // 1. Idempotency Check: strictly one invoice per source transaction
        $existing = PlatformSalesInvoice::where('source_type', 'INITIAL_LICENSE')
            ->where('source_id', $license->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($license, $createdBy) {
            // Re-check inside transaction lock
            $existing = PlatformSalesInvoice::where('source_type', 'INITIAL_LICENSE')
                ->where('source_id', $license->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $date = $license->issued_at ? Carbon::parse($license->issued_at) : Carbon::today();
            $invoiceNumber = PlatformInvoiceSequence::nextInvoiceNumber($date);
            $plan = $license->plan;

            $baseAmount = (float) $license->amount;
            $addonAmount = (float) ($license->additional_doctor_amount ?? 0.00);
            $subtotal = $baseAmount + $addonAmount;
            $discount = (float) ($license->discount_amount ?? 0.00);
            $netAmount = max(0.00, $subtotal - $discount);

            // Determine payment status
            $isPaid = in_array(strtoupper($license->status), ['ACTIVE', 'TRIAL']) || $netAmount <= 0.00 || !empty($license->payment_date);
            $status = $isPaid ? 'PAID' : 'ISSUED';
            $paymentMethod = $license->payment_method ?: ($netAmount <= 0.00 ? 'FREE_TRIAL' : 'CASH');
            $paymentDate = $license->payment_date ?: ($isPaid ? $date->toDateString() : null);

            $invoice = PlatformSalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $date->toDateString(),
                'clinic_id' => $license->clinic_id,
                'license_id' => $license->id,
                'sale_type' => 'INITIAL_LICENSE',
                'source_type' => 'INITIAL_LICENSE',
                'source_id' => $license->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'net_amount' => $netAmount,
                'status' => $status,
                'payment_method' => $paymentMethod,
                'payment_reference' => $license->payment_reference,
                'payment_date' => $paymentDate,
                'notes' => $license->notes ?: "Initial platform subscription license ({$plan?->name})",
                'created_by' => $createdBy ?: $license->issued_by,
            ]);

            // Line Item 1: Base Plan
            PlatformInvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_type' => 'BASE_PLAN',
                'description' => "Base Plan: " . ($plan ? "{$plan->name} ({$plan->duration_months} Months)" : 'Subscription Plan'),
                'quantity' => 1,
                'unit_price' => $baseAmount,
                'amount' => $baseAmount,
            ]);

            // Line Item 2: Additional Doctor Seats (if any)
            if (($license->additional_doctor_seats ?? 0) > 0) {
                $feePerSeat = (float) ($license->additional_doctor_fee_per_year ?: 2999.00);
                PlatformInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'ADDITIONAL_DOCTOR_SEATS',
                    'description' => "Additional Doctor Seats ({$license->additional_doctor_seats} seat(s) @ ₹" . number_format($feePerSeat, 2) . "/yr)",
                    'quantity' => (int) $license->additional_doctor_seats,
                    'unit_price' => $feePerSeat,
                    'amount' => $addonAmount,
                ]);
            }

            // Line Item 3: Trial = ₹0 (if TRIAL plan or trial duration noted)
            if ($plan && (strtoupper($plan->code) === 'TRIAL' || str_contains(strtolower($license->notes ?? ''), 'trial'))) {
                PlatformInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'TRIAL',
                    'description' => "Complimentary Trial Period (₹0)",
                    'quantity' => 1,
                    'unit_price' => 0.00,
                    'amount' => 0.00,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * Generate or return existing invoice for a License Renewal
     */
    public static function createForRenewal(LicenseRenewal $renewal, ?int $createdBy = null): PlatformSalesInvoice
    {
        // Idempotency check
        $existing = PlatformSalesInvoice::where('source_type', 'LICENSE_RENEWAL')
            ->where('source_id', $renewal->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($renewal, $createdBy) {
            $existing = PlatformSalesInvoice::where('source_type', 'LICENSE_RENEWAL')
                ->where('source_id', $renewal->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $date = $renewal->created_at ? Carbon::parse($renewal->created_at) : Carbon::today();
            $invoiceNumber = PlatformInvoiceSequence::nextInvoiceNumber($date);
            $plan = $renewal->plan;

            $baseAmount = (float) $renewal->base_amount;
            $totalDoctorSeatAmount = (float) $renewal->total_doctor_seat_amount;
            $subtotal = $baseAmount + $totalDoctorSeatAmount;
            $discount = (float) $renewal->discount_amount;
            $netAmount = (float) $renewal->net_amount;

            $status = $renewal->payment_received ? 'PAID' : 'ISSUED';
            $paymentDate = $renewal->payment_date ? Carbon::parse($renewal->payment_date)->toDateString() : ($status === 'PAID' ? $date->toDateString() : null);

            $invoice = PlatformSalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $date->toDateString(),
                'clinic_id' => $renewal->clinic_id,
                'license_id' => $renewal->license_id,
                'sale_type' => 'LICENSE_RENEWAL',
                'source_type' => 'LICENSE_RENEWAL',
                'source_id' => $renewal->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'net_amount' => $netAmount,
                'status' => $status,
                'payment_method' => $renewal->payment_method ?: 'CASH',
                'payment_reference' => $renewal->payment_reference,
                'payment_date' => $paymentDate,
                'notes' => $renewal->notes ?: "License renewal (#{$renewal->renewal_number}) for {$renewal->duration_months} months",
                'created_by' => $createdBy ?: $renewal->renewed_by,
            ]);

            // Line Item 1: Renewal Plan
            PlatformInvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_type' => 'RENEWAL_PLAN',
                'description' => "Renewal Plan: " . ($plan ? "{$plan->name} ({$renewal->duration_months} Months)" : 'Subscription Plan'),
                'quantity' => 1,
                'unit_price' => $baseAmount,
                'amount' => $baseAmount,
            ]);

            // Line Item 2: Existing Doctor Seats Renewed
            if ($renewal->existing_doctor_seats_renewed > 0) {
                $unitPrice = $renewal->existing_doctor_seats_renewed > 0
                    ? round((float) $renewal->existing_doctor_seat_amount / $renewal->existing_doctor_seats_renewed, 2)
                    : 2999.00;

                PlatformInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'EXISTING_DOCTOR_SEATS',
                    'description' => "Existing Doctor Seat Renewal ({$renewal->existing_doctor_seats_renewed} seat(s))",
                    'quantity' => (int) $renewal->existing_doctor_seats_renewed,
                    'unit_price' => $unitPrice,
                    'amount' => (float) $renewal->existing_doctor_seat_amount,
                ]);
            }

            // Line Item 3: New Doctor Seats Purchased
            if ($renewal->new_doctor_seats_purchased > 0) {
                $unitPrice = $renewal->new_doctor_seats_purchased > 0
                    ? round((float) $renewal->new_doctor_seat_amount / $renewal->new_doctor_seats_purchased, 2)
                    : 2999.00;

                PlatformInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'NEW_DOCTOR_SEATS',
                    'description' => "New Doctor Seats Purchased ({$renewal->new_doctor_seats_purchased} seat(s))",
                    'quantity' => (int) $renewal->new_doctor_seats_purchased,
                    'unit_price' => $unitPrice,
                    'amount' => (float) $renewal->new_doctor_seat_amount,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * Generate or return existing invoice for a Mid-Term Doctor Seat Add-on
     */
    public static function createForSeatAddon(ClinicDoctorSeatAddon $addon, ?int $createdBy = null): PlatformSalesInvoice
    {
        // Idempotency check
        $existing = PlatformSalesInvoice::where('source_type', 'DOCTOR_ADDON')
            ->where('source_id', $addon->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($addon, $createdBy) {
            $existing = PlatformSalesInvoice::where('source_type', 'DOCTOR_ADDON')
                ->where('source_id', $addon->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $date = $addon->purchase_date ? Carbon::parse($addon->purchase_date) : Carbon::today();
            $invoiceNumber = PlatformInvoiceSequence::nextInvoiceNumber($date);

            $price = (float) $addon->price;
            $seats = max(1, (int) $addon->seats_count);
            $unitPrice = round($price / $seats, 2);

            $invoice = PlatformSalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $date->toDateString(),
                'clinic_id' => $addon->clinic_id,
                'license_id' => $addon->license_id,
                'sale_type' => 'DOCTOR_ADDON',
                'source_type' => 'DOCTOR_ADDON',
                'source_id' => $addon->id,
                'subtotal' => $price,
                'discount' => 0.00,
                'net_amount' => $price,
                'status' => 'PAID',
                'payment_method' => $addon->payment_method ?: 'CASH',
                'payment_reference' => $addon->payment_reference,
                'payment_date' => $addon->payment_date ? Carbon::parse($addon->payment_date)->toDateString() : $date->toDateString(),
                'notes' => $addon->notes ?: "Mid-term doctor seat add-on ({$seats} seat(s))",
                'created_by' => $createdBy ?: $addon->created_by_developer_user_id,
            ]);

            $validUntilStr = $addon->valid_until ? Carbon::parse($addon->valid_until)->format('d M Y') : '1 Year';

            PlatformInvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_type' => 'DOCTOR_ADDON',
                'description' => "Mid-Term Doctor Seat Add-on ({$seats} seat(s), valid until {$validUntilStr})",
                'quantity' => $seats,
                'unit_price' => $unitPrice,
                'amount' => $price,
            ]);

            return $invoice;
        });
    }

    /**
     * Cancel an invoice using controlled cancellation/credit-note pattern (Immutability compliant)
     */
    public static function cancelInvoice(PlatformSalesInvoice $invoice, string $reason, ?int $cancelledBy = null): PlatformSalesInvoice
    {
        if ($invoice->isCancelled()) {
            return $invoice;
        }

        $invoice->status = 'CANCELLED';
        $invoice->cancellation_reason = $reason;
        $invoice->cancelled_at = now();
        $invoice->cancelled_by = $cancelledBy;
        $invoice->save();

        return $invoice;
    }

    /**
     * Reconcile/backfill invoices for existing authoritative commercial records
     */
    public static function reconcileExistingCommercialRecords(): array
    {
        $createdLicenses = 0;
        $createdRenewals = 0;
        $createdAddons = 0;

        // 1. Initial Licenses
        $licenses = License::where('is_renewal', false)->get();
        foreach ($licenses as $license) {
            $existing = PlatformSalesInvoice::where('source_type', 'INITIAL_LICENSE')
                ->where('source_id', $license->id)
                ->first();

            if (!$existing) {
                self::createForInitialLicense($license);
                $createdLicenses++;
            }
        }

        // 2. Renewals
        $renewals = LicenseRenewal::all();
        foreach ($renewals as $renewal) {
            $existing = PlatformSalesInvoice::where('source_type', 'LICENSE_RENEWAL')
                ->where('source_id', $renewal->id)
                ->first();

            if (!$existing) {
                self::createForRenewal($renewal);
                $createdRenewals++;
            }
        }

        // 3. Add-ons
        $addons = ClinicDoctorSeatAddon::all();
        foreach ($addons as $addon) {
            $existing = PlatformSalesInvoice::where('source_type', 'DOCTOR_ADDON')
                ->where('source_id', $addon->id)
                ->first();

            if (!$existing) {
                self::createForSeatAddon($addon);
                $createdAddons++;
            }
        }

        return [
            'licenses' => $createdLicenses,
            'renewals' => $createdRenewals,
            'addons' => $createdAddons,
            'total' => $createdLicenses + $createdRenewals + $createdAddons,
        ];
    }
}
