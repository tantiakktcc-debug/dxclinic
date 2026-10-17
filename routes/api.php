<?php

use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Clinic;
use App\Services\CashReconciliationService;
use App\Services\DiagnosticBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DxClinic API Routes (Revision 18 - Section 27, 28, 29)
|--------------------------------------------------------------------------
*/

// 0. Clinic Lookup (Revision 34 Multi-Tenant)
Route::get('/clinic-lookup', function (Request $request): JsonResponse {
    $code = strtoupper(trim((string) $request->query('code', '')));
    if (!$code) {
        return response()->json(['found' => false]);
    }

    $clinic = Clinic::where('clinic_code', $code)->first();
    if (!$clinic) {
        return response()->json(['found' => false]);
    }

    return response()->json([
        'found' => true,
        'clinic_code' => $clinic->clinic_code,
        'name' => $clinic->name,
        'city' => $clinic->city,
        'status' => $clinic->activation_status,
        'is_activated' => $clinic->isActivated(),
    ]);
});

use App\Services\TenantContext;
use Illuminate\Support\Facades\Auth;

// 1. API Health & Operational Status (Public System Health - Non-sensitive)
Route::get('/health', function (): JsonResponse {
    try {
        DB::connection()->getPdo();
        $dbConnected = true;
    } catch (\Throwable $e) {
        $dbConnected = false;
    }

    return response()->json([
        'status' => $dbConnected ? 'ok' : 'degraded',
        'database' => $dbConnected ? 'connected' : 'disconnected',
        'server_time' => now()->toIso8601String(),
    ]);
});

// Authenticated / Tenant-Aware API Endpoints (Requires Web Session or Signed URL)
Route::middleware(['web'])->group(function () {
    // 2. Today's OPD Queue Status (for Digital Display / Reception / PWA)
    Route::get('/queue/today', function (Request $request): JsonResponse {
        $clinic = null;

        // Alternative: Verified signed display URL with clinic_code for dedicated TV display
        if ($request->hasValidSignature() && $request->filled('clinic_code')) {
            $clinic = Clinic::where('clinic_code', strtoupper(trim((string) $request->query('clinic_code'))))->first();
        } elseif (Auth::guard('web')->check() && Auth::guard('web')->user()?->clinic_id) {
            $clinic = Auth::guard('web')->user()->clinic ?? TenantContext::clinic();
        }

        if (!$clinic) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $date = $request->query('date', now()->toDateString());

        $appointments = Appointment::with(['patient:id,patient_id,name,city', 'visit:id,appointment_id,status'])
            ->where('clinic_id', $clinic->id)
            ->whereDate('appointment_date', $date)
            ->orderBy('token_number')
            ->get(['id', 'clinic_id', 'patient_id', 'token_number', 'formatted_token', 'session_type', 'booking_source', 'status']);

        return response()->json([
            'date' => $date,
            'clinic' => $clinic->name,
            'clinic_name' => $clinic->name,
            'clinic_id' => $clinic->id,
            'clinic_code' => $clinic->clinic_code,
            'total_appointments' => $appointments->count(),
            'queue' => $appointments->map(function ($app) {
                return [
                    'token' => $app->formatted_token,
                    'token_number' => $app->token_number,
                    'patient_name' => $app->patient->name,
                    'patient_id' => $app->patient->patient_id,
                    'city' => $app->patient->city,
                    'session' => $app->session_type,
                    'source' => $app->booking_source,
                    'status' => $app->status,
                    'has_arrived' => $app->status === 'ARRIVED',
                ];
            }),
        ]);
    })->name('api.queue.today');

    // 3. Diagnostic Frequently Used Tests Master (Tenant-Scoped)
    Route::get('/diagnostics/frequent', function (Request $request, DiagnosticBillingService $billingService): JsonResponse {
        $limit = (int) $request->query('limit', 12);
        $clinicId = null;
        if (Auth::guard('web')->check() && Auth::guard('web')->user()?->clinic_id) {
            $clinicId = (int) Auth::guard('web')->user()->clinic_id;
        }

        $items = $billingService->getFrequentlyUsed($limit, $clinicId);

        return response()->json([
            'count' => $items->count(),
            'items' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'type' => $item->type,
                    'rate' => (float) $item->rate,
                    'usage_count' => (int) $item->usage_count,
                ];
            }),
        ]);
    })->name('api.diagnostics.frequent');

    // 4. Daily Financial Reconciliation Summary (Authenticated Tenant Only)
    $dailySummaryHandler = function (Request $request, CashReconciliationService $reconService): JsonResponse {
        if (!Auth::guard('web')->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $clinic = Auth::guard('web')->user()?->clinic ?? TenantContext::clinic();
        if (!$clinic) {
            return response()->json(['message' => 'Clinic not configured or unauthorized.'], 403);
        }

        $date = $request->query('date', now()->toDateString());
        $report = $reconService->getDailyReport($clinic, $date);

        return response()->json([
            'date' => $date,
            'clinic' => $clinic->name,
            'clinic_name' => $clinic->name,
            'clinic_id' => $clinic->id,
            'clinic_code' => $clinic->clinic_code,
            'opening_bank_balance' => (float) ($report['total_opening_bank_balance'] ?? 0),
            'opening_cash_in_hand' => (float) ($report['cash_control']['opening_cash'] ?? 0),
            'cash_collections' => (float) ($report['cash_control']['cash_collections'] ?? 0),
            'upi_collections' => (float) ($report['bank_control']['upi_collected'] ?? 0),
            'expenses' => (float) ($report['expenses']['total_expenses'] ?? 0),
            'bank_deposits' => (float) ($report['cash_control']['cash_deposited_to_bank'] ?? 0),
            'expected_closing_cash' => (float) ($report['cash_control']['expected_closing_cash'] ?? 0),
            'physical_closing_cash' => (float) ($report['cash_control']['physical_closing_cash'] ?? 0),
            'cash_variance' => (float) ($report['cash_control']['variance'] ?? 0),
            'is_balanced' => abs($report['cash_control']['variance'] ?? 0) < 0.01,
        ]);
    };

    Route::get('/reports/daily-summary', $dailySummaryHandler)->name('api.reports.daily-summary');
    Route::get('/daily-summary', $dailySummaryHandler)->name('api.daily-summary');
});

// WhatsApp Webhook Endpoints (Contract Section 9, 10) — Meta Cloud API
Route::prefix('v1/webhooks/whatsapp')->group(function () {
    Route::get('/', [\App\Http\Controllers\WhatsAppWebhookController::class, 'verify'])->name('api.whatsapp.webhook.verify');
    Route::post('/', [\App\Http\Controllers\WhatsAppWebhookController::class, 'handle'])->name('api.whatsapp.webhook.handle');
});

// Baileys Development Gateway — Internal Endpoint (127.0.0.1:9090 → Laravel only)
// SECURITY: This route is bound to 127.0.0.1 by the Baileys gateway.
//           NEVER expose this route via Cloudflare or public reverse proxy.
//           Protected by HMAC-SHA256 gateway signature validation.
Route::prefix('internal/baileys')->middleware(['throttle:600,1'])->group(function () {
    Route::post('/webhook', [\App\Http\Controllers\BaileysGatewayWebhookController::class, 'handle'])
        ->name('api.baileys.gateway.webhook');
});
