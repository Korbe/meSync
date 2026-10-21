<?php

namespace Tests\Feature;

use App\Models\MaintenanceContract;
use App\Models\User;
use App\Notifications\ContractDueNotification;
use App\Notifications\ContractDueTodayNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DueTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-21 08:00:00');
        Notification::fake();
    }

    private function contract(string $due, array $attributes = []): MaintenanceContract
    {
        return MaintenanceContract::factory()->create([
            'start_date' => '2025-01-01', 'next_invoice_date' => $due, 'monthly_price' => 39, ...$attributes,
        ]);
    }

    public function test_status_is_due_today_on_the_due_date(): void
    {
        $this->assertSame('due_today', $this->contract('2026-10-21')->status);
        $this->assertSame('overdue', $this->contract('2026-10-20')->status);
        $this->assertSame('due_soon', $this->contract('2026-10-22')->status);
    }

    public function test_a_mail_is_sent_on_the_due_date_once(): void
    {
        $today = $this->contract('2026-10-21');
        $soon = $this->contract('2026-10-25');

        $this->artisan('contracts:remind')->assertSuccessful();

        // Due today: the "heute fällig" mail, not the early reminder on the same day
        Notification::assertSentTo($today->user, ContractDueTodayNotification::class);
        Notification::assertNotSentTo($today->user, ContractDueNotification::class);
        Notification::assertSentTo($soon->user, ContractDueNotification::class);
        $this->assertSame('2026-10-21', $today->fresh()->due_notice_sent_for->toDateString());

        // Running again the same day sends nothing new
        $this->artisan('contracts:remind');
        Notification::assertSentToTimes($today->user, ContractDueTodayNotification::class, 1);
    }

    public function test_reminder_7_days_ahead_and_due_date_mail_both_come(): void
    {
        $contract = $this->contract('2026-10-28');

        $this->artisan('contracts:remind');
        Notification::assertSentToTimes($contract->user, ContractDueNotification::class, 1);

        Carbon::setTestNow('2026-10-28 08:00:00');
        $this->artisan('contracts:remind');
        Notification::assertSentToTimes($contract->user, ContractDueTodayNotification::class, 1);
        Notification::assertSentToTimes($contract->user, ContractDueNotification::class, 1);
    }

    public function test_no_due_date_mail_for_ended_or_demo_contracts(): void
    {
        $ended = $this->contract('2026-10-21', ['end_date' => '2026-10-01']);
        $demo = $this->contract('2026-10-21', ['user_id' => User::factory()->create(['email' => User::DEMO_EMAIL])->id]);

        $this->artisan('contracts:remind');

        Notification::assertNothingSentTo($ended->user);
        Notification::assertNothingSentTo($demo->user);
    }

    public function test_mail_keeps_the_due_date_even_when_sent_after_invoicing(): void
    {
        $contract = $this->contract('2026-10-21');
        $notification = new ContractDueTodayNotification($contract);

        // Invoiced before the queued mail goes out: the contract moves on a year
        $contract->markInvoiced(now());
        $mail = $notification->toMail($contract->user);

        $this->assertStringContainsString('21.10.2026', implode(' ', $mail->introLines));
        $this->assertStringContainsString('Heute fällig', $mail->subject);
    }

    public function test_dashboard_and_sidebar_show_contracts_due_today(): void
    {
        $contract = $this->contract('2026-10-21', ['name' => 'gasthof-seeblick.at']);
        $this->contract('2026-10-25', ['user_id' => $contract->user_id]);

        $this->actingAs($contract->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('dueToday', 1)
                ->where('dueToday.0.name', 'gasthof-seeblick.at')
                ->where('dueToday.0.status', 'due_today')
                ->where('dueTodayCount', 1));
    }
}
