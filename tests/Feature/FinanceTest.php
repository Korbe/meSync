<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\FinanceOverview;
use App\Support\Amount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16');
    }

    private function contract(User $user, array $attributes = []): RecurringExpense
    {
        return $user->recurringExpenses()->create([
            'name' => 'Handy', 'type' => 'expense', 'scope' => 'private', 'category' => 'subscriptions',
            'amount' => 20, 'interval_months' => 1, 'share_percent' => 100, 'start_date' => '2026-01-01',
            ...$attributes,
        ]);
    }

    private function entry(User $user, array $attributes = []): void
    {
        $user->financeEntries()->create([
            'date' => '2026-10-05', 'description' => 'Hofer', 'type' => 'expense', 'scope' => 'private',
            'category' => 'groceries', 'amount' => 10, ...$attributes,
        ]);
    }

    public function test_amounts_can_be_typed_like_spreadsheet_cells(): void
    {
        $this->assertSame(12.5, Amount::parse('12,50'));
        $this->assertSame(1234.5, Amount::parse('1.234,50'));
        $this->assertSame(9.99, Amount::parse('9.99'));
        $this->assertSame(36.42, Amount::parse('5,92+6,5+24'));
        $this->assertSame(27.38, Amount::parse('=200-172,62'));
        $this->assertSame(16.99, Amount::parse('16,99 €'));
        $this->assertTrue(Amount::isFormula('5,92+6,5'));
        $this->assertFalse(Amount::isFormula('5,92'));

        $this->expectException(InvalidArgumentException::class);
        Amount::parse('abc');
    }

    public function test_contracts_are_due_in_their_interval_and_period(): void
    {
        $user = User::factory()->create();
        $yearly = $this->contract($user, ['interval_months' => 12, 'start_date' => '2025-03-15', 'amount' => 120]);
        $quarterly = $this->contract($user, ['interval_months' => 3, 'start_date' => '2026-02-01', 'end_date' => '2026-08-31']);

        $this->assertTrue($yearly->isDueIn(2026, 3));
        $this->assertFalse($yearly->isDueIn(2026, 4));
        $this->assertFalse($yearly->isDueIn(2025, 2));
        $this->assertSame(10.0, $yearly->monthly_amount);
        $this->assertSame(120.0, $yearly->yearly_amount);

        $this->assertSame([2, 5, 8], array_values(array_filter(range(1, 12), fn ($month) => $quarterly->isDueIn(2026, $month))));
    }

    public function test_overview_adds_contracts_with_own_share_and_bookings_per_month(): void
    {
        $user = User::factory()->create();
        $this->contract($user, ['name' => 'Gehalt', 'type' => 'income', 'category' => 'salary', 'amount' => 2000]);
        $this->contract($user, ['name' => 'Spotify', 'amount' => 17, 'share_percent' => 50]);
        $this->contract($user, ['name' => 'Vignette', 'amount' => 100, 'interval_months' => 12, 'start_date' => '2026-02-01', 'category' => 'mobility']);
        $this->entry($user, ['amount' => 10]);
        $this->entry($user, ['amount' => 5, 'description' => 'hofer ']);   // same row, case and spaces don't matter
        $this->entry($user, ['amount' => 50, 'date' => '2026-11-02', 'description' => 'Tanken', 'category' => 'mobility']);

        $overview = app(FinanceOverview::class)->year($user, 2026);
        [$income, $fixed, $variable] = $overview['groups'];

        $this->assertSame(24000.0, $income['total']);
        $this->assertSame(8.5, $fixed['totals'][0]);                  // January: half of Spotify
        $this->assertSame(108.5, $fixed['totals'][1]);                // February: + yearly Vignette
        $this->assertSame(15.0, $variable['totals'][9]);              // October: Hofer 10 + 5
        $this->assertCount(1, collect($variable['categories'])->firstWhere('category', 'groceries')['lines']);
        $this->assertSame(2000 - 8.5 - 15.0, $overview['summary']['surplus'][9]);
    }

    public function test_business_scope_includes_invoice_payments_and_inventory(): void
    {
        $user = User::factory()->create();
        $this->contract($user, ['name' => 'Hosting', 'scope' => 'business', 'category' => 'hosting', 'amount' => 25]);
        $this->contract($user, ['name' => 'Miete', 'scope' => 'private', 'category' => 'housing', 'amount' => 700]);

        $customer = Customer::factory()->for($user)->create();
        $invoice = $user->invoices()->create([
            'customer_id' => $customer->id, 'number' => '2026-001', 'invoice_date' => '2026-09-01', 'due_date' => '2026-09-15',
            'tax_rate' => 20, 'paid_amount' => 0, 'recipient' => [], 'issuer' => [],
        ]);
        $invoice->payments()->create(['paid_at' => '2026-09-10', 'amount' => 600, 'method' => 'transfer']);
        InventoryItem::factory()->for($user)->create(['price' => 1000, 'tax_rate' => 20, 'purchased_at' => '2026-09-20']);

        $business = app(FinanceOverview::class)->year($user, 2026, 'business');
        $this->assertSame(600.0, $business['summary']['income'][8]);
        $this->assertSame(25.0 + 1200.0, $business['summary']['expenses'][8]);
        $this->assertSame('invoices', $business['groups'][0]['categories'][0]['lines'][0]['source']);

        $private = app(FinanceOverview::class)->year($user, 2026, 'private');
        $this->assertSame(0.0, $private['summary']['income'][8]);
        $this->assertSame(700.0, $private['summary']['expenses'][8]);
    }

    public function test_bookings_can_be_entered_as_sum_edited_and_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('finance.entries.store'), [
            'date' => '2026-10-10', 'description' => 'Billa', 'type' => 'expense', 'scope' => 'private',
            'category' => 'groceries', 'amount' => '5,92+6,50',
        ])->assertSessionHas('success');

        $entry = $user->financeEntries()->first();
        $this->assertSame('12.42', $entry->amount);
        $this->assertSame('5,92+6,50', $entry->amount_input);

        $this->actingAs($user)->put(route('finance.entries.update', $entry), [
            'date' => '2026-10-10', 'description' => 'Billa', 'type' => 'expense', 'scope' => 'business',
            'category' => 'office', 'amount' => '20',
        ]);
        $entry->refresh();
        $this->assertSame('20.00', $entry->amount);
        $this->assertNull($entry->amount_input);
        $this->assertSame('business', $entry->scope);

        $this->actingAs($user)->post(route('finance.entries.store'), [
            'date' => '2026-10-10', 'description' => 'X', 'type' => 'expense', 'scope' => 'private', 'category' => 'groceries', 'amount' => 'zehn',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($user)->delete(route('finance.entries.destroy', $entry));
        $this->assertSame(0, $user->financeEntries()->count());
    }

    public function test_price_change_keeps_past_months(): void
    {
        $user = User::factory()->create();
        $contract = $this->contract($user, ['name' => 'Versicherung', 'category' => 'insurance', 'amount' => 31.43]);

        $this->actingAs($user)->put(route('finance.contracts.update', $contract), [
            'name' => 'Versicherung', 'type' => 'expense', 'scope' => 'private', 'category' => 'insurance',
            'amount' => '32,91', 'interval_months' => 1, 'share_percent' => 100, 'start_date' => '2026-01-01',
            'change_from' => '2026-03-01',
        ])->assertRedirect();

        $this->assertSame('2026-02-28', $contract->fresh()->end_date->toDateString());

        $values = collect(app(FinanceOverview::class)->year($user, 2026)['groups'][1]['categories'][0]['values']);
        $this->assertSame(31.43, $values[1]);
        $this->assertSame(32.91, $values[2]);
    }

    public function test_pages_render_and_are_protected(): void
    {
        $user = User::factory()->create();
        $contract = $this->contract($user);
        $this->entry($user);

        $this->actingAs($user)->get(route('finance.index', ['scope' => 'private']))
            ->assertInertia(fn (Assert $page) => $page->component('Finance/Overview')->where('overview.scope', 'private')->has('overview.groups', 3));
        $this->actingAs($user)->get(route('finance.entries.index', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page->component('Finance/Entries')->has('entries', 1)->has('contracts', 1)->has('suggestions', 1));
        $this->actingAs($user)->get(route('finance.contracts.index'))->assertInertia(fn (Assert $page) => $page->component('Finance/Contracts/Index')->has('contracts', 1));
        $this->actingAs($user)->get(route('finance.contracts.create'))->assertOk();
        $this->actingAs($user)->get(route('finance.contracts.edit', $contract))->assertOk();

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('finance.contracts.edit', $contract))->assertForbidden();
        $this->actingAs($other)->delete(route('finance.entries.destroy', $user->financeEntries()->first()))->assertForbidden();
        $this->actingAs($other)->get(route('finance.index'))->assertInertia(fn (Assert $page) => $page->where('overview.summary.totals.expenses', 0));
    }

    public function test_finances_are_part_of_the_backup(): void
    {
        $user = User::factory()->create();
        $this->contract($user, ['share_percent' => 50]);
        $this->entry($user, ['amount_input' => '4+6']);

        $json = $this->actingAs($user)->get(route('backup.download'))->streamedContent();
        $user->recurringExpenses()->delete();
        $user->financeEntries()->delete();

        $this->actingAs($user)->post(route('backup.restore'), [
            'file' => UploadedFile::fake()->createWithContent('backup.json', $json), 'mode' => 'replace',
        ])->assertSessionHas('success');

        $this->assertSame(50, $user->recurringExpenses()->first()->share_percent);
        $this->assertSame('4+6', $user->financeEntries()->first()->amount_input);
    }

    public function test_confirmed_payments_use_the_debited_amount_and_open_months_are_marked(): void
    {
        $user = User::factory()->create();
        $contract = $this->contract($user, ['name' => 'Spotify', 'amount' => 16.99, 'share_percent' => 50]);

        // September confirmed with a different amount, October (current month) still open
        $this->actingAs($user)->post(route('finance.payments.store', $contract), ['period' => '2026-09', 'amount' => '17,99'])
            ->assertSessionHas('success');

        $overview = app(FinanceOverview::class)->year($user, 2026);
        $line = $overview['groups'][1]['categories'][0]['lines'][0];

        $this->assertSame(9.0, $line['values'][8]);                  // half of 17,99
        $this->assertSame(8.5, $line['values'][9]);                  // planned half of 16,99
        $this->assertSame('confirmed', $line['status'][8]);
        $this->assertSame('open', $line['status'][9]);
        $this->assertSame('plan', $line['status'][10]);
        $this->assertSame(9, $line['open']);                         // Jan-Aug + Oct
        $this->assertSame(1, $overview['open'][9]);
        $this->assertSame(0, $overview['open'][8]);
        $this->assertSame('17.99', $contract->payments()->first()->amount);

        // Undo
        $this->actingAs($user)->delete(route('finance.payments.destroy', $contract), ['period' => '2026-09']);
        $this->assertSame(0, $contract->payments()->count());
    }

    public function test_confirming_with_adopt_continues_the_contract_with_the_new_price(): void
    {
        $user = User::factory()->create();
        $contract = $this->contract($user, ['name' => 'Autoversicherung', 'category' => 'insurance', 'amount' => 133.24]);
        $contract->payments()->create(['period' => '2026-11-01', 'amount' => 133.24, 'confirmed_at' => now()]);

        $this->actingAs($user)->post(route('finance.payments.store', $contract), ['period' => '2026-09', 'amount' => '134,24', 'adopt' => 1]);

        $this->assertSame('2026-08-31', $contract->fresh()->end_date->toDateString());
        $next = $user->recurringExpenses()->whereDate('start_date', '2026-09-01')->first();
        $this->assertSame('134.24', $next->amount);
        $this->assertSame(['2026-09-01', '2026-11-01'], $next->payments()->pluck('period')->all());   // later confirmations moved along
        $this->assertSame(0, $contract->payments()->count());
    }

    public function test_only_due_months_can_be_confirmed_and_all_can_be_confirmed_at_once(): void
    {
        $user = User::factory()->create();
        $yearly = $this->contract($user, ['name' => 'Vignette', 'interval_months' => 12, 'start_date' => '2026-02-01', 'amount' => 100]);
        $this->contract($user, ['name' => 'Handy']);
        $this->contract($user, ['name' => 'Hosting', 'scope' => 'business', 'category' => 'hosting']);

        $this->actingAs($user)->post(route('finance.payments.store', $yearly), ['period' => '2026-10', 'amount' => '100'])
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)->post(route('finance.payments.confirm-all'), ['period' => '2026-10', 'scope' => 'private']);
        $this->assertSame(1, \App\Models\RecurringPayment::count());   // only the private monthly one is due

        $this->actingAs($user)->get(route('finance.entries.index', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contracts.0.name', 'Handy')
                ->where('contracts.0.payment.amount', '20.00')
                ->where('contracts.1.payment', null)
                ->where('openMonths.0.month', '2026-01')->etc());
    }

    public function test_other_users_cannot_confirm(): void
    {
        $contract = $this->contract(User::factory()->create());

        $this->actingAs(User::factory()->create())->post(route('finance.payments.store', $contract), ['period' => '2026-10'])->assertForbidden();
    }

    public function test_confirmations_are_part_of_the_backup(): void
    {
        $user = User::factory()->create();
        $contract = $this->contract($user);
        $contract->payments()->create(['period' => '2026-09-01', 'amount' => 21.5, 'confirmed_at' => now()]);

        $json = $this->actingAs($user)->get(route('backup.download'))->streamedContent();
        $user->recurringExpenses()->delete();

        $this->actingAs($user)->post(route('backup.restore'), [
            'file' => UploadedFile::fake()->createWithContent('backup.json', $json), 'mode' => 'replace',
        ]);

        $this->assertSame('21.50', $user->recurringExpenses()->first()->payments()->first()->amount);
    }
}
