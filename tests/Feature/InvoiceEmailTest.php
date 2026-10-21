<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(?User $user = null, array $issuer = []): Invoice
    {
        $user ??= User::factory()->create();
        $customer = Customer::factory()->for($user)->create([
            'title_prefix' => null, 'title_suffix' => null, 'first_name' => 'Maria', 'last_name' => 'Huber',
            'company_name' => null, 'company_suffix' => null, 'email' => 'maria@huber.at',
        ]);
        $invoice = $user->invoices()->create([
            'customer_id' => $customer->id, 'number' => '2026-013', 'invoice_date' => '2026-10-21', 'due_date' => '2026-11-04',
            'tax_rate' => 20, 'paid_amount' => 0, 'recipient' => $customer->toRecipient(),
            'issuer' => ['name' => 'Korbitsch IT', 'email' => 'info@korbitsch.at', 'iban' => 'AT611904300234573201', 'bic' => 'KSPKAT2KXXX', ...$issuer],
        ]);
        $invoice->items()->create(['position' => 1, 'description' => 'PC-Hilfe', 'quantity' => 1, 'unit_price' => 100]);

        return $invoice;
    }

    public function test_sending_queues_the_mail_with_the_entered_details(): void
    {
        Queue::fake();
        $invoice = $this->invoice();

        $this->actingAs($invoice->user)->post(route('invoices.send', $invoice), [
            'to' => 'maria@huber.at', 'subject' => 'Rechnung 2026-013 von Korbitsch IT', 'message' => 'Danke!',
        ])->assertSessionHas('success');

        Queue::assertPushed(SendInvoiceEmail::class, fn ($job) => $job->to === 'maria@huber.at' && $job->message === 'Danke!');
        $this->assertNull($invoice->fresh()->sent_at);   // only once it has really gone out
    }

    public function test_the_job_sends_the_mail_and_records_it(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-10-21 14:30:00');
        $invoice = $this->invoice();

        (new SendInvoiceEmail($invoice, 'maria@huber.at', 'Rechnung 2026-013', null))->handle();

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('maria@huber.at')
            && $mail->hasReplyTo('info@korbitsch.at')
            && $mail->hasSubject('Rechnung 2026-013'));

        $invoice->refresh();
        $this->assertSame('2026-10-21 14:30:00', $invoice->sent_at->toDateTimeString());
        $this->assertSame('maria@huber.at', $invoice->sent_to);
    }

    public function test_mail_content_says_it_is_automatic_and_where_to_reply(): void
    {
        $invoice = $this->invoice();
        $mail = new InvoiceMail($invoice, 'Rechnung 2026-013', 'Danke für den Termin!');

        $mail->assertSeeInHtml('Guten Tag Maria Huber,');
        $mail->assertSeeInHtml('Diese E-Mail wurde automatisch erstellt.');
        $mail->assertSeeInHtml('info@korbitsch.at');
        $mail->assertSeeInHtml('Danke für den Termin!');
        $mail->assertSeeInHtml('€ 120,00');
        $mail->assertSeeInHtml('AT61 1904 3002 3457 3201');
        $mail->assertSeeInText('Diese E-Mail wurde automatisch erstellt.');

        // The PDF is generated when the mail is built (a fresh PDF differs in its timestamps)
        $attachments = $mail->attachments();
        $this->assertCount(1, $attachments);
        $this->assertSame($invoice->fileName(), $attachments[0]->as);
        $this->assertSame('application/pdf', $attachments[0]->mime);
        $content = $attachments[0]->attachWith(fn () => null, fn ($data) => $data());
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_cash_paid_invoices_ask_for_nothing(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['paid_in_cash' => true]);

        $mail = new InvoiceMail($invoice->fresh(), 'Rechnung');
        $mail->assertSeeInHtml('bereits bar bezahlt');
        $mail->assertDontSeeInHtml('Zahlbar bis');
    }

    public function test_validation_demo_and_missing_reply_address(): void
    {
        Queue::fake();
        $invoice = $this->invoice();

        $this->actingAs($invoice->user)->post(route('invoices.send', $invoice), ['to' => 'keine-mail', 'subject' => 'X'])
            ->assertSessionHasErrors('to');

        // No business email: nowhere to send answers to
        $noReply = $this->invoice(null, ['email' => null]);
        $this->actingAs($noReply->user)->post(route('invoices.send', $noReply), ['to' => 'a@b.at', 'subject' => 'X'])
            ->assertSessionHasErrors('to');

        // The public demo doesn't send mails
        $demo = $this->invoice(User::factory()->create(['email' => User::DEMO_EMAIL]));
        $this->actingAs($demo->user)->post(route('invoices.send', $demo), ['to' => 'a@b.at', 'subject' => 'X'])
            ->assertSessionHas('error');

        // Someone else's invoice
        $this->actingAs(User::factory()->create())->post(route('invoices.send', $invoice), ['to' => 'a@b.at', 'subject' => 'X'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_business_designation_is_used_in_subject_sender_and_signature(): void
    {
        $invoice = $this->invoice(null, ['name' => 'Korbitsch Lukas']);
        $invoice->user->businessProfile()->create(['name' => 'Korbitsch Lukas', 'brand_name' => 'Korbitsch Web Software und IT Dienstleistungen', 'email' => 'info@korbitsch.at']);

        $this->actingAs($invoice->user)->get(route('invoices.edit', $invoice))
            ->assertInertia(fn (Assert $page) => $page->where('mail.subject', 'Rechnung 2026-013 von Korbitsch Web Software und IT Dienstleistungen'));

        $mail = new InvoiceMail($invoice->fresh(), 'Rechnung 2026-013');
        $mail->assertFrom(config('mail.from.address'), 'Korbitsch Web Software und IT Dienstleistungen');
        $mail->assertSeeInHtml('Korbitsch Web Software und IT Dienstleistungen');
        $mail->assertSeeInHtml('Beste Grüße');
        $mail->assertSeeInHtml('Korbitsch Lukas');   // legal name stays in the signature
    }

    public function test_signature_uses_the_current_profile_name_also_for_older_invoices(): void
    {
        // Invoice created before the title was added to the profile
        $invoice = $this->invoice(null, ['name' => 'Korbitsch Lukas']);
        $invoice->user->businessProfile()->create(['name' => 'Ing. Korbitsch Lukas', 'email' => 'info@korbitsch.at']);

        $mail = new InvoiceMail($invoice->fresh(), 'Rechnung 2026-013');

        $mail->assertSeeInOrderInHtml(['Beste Grüße', 'Ing. Korbitsch Lukas']);
        $mail->assertDontSeeInHtml('Freundliche Grüße');
    }

    public function test_edit_page_prefills_the_customer_email(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($invoice->user)->get(route('invoices.edit', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mail.to', 'maria@huber.at')
                ->where('mail.subject', 'Rechnung 2026-013 von Korbitsch IT')
                ->where('mail.replyTo', 'info@korbitsch.at'));
    }
}
