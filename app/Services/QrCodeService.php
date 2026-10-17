<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    /**
     * Generate SVG string for a given URL
     */
    public function generateSvg(string $url, int $size = 250): string
    {
        return (string) QrCode::size($size)->generate($url);
    }

    /**
     * Generate PNG binary data using GD for a given URL
     */
    public function generatePng(string $url, int $scale = 10, int $margin = 4): string
    {
        $qr = Encoder::encode($url, ErrorCorrectionLevel::M());
        $matrix = $qr->getMatrix();
        $matrixWidth = $matrix->getWidth();
        $matrixHeight = $matrix->getHeight();

        $imgWidth = ($matrixWidth + 2 * $margin) * $scale;
        $imgHeight = ($matrixHeight + 2 * $margin) * $scale;

        $img = imagecreatetruecolor($imgWidth, $imgHeight);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        imagefilledrectangle($img, 0, 0, $imgWidth, $imgHeight, $white);

        for ($y = 0; $y < $matrixHeight; $y++) {
            for ($x = 0; $x < $matrixWidth; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $x1 = ($x + $margin) * $scale;
                    $y1 = ($y + $margin) * $scale;
                    $x2 = $x1 + $scale - 1;
                    $y2 = $y1 + $scale - 1;
                    imagefilledrectangle($img, $x1, $y1, $x2, $y2, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        return (string) ob_get_clean();
    }
}
