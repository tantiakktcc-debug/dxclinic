<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Services\QrCodeService;
use Livewire\Component;

class BookingQrManager extends Component
{
    public ?Clinic $clinic = null;
    public string $localUrl = '';
    public string $prodUrl = '';

    public function mount(): void
    {
        $this->clinic = \App\Services\TenantContext::clinic();
        abort_unless($this->clinic, 403, 'Unauthorized clinic context.');
        $slug = ($this->clinic && $this->clinic->public_slug) ? $this->clinic->public_slug : 'clinic';
        $defaultLocal = url('/book/' . $slug);
        $defaultProd = rtrim((string) config('app.public_booking_url', 'https://dxclinic.example.local/book'), '/') . '/' . $slug;

        if ($this->clinic) {
            $this->localUrl = $this->clinic->booking_qr_local_url ?: $defaultLocal;
            $this->prodUrl = $this->clinic->booking_qr_prod_url ?: $defaultProd;
        } else {
            $this->localUrl = $defaultLocal;
            $this->prodUrl = $defaultProd;
        }
    }


    public function saveUrls(): void
    {
        $this->validate([
            'localUrl' => 'required|url',
            'prodUrl' => 'required|url',
        ]);

        if ($this->clinic) {
            $this->clinic->update([
                'booking_qr_local_url' => trim($this->localUrl),
                'booking_qr_prod_url' => trim($this->prodUrl),
            ]);

            AuditLog::log('BOOKING_QR_URLS_UPDATED', 'CLINIC', $this->clinic->id, [
                'local_url' => $this->localUrl,
                'prod_url' => $this->prodUrl,
            ]);

            session()->flash('success', 'Booking URLs updated and QR codes regenerated successfully.');
        }
    }

    public function render(QrCodeService $qrService)
    {
        $localQrSvg = $qrService->generateSvg($this->localUrl, 180);
        $prodQrSvg = $qrService->generateSvg($this->prodUrl, 180);

        return view('livewire.settings.booking-qr-manager', [
            'localQrSvg' => $localQrSvg,
            'prodQrSvg' => $prodQrSvg,
        ])->layout('components.layouts.app', ['title' => 'Online Booking / QR']);
    }
}
