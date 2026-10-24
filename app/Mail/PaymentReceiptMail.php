<?php

namespace App\Mail;

use App\Mail\Concerns\SentByBusiness;
use App\Models\InvoicePayment;
use App\Services\PaymentReceiptPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

/**
 * Confirmation of a received payment for the customer, PDF attached. Goes through the queue.
 */
class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use SentByBusiness;

    public string $mailSubject;

    public ?string $personalMessage = null;

    public function __construct(public InvoicePayment $payment)
    {
        $this->mailSubject = "Zahlungsbestätigung zur Rechnung {$payment->invoice->number}";
    }

    protected function document(): Model
    {
        return $this->payment->invoice;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-receipt',
            with: [
                ...$this->businessViewData(),
                'payment' => $this->payment,
                'invoice' => $this->payment->invoice,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(PaymentReceiptPdf::class);

        return [
            Attachment::fromData(fn () => $pdf->render($this->payment), $pdf->fileName($this->payment))
                ->withMime('application/pdf'),
        ];
    }
}
