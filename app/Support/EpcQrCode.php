<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * SEPA payment QR code ("GiroCode" / EPC069-12). Banking apps that scan it
 * prefill recipient, IBAN, BIC, amount and payment reference.
 */
class EpcQrCode
{
    public static function payload(string $name, string $iban, ?string $bic, float $amount, string $reference): string
    {
        return implode("\n", [
            'BCD',                                          // service tag
            '002',                                          // version (BIC optional)
            '1',                                            // character set: UTF-8
            'SCT',                                          // SEPA credit transfer
            strtoupper(str_replace(' ', '', (string) $bic)),
            mb_substr($name, 0, 70),
            strtoupper(str_replace(' ', '', $iban)),
            'EUR'.number_format($amount, 2, '.', ''),
            '',                                             // purpose code
            '',                                             // structured reference
            mb_substr($reference, 0, 140),                  // unstructured reference (Verwendungszweck)
        ]);
    }

    /**
     * SVG as data URI, ready for an <img> in the PDF.
     */
    public static function dataUri(string $name, string $iban, ?string $bic, float $amount, string $reference, int $size = 300): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd(),
        ));

        // The EPC standard requires error correction level M
        $svg = $writer->writeString(
            self::payload($name, $iban, $bic, $amount, $reference),
            'UTF-8',
            ErrorCorrectionLevel::M(),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
