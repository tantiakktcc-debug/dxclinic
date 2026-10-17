<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicLookupController extends Controller
{
    /**
     * Minimal clinic confirmation lookup for login screen.
     * Rate limited and does NOT expose owner, phone, address, or license details.
     */
    public function lookup(string $clinic_code): JsonResponse
    {
        $clinic = Clinic::where('clinic_code', strtoupper(trim($clinic_code)))->first();

        if (!$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'Clinic not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'clinic_code' => $clinic->clinic_code,
            'clinic_name' => $clinic->name,
            'city' => $clinic->city,
        ]);
    }
}
