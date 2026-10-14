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
}
