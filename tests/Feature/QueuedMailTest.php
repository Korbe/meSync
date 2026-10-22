<?php

namespace Tests\Feature;

use App\Models\MaintenanceContract;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordQueued;
use App\Notifications\Auth\VerifyEmailQueued;
use App\Notifications\ContractDueNotification;
use App\Notifications\SiteDownNotification;
use App\Notifications\SiteRecoveredNotification;
use App\Notifications\SslExpiringNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuedMailTest extends TestCase
{
    use RefreshDatabase;

    private function pushed(string $notification): int
    {
        return Queue::pushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof $notification)->count();
    }

    public function test_every_mail_goes_through_the_queue(): void
    {
        foreach ([ContractDueNotification::class, SiteDownNotification::class, SiteRecoveredNotification::class, SslExpiringNotification::class, VerifyEmailQueued::class, ResetPasswordQueued::class] as $class) {
            $this->assertTrue(is_subclass_of($class, ShouldQueue::class), "{$class} is not queued");
        }
    }

    public function test_reminders_and_monitoring_mails_are_queued(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-10-21');

        $contract = MaintenanceContract::factory()->create(['start_date' => '2026-01-01', 'next_invoice_date' => '2026-10-25']);
        $this->artisan('contracts:remind');

        $this->assertSame(1, $this->pushed(ContractDueNotification::class));
    }

    public function test_verification_and_password_reset_mails_are_queued(): void
    {
        Queue::fake();
        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();
        $this->post(route('password.email'), ['email' => $user->email]);

        $this->assertSame(1, $this->pushed(VerifyEmailQueued::class));
        $this->assertSame(1, $this->pushed(ResetPasswordQueued::class));
    }

    public function test_recovered_mail_shows_the_outage_duration_even_when_sent_later(): void
    {
        Carbon::setTestNow('2026-10-21 12:00:00');
        $monitor = MaintenanceContract::factory()->create()->syncMonitor(['enabled' => true]);
        $notification = new SiteRecoveredNotification($monitor, now()->subMinutes(30));

        // The worker sends it two hours later
        Carbon::setTestNow('2026-10-21 14:00:00');
        $mail = $notification->toMail($monitor->user);

        $this->assertStringContainsString('30 Minuten', implode(' ', $mail->introLines));
    }
}
