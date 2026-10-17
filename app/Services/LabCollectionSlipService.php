<?php

namespace App\Services;

use App\Models\DiagnosticBill;

class LabCollectionSlipService
{
    /**
     * Generate operational lab collection slip data (Section 70, 79)
     * Contains only patient and test requisition data. No result/sample tracking.
     */
    public function getSlipData(DiagnosticBill $bill): array
    {
        $bill->loadMissing(['clinic', 'patient', 'items']);

        return [
            'clinic_name' => $bill->clinic->name,
            'clinic_address' => $bill->clinic->address,
            'clinic_city' => $bill->clinic->city,
            'bill_number' => $bill->bill_number,
            'date' => $bill->bill_date->format('d/m/Y'),
            'patient_id' => $bill->patient->patient_id,
            'patient_name' => $bill->patient->name,
            'age_sex' => "{$bill->patient->age} Y / {$bill->patient->sex}",
            'mobile' => $bill->patient->mobile,
            'address' => $bill->patient->formatted_address,
            'items' => $bill->items->map(fn($item) => [
                'name' => $item->item_name,
                'type' => $item->item_type,
                'quantity' => $item->quantity,
            ])->toArray(),
            'slip_code' => 'LAB-' . strtoupper(substr(md5($bill->id . $bill->bill_number), 0, 8)),
        ];
    }
}
