<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MaintenanceContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MaintenanceContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14');
    }

    private function contractFor(User $user, array $attributes = []): MaintenanceContract
    {
        return MaintenanceContract::factory()->create(['user_id' => $user->id, ...$attributes]);
    }

    public function test_contract_can_be_created_for_own_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('contracts.store'), [
                'customer_id' => $customer->id,
                'type' => 'webapp',
                'name' => 'Kundenportal',
                'url' => 'https://portal.example.at',
                'monthly_price' => '59',
                'start_date' => '2026-03-15',
                'next_invoice_date' => '2027-03-15',
            ])
            ->assertRedirect(route('contracts.index'));

        $contract = $user->maintenanceContracts()->sole();

        $this->assertSame(708.0, $contract->annual_price);
        $this->assertSame('Webanwendung', $contract->type_label);
        $this->assertSame('2027-03-15', $contract->next_invoice_date->toDateString());
    }

    public function test_contract_cannot_be_assigned_to_foreign_customer(): void
    {
        $user = User::factory()->create();
        $foreignCustomer = Customer::factory()->create();

        $this->actingAs($user)
            ->post(route('contracts.store'), [
                'customer_id' => $foreignCustomer->id,
                'type' => 'website',
                'name' => 'Fremd',
                'monthly_price' => '39',
                'start_date' => '2026-01-01',
                'next_invoice_date' => '2026-01-01',
            ])
            ->assertSessionHasErrors('customer_id');
    }

    public function test_status_is_derived_from_next_invoice_date(): void
    {
        $user = User::factory()->create();

        $this->assertSame('overdue', $this->contractFor($user, ['next_invoice_date' => '2026-10-01'])->status);
        $this->assertSame('due_soon', $this->contractFor($user, ['next_invoice_date' => '2026-11-01'])->status);
        $this->assertSame('active', $this->contractFor($user, ['next_invoice_date' => '2027-03-01'])->status);
        $this->assertSame('ended', $this->contractFor($user, ['next_invoice_date' => '2027-03-01', 'end_date' => '2027-01-31'])->status);
    }

    public function test_marking_invoiced_records_history_and_moves_due_date_by_one_year(): void
    {
        $user = User::factory()->create();
        $contract = $this->contractFor($user, ['type' => 'website', 'monthly_price' => 39, 'start_date' => '2025-10-01', 'next_invoice_date' => '2026-10-01']);

        $this->actingAs($user)
            ->post(route('contracts.invoices.store', $contract), ['invoice_number' => 'RE-2026-042'])
            ->assertSessionHas('success');

        $contract->refresh();
        $invoice = $contract->invoices()->sole();

        $this->assertSame('2027-10-01', $contract->next_invoice_date->toDateString());
        $this->assertSame('2026-10-01', $invoice->period_start->toDateString());
        $this->assertSame('2027-09-30', $invoice->period_end->toDateString());
        $this->assertSame('468.00', $invoice->amount);
        $this->assertSame('2026-10-14', $invoice->invoiced_at->toDateString());
        $this->assertSame('RE-2026-042', $invoice->invoice_number);
    }

    public function test_ended_contract_cannot_be_invoiced(): void
    {
        $user = User::factory()->create();
        $contract = $this->contractFor($user, ['next_invoice_date' => '2027-03-01', 'end_date' => '2027-01-31']);

        $this->actingAs($user)
            ->post(route('contracts.invoices.store', $contract))
            ->assertSessionHas('error');

        $this->assertSame(0, $contract->invoices()->count());
    }

    public function test_latest_invoice_can_be_undone(): void
    {
        $user = User::factory()->create();
        $contract = $this->contractFor($user, ['start_date' => '2024-10-01', 'next_invoice_date' => '2025-10-01']);
        $first = $contract->markInvoiced(Carbon::parse('2025-10-01'));
        $second = $contract->markInvoiced(Carbon::parse('2026-10-01'));

        // Only the latest invoice may be undone
        $this->actingAs($user)->delete(route('contracts.invoices.destroy', [$contract, $first]))->assertStatus(422);

        $this->actingAs($user)->delete(route('contracts.invoices.destroy', [$contract, $second]))->assertSessionHas('success');

        $this->assertSame('2026-10-01', $contract->fresh()->next_invoice_date->toDateString());
        $this->assertModelMissing($second);
        $this->assertModelExists($first);
    }

    public function test_index_shows_own_contracts_and_sidebar_due_count(): void
    {
        $user = User::factory()->create();
        $this->contractFor($user, ['next_invoice_date' => '2026-10-01']);   // overdue
        $this->contractFor($user, ['next_invoice_date' => '2026-11-01']);   // due soon
        $this->contractFor($user, ['next_invoice_date' => '2027-06-01']);   // later
        MaintenanceContract::factory()->create(['next_invoice_date' => '2026-10-01']); // other user

        $this->actingAs($user)
            ->get(route('contracts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('MaintenanceContracts/Index')
                ->has('contracts', 3)
                ->where('dueContractsCount', 2));
    }

    public function test_contracts_of_other_users_are_forbidden(): void
    {
        $user = User::factory()->create();
        $contract = MaintenanceContract::factory()->create();

        $this->actingAs($user)->get(route('contracts.edit', $contract))->assertForbidden();
        $this->actingAs($user)->post(route('contracts.invoices.store', $contract))->assertForbidden();
        $this->actingAs($user)->delete(route('contracts.destroy', $contract))->assertForbidden();
    }

    public function test_contract_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $contract = $this->contractFor($user);

        $this->actingAs($user)->get(route('contracts.delete', $contract))->assertOk();
        $this->actingAs($user)->delete(route('contracts.destroy', $contract))->assertRedirect(route('contracts.index'));

        $this->assertModelMissing($contract);
    }

    public function test_contract_information_pdf_can_be_downloaded(): void
    {
        $user = User::factory()->create();
        $user->businessProfile()->create(['name' => 'Korbitsch IT', 'default_tax_rate' => 20]);
        $contract = $this->contractFor($user, [
            'type' => 'website', 'name' => 'gasthof-seeblick.at', 'monthly_price' => 39,
            'start_date' => '2025-03-01', 'next_invoice_date' => '2026-03-01',
        ]);

        $response = $this->actingAs($user)->get(route('contracts.pdf', $contract))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('Wartungsvertrag-gasthof-seeblick.at.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_contract_pdf_shows_period_price_and_parties(): void
    {
        $user = User::factory()->create();
        $user->businessProfile()->create(['name' => 'Korbitsch IT', 'default_tax_rate' => 20]);
        $customer = Customer::factory()->for($user)->create(['title_prefix' => null, 'title_suffix' => null, 'first_name' => 'Maria', 'last_name' => 'Huber', 'company_name' => null]);
        $contract = $this->contractFor($user, [
            'customer_id' => $customer->id, 'type' => 'webapp', 'name' => 'Buchungssystem', 'monthly_price' => 59,
            'start_date' => '2025-03-01', 'next_invoice_date' => '2026-03-01', 'end_date' => '2027-02-28',
        ]);

        $html = view('pdf.contract', [
            'contract' => $contract->load(['customer', 'invoices']),
            'issuer' => $user->businessProfile->toIssuer(),
            'recipient' => $customer->toRecipient(),
            'logo' => null,
            'services' => $contract->type->services(),
            'period' => null,
            'prices' => ['monthly' => 59.0, 'annual' => 708.0, 'tax_rate' => 20.0, 'tax' => 141.6, 'annual_gross' => 849.6],
        ])->render();

        $this->assertStringContainsString('01.03.2025', $html);
        $this->assertStringContainsString('bis 28.02.2027', $html);
        $this->assertStringContainsString('€ 849,60', $html);
        $this->assertStringContainsString('Maria Huber', $html);
        $this->assertStringContainsString('Korbitsch IT', $html);
        $this->assertStringContainsString('Webanwendung', $html);
    }

    public function test_contract_pdf_of_other_users_is_forbidden(): void
    {
        $contract = $this->contractFor(User::factory()->create());

        $this->actingAs(User::factory()->create())->get(route('contracts.pdf', $contract))->assertForbidden();
    }

    public function test_only_the_domain_needs_to_be_typed_in(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $payload = fn ($url) => [
            'customer_id' => $customer->id, 'type' => 'website', 'name' => 'Seite', 'url' => $url,
            'monthly_price' => 39, 'start_date' => '2026-10-01', 'next_invoice_date' => '2026-10-01', 'monitor_enabled' => true,
        ];

        foreach ([
            'musterfirma.at' => 'https://musterfirma.at',
            '  www.musterfirma.at/shop/ ' => 'https://www.musterfirma.at/shop',
            'http://alt.musterfirma.at' => 'http://alt.musterfirma.at',
            'https://musterfirma.at/' => 'https://musterfirma.at',
        ] as $input => $expected) {
            $this->actingAs($user)->post(route('contracts.store'), $payload($input))->assertSessionHasNoErrors();
            $this->assertSame($expected, $user->maintenanceContracts()->latest('id')->first()->url, $input);
        }

        $this->actingAs($user)->post(route('contracts.store'), $payload('ftp://musterfirma.at'))->assertSessionHasErrors('url');
        $this->actingAs($user)->post(route('contracts.store'), $payload(''))->assertSessionHasNoErrors();
        $this->assertNull($user->maintenanceContracts()->latest('id')->first()->url);
    }

    public function test_contracts_are_only_billed_when_due_unless_on_purpose(): void
    {
        $user = User::factory()->create();
        $running = MaintenanceContract::factory()->for($user)->create(['start_date' => today()->subMonth(), 'next_invoice_date' => today()->addMonths(11)]);
        $due = MaintenanceContract::factory()->for($user)->create(['start_date' => today()->subYear(), 'next_invoice_date' => today()->addDays(10)]);

        // Not due: no invoice and no billing by mistake
        $this->actingAs($user)->get(route('invoices.create', ['contract' => $running->id]))->assertRedirect(route('contracts.edit', $running));
        $this->actingAs($user)->post(route('contracts.invoices.store', $running), ['invoiced_at' => today()->toDateString()])->assertSessionHas('error');
        $this->assertSame(0, $running->invoices()->count());

        // On purpose ("vorzeitig verrechnen")
        $this->actingAs($user)->get(route('invoices.create', ['contract' => $running->id, 'early' => 1]))->assertOk();
        $this->actingAs($user)->post(route('contracts.invoices.store', $running), ['invoiced_at' => today()->toDateString(), 'early' => true])->assertSessionHas('success');
        $this->assertSame(1, $running->invoices()->count());

        // Due within 30 days: as before
        $this->actingAs($user)->get(route('invoices.create', ['contract' => $due->id]))->assertOk();
    }
}
