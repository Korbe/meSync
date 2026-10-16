<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_fills_only_the_demo_user_with_consistent_data(): void
    {
        $other = User::factory()->create();

        $this->seed();

        $user = DatabaseSeeder::demoUser();
        $this->assertSame(35, $user->customers()->count());
        $this->assertGreaterThan(0, $user->maintenanceContracts()->count());
        $this->assertGreaterThan(45, $user->invoices()->count());
        $this->assertNotNull($user->businessProfile);
        $this->assertSame(0, $user->maintenanceContracts()->where('monitor_enabled', true)->count());

        // Invoice numbers are unique and every invoice has items
        $this->assertSame($user->invoices()->count(), $user->invoices()->distinct('number')->count('number'));
        $this->assertSame(0, $user->invoices()->doesntHave('items')->count());

        // No invoice is overpaid
        foreach ($user->invoices()->with(['items', 'payments'])->get() as $invoice) {
            $this->assertLessThanOrEqual(max(0, $invoice->total) + 0.01, $invoice->payments->sum('amount'), $invoice->number);
        }

        // Other users stay untouched
        $this->assertSame(0, $other->customers()->count());
        $this->assertSame(0, $other->invoices()->count());
        $this->assertNull($other->businessProfile);
    }

    public function test_seeding_again_starts_the_demo_user_over(): void
    {
        $this->seed();
        $this->seed();

        $user = DatabaseSeeder::demoUser();
        $this->assertSame(1, User::where('email', DatabaseSeeder::DEMO_EMAIL)->count());
        $this->assertSame(35, $user->customers()->count());
    }

    public function test_other_users_cannot_be_seeded(): void
    {
        $this->expectException(\LogicException::class);

        app(DatabaseSeeder::class)->setContainer(app())->seedFor(User::factory()->create());
    }
}
