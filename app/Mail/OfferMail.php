<?php

namespace App\Mail;

use App\Mail\Concerns\SentByBusiness;
use App\Models\Offer;
use App\Services\OfferPdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

/**
 * The offer for the customer, with the PDF attached. Answers go to the business email.
 */
class OfferMail extends Mailable
{
    use SentByBusiness;

    public function __construct(
        public Offer $offer,
        public string $mailSubject,
        public ?string $personalMessage = null,
    ) {
    }

    protected function document(): Model
    {
        return $this->offer;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.offer',
            with: [
                ...$this->businessViewData(),
                'offer' => $this->offer,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(OfferPdf::class)->render($this->offer), $this->offer->fileName())
                ->withMime('application/pdf'),
        ];
    }
}
