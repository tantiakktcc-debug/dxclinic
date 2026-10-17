<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicDoctorSeatAddon;
use App\Models\Doctor;
use App\Models\LicenseRequest;
use App\Services\LicenseRequestService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class LicenseSubscriptionManager extends Component
{
    public int $addonSeatsCount = 1;
    public ?string $purchaseSuccessMessage = null;
    public ?string $purchaseErrorMessage = null;

    /**
     * Request additional doctor seats.
     * Creates an authoritative LicenseRequest with type DOCTOR_SEAT_ADDON in PENDING status.
     * Does NOT immediately activate capacity. Instructs user to contact developer.
     */
    public function requestDoctorSeatAddon(): void
    {
        $user = auth()->user();
        abort_unless($user, 401, 'Unauthenticated.');
        abort_unless(in_array($user->role, ['ADMIN', 'SUPERADMIN'], true), 403, 'Unauthorized: Only clinic administrators may request doctor seat add-ons.');

        $clinic = TenantContext::clinic() ?? $user->clinic;
        abort_unless($clinic, 404, 'Clinic context not found.');

        $this->validate([
            'addonSeatsCount' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        try {
            $request = LicenseRequestService::submitDoctorSeatAddonRequest(
                ['addon_seats_count' => $this->addonSeatsCount],
                $user,
                $clinic
            );

            // Audit Trail
            AuditLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'action' => 'DOCTOR_SEAT_ADDON_REQUESTED',
                'entity_type' => LicenseRequest::class,
                'entity_id' => $request->id,
                'new_values' => [
                    'request_number' => $request->request_number,
                    'requested_seats' => $this->addonSeatsCount,
                    'net_amount' => $request->net_amount,
                    'status' => 'PENDING',
                ],
                'ip_address' => request()?->ip(),
                'reason' => "Administrator submitted request for +{$this->addonSeatsCount} doctor seat(s)",
                'change_category' => 'ADMINISTRATIVE',
            ]);

            $this->purchaseSuccessMessage = "Additional doctor seat request submitted. Please contact the application developer to make payment and activate the requested doctor seat(s).";
            $this->purchaseErrorMessage = null;
        } catch (\Throwable $e) {
            $this->purchaseErrorMessage = $e->getMessage();
            $this->purchaseSuccessMessage = null;
        }
    }

    /**
     * Backward-compatible alias for requestDoctorSeatAddon.
     */
    public function purchaseDoctorSeatAddon(): void
    {
        $this->requestDoctorSeatAddon();
    }

    public function simulateCancelledPayment(): void
    {
        $this->purchaseErrorMessage = 'Payment was cancelled or failed. Your doctor seat entitlement has not been changed.';
        $this->purchaseSuccessMessage = null;
    }

    public function render()
    {
        $clinic = TenantContext::clinic() ?? auth()->user()?->clinic;
        $license = $clinic?->activeLicense ?? $clinic?->latestLicense;
        $doctors = $clinic ? $clinic->doctors()->where('is_active', true)->orderBy('name')->get() : collect();

        $remainingDays = $license ? $license->remainingDays() : 0;
        $remainingFormatted = $license ? $license->remainingDaysFormatted() : 'No License';

        $pricePerSeat = (float) ($license?->plan?->additional_doctor_seat_price ?? 2999.00);
        $totalPayable = $pricePerSeat * $this->addonSeatsCount;
        $currentCapacity = $clinic ? $clinic->totalDoctorCapacity() : 1;
        $resultingCapacity = $currentCapacity + $this->addonSeatsCount;

        // Pending Doctor Seat Add-on Requests
        $pendingSeatRequest = $clinic ? LicenseRequest::where('clinic_id', $clinic->id)
            ->where('request_type', 'DOCTOR_SEAT_ADDON')
            ->whereIn('status', ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING'])
            ->latest('id')
            ->first() : null;

        $pendingRequestedSeats = $pendingSeatRequest ? (int) ($pendingSeatRequest->additional_doctor_count ?: $pendingSeatRequest->requested_doctor_count ?: 0) : 0;
        $activeAddonsCount = $clinic ? (int) $clinic->activeDoctorSeatAddons()->sum('seats_count') : 0;

        $developerInfo = [
            'name' => 'Kundan Kumar',
            'company' => 'Nexora Solutions',
            'location' => 'Varanasi, Uttar Pradesh, India',
            'phone' => config('dxclinic.developer_phone') ?: '+919876543210',
            'email' => config('dxclinic.developer_email', 'contact@nexorasolutionsind.co.in'),
            'website' => config('dxclinic.developer_website', 'https://nexorasolutionsind.co.in/'),
        ];

        return view('livewire.settings.license-subscription-manager', [
            'clinic' => $clinic,
            'license' => $license,
            'doctors' => $doctors,
            'remainingDays' => $remainingDays,
            'remainingFormatted' => $remainingFormatted,
            'pricePerSeat' => $pricePerSeat,
            'totalPayable' => $totalPayable,
            'currentCapacity' => $currentCapacity,
            'resultingCapacity' => $resultingCapacity,
            'pendingSeatRequest' => $pendingSeatRequest,
            'pendingRequestedSeats' => $pendingRequestedSeats,
            'activeAddonsCount' => $activeAddonsCount,
            'developerInfo' => $developerInfo,
            'canPurchaseSeats' => in_array(auth()->user()?->role, ['ADMIN', 'SUPERADMIN'], true),
        ])->layout('components.layouts.app', ['title' => 'License & Subscription — DxClinic']);
    }
}
