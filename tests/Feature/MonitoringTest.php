<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MaintenanceContract;
use App\Models\Monitor;
use App\Models\SiteCheck;
use App\Models\User;
use App\Services\SiteMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 12:00:00');
        Notification::fake();
    }

    /**
     * Monitor of a running maintenance contract.
     */
    private function monitor(User $user, array $attributes = []): Monitor
    {
        $contract = MaintenanceContract::factory()->create([
            'user_id' => $user->id, 'url' => 'http://kunde.example', 'name' => $attributes['name'] ?? 'Kunde',
            'start_date' => '2026-01-01', 'next_invoice_date' => '2027-01-01',
        ]);

        $monitor = $contract->syncMonitor(['enabled' => true]);
        $monitor->forceFill($attributes)->save();

        return $monitor;
    }

    /**
     * @param  array<int, bool>  $results  oldest first, one check every 10 minutes ending now
     */
    private function history(Monitor $monitor, array $results): void
    {
        foreach (array_values($results) as $index => $up) {
            $monitor->siteChecks()->create([
                'checked_at' => now()->subMinutes((count($results) - 1 - $index) * 10),
                'up' => $up, 'response_ms' => $up ? 200 : null, 'error' => $up ? null : 'HTTP 500',
            ]);
        }
    }

    public function test_monitoring_can_be_switched_on_and_off_afterwards(): void
    {
        $user = User::factory()->create();
        $monitor = $this->monitor($user, ['status' => 'down', 'failures' => 3, 'error' => 'HTTP 500']);

        $this->actingAs($user)->patch(route('monitors.toggle', $monitor), ['enabled' => false])->assertSessionHas('success');
        $monitor->refresh();
        $this->assertFalse($monitor->enabled);
        $this->assertNull($monitor->status);
        $this->assertSame(0, $monitor->failures);

        $this->actingAs($user)->patch(route('monitors.toggle', $monitor), ['enabled' => true]);
        $this->assertTrue($monitor->fresh()->enabled);

        $this->actingAs(User::factory()->create())->patch(route('monitors.toggle', $monitor), ['enabled' => false])->assertForbidden();
    }

    public function test_websites_without_contract_can_be_monitored(): void
    {
        Http::fake(['app.korbitsch.at*' => Http::response('ok', 200)]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('monitors.store'), ['name' => 'Meine App', 'url' => 'https://app.korbitsch.at/'])
            ->assertSessionHas('success');

        // Checked right away, then by the scheduler like every other website
        $monitor = $user->monitors()->sole();
        $this->assertNull($monitor->maintenance_contract_id);
        $this->assertSame('https://app.korbitsch.at', $monitor->url);
        $this->assertSame('up', $monitor->status);
        $this->assertSame(1, Monitor::active()->count());

        $this->actingAs($user)->get(route('monitoring.index'))
            ->assertInertia(fn (Assert $page) => $page->has('sites', 1)->where('sites.0.name', 'Meine App')->where('sites.0.contract_id', null));

        $this->actingAs($user)->put(route('monitors.update', $monitor), ['name' => 'Meine App 2', 'url' => 'https://app2.korbitsch.at'])->assertSessionHasNoErrors();
        $monitor->refresh();
        $this->assertSame('Meine App 2', $monitor->name);
        $this->assertNull($monitor->status);   // other address, status starts over

        $this->actingAs(User::factory()->create())->delete(route('monitors.destroy', $monitor))->assertForbidden();
        $this->actingAs($user)->delete(route('monitors.destroy', $monitor));
        $this->assertSame(0, $user->monitors()->count());
    }

    public function test_monitors_of_contracts_are_edited_in_the_contract(): void
    {
        $user = User::factory()->create();
        $monitor = $this->monitor($user);

        $this->actingAs($user)->put(route('monitors.update', $monitor), ['name' => 'X', 'url' => 'https://x.at'])->assertStatus(422);
        $this->actingAs($user)->delete(route('monitors.destroy', $monitor))->assertStatus(422);
    }

    public function test_every_check_is_recorded(): void
    {
        Http::fake(['kunde.example*' => Http::response('ok', 200)]);
        $monitor = $this->monitor(User::factory()->create());

        app(SiteMonitor::class)->check($monitor);

        $check = $monitor->siteChecks()->first();
        $this->assertTrue($check->up);
        $this->assertNotNull($check->response_ms);
    }

    public function test_demo_sites_are_simulated_and_never_requested(): void
    {
        Http::fake();
        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);
        $monitor = $this->monitor($demo, ['url' => 'https://gasthof-seeblick.at']);

        app(SiteMonitor::class)->check($monitor);

        Http::assertNothingSent();
        $this->assertSame('up', $monitor->fresh()->status);
        $this->assertSame(1, $monitor->siteChecks()->count());
    }

    public function test_center_shows_uptime_timeline_and_outages(): void
    {
        $user = User::factory()->create();
        $stable = $this->monitor($user, ['name' => 'Stabil', 'status' => 'up']);
        $flaky = $this->monitor($user, ['name' => 'Wackelig', 'status' => 'down']);
        $this->monitor($user, ['name' => 'Aus', 'enabled' => false]);

        $this->history($stable, array_fill(0, 10, true));
        // single hiccup (no outage), then an outage of 3 checks that ended, then down again right now
        $this->history($flaky, [true, false, true, false, false, false, true, true, false, false]);

        $this->actingAs($user)->get(route('monitoring.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Monitoring/Index')
                ->has('sites', 2)
                ->has('sites.0.timeline', 48)
                ->where('sites.0.name', 'Stabil')
                ->where('sites.0.uptime.24h', 100)
                ->where('sites.1.uptime.24h', 40)
                ->where('sites.1.incidents_30d', 2)
                ->has('unmonitored', 1)
                ->where('unmonitored.0.name', 'Aus')
                ->where('summary.total', 2)
                ->where('summary.down', 1)
                ->where('summary.uptime_30d', 70)
                ->has('incidents', 2)
                ->where('incidents.0.ended_at', null)                 // current outage first, still running
                ->where('incidents.1.minutes', 30)
                ->where('incidents.1.error', 'HTTP 500'));
    }

    public function test_old_checks_are_pruned(): void
    {
        $monitor = $this->monitor(User::factory()->create());
        $monitor->siteChecks()->create(['checked_at' => now()->subDays(31), 'up' => true]);
        $monitor->siteChecks()->create(['checked_at' => now()->subDays(29), 'up' => true]);

        $this->artisan('model:prune', ['--model' => [SiteCheck::class]]);

        $this->assertSame(1, SiteCheck::count());
    }

    public function test_sidebar_counts_sites_that_are_down(): void
    {
        $user = User::factory()->create();
        $this->monitor($user, ['status' => 'down']);
        $this->monitor($user, ['status' => 'up']);

        $this->actingAs($user)->get(route('monitoring.index'))
            ->assertInertia(fn (Assert $page) => $page->where('sitesDownCount', 1));
    }

    public function test_sites_are_ordered_by_creation_and_can_be_reordered(): void
    {
        $user = User::factory()->create();
        $first = $this->monitor($user, ['name' => 'Zebra']);
        $second = $this->monitor($user, ['name' => 'Anton']);
        $third = $this->monitor($user, ['name' => 'Moser']);
        $foreign = $this->monitor(User::factory()->create());

        $names = fn () => collect($this->actingAs($user)->get(route('monitoring.index'))->viewData('page')['props']['sites'])->pluck('name')->all();

        // Default: by creation, not by name
        $this->assertSame(['Zebra', 'Anton', 'Moser'], $names());

        // Own order; ids of other users are ignored
        $this->actingAs($user)->put(route('monitoring.order'), ['ids' => [$third->id, $foreign->id, $first->id, $second->id]]);
        $this->assertSame(['Moser', 'Zebra', 'Anton'], $names());
        $this->assertNull($foreign->fresh()->position);

        // A new website is added at the end
        $this->monitor($user, ['name' => 'Neu']);
        $this->assertSame(['Moser', 'Zebra', 'Anton', 'Neu'], $names());

        // Reset: back to creation order
        $this->actingAs($user)->delete(route('monitoring.order.reset'));
        $this->assertSame(['Zebra', 'Anton', 'Moser', 'Neu'], $names());
    }

    public function test_password_protected_sites_are_checked_with_basic_auth(): void
    {
        Http::fake(['kunde.example*' => Http::response('ok', 200)]);
        $monitor = $this->monitor(User::factory()->create(), ['auth' => 'basic', 'auth_user' => 'kunde', 'auth_password' => 'geheim']);

        app(SiteMonitor::class)->check($monitor);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('kunde:geheim')));
        $this->assertSame(1, $monitor->siteChecks()->where('up', true)->count());
    }

    public function test_password_prompt_counts_as_up_only_when_chosen(): void
    {
        Http::fake(['kunde.example*' => Http::response('Unauthorized', 401)]);
        $user = User::factory()->create();
        $accept = $this->monitor($user, ['auth' => 'accept_401']);
        $plain = $this->monitor($user);

        app(SiteMonitor::class)->check($accept);
        app(SiteMonitor::class)->check($plain);

        $this->assertTrue($accept->siteChecks()->first()->up);
        $this->assertFalse($plain->siteChecks()->first()->up);
        $this->assertStringContainsString('Passwortschutz', $plain->fresh()->error);
    }

    public function test_monitor_password_is_encrypted_hidden_and_kept_when_left_empty(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $payload = [
            'customer_id' => $customer->id, 'type' => 'website', 'name' => 'Geschützt', 'url' => 'kunde.example',
            'monthly_price' => 39, 'start_date' => '2026-01-01', 'next_invoice_date' => '2027-01-01', 'monitor_enabled' => true,
            'monitor_auth' => 'basic', 'monitor_auth_user' => 'kunde',
        ];

        // A password is needed the first time
        $this->actingAs($user)->post(route('contracts.store'), $payload)->assertSessionHasErrors('monitor_auth_password');

        $this->actingAs($user)->post(route('contracts.store'), [...$payload, 'monitor_auth_password' => 'geheim'])->assertSessionHasNoErrors();
        $monitor = $user->maintenanceContracts()->first()->monitor;

        $this->assertSame('https://kunde.example', $monitor->url);
        $this->assertNotSame('geheim', $monitor->getRawOriginal('auth_password'));   // encrypted at rest
        $this->assertSame('geheim', $monitor->password());
        $this->assertArrayNotHasKey('auth_password', $monitor->toArray());
        $this->assertTrue($monitor->has_password);

        // Empty field on update keeps the stored password
        $contract = $monitor->maintenanceContract;
        $this->actingAs($user)->put(route('contracts.update', $contract), [...$payload, 'monitor_auth_password' => '']);
        $this->assertSame('geheim', $monitor->fresh()->password());

        // The edit page never contains it
        $this->actingAs($user)->get(route('contracts.edit', $contract))->assertDontSee('geheim');

        // Protection switched off: credentials are removed
        $this->actingAs($user)->put(route('contracts.update', $contract), [...$payload, 'monitor_auth' => 'none']);
        $monitor->refresh();
        $this->assertNull($monitor->auth_user);
        $this->assertFalse($monitor->has_password);

        // Address removed: nothing left to monitor
        $this->actingAs($user)->put(route('contracts.update', $contract), [...$payload, 'url' => '', 'monitor_auth' => 'none']);
        $this->assertSame(0, $user->monitors()->count());
    }
}
