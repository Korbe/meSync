<?php

namespace App\Jobs;

use App\Mail\OfferMail;
use App\Models\Offer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the offer to the customer through the queue and records when and to whom,
 * only after the mail has actually gone out. From then on the offer can't be edited.
 */
class SendOfferEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Offer $offer,
        public string $to,
        public string $subject,
        public ?string $message = null,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->to)->send(new OfferMail($this->offer, $this->subject, $this->message));

        $this->offer->forceFill(['sent_at' => $this->offer->sent_at ?? now(), 'sent_to' => $this->to])->save();
    }
}
