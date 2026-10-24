<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Test mail from the email test center: shows that the mail server works. Carries the own
 * logo from the profile like the mails to customers.
 */
class TestMail extends Mailable
{
    public function __construct(public User $user, public string $via, public string $requestedAt)
    {
    }

    public function envelope(): Envelope
    {
        // Sent under the business name like the mails to customers, not the app name
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->user->businessProfile?->displayName() ?: config('mail.from.name')),
            subject: 'Testmail – '.$this->requestedAt,
        );
    }

    public function content(): Content
    {
        $logo = $this->user->getFirstMedia('logo');

        return new Content(markdown: 'mail.test', with: [
            'via' => $this->via,
            'requestedAt' => $this->requestedAt,
            'sentAt' => now()->format('d.m.Y H:i:s'),
            'mailer' => config('mail.default'),
            'logoPath' => $logo && is_file($logo->getPath()) ? $logo->getPath() : null,
            'signatureName' => $this->user->businessProfile?->name,
        ]);
    }
}
