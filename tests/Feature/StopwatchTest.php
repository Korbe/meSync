<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\TimeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StopwatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-21 10:00:00');
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->for($this->user)->create(['title_prefix' => null, 'title_suffix' => null, 'first_name' => 'Maria', 'last_name' => 'Huber', 'company_name' => null]);
        // 50 € for the first 30 minutes, then 20 € per started 15 minutes
        $this->user->catalogItems()->create(['name' => 'PC-Hilfe vor Ort', 'unit' => 'Stück', 'unit_price' => 50, 'pricing' => 'time', 'base_minutes' => 30, 'step_minutes' => 15, 'step_price' => 20]);
    }

    private function lastRun(): TimeSession
    {
        return $this->user->timeSessions()->latest('id')->first();
    }

    public function test_start_pause_resume_and_stop_measure_only_working_time(): void
    {
        $this->actingAs($this->user)->post(route('stopwatch.start'), ['customer_id' => $this->customer->id]);
        $session = $this->lastRun();
        $this->assertSame('running', $session->status);
        $this->assertNotNull($session->catalog_item_id);   // the time rate is preselected

        $this->travel(40)->minutes();
        $this->post(route('stopwatch.pause', $session));
        $this->travel(20)->minutes();                       // break, not counted
        $this->post(route('stopwatch.resume', $session));
        $this->travel(25)->minutes();
        $this->post(route('stopwatch.stop', $session));

        $session->refresh();
        $this->assertSame('stopped', $session->status);
        $this->assertSame(65 * 60, $session->elapsed_seconds);
        $this->assertSame(65, $session->minutes);
        $this->assertSame(50.0 + 3 * 20, $session->price);   // 30 + 3 started quarter hours
    }

    public function test_time_keeps_running_on_the_server(): void
    {
        $this->actingAs($this->user)->post(route('stopwatch.start'));
        $this->travel(90)->minutes();

        // Another page, e.g. after reopening the app
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTimer.status', 'running')
                ->where('activeTimer.elapsed_seconds', 90 * 60));
    }

    public function test_only_one_stopwatch_at_a_time(): void
    {
        $this->actingAs($this->user)->post(route('stopwatch.start'));
        $this->actingAs($this->user)->post(route('stopwatch.start'))->assertSessionHas('error');

        $this->assertSame(1, $this->user->timeSessions()->count());
    }

    public function test_duration_can_be_corrected_and_minutes_are_rounded_up(): void
    {
        $this->actingAs($this->user)->post(route('stopwatch.start'));
        $this->travel(30)->seconds();
        $this->post(route('stopwatch.stop', $this->lastRun()));
        $this->assertSame(1, $this->lastRun()->minutes);   // started minute counts

        $this->actingAs($this->user)->patch(route('stopwatch.update', $this->lastRun()), ['billed_minutes' => 45, 'notes' => "Drucker eingerichtet\nUpdates"]);
        $session = $this->lastRun();
        $this->assertSame(45, $session->minutes);
        $this->assertSame(70.0, $session->price);
    }

    public function test_runs_become_invoice_items_and_are_marked_billed(): void
    {
        $runs = collect([[75, "Drucker eingerichtet\nUpdates installiert"], [20, null]])->map(function ($run) {
            [$minutes, $notes] = $run;
            $session = $this->user->timeSessions()->create([
                'customer_id' => $this->customer->id, 'catalog_item_id' => $this->user->catalogItems()->value('id'),
                'started_at' => now(), 'seconds' => $minutes * 60, 'stopped_at' => now(), 'notes' => $notes,
            ]);
            $this->travel(1)->days();

            return $session;
        });

        $this->actingAs($this->user)->get(route('invoices.create', ['sessions' => $runs->pluck('id')->all()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.customer_id', $this->customer->id)
                ->where('invoice.time_session_ids', $runs->pluck('id')->all())
                ->has('invoice.items', 2)
                ->where('invoice.items.0.description', "PC-Hilfe vor Ort\n- Drucker eingerichtet\n- Updates installiert\n21.10.2026 · Dauer: 1 Std. 15 Min.")
                ->where('invoice.items.0.unit_price', 50 + 3 * 20)
                ->where('invoice.items.1.unit_price', 50));

        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'number' => '2026-050', 'invoice_date' => '2026-10-23', 'due_date' => '2026-11-06',
            'tax_rate' => 20, 'paid_amount' => 0, 'time_session_ids' => $runs->pluck('id')->all(),
            'items' => [['description' => 'PC-Hilfe', 'quantity' => 1, 'unit' => 'Stück', 'unit_price' => 160]],
        ])->assertSessionHasNoErrors();

        $invoice = $this->user->invoices()->first();
        $this->assertSame(2, $this->user->timeSessions()->where('invoice_id', $invoice->id)->count());

        // Billed runs can't be billed again
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'number' => '2026-051', 'invoice_date' => '2026-10-23', 'due_date' => '2026-11-06',
            'tax_rate' => 20, 'paid_amount' => 0, 'time_session_ids' => [$runs->first()->id],
            'items' => [['description' => 'X', 'quantity' => 1, 'unit' => 'Stück', 'unit_price' => 1]],
        ])->assertSessionHasErrors('time_session_ids.0');

        // Invoice deleted: the runs are open again
        $invoice->delete();
        $this->assertSame(0, $this->user->timeSessions()->whereNotNull('invoice_id')->count());
    }

    public function test_page_shows_billed_runs_with_their_invoice_number(): void
    {
        $invoice = $this->user->invoices()->create([
            'customer_id' => $this->customer->id, 'number' => '2026-050', 'invoice_date' => '2026-10-21', 'due_date' => '2026-11-04',
            'tax_rate' => 20, 'paid_amount' => 0, 'recipient' => [], 'issuer' => [],
        ]);
        $this->user->timeSessions()->create([
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id,
            'started_at' => now(), 'seconds' => 3600, 'stopped_at' => now(),
        ]);

        $this->actingAs($this->user)->get(route('stopwatch.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('billed', 1)->where('billed.0.invoice.number', '2026-050'));
    }

    public function test_page_and_protection(): void
    {
        $this->actingAs($this->user)->post(route('stopwatch.start'));
        $session = $this->lastRun();

        $this->actingAs($this->user)->get(route('stopwatch.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Stopwatch/Index')->where('active.id', $session->id)->has('rates', 1));

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('stopwatch.stop', $session))->assertForbidden();
        $this->actingAs($other)->patch(route('stopwatch.update', $session), ['notes' => 'x'])->assertForbidden();
        $this->actingAs($other)->delete(route('stopwatch.destroy', $session))->assertForbidden();
    }

    public function test_runs_are_part_of_the_backup(): void
    {
        $this->user->timeSessions()->create([
            'customer_id' => $this->customer->id, 'catalog_item_id' => $this->user->catalogItems()->value('id'),
            'started_at' => now(), 'seconds' => 3600, 'stopped_at' => now(), 'notes' => 'Router getauscht',
        ]);

        $json = $this->actingAs($this->user)->get(route('backup.download'))->streamedContent();
        $this->actingAs($this->user)->post(route('backup.restore'), [
            'file' => UploadedFile::fake()->createWithContent('backup.json', $json), 'mode' => 'replace',
        ])->assertSessionHas('success');

        $session = $this->user->timeSessions()->with(['customer', 'catalogItem'])->first();
        $this->assertSame('Router getauscht', $session->notes);
        $this->assertSame('Huber', $session->customer->last_name);
        $this->assertSame('PC-Hilfe vor Ort', $session->catalogItem->name);
        $this->assertSame(1, $this->user->timeSessions()->count());
    }
}
