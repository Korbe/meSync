<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Support\EpcQrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the invoice PDF in memory. Nothing is written to disk.
 */
class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'payments', 'cancels', 'cancellation', 'user.businessProfile']);
        $issuer = $invoice->issuer;

        // Invoices created before the color setting existed use the current profile color
        $issuer['invoice_color'] ??= $invoice->user->businessProfile?->invoice_color;

        // Paid in cash when the invoice was created: the PDF doubles as a receipt.
        // Payments recorded later never change the PDF that was sent to the customer.
        $cashPayment = $invoice->paid_in_cash
            ? $invoice->payments->first(fn ($payment) => $payment->method === PaymentMethod::Cash)
            : null;
        $paid = $cashPayment ? ['date' => $cashPayment->paid_at, 'cash' => true, 'method' => PaymentMethod::Cash->label()] : null;

        // No payment QR for cash receipts, cancelled invoices and credits
        $qrCode = ! empty($issuer['iban']) && $invoice->amount_due > 0 && ! $paid && ! $invoice->cancellation
            ? EpcQrCode::dataUri(
                $issuer['account_holder'] ?: ($issuer['name'] ?? ''),
                $issuer['iban'],
                $issuer['bic'] ?? null,
                $invoice->amount_due,
                "Rechnung {$invoice->number}",
            )
            : null;

        $logo = $invoice->user->getFirstMedia('logo');

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'issuer' => $issuer,
            'recipient' => $invoice->recipient,
            'logo' => $logo ? 'data:'.$logo->mime_type.';base64,'.base64_encode(file_get_contents($logo->getPath())) : null,
            'qrCode' => $qrCode,
            'paid' => $paid,
        ])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();
        $this->drawPageNumbers($pdf->getDomPDF(), "Seite {PAGE_NUM} von {PAGE_COUNT} von Rechnung {$invoice->number}");

        return $pdf->output();
    }

    public function download(Invoice $invoice): Response
    {
        $name = $invoice->fileName();

        return response($this->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $name, Str::ascii($name)),
        ]);
    }

    /**
     * dompdf has no CSS counter for the total page count, so draw the line
     * onto every page, centered at the bottom.
     */
    private function drawPageNumbers(Dompdf $dompdf, string $text): void
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
