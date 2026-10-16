<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MaintenanceContract;
use App\Models\User;
use App\Support\EpcQrCode;
use App\Support\InvoiceColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14');
    }

    private function userWithProfile(): User
    {
        $user = User::factory()->create();
        $user->businessProfile()->create([
            'name' => 'Lukas Korbitsch', 'street' => 'Kiesweg 19', 'zip' => '9500', 'city' => 'Villach', 'country' => 'Österreich',
            'iban' => 'AT611904300234573201', 'bic' => 'BKAUATWW', 'account_holder' => 'Lukas Korbitsch',
        ]);

        return $user;
    }

    private function payload(Customer $customer, array $overrides = []): array
    {
        return [
            'customer_id' => $customer->id,
            'number' => '2026-001',
            'invoice_date' => '2026-10-14',
            'due_date' => '2026-10-28',
            'tax_rate' => '20',
            'paid_amount' => '0',
            'notes' => 'Danke!',
            'items' => [
                ['description' => 'Website', 'quantity' => '1', 'unit' => 'Stück', 'unit_price' => '500,50'],
                ['description' => 'Support', 'quantity' => '2', 'unit' => 'Stunde', 'unit_price' => '80'],
            ],
            ...$overrides,
        ];
    }

    public function test_invoice_can_be_created_with_items_and_snapshots(): void
    {
        $user = $this->userWithProfile();
        $customer = Customer::factory()->for($user)->create([
            'title_prefix' => null, 'first_name' => 'Markus', 'last_name' => 'Kirisits', 'title_suffix' => null,
            'company_name' => 'FCG Villach', 'company_suffix' => null,
        ]);

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer))->assertSessionHasNoErrors();

        $invoice = $user->invoices()->with('items')->sole();

        $this->assertCount(2, $invoice->items);
        $this->assertSame(660.5, $invoice->subtotal);
        $this->assertSame(132.1, $invoice->tax_amount);
        $this->assertSame(792.6, $invoice->total);
        $this->assertSame('Markus Kirisits - FCG Villach', $invoice->recipient['name']);
        $this->assertSame('AT611904300234573201', $invoice->issuer['iban']);
    }

    public function test_next_number_continues_the_yearly_sequence(): void
    {
        $user = $this->userWithProfile();
        $customer = Customer::factory()->for($user)->create();

        $this->assertSame('2026-001', Invoice::nextNumberFor($user, 2026));

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer, ['number' => '2026-013']));

        $this->assertSame('2026-014', Invoice::nextNumberFor($user, 2026));
        $this->assertSame('2027-001', Invoice::nextNumberFor($user, 2027));

        $this->actingAs($user)->get(route('invoices.create'))
            ->assertInertia(fn (Assert $page) => $page->where('invoice.number', '2026-014'));
    }

    public function test_invoice_number_must_be_unique_per_user(): void
    {
        $user = $this->userWithProfile();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer));
        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer))->assertSessionHasErrors('number');
    }

    public function test_invoice_from_contract_marks_contract_as_invoiced(): void
    {
        $user = $this->userWithProfile();
        $contract = MaintenanceContract::factory()->create([
            'user_id' => $user->id, 'type' => 'webapp', 'monthly_price' => 59, 'name' => 'Kundenportal',
            'start_date' => '2025-10-14', 'next_invoice_date' => '2026-10-14',
        ]);

        // Form is prefilled from the contract
        $this->actingAs($user)->get(route('invoices.create', ['contract' => $contract->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.maintenance_contract_id', $contract->id)
                ->where('invoice.customer_id', $contract->customer_id)
                ->where('invoice.items.0.unit_price', 708));

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($contract->customer, [
            'maintenance_contract_id' => $contract->id,
            'items' => [['description' => 'Webanwendung Wartung & Betreuung', 'quantity' => '1', 'unit' => 'Stück', 'unit_price' => '708']],
        ]))->assertSessionHasNoErrors();

        $invoice = $user->invoices()->sole();
        $billing = $contract->invoices()->sole();

        $this->assertSame('2027-10-14', $contract->fresh()->next_invoice_date->toDateString());
        $this->assertSame($invoice->id, $billing->invoice_id);
        $this->assertSame('2026-001', $billing->invoice_number);
    }

    public function test_deleting_contract_invoice_rolls_contract_back(): void
    {
        $user = $this->userWithProfile();
        $contract = MaintenanceContract::factory()->create(['user_id' => $user->id, 'start_date' => '2025-10-14', 'next_invoice_date' => '2026-10-14']);

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($contract->customer, ['maintenance_contract_id' => $contract->id]));
        $invoice = $user->invoices()->sole();

        $this->actingAs($user)->get(route('invoices.delete', $invoice))
            ->assertInertia(fn (Assert $page) => $page->where('undoesContractBilling', true));

        $this->actingAs($user)->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));

        $this->assertModelMissing($invoice);
        $this->assertSame('2026-10-14', $contract->fresh()->next_invoice_date->toDateString());
        $this->assertSame(0, $contract->invoices()->count());
    }

    public function test_pdf_is_generated_on_the_fly_and_not_stored(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->userWithProfile();
        $customer = Customer::factory()->for($user)->create(['first_name' => 'Markus', 'last_name' => 'Kirisits', 'company_name' => null, 'company_suffix' => null, 'title_prefix' => null, 'title_suffix' => null]);
        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer));
        $invoice = $user->invoices()->sole();

        $response = $this->actingAs($user)->get(route('invoices.download', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('2026-001-Markus Kirisits.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_invoice_can_be_cancelled_with_a_cancellation_invoice(): void
    {
        $user = $this->userWithProfile();
        $customer = Customer::factory()->for($user)->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer, ['number' => '2026-013']));
        $original = $user->invoices()->sole();

        $this->actingAs($user)->post(route('invoices.cancel', $original))->assertSessionHas('success');

        $cancellation = $user->invoices()->where('id', '!=', $original->id)->with('items')->sole();

        $this->assertSame('2026-014', $cancellation->number);
        $this->assertSame($original->id, $cancellation->cancels_invoice_id);
        $this->assertSame(-792.6, $cancellation->total);
        $this->assertSame(0.0, round($original->fresh()->total + $cancellation->total, 2));
        $this->assertSame($original->recipient, $cancellation->recipient);
    }

    public function test_cancelled_invoices_are_locked(): void
    {
        $user = $this->userWithProfile();
        $customer = Customer::factory()->for($user)->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer));
        $original = $user->invoices()->sole();
        $this->actingAs($user)->post(route('invoices.cancel', $original));
        $cancellation = $original->cancellation()->sole();

        // Neither can be edited or cancelled again, the original cannot be deleted
        $this->actingAs($user)->put(route('invoices.update', $original), $this->payload($customer))->assertForbidden();
        $this->actingAs($user)->put(route('invoices.update', $cancellation), $this->payload($customer, ['number' => $cancellation->number]))->assertForbidden();
        $this->actingAs($user)->post(route('invoices.cancel', $original))->assertForbidden();
        $this->actingAs($user)->post(route('invoices.cancel', $cancellation))->assertForbidden();
        $this->actingAs($user)->delete(route('invoices.destroy', $original))->assertForbidden();

        // Both can still be viewed and downloaded
        $this->actingAs($user)->get(route('invoices.edit', $original))
            ->assertInertia(fn (Assert $page) => $page->where('locked', true)->where('canCancel', false)->where('canDelete', false));
        $this->actingAs($user)->get(route('invoices.download', $cancellation))->assertOk();

        // Deleting the cancellation makes the original valid again
        $this->actingAs($user)->delete(route('invoices.destroy', $cancellation))->assertRedirect(route('invoices.index'));
        $this->assertFalse($original->fresh()->isLocked());
    }

    public function test_cancelling_contract_invoice_rolls_contract_back(): void
    {
        $user = $this->userWithProfile();
        $contract = MaintenanceContract::factory()->create(['user_id' => $user->id, 'start_date' => '2025-10-14', 'next_invoice_date' => '2026-10-14']);

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($contract->customer, ['maintenance_contract_id' => $contract->id]));
        $this->assertSame('2027-10-14', $contract->fresh()->next_invoice_date->toDateString());

        $this->actingAs($user)->post(route('invoices.cancel', $user->invoices()->sole()));

        $this->assertSame('2026-10-14', $contract->fresh()->next_invoice_date->toDateString());
        $this->assertSame(0, $contract->invoices()->count());
    }

    public function test_invoice_color_is_saved_and_snapshotted(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)->put(route('business-profile.update'), [
            'name' => 'Lukas Korbitsch', 'payment_terms_days' => 14, 'default_tax_rate' => 20, 'invoice_color' => '#7C3AED',
        ])->assertSessionHasNoErrors();

        $this->assertSame('#7c3aed', $user->fresh()->businessProfile->invoice_color);

        $customer = Customer::factory()->for($user)->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer));
        $this->assertSame('#7c3aed', $user->invoices()->sole()->issuer['invoice_color']);

        $this->actingAs($user)->put(route('business-profile.update'), [
            'payment_terms_days' => 14, 'default_tax_rate' => 20, 'invoice_color' => 'lila',
        ])->assertSessionHasErrors('invoice_color');
    }

    public function test_invoice_palette_keeps_text_readable(): void
    {
        // Dark accents get white text, light accents dark text
        $this->assertSame('#ffffff', InvoiceColors::palette('#171717')['onBase']);
        $this->assertSame('#ffffff', InvoiceColors::palette('#2563eb')['onBase']);
        $this->assertSame('#0a0a0a', InvoiceColors::palette('#f59e0b')['onBase']);

        // Invalid or missing colors fall back to the default
        $this->assertSame(InvoiceColors::DEFAULT, InvoiceColors::palette(null)['base']);
        $this->assertSame(InvoiceColors::DEFAULT, InvoiceColors::palette('red')['base']);
    }

    public function test_epc_qr_payload_contains_all_payment_data(): void
    {
        $payload = EpcQrCode::payload('Lukas Korbitsch', 'AT61 1904 3002 3457 3201', 'BKAUATWW', 849.6, 'Rechnung 2026-013');

        $this->assertSame(
            "BCD\n002\n1\nSCT\nBKAUATWW\nLukas Korbitsch\nAT611904300234573201\nEUR849.60\n\n\nRechnung 2026-013",
            $payload,
        );
    }

    public function test_invoices_of_other_users_are_forbidden(): void
    {
        $user = $this->userWithProfile();
        $other = $this->userWithProfile();
        $customer = Customer::factory()->for($other)->create();
        $this->actingAs($other)->post(route('invoices.store'), $this->payload($customer));
        $invoice = $other->invoices()->sole();

        $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($user)->get(route('invoices.download', $invoice))->assertForbidden();
        $this->actingAs($user)->delete(route('invoices.destroy', $invoice))->assertForbidden();

        // A foreign customer cannot be invoiced either
        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer, ['number' => '2026-099']))->assertSessionHasErrors('customer_id');
    }

    public function test_business_profile_can_be_saved_and_is_normalized(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('business-profile.update'), [
            'name' => 'Lukas Korbitsch',
            'iban' => 'at61 1904 3002 3457 3201',
            'bic' => 'bkauatww',
            'payment_terms_days' => 14,
            'default_tax_rate' => 20,
        ])->assertSessionHasNoErrors();

        $this->assertSame('AT611904300234573201', $user->fresh()->businessProfile->iban);
        $this->assertSame('BKAUATWW', $user->fresh()->businessProfile->bic);

        $this->actingAs($user)->put(route('business-profile.update'), [
            'iban' => 'kaputt', 'payment_terms_days' => 14, 'default_tax_rate' => 20,
        ])->assertSessionHasErrors('iban');
    }

    public function test_invoice_can_be_marked_as_paid_in_cash_when_created(): void
    {
        $user = $this->userWithProfile();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer, ['paid_in_cash' => true]))
            ->assertSessionHasNoErrors();

        $invoice = $user->invoices()->with('payments')->sole();
        $payment = $invoice->payments->sole();

        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame(\App\Enums\PaymentMethod::Cash, $payment->method);
        $this->assertSame('2026-10-14', $payment->paid_at->toDateString());
        $this->assertSame('792.60', $payment->amount);
        $this->assertTrue($invoice->paid_in_cash);

        // The PDF still renders (as a receipt without payment QR)
        $this->actingAs($user)->get(route('invoices.download', $invoice))->assertOk();
    }

    public function test_later_payments_do_not_turn_the_pdf_into_a_receipt(): void
    {
        $user = $this->userWithProfile();
        $customer = Customer::factory()->for($user)->create();
        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer));
        $invoice = $user->invoices()->sole();

        // Fully paid later by transfer: tracked internally, but the invoice is no cash receipt
        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), ['paid_at' => '2026-10-20', 'amount' => '792,60', 'method' => 'cash']);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertFalse($invoice->paid_in_cash);
    }
}
