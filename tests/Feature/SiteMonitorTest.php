<?php

namespace Tests\Feature;

use App\Models\MaintenanceContract;
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

    public function test_site_is_marked_down_after_two_failures_and_owner_is_notified_once(): void
    {
        $contract = $this->contract();
        Http::fake(['kunde.example/*' => Http::response('Fehler', 500), 'kunde.example' => Http::response('Fehler', 500)]);

        $this->artisan('sites:check')->assertSuccessful();
        $this->assertNull($contract->fresh()->monitor_status);    // first failure: no alarm yet
        Notification::assertNothingSent();

        $this->artisan('sites:check');
        $this->assertSame('down', $contract->fresh()->monitor_status);
        $this->assertSame('HTTP 500', $contract->fresh()->monitor_error);
        Notification::assertSentToTimes($contract->user, SiteDownNotification::class, 1);

        $this->artisan('sites:check');
        Notification::assertSentToTimes($contract->user, SiteDownNotification::class, 1);
    }

    public function test_recovery_sends_an_all_clear(): void
    {
        $contract = $this->contract(['monitor_status' => 'down', 'monitor_status_since' => now()->subHour(), 'monitor_failures' => 3]);
        Http::fake(['*' => Http::response('OK', 200)]);

        $this->artisan('sites:check');

        $contract->refresh();
        $this->assertSame('up', $contract->monitor_status);
        $this->assertSame(0, $contract->monitor_failures);
        Notification::assertSentTo($contract->user, SiteRecoveredNotification::class);
    }

    public function test_only_active_contracts_with_url_and_monitoring_are_checked(): void
    {
        $checked = $this->contract();
        $disabled = $this->contract(['monitor_enabled' => false]);
        $ended = $this->contract(['end_date' => '2026-06-30']);
        $noUrl = $this->contract(['url' => null]);
        Http::fake(['*' => Http::response('OK', 200)]);

        $this->artisan('sites:check');

        $this->assertSame('up', $checked->fresh()->monitor_status);
        $this->assertNull($disabled->fresh()->monitor_checked_at);
        $this->assertNull($ended->fresh()->monitor_checked_at);
        $this->assertNull($noUrl->fresh()->monitor_checked_at);
    }

    public function test_site_can_be_checked_manually(): void
    {
        $contract = $this->contract();
        Http::fake(['*' => Http::response('OK', 200)]);

        $this->actingAs($contract->user)->post(route('contracts.check', $contract))->assertSessionHas('success');

        $this->assertSame('up', $contract->fresh()->monitor_status);
        $this->actingAs(User::factory()->create())->post(route('contracts.check', $contract))->assertForbidden();
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
        $contract = $this->contract(['next_invoice_date' => '2026-10-20', 'monitor_status' => 'down', 'monitor_status_since' => now()]);

        $this->actingAs($contract->user)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Dashboard')
                ->has('revenueByMonth', 12)
                ->has('dueContracts', 1)
                ->where('sites.0.monitor_status', 'down'));
    }
}
