<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the invoice to the customer through the queue and records when and to whom,
 * only after the mail has actually gone out.
 */
class SendInvoiceEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Invoice $invoice,
        public string $to,
        public string $subject,
        public ?string $message = null,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->to)->send(new InvoiceMail($this->invoice, $this->subject, $this->message));

        $this->invoice->forceFill(['sent_at' => now(), 'sent_to' => $this->to])->save();
    }
}
