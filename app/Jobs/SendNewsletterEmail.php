<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\NewsletterRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the newsletter to one recipient through the queue and notes the result, so the
 * newsletter page shows who got it and who didn't.
 */
class SendNewsletterEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public NewsletterRecipient $recipient)
    {
    }

    public function handle(): void
    {
        $recipient = $this->recipient;

        // Unsubscribed in the meantime (e.g. through an earlier newsletter): skip
        if ($recipient->sent_at || $recipient->customer?->newsletter_opt_out_at) {
            return;
        }

        Mail::to($recipient->email, $recipient->name)->send(new NewsletterMail($recipient->newsletter, $recipient->customer));

        $recipient->forceFill(['sent_at' => now(), 'failed_at' => null, 'error' => null])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->recipient->forceFill(['failed_at' => now(), 'error' => Str::limit($exception->getMessage(), 490)])->save();
    }
}
