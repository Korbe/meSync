<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fills the demo user (demo@mesync.test / password) with random data and controls
 * which seeders run in which order. Other users are never touched.
 *
 *   php artisan db:seed   → demo user from scratch, with fresh data
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_EMAIL = User::DEMO_EMAIL;

    public function run(): void
    {
        if (app()->isProduction() && ! $this->command->confirm('Produktivumgebung! Wirklich einen Demo-Benutzer anlegen?')) {
            return;
        }

        // Start over: deleting the user cascades to all of its data
        User::where('email', self::DEMO_EMAIL)->first()?->delete();

        $user = self::demoUser();
        $this->command?->info("Zufallsdaten für {$user->email}");

        $this->seedFor($user);
    }

    /**
     * Fill the demo user with random data. Also used by the local demo login.
     */
    public function seedFor(User $user): void
    {
        if ($user->email !== self::DEMO_EMAIL) {
            throw new \LogicException('Zufallsdaten werden nur für den Demo-Benutzer erzeugt.');
        }

        $this->callWith(BusinessProfileSeeder::class, ['user' => $user]);
        $this->callWith(CatalogItemSeeder::class, ['user' => $user]);
        $this->callWith(CustomerSeeder::class, ['user' => $user]);
        $this->callWith(MaintenanceContractSeeder::class, ['user' => $user]);
        $this->callWith(InvoiceSeeder::class, ['user' => $user]);
        $this->callWith(InventoryItemSeeder::class, ['user' => $user]);
        $this->callWith(FinanceSeeder::class, ['user' => $user]);
    }

    /**
     * The demo user (password "password"), created when missing.
     */
    public static function demoUser(): User
    {
        return User::where('email', self::DEMO_EMAIL)->first() ?? User::factory()->create([
            'name' => 'Demo',
            'email' => self::DEMO_EMAIL,
        ]);
    }
}
