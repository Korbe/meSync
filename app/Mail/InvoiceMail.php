<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\InvoicePdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The invoice for the customer, with the PDF attached. Answers go to the business email
 * (reply-to), the sender stays the app's no-reply address.
 */
class InvoiceMail extends Mailable
{
    public function __construct(
        public Invoice $invoice,
        public string $mailSubject,
        public ?string $personalMessage = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $issuer = $this->issuer();

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->senderName() ?: config('mail.from.name')),
            replyTo: [new Address($issuer['email'], $issuer['name'] ?? null)],
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        $logo = $this->invoice->user->getFirstMedia('logo');

        return new Content(
            markdown: 'mail.invoice',
            with: [
                'invoice' => $this->invoice,
                'issuer' => $this->issuer(),
                'recipient' => $this->invoice->recipient,
                'salutation' => $this->salutation(),
                'senderName' => $this->senderName(),
                'signatureName' => $this->signatureName(),
                'personalMessage' => $this->personalMessage,
                'logoPath' => $logo && is_file($logo->getPath()) ? $logo->getPath() : null,
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

    /**
     * Sender details as printed on the invoice; the reply-to address comes from there.
     *
     * @return array<string, mixed>
     */
    private function issuer(): array
    {
        return array_filter($this->invoice->issuer ?? []) + array_filter($this->invoice->user->businessProfile?->toIssuer() ?? []);
    }

    /**
     * Business designation from the profile ("Korbitsch Web Software und IT Dienstleistungen"),
     * else the name on the invoice.
     */
    private function senderName(): ?string
    {
        return $this->invoice->user->businessProfile?->brand_name ?: ($this->issuer()['name'] ?? null);
    }

    /**
     * Name under the mail: the current one from the profile (e.g. with a newly added title),
     * not the one stored with an older invoice. The PDF keeps its own.
     */
    private function signatureName(): ?string
    {
        return $this->invoice->user->businessProfile?->name ?: ($this->issuer()['name'] ?? null);
    }

    private function salutation(): string
    {
        $customer = $this->invoice->customer;

        if ($customer && $customer->last_name) {
            return 'Guten Tag '.trim($customer->full_name).',';
        }

        return 'Sehr geehrte Damen und Herren,';
    }
}
