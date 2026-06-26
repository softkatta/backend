<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class UpiPaymentService
{
    public function buildPayload(
        string $vpa,
        string $payeeName,
        float $amount,
        string $note,
        string $currency = 'INR',
    ): string {
        $params = [
            'pa' => $vpa,
            'pn' => $payeeName,
            'am' => number_format($amount, 2, '.', ''),
            'cu' => $currency,
            'tn' => $note,
        ];

        return 'upi://pay?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function qrSvgDataUri(string $payload, int $size = 140): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd
            )
        );

        $svg = $writer->writeString($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
