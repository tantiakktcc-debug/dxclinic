<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookingQrController extends Controller
{
    protected QrCodeService $qrService;

    public function __construct(QrCodeService $qrService)
    {
        $this->qrService = $qrService;
    }

    protected function getUrl(string $type, Clinic $clinic): string
    {
        $slug = $clinic->public_slug ?: 'clinic';
        if (strtolower($type) === 'production') {
            return $clinic->booking_qr_prod_url ?: (rtrim((string) config('app.public_booking_url', 'https://dxclinic.example.local/book'), '/') . '/' . $slug);
        }

        return $clinic->booking_qr_local_url ?: url('/book/' . $slug);
    }


    public function downloadPng(Request $request, string $type = 'local'): Response
    {
        $clinic = \App\Services\TenantContext::clinic() ?? $request->user()?->clinic ?? new Clinic();
        $url = $this->getUrl($type, $clinic);
        $pngData = $this->qrService->generatePng($url);

        $filename = strtolower($type) === 'production'
            ? 'DxClinic-Booking-QR-Production.png'
            : 'DxClinic-Booking-QR-Local.png';

        return response($pngData, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($pngData),
        ]);
    }

    public function downloadSvg(Request $request, string $type = 'local'): Response
    {
        $clinic = \App\Services\TenantContext::clinic() ?? $request->user()?->clinic ?? new Clinic();
        $url = $this->getUrl($type, $clinic);
        $svgData = $this->qrService->generateSvg($url);

        $filename = strtolower($type) === 'production'
            ? 'DxClinic-Booking-QR-Production.svg'
            : 'DxClinic-Booking-QR-Local.svg';

        return response($svgData, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($svgData),
        ]);
    }

    public function printPoster(Request $request, string $type = 'local')
    {
        $clinic = \App\Services\TenantContext::clinic() ?? $request->user()?->clinic ?? new Clinic();
        $url = $this->getUrl($type, $clinic);
        $qrSvg = $this->qrService->generateSvg($url, 260);

        return view('print.booking-qr-poster', [
            'clinic' => $clinic,
            'url' => $url,
            'type' => ucfirst($type),
            'qrSvg' => $qrSvg,
        ]);
    }
}
