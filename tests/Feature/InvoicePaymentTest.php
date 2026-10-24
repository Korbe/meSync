<?php

namespace Tests\Feature;

use App\Mail\PaymentReceiptMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\PaymentReceiptPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15');
    }

    /**
     * Invoice over 100 € net + 20 % = 120 €.
     */
    private function invoiceFor(User $user, array $attributes = []): Invoice
    {
        $customer = Customer::factory()->for($user)->create();
        $invoice = $user->invoices()->create([
            'customer_id' => $customer->id, 'number' => '2026-'.fake()->unique()->numberBetween(100, 999),
            'invoice_date' => '2026-10-01', 'due_date' => '2026-10-30', 'tax_rate' => 20, 'paid_amount' => 0,
            'recipient' => $customer->toRecipient(), 'issuer' => [],
            ...$attributes,
        ]);
        $invoice->items()->create(['position' => 1, 'description' => 'Leistung', 'quantity' => 1, 'unit' => 'Stück', 'unit_price' => 100]);

        return $invoice->fresh();
    }

    public function test_payment_status_follows_payments_and_due_date(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        $this->assertSame('open', $invoice->payment_status);
        $this->assertSame(120.0, $invoice->outstanding);

        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), ['paid_at' => '2026-10-10', 'amount' => '50'])
            ->assertSessionHasNoErrors();
        $this->assertSame('partial', $invoice->fresh()->payment_status);
        $this->assertSame(70.0, $invoice->fresh()->outstanding);

        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), ['paid_at' => '2026-10-12', 'amount' => '70,00']);
        $this->assertSame('paid', $invoice->fresh()->payment_status);

        $this->assertSame('overdue', $this->invoiceFor($user, ['due_date' => '2026-10-10'])->payment_status);
    }

    public function test_payment_cannot_exceed_outstanding_amount(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), ['paid_at' => '2026-10-10', 'amount' => '500'])
            ->assertSessionHasErrors('amount');
    }

    public function test_payment_can_be_removed(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);
        $payment = $invoice->payments()->create(['paid_at' => '2026-10-10', 'amount' => 120]);

        $this->actingAs($user)->delete(route('invoices.payments.destroy', [$invoice, $payment]))->assertSessionHas('success');

        $this->assertSame('open', $invoice->fresh()->payment_status);
    }

    public function test_cancelled_invoices_cannot_receive_payments(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);
        $cancellation = $invoice->load('items')->cancel();

        $this->assertSame('cancelled', $invoice->fresh()->payment_status);
        $this->assertStringEndsWith('-Stornorechnung.pdf', $cancellation->fileName());
        $this->assertStringEndsNotWith('-Stornorechnung.pdf', $invoice->fileName());
        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), ['paid_at' => '2026-10-10', 'amount' => '10'])
            ->assertForbidden();
    }

    public function test_a_cash_payment_gets_a_confirmation_for_the_customer(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user, ['issuer' => ['name' => 'Korbitsch', 'email' => 'office@example.test']]);
        $invoice->payments()->create(['paid_at' => '2026-10-10', 'amount' => 50, 'method' => 'cash']);
        $payment = $invoice->payments()->first();

        // The PDF opens in the browser, named after the invoice
        $response = $this->actingAs($user)->get(route('invoices.payments.receipt', [$invoice, $payment]))->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('Zahlungsbest', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));

        $this->post(route('invoices.payments.receipt.send', [$invoice, $payment]), ['to' => 'kunde@example.test'])->assertSessionHas('success');
        Mail::assertQueued(PaymentReceiptMail::class, fn (PaymentReceiptMail $mail) => $mail->hasTo('kunde@example.test')
            && $mail->payment->is($payment)
            && str_starts_with(app(PaymentReceiptPdf::class)->render($mail->payment), '%PDF'));

        // Only the own payments, never in the demo
        $this->actingAs(User::factory()->create())->get(route('invoices.payments.receipt', [$invoice, $payment]))->assertForbidden();
        $other = $this->invoiceFor($user);
        $this->actingAs($user)->get(route('invoices.payments.receipt', [$other, $payment]))->assertNotFound();
    }

    public function test_the_demo_sends_no_payment_confirmation(): void
    {
        Mail::fake();
        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);
        $invoice = $this->invoiceFor($demo, ['issuer' => ['email' => 'office@example.test']]);
        $invoice->payments()->create(['paid_at' => '2026-10-10', 'amount' => 50, 'method' => 'cash']);

        $this->actingAs($demo)->post(route('invoices.payments.receipt.send', [$invoice, $invoice->payments()->first()]), ['to' => 'kunde@example.test'])
            ->assertSessionHas('error');
        Mail::assertNothingQueued();
    }

    public function test_payments_of_other_users_are_forbidden(): void
    {
        $invoice = $this->invoiceFor(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('invoices.payments.store', $invoice), ['paid_at' => '2026-10-10', 'amount' => '10'])
            ->assertForbidden();
    }
}
