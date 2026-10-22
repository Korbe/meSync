<?php

namespace App\Mail;

use App\Mail\Concerns\SentByBusiness;
use App\Models\Offer;
use App\Models\User;
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
                'documents' => array_values(array_intersect_key(User::DOCUMENTS, $this->offer->user->documents())),
            ],
        );
    }

    /**
     * The offer, plus terms and privacy policy from the profile when uploaded.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(OfferPdf::class)->render($this->offer), $this->offer->fileName())
                ->withMime('application/pdf'),
            ...collect($this->offer->user->documents())
                ->map(fn ($media, $collection) => Attachment::fromPath($media->getPath())
                    ->as(User::DOCUMENTS[$collection].'.pdf')
                    ->withMime('application/pdf'))
                ->values()
                ->all(),
        ];
    }
}
