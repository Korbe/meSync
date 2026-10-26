<?php

namespace App\Mail\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mails to customers about a document (invoice, offer): sent from the app's no-reply address
 * under the business name, answers go to the business email (reply-to).
 */
trait SentByBusiness
{
    /**
     * The invoice or offer, with "issuer" snapshot, "user" and "customer".
     */
    abstract protected function document(): Model;

    public function envelope(): Envelope
    {
        $issuer = $this->issuer();

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->senderName() ?: config('mail.from.name')),
            replyTo: [new Address($issuer['email'], $issuer['name'] ?? null)],
            subject: $this->mailSubject,
        );
    }

    /**
     * Data every customer mail view needs (header, signature, footer).
     *
     * @return array<string, mixed>
     */
    protected function businessViewData(): array
    {
        $logo = $this->document()->user->getFirstMedia('logo');
        $logoDark = $this->document()->user->getFirstMedia('logo_dark');

        return [
            'issuer' => $this->issuer(),
            'salutation' => $this->salutation(),
            'senderName' => $this->senderName(),
            'signatureName' => $this->signatureName(),
            'personalMessage' => $this->personalMessage,
            'logoPath' => $logo && is_file($logo->getPath()) ? $logo->getPath() : null,
            // Light lettering for mail programs in dark mode
            'logoDarkPath' => $logoDark && is_file($logoDark->getPath()) ? $logoDark->getPath() : null,
        ];
    }

    /**
     * Sender details as printed on the document; the reply-to address comes from there.
     *
     * @return array<string, mixed>
     */
    private function issuer(): array
    {
        $document = $this->document();

        return array_filter($document->issuer ?? []) + array_filter($document->user->businessProfile?->toIssuer() ?? []);
    }

    /**
     * Business designation from the profile ("Korbitsch Web Software und IT Dienstleistungen"),
     * else the name on the document.
     */
    private function senderName(): ?string
    {
        return $this->document()->user->businessProfile?->brand_name ?: ($this->issuer()['name'] ?? null);
    }

    /**
     * Name under the mail: the current one from the profile (e.g. with a newly added title),
     * not the one stored with an older document. The PDF keeps its own.
     */
    private function signatureName(): ?string
    {
        return $this->document()->user->businessProfile?->name ?: ($this->issuer()['name'] ?? null);
    }

    private function salutation(): string
    {
        $customer = $this->document()->customer;

        if ($customer && $customer->last_name) {
            return 'Guten Tag '.trim($customer->full_name).',';
        }

        return 'Sehr geehrte Damen und Herren,';
    }
}
