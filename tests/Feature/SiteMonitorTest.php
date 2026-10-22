<?php

namespace Tests\Feature;

use App\Models\MaintenanceContract;
use App\Models\Monitor;
use App\Models\User;
use App\Notifications\ContractDueNotification;
use App\Notifications\SiteDownNotification;
use App\Notifications\SiteRecoveredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SiteMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 12:00');
        Notification::fake();
    }

    private function contract(array $attributes = []): MaintenanceContract
    {
        // http:// so the tests don't open real TLS connections for the certificate check
        return MaintenanceContract::factory()->create([
            'url' => 'http://kunde.example',
            'start_date' => '2026-01-01',
            'next_invoice_date' => '2027-01-01',
            ...$attributes,
        ]);
    }

    private function monitor(array $contract = [], array $monitor = []): Monitor
    {
        $site = $this->contract($contract)->syncMonitor(['enabled' => true]);
        $site->forceFill($monitor)->save();

        return $site;
    }

    public function test_site_is_marked_down_after_two_failures_and_owner_is_notified_once(): void
    {
        $monitor = $this->monitor();
        Http::fake(['kunde.example/*' => Http::response('Fehler', 500), 'kunde.example' => Http::response('Fehler', 500)]);

        $this->artisan('sites:check')->assertSuccessful();
        $this->assertNull($monitor->fresh()->status);    // first failure: no alarm yet
        Notification::assertNothingSent();

        $this->artisan('sites:check');
        $this->assertSame('down', $monitor->fresh()->status);
        $this->assertSame('HTTP 500', $monitor->fresh()->error);
        Notification::assertSentToTimes($monitor->user, SiteDownNotification::class, 1);

        $this->artisan('sites:check');
        Notification::assertSentToTimes($monitor->user, SiteDownNotification::class, 1);
    }

    public function test_recovery_sends_an_all_clear(): void
    {
        $monitor = $this->monitor([], ['status' => 'down', 'status_since' => now()->subHour(), 'failures' => 3]);
        Http::fake(['*' => Http::response('OK', 200)]);

        $this->artisan('sites:check');

        $monitor->refresh();
        $this->assertSame('up', $monitor->status);
        $this->assertSame(0, $monitor->failures);
        Notification::assertSentTo($monitor->user, SiteRecoveredNotification::class);
    }

    public function test_only_switched_on_monitors_of_running_contracts_and_own_sites_are_checked(): void
    {
        $checked = $this->monitor();
        $disabled = $this->monitor([], ['enabled' => false]);
        $ended = $this->monitor(['end_date' => '2026-06-30']);
        $pending = $this->monitor(['start_date' => null, 'next_invoice_date' => null]);
        $standalone = $checked->user->monitors()->make(['name' => 'App', 'url' => 'http://app.example']);
        $standalone->forceFill(['user_id' => $checked->user_id])->save();
        $this->assertNull($this->contract(['url' => null])->syncMonitor());
        Http::fake(['*' => Http::response('OK', 200)]);

        $this->artisan('sites:check');

        $this->assertSame('up', $checked->fresh()->status);
        $this->assertSame('up', $standalone->fresh()->status);
        $this->assertNull($disabled->fresh()->checked_at);
        $this->assertNull($ended->fresh()->checked_at);
        $this->assertNull($pending->fresh()->checked_at);
    }

    public function test_site_can_be_checked_manually(): void
    {
        $monitor = $this->monitor();
        Http::fake(['*' => Http::response('OK', 200)]);

        $this->actingAs($monitor->user)->post(route('monitors.check', $monitor))->assertSessionHas('success');

        $this->assertSame('up', $monitor->fresh()->status);
        $this->actingAs(User::factory()->create())->post(route('monitors.check', $monitor))->assertForbidden();
    }

    public function test_due_contracts_are_reminded_once_seven_days_ahead(): void
    {
        $dueSoon = $this->contract(['next_invoice_date' => '2026-10-20']);
        $later = $this->contract(['next_invoice_date' => '2026-12-01']);

        $this->artisan('contracts:remind')->assertSuccessful();

        Notification::assertSentTo($dueSoon->user, ContractDueNotification::class);
        Notification::assertNotSentTo($later->user, ContractDueNotification::class);
        $this->assertSame('2026-10-20', $dueSoon->fresh()->reminder_sent_for->toDateString());

        // Running again the next day does not repeat the reminder
        $this->artisan('contracts:remind');
        Notification::assertSentToTimes($dueSoon->user, ContractDueNotification::class, 1);
    }

    public function test_demo_user_gets_no_reminders(): void
    {
        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);
        $contract = $this->contract(['user_id' => $demo->id, 'next_invoice_date' => '2026-10-20']);

        $this->artisan('contracts:remind')->assertSuccessful();

        Notification::assertNothingSentTo($demo);
        $this->assertNull($contract->fresh()->reminder_sent_for);
    }

    public function test_dashboard_shows_overview(): void
    {
        $monitor = $this->monitor(['next_invoice_date' => '2026-10-20'], ['status' => 'down', 'status_since' => now()]);

        $this->actingAs($monitor->user)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Dashboard')
                ->has('revenueByMonth', 12)
                ->has('dueContracts', 1)
                ->where('sites.0.status', 'down'));
    }
}
