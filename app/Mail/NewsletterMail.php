<?php

namespace App\Mail;

use App\Mail\Concerns\SentByBusiness;
use App\Models\Customer;
use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * A newsletter for one customer: personal salutation and a link to unsubscribe. Without a
 * customer it's the test mail to yourself.
 */
class NewsletterMail extends Mailable
{
    use SentByBusiness;

    public string $mailSubject;

    public ?string $personalMessage = null;

    public function __construct(public Newsletter $newsletter, public ?Customer $customer = null)
    {
        $this->mailSubject = $newsletter->subject;
    }

    protected function document(): Model
    {
        return $this->newsletter;
    }

    /**
     * Signed, so nobody can unsubscribe others by trying ids. Never expires: also old mails work.
     */
    public function unsubscribeUrl(): ?string
    {
        return $this->customer ? URL::signedRoute('newsletter.unsubscribe', $this->customer) : null;
    }

    /**
     * One-click unsubscribe in the mail client (Gmail, Outlook) next to the sender.
     */
    public function headers(): Headers
    {
        $url = $this->unsubscribeUrl();

        return new Headers(text: $url ? [
            'List-Unsubscribe' => "<{$url}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ] : []);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.newsletter',
            with: [
                ...$this->businessViewData(),
                // Single line breaks as typed (Markdown would join them into one line)
                'body' => preg_replace('/(?<!\n)\n(?!\n)/', "  \n", str_replace("\r\n", "\n", trim($this->newsletter->body))),
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ],
        );
    }

    /**
     * Personal salutation from the customer (the trait takes it from the document).
     */
    private function salutation(): string
    {
        if ($this->customer?->last_name) {
            return 'Guten Tag '.trim($this->customer->full_name).',';
        }

        return 'Sehr geehrte Damen und Herren,';
    }
}
