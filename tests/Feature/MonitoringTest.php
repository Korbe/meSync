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
}
