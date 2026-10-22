<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Support\EpcQrCode;
use App\Support\PdfPageNumbers;
use Barryvdh\DomPDF\Facade\Pdf;
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
        // An attached original replaces the generated PDF
        if ($original = $invoice->getFirstMedia('original')) {
            return file_get_contents($original->getPath());
        }

        $invoice->loadMissing(['items', 'payments', 'user.businessProfile']);

        // Always complete: callers like the export load these with only a few columns,
        // the PDF also needs e.g. the date of the cancelled invoice
        $invoice->load(['cancels', 'cancellation']);
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
                ($issuer['account_holder'] ?? null) ?: ($issuer['name'] ?? ''),
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
        PdfPageNumbers::draw($pdf->getDomPDF(), "Seite {PAGE_NUM} von {PAGE_COUNT} von Rechnung {$invoice->number}");

        return $pdf->output();
    }

    public function download(Invoice $invoice): Response
    {
        $name = $invoice->fileName();

        return response($this->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $name, Str::ascii($name)),
        ]);
    }
}
