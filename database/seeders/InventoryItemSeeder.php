<?php

namespace Database\Seeders;

use App\Enums\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryItemSeeder extends Seeder
{
    /**
     * [name, category, net price, supplier, days ago]
     */
    private const ITEMS = [
        ['MacBook Pro 14″ M3 Pro', InventoryCategory::Computer, 2290.00, 'Apple Store', 640],
        ['Werkstatt-PC Ryzen 7', InventoryCategory::Computer, 1349.00, 'Selbst gebaut', 420],
        ['iPhone 15', InventoryCategory::Mobile, 799.00, 'Hartlauer', 380],
        ['Dell UltraSharp 27″ U2723QE', InventoryCategory::Peripherals, 529.00, 'Amazon', 600],
        ['Dell UltraSharp 27″ U2723QE', InventoryCategory::Peripherals, 529.00, 'Amazon', 600],
        ['Logitech MX Keys + MX Master 3S', InventoryCategory::Peripherals, 189.00, 'MediaMarkt', 300],
        ['Synology DS923+ NAS', InventoryCategory::Network, 589.00, 'Geizhals / e-tec', 500],
        ['Ubiquiti UniFi Dream Machine', InventoryCategory::Network, 349.00, 'Amazon', 210],
        ['Fluke Netzwerktester', InventoryCategory::Tools, 1180.00, 'Conrad', 150],
        ['iFixit Pro Tech Toolkit', InventoryCategory::Tools, 69.00, 'iFixit', 700],
        ['Lötstation Weller WE 1010', InventoryCategory::Tools, 159.00, 'Conrad', 260],
        ['Höhenverstellbarer Schreibtisch', InventoryCategory::Office, 649.00, 'IKEA', 820],
        ['Bürostuhl Steelcase Series 2', InventoryCategory::Office, 1190.00, 'Bene', 820],
        ['JetBrains All Products Pack', InventoryCategory::Software, 249.00, 'JetBrains', 90],
        ['Adobe Creative Cloud (Jahreslizenz)', InventoryCategory::Software, 715.00, 'Adobe', 40],
    ];

    public function run(User $user): void
    {
        foreach (self::ITEMS as [$name, $category, $price, $supplier, $daysAgo]) {
            $user->inventoryItems()->create([
                'name' => $name,
                'category' => $category,
                'serial_number' => fake()->optional(0.6)->bothify('??##-####-####'),
                'supplier' => $supplier,
                'purchased_at' => today()->subDays($daysAgo),
                'price' => $price,
                'tax_rate' => 20,
                'useful_life_years' => $price > InventoryItem::GWG_LIMIT ? $category->usefulLifeYears() : null,
                'status' => 'active',
            ]);
        }

        // Old laptop, sold after the new MacBook arrived
        $user->inventoryItems()->create([
            'name' => 'Lenovo ThinkPad T14',
            'category' => InventoryCategory::Computer,
            'supplier' => 'Lenovo',
            'purchased_at' => today()->subDays(1300),
            'price' => 1290.00,
            'tax_rate' => 20,
            'useful_life_years' => 3,
            'status' => 'sold',
            'retired_at' => today()->subDays(600),
            'sale_price' => 350.00,
            'notes' => 'Über willhaben verkauft.',
        ]);
    }
}
