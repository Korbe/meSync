<?php

namespace Tests\Feature;

use App\Enums\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    // Minimal real PDF, so the MIME type check of the media library accepts it
    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16');
    }

    private function item(User $user, array $attributes = []): InventoryItem
    {
        return InventoryItem::factory()->for($user)->create($attributes);
    }

    public function test_gwg_is_written_off_in_the_year_of_purchase(): void
    {
        $item = $this->item(User::factory()->create(), ['price' => 999, 'purchased_at' => '2026-03-01', 'useful_life_years' => null]);

        $this->assertTrue($item->is_gwg);
        $this->assertSame([['year' => 2026, 'depreciation' => 999.0, 'book_value' => 0.0]], $item->depreciationSchedule());
        $this->assertSame(0.0, $item->book_value);
    }

    public function test_depreciation_uses_the_half_year_rule(): void
    {
        $user = User::factory()->create();

        // Bought in the first half: full yearly amount in the first year
        $first = $this->item($user, ['price' => 3000, 'purchased_at' => '2025-03-10', 'useful_life_years' => 3]);
        $this->assertSame([1000.0, 1000.0, 1000.0], array_column($first->depreciationSchedule(), 'depreciation'));
        $this->assertSame(1000.0, $first->book_value);

        // Bought in the second half: half in the first year, the rest at the end
        $second = $this->item($user, ['price' => 3000, 'purchased_at' => '2025-09-10', 'useful_life_years' => 3]);
        $this->assertSame([500.0, 1000.0, 1000.0, 500.0], array_column($second->depreciationSchedule(), 'depreciation'));
        $this->assertSame(1500.0, $second->book_value);
    }

    public function test_sold_items_have_no_book_value_and_stop_depreciating(): void
    {
        $item = $this->item(User::factory()->create(), [
            'price' => 3000, 'purchased_at' => '2024-02-01', 'useful_life_years' => 3,
            'status' => 'sold', 'retired_at' => '2025-05-01', 'sale_price' => 800,
        ]);

        $this->assertSame([2024], array_column($item->depreciationSchedule(), 'year'));
        $this->assertSame(0.0, $item->book_value);
    }

    public function test_item_can_be_created_with_receipt_and_comma_prices(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('inventory.store'), [
            'name' => 'Laptop',
            'category' => 'computer',
            'purchased_at' => '2026-02-01',
            'price' => '1499,90',
            'tax_rate' => '20',
            'status' => 'active',
            'receipt' => UploadedFile::fake()->createWithContent('rechnung.pdf', self::PDF),
        ])->assertRedirect(route('inventory.index'));

        $item = $user->inventoryItems()->first();
        $this->assertSame('1499.90', $item->price);
        $this->assertSame(InventoryCategory::Computer->usefulLifeYears(), $item->useful_life_years);
        $this->assertTrue($item->hasMedia('receipt'));

        $this->actingAs($user)->get(route('inventory.receipt', $item))->assertOk();
    }

    public function test_gwg_gets_no_useful_life_and_active_items_no_sale_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('inventory.store'), [
            'name' => 'Maus', 'category' => 'peripherals', 'purchased_at' => '2026-02-01',
            'price' => '49', 'tax_rate' => '20', 'useful_life_years' => '5',
            'status' => 'active', 'retired_at' => '2026-05-01', 'sale_price' => '10',
        ]);

        $item = $user->inventoryItems()->first();
        $this->assertNull($item->useful_life_years);
        $this->assertNull($item->retired_at);
        $this->assertNull($item->sale_price);
    }

    public function test_retired_items_need_a_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('inventory.store'), [
            'name' => 'Alt', 'category' => 'computer', 'purchased_at' => '2024-02-01',
            'price' => '500', 'tax_rate' => '20', 'status' => 'sold',
        ])->assertSessionHasErrors('retired_at');
    }

    public function test_index_shows_totals_of_active_items(): void
    {
        $user = User::factory()->create();
        $this->item($user, ['price' => 3000, 'purchased_at' => '2025-03-10', 'useful_life_years' => 3]);
        $this->item($user, ['price' => 500, 'purchased_at' => '2026-01-10', 'useful_life_years' => null]);
        $this->item($user, ['price' => 800, 'status' => 'disposed', 'retired_at' => '2026-05-01', 'purchased_at' => '2023-01-01']);
        $this->item(User::factory()->create());

        $this->actingAs($user)->get(route('inventory.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Index')
                ->has('items', 3)
                ->where('totals.count', 2)
                ->where('totals.price', 3500)
                ->where('totals.book_value', 1000)
                ->where('totals.bought_this_year', 500));
    }

    public function test_form_pages_render(): void
    {
        $user = User::factory()->create();
        $item = $this->item($user, ['price' => 3000, 'purchased_at' => '2025-03-10', 'useful_life_years' => 3]);

        $this->actingAs($user)->get(route('inventory.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Inventory/Create')->has('categories', count(InventoryCategory::cases())));

        $this->actingAs($user)->get(route('inventory.edit', $item))
            ->assertInertia(fn (Assert $page) => $page->component('Inventory/Update')->has('schedule', 3)->where('receipt', null));

        $this->actingAs($user)->get(route('inventory.delete', $item))->assertOk();
    }

    public function test_receipts_are_stored_privately(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $item = $this->item($user);
        $media = $item->addMedia(UploadedFile::fake()->createWithContent('beleg.pdf', self::PDF))->toMediaCollection('receipt');

        // Private disk, not the public one that is linked into public/storage
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(app('router')->has('storage.local'));

        // Only through the app, only for the owner
        $this->get(route('inventory.receipt', $item))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('inventory.receipt', $item))->assertForbidden();
        $this->actingAs($user)->get(route('inventory.receipt', $item))->assertOk();
    }

    public function test_items_of_other_users_are_protected(): void
    {
        $item = $this->item(User::factory()->create());
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('inventory.edit', $item))->assertForbidden();
        $this->actingAs($user)->get(route('inventory.receipt', $item))->assertForbidden();
        $this->actingAs($user)->delete(route('inventory.destroy', $item))->assertForbidden();
    }

    public function test_csv_export_is_excel_friendly(): void
    {
        $user = User::factory()->create();
        $this->item($user, ['name' => 'Laptop', 'price' => 1234.5, 'purchased_at' => '2026-02-01', 'useful_life_years' => 3]);

        $csv = $this->actingAs($user)->get(route('inventory.csv'))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Laptop;', $csv);
        $this->assertStringContainsString('1234,50', $csv);
    }

    public function test_inventory_and_receipts_are_part_of_the_backup(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $item = $this->item($user, ['name' => 'Laptop']);
        $item->addMedia(UploadedFile::fake()->createWithContent('beleg.pdf', self::PDF))->toMediaCollection('receipt');

        $json = $this->actingAs($user)->get(route('backup.download'))->streamedContent();
        $item->delete();

        $this->actingAs($user)->post(route('backup.restore'), [
            'file' => UploadedFile::fake()->createWithContent('backup.json', $json),
            'mode' => 'replace',
        ])->assertSessionHas('success');

        $restored = $user->inventoryItems()->first();
        $this->assertSame('Laptop', $restored->name);
        $this->assertTrue($restored->hasMedia('receipt'));
    }
}
