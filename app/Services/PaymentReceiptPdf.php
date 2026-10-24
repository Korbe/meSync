<?php

namespace App\Services;

use App\Models\InvoicePayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirmation for the customer that a payment was received (e.g. cash), with the own
 * signature. Rendered in memory, the invoice PDF stays untouched.
 */
class PaymentReceiptPdf
{
    public function render(InvoicePayment $payment): string
    {
        $invoice = $payment->invoice;
        $invoice->loadMissing(['items', 'payments', 'user.businessProfile']);

        $issuer = $invoice->issuer;
        $issuer['invoice_color'] ??= $invoice->user->businessProfile?->invoice_color;

        // Paid up to and including this payment: what was open afterwards
        $paidSoFar = $invoice->payments
            ->filter(fn (InvoicePayment $other) => $other->paid_at->lt($payment->paid_at) || ($other->paid_at->eq($payment->paid_at) && $other->id <= $payment->id))
            ->sum('amount');

        $image = fn ($media) => $media && is_file($media->getPath())
            ? 'data:'.$media->mime_type.';base64,'.base64_encode(file_get_contents($media->getPath()))
            : null;

        return Pdf::loadView('pdf.payment-receipt', [
            'payment' => $payment,
            'invoice' => $invoice,
            'issuer' => $issuer,
            'recipient' => $invoice->recipient,
            'logo' => $image($invoice->user->getFirstMedia('logo')),
            'signature' => $image($invoice->user->getFirstMedia('signature')),
            'signatureName' => $invoice->user->businessProfile?->name ?: ($issuer['name'] ?? null),
            'paidSoFar' => round((float) $paidSoFar, 2),
            'openAfter' => max(0, round($invoice->amount_due - $paidSoFar, 2)),
        ])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * Like "2026-013-Musterfirma GmbH-Zahlungsbestätigung-2026-10-24.pdf".
     */
    public function fileName(InvoicePayment $payment): string
    {
        return Str::beforeLast($payment->invoice->fileName(), '.pdf').'-Zahlungsbestätigung-'.$payment->paid_at->format('Y-m-d').'.pdf';
    }

    public function download(InvoicePayment $payment): Response
    {
        $name = $this->fileName($payment);

        return response($this->render($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $name, Str::ascii($name)),
        ]);
    }
}
