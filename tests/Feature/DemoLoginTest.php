<?php

namespace Tests\Feature;

use App\Models\MaintenanceContract;
use App\Models\User;
use App\Notifications\ContractDueNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_login_creates_seeds_and_logs_in_the_demo_user(): void
    {
        $this->post(route('demo.login'))->assertRedirect(route('dashboard'));

        $user = DatabaseSeeder::demoUser();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(35, $user->customers()->count());

        // Second login does not seed again
        auth()->logout();
        $this->post(route('demo.login'));
        $this->assertSame(35, $user->customers()->count());
    }

    public function test_no_mails_are_sent_to_the_demo_user(): void
    {
        $contract = MaintenanceContract::factory()->for(DatabaseSeeder::demoUser())->create();
        $notification = new ContractDueNotification($contract);
        $other = User::factory()->create();

        // Without a mail route the mail channel skips the notification
        $this->assertNull($contract->user->routeNotificationFor('mail', $notification));
        $this->assertSame($other->email, $other->routeNotificationFor('mail', $notification));
    }

    public function test_demo_login_is_only_for_guests(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('demo.login'))->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }
}
