<?php

namespace App\Support;

use Dompdf\Dompdf;

class PdfPageNumbers
{
    /**
     * dompdf has no CSS counter for the total page count, so draw the line
     * onto every page, centered at the bottom. Call after render().
     *
     * @param  string  $text  with {PAGE_NUM} and {PAGE_COUNT} placeholders
     */
    public static function draw(Dompdf $dompdf, string $text): void
    {
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $size = 6.5;

        // Measure with placeholder digits, the real numbers are filled in per page
        $width = $metrics->getTextWidth(str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], '9', $text), $font, $size);

        $canvas->page_text(($canvas->get_width() - $width) / 2, $canvas->get_height() - 22, $text, $font, $size, [0.64, 0.64, 0.64]);
    }
}
