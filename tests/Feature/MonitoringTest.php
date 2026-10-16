<?php

namespace Tests\Feature;

use App\Models\MaintenanceContract;
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

    private function contract(User $user, array $attributes = []): MaintenanceContract
    {
        return MaintenanceContract::factory()->create([
            'user_id' => $user->id, 'url' => 'http://kunde.example', 'monitor_enabled' => true,
            'start_date' => '2026-01-01', 'next_invoice_date' => '2027-01-01', ...$attributes,
        ]);
    }

    /**
     * @param  array<int, bool>  $results  oldest first, one check every 10 minutes ending now
     */
    private function history(MaintenanceContract $contract, array $results): void
    {
        foreach (array_values($results) as $index => $up) {
            $contract->siteChecks()->create([
                'checked_at' => now()->subMinutes((count($results) - 1 - $index) * 10),
                'up' => $up, 'response_ms' => $up ? 200 : null, 'error' => $up ? null : 'HTTP 500',
            ]);
        }
    }

    public function test_monitoring_can_be_switched_on_and_off_afterwards(): void
    {
        $user = User::factory()->create();
        $contract = $this->contract($user, ['monitor_status' => 'down', 'monitor_failures' => 3, 'monitor_error' => 'HTTP 500']);

        $this->actingAs($user)->patch(route('contracts.monitor', $contract), ['enabled' => false])->assertSessionHas('success');
        $contract->refresh();
        $this->assertFalse($contract->monitor_enabled);
        $this->assertNull($contract->monitor_status);
        $this->assertSame(0, $contract->monitor_failures);

        $this->actingAs($user)->patch(route('contracts.monitor', $contract), ['enabled' => true]);
        $this->assertTrue($contract->fresh()->monitor_enabled);

        $this->actingAs(User::factory()->create())->patch(route('contracts.monitor', $contract), ['enabled' => false])->assertForbidden();
    }

    public function test_every_check_is_recorded(): void
    {
        Http::fake(['kunde.example*' => Http::response('ok', 200)]);
        $contract = $this->contract(User::factory()->create());

        app(SiteMonitor::class)->check($contract);

        $check = $contract->siteChecks()->first();
        $this->assertTrue($check->up);
        $this->assertNotNull($check->response_ms);
    }

    public function test_demo_sites_are_simulated_and_never_requested(): void
    {
        Http::fake();
        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);
        $contract = $this->contract($demo, ['url' => 'https://gasthof-seeblick.at']);

        app(SiteMonitor::class)->check($contract);

        Http::assertNothingSent();
        $this->assertSame('up', $contract->fresh()->monitor_status);
        $this->assertSame(1, $contract->siteChecks()->count());
    }

    public function test_center_shows_uptime_timeline_and_outages(): void
    {
        $user = User::factory()->create();
        $stable = $this->contract($user, ['name' => 'Stabil', 'monitor_status' => 'up']);
        $flaky = $this->contract($user, ['name' => 'Wackelig', 'monitor_status' => 'down']);
        $this->contract($user, ['name' => 'Aus', 'monitor_enabled' => false]);
        $this->contract($user, ['name' => 'Ohne URL', 'url' => null]);

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
        $contract = $this->contract(User::factory()->create());
        $contract->siteChecks()->create(['checked_at' => now()->subDays(91), 'up' => true]);
        $contract->siteChecks()->create(['checked_at' => now()->subDays(5), 'up' => true]);

        $this->artisan('model:prune', ['--model' => [SiteCheck::class]]);

        $this->assertSame(1, SiteCheck::count());
    }

    public function test_sidebar_counts_sites_that_are_down(): void
    {
        $user = User::factory()->create();
        $this->contract($user, ['monitor_status' => 'down']);
        $this->contract($user, ['monitor_status' => 'up']);

        $this->actingAs($user)->get(route('monitoring.index'))
            ->assertInertia(fn (Assert $page) => $page->where('sitesDownCount', 1));
    }

    public function test_sites_are_ordered_by_creation_and_can_be_reordered(): void
    {
        $user = User::factory()->create();
        $first = $this->contract($user, ['name' => 'Zebra']);
        $second = $this->contract($user, ['name' => 'Anton']);
        $third = $this->contract($user, ['name' => 'Moser']);
        $foreign = $this->contract(User::factory()->create());

        $names = fn () => collect($this->actingAs($user)->get(route('monitoring.index'))->viewData('page')['props']['sites'])->pluck('name')->all();

        // Default: by creation, not by name
        $this->assertSame(['Zebra', 'Anton', 'Moser'], $names());

        // Own order; ids of other users are ignored
        $this->actingAs($user)->put(route('monitoring.order'), ['ids' => [$third->id, $foreign->id, $first->id, $second->id]]);
        $this->assertSame(['Moser', 'Zebra', 'Anton'], $names());
        $this->assertNull($foreign->fresh()->monitor_position);

        // A new website is added at the end
        $this->contract($user, ['name' => 'Neu']);
        $this->assertSame(['Moser', 'Zebra', 'Anton', 'Neu'], $names());

        // Reset: back to creation order
        $this->actingAs($user)->delete(route('monitoring.order.reset'));
        $this->assertSame(['Zebra', 'Anton', 'Moser', 'Neu'], $names());
    }

    public function test_password_protected_sites_are_checked_with_basic_auth(): void
    {
        Http::fake(['kunde.example*' => Http::response('ok', 200)]);
        $contract = $this->contract(User::factory()->create(), [
            'monitor_auth' => 'basic', 'monitor_auth_user' => 'kunde', 'monitor_auth_password' => 'geheim',
        ]);

        app(SiteMonitor::class)->check($contract);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('kunde:geheim')));
        $this->assertSame(1, $contract->siteChecks()->where('up', true)->count());
    }

    public function test_password_prompt_counts_as_up_only_when_chosen(): void
    {
        Http::fake(['kunde.example*' => Http::response('Unauthorized', 401)]);
        $user = User::factory()->create();
        $accept = $this->contract($user, ['monitor_auth' => 'accept_401']);
        $plain = $this->contract($user);

        app(SiteMonitor::class)->check($accept);
        app(SiteMonitor::class)->check($plain);

        $this->assertTrue($accept->siteChecks()->first()->up);
        $this->assertFalse($plain->siteChecks()->first()->up);
        $this->assertStringContainsString('Passwortschutz', $plain->fresh()->monitor_error);
    }

    public function test_monitor_password_is_encrypted_hidden_and_kept_when_left_empty(): void
    {
        $user = User::factory()->create();
        $customer = \App\Models\Customer::factory()->for($user)->create();
        $payload = [
            'customer_id' => $customer->id, 'type' => 'website', 'name' => 'Geschützt', 'url' => 'kunde.example',
            'monthly_price' => 39, 'start_date' => '2026-01-01', 'next_invoice_date' => '2027-01-01', 'monitor_enabled' => true,
            'monitor_auth' => 'basic', 'monitor_auth_user' => 'kunde',
        ];

        // A password is needed the first time
        $this->actingAs($user)->post(route('contracts.store'), $payload)->assertSessionHasErrors('monitor_auth_password');

        $this->actingAs($user)->post(route('contracts.store'), [...$payload, 'monitor_auth_password' => 'geheim'])->assertSessionHasNoErrors();
        $contract = $user->maintenanceContracts()->first();

        $this->assertNotSame('geheim', $contract->getRawOriginal('monitor_auth_password'));   // encrypted at rest
        $this->assertSame('geheim', $contract->monitorPassword());
        $this->assertArrayNotHasKey('monitor_auth_password', $contract->toArray());
        $this->assertTrue($contract->has_monitor_password);

        // Empty field on update keeps the stored password
        $this->actingAs($user)->put(route('contracts.update', $contract), [...$payload, 'monitor_auth_password' => '']);
        $this->assertSame('geheim', $contract->fresh()->monitorPassword());

        // The edit page never contains it
        $this->actingAs($user)->get(route('contracts.edit', $contract))->assertDontSee('geheim');

        // Protection switched off: credentials are removed
        $this->actingAs($user)->put(route('contracts.update', $contract), [...$payload, 'monitor_auth' => 'none']);
        $contract->refresh();
        $this->assertNull($contract->monitor_auth_user);
        $this->assertFalse($contract->has_monitor_password);
    }
}
