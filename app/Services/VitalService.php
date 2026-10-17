<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\Vital;

class VitalService
{
    /**
     * Record or update vitals for a visit (Section 56)
     */
    public function recordVitals(Visit $visit, array $data): Vital
    {
        $filtered = [
            'weight' => !empty($data['weight']) ? (float) $data['weight'] : null,
            'height' => !empty($data['height']) ? (float) $data['height'] : null,
            'bp_systolic' => !empty($data['bp_systolic']) ? (int) $data['bp_systolic'] : null,
            'bp_diastolic' => !empty($data['bp_diastolic']) ? (int) $data['bp_diastolic'] : null,
            'pulse' => !empty($data['pulse']) ? (int) $data['pulse'] : null,
            'spo2' => !empty($data['spo2']) ? (int) $data['spo2'] : null,
            'temperature' => !empty($data['temperature']) ? (float) $data['temperature'] : null,
            'fever' => !empty($data['fever']) ? (bool) $data['fever'] : false,
            'respiratory_rate' => !empty($data['respiratory_rate']) ? (int) $data['respiratory_rate'] : null,
            'rbs' => !empty($data['rbs']) ? (float) $data['rbs'] : null,
        ];

        return Vital::updateOrCreate(
            ['visit_id' => $visit->id],
            $filtered
        );
    }
}
