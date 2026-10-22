<?php

namespace App\Mail;

use App\Mail\Concerns\SentByBusiness;
use App\Models\Invoice;
use App\Services\InvoicePdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

/**
 * The invoice for the customer, with the PDF attached. Answers go to the business email
 * (reply-to), the sender stays the app's no-reply address.
 */
class InvoiceMail extends Mailable
{
    use SentByBusiness;

    public function __construct(
        public Invoice $invoice,
        public string $mailSubject,
        public ?string $personalMessage = null,
    ) {
    }

    protected function document(): Model
    {
        return $this->invoice;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
            with: [
                ...$this->businessViewData(),
                'invoice' => $this->invoice,
                'recipient' => $this->invoice->recipient,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(InvoicePdf::class)->render($this->invoice), $this->invoice->fileName())
                ->withMime('application/pdf'),
        ];
    }
}
