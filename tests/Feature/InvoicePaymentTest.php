<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_payments_of_other_users_are_forbidden(): void
    {
        $invoice = $this->invoiceFor(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('invoices.payments.store', $invoice), ['paid_at' => '2026-10-10', 'amount' => '10'])
            ->assertForbidden();
    }
}
