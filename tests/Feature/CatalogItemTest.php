<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_item_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'PC-Hilfe vor Ort', 'description' => 'inkl. Anfahrt Villach', 'unit' => 'Stunde', 'unit_price' => '65,00',
        ])->assertRedirect(route('catalog.index'));

        $item = $user->catalogItems()->sole();
        $this->assertSame('65.00', $item->unit_price);

        $this->actingAs($user)->put(route('catalog.update', $item), ['name' => 'PC-Hilfe', 'unit' => 'Stunde', 'unit_price' => '70'])
            ->assertRedirect(route('catalog.index'));
        $this->assertSame('PC-Hilfe', $item->fresh()->name);

        $this->actingAs($user)->delete(route('catalog.destroy', $item))->assertRedirect(route('catalog.index'));
        $this->assertModelMissing($item);
    }

    public function test_catalog_is_offered_in_the_invoice_form(): void
    {
        $user = User::factory()->create();
        CatalogItem::factory()->for($user)->create(['name' => 'Website Basic']);
        CatalogItem::factory()->create(); // other user

        $this->actingAs($user)->get(route('invoices.create'))
            ->assertInertia(fn (Assert $page) => $page->has('catalog', 1)->where('catalog.0.name', 'Website Basic'));
    }

    public function test_catalog_item_created_from_the_invoice_form_returns_there(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('invoices.create'))
            ->post(route('catalog.store'), [
                'name' => 'PC-Hilfe vor Ort', 'pricing' => 'time', 'unit_price' => '50', 'base_minutes' => 30, 'step_minutes' => 15, 'step_price' => '20,00', 'inline' => true,
            ])
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHas('success');

        $item = $user->catalogItems()->sole();
        $this->assertSame('time', $item->pricing);
        $this->assertSame('20.00', $item->step_price);

        // The invoice form gets it right away
        $this->actingAs($user)->get(route('invoices.create'))
            ->assertInertia(fn (Assert $page) => $page->where('catalog.0.name', 'PC-Hilfe vor Ort'));
    }

    public function test_catalog_items_of_other_users_are_forbidden(): void
    {
        $item = CatalogItem::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('catalog.edit', $item))->assertForbidden();
        $this->actingAs(User::factory()->create())->delete(route('catalog.destroy', $item))->assertForbidden();
    }

    public function test_time_rate_can_be_created_and_calculates_started_steps(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'PC-Hilfe', 'pricing' => 'time', 'unit_price' => '50',
            'base_minutes' => 30, 'step_minutes' => 15, 'step_price' => '20,00',
        ])->assertSessionHasNoErrors();

        $item = $user->catalogItems()->sole();

        $this->assertTrue($item->isTimeBased());
        $this->assertSame(50.0, $item->priceForMinutes(20));   // within the first half hour
        $this->assertSame(50.0, $item->priceForMinutes(30));
        $this->assertSame(70.0, $item->priceForMinutes(31));   // a started quarter counts fully
        $this->assertSame(130.0, $item->priceForMinutes(80));  // 50 + 4 × 20
    }

    public function test_time_rate_requires_its_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('catalog.store'), ['name' => 'PC-Hilfe', 'pricing' => 'time', 'unit_price' => '50'])
            ->assertSessionHasErrors(['base_minutes', 'step_minutes', 'step_price']);
    }

    public function test_fixed_price_clears_time_fields(): void
    {
        $user = User::factory()->create();
        $item = CatalogItem::factory()->for($user)->create(['pricing' => 'time', 'base_minutes' => 30, 'step_minutes' => 15, 'step_price' => 20]);

        $this->actingAs($user)->put(route('catalog.update', $item), ['name' => 'Pauschal', 'pricing' => 'fixed', 'unit_price' => '99']);

        $item->refresh();
        $this->assertFalse($item->isTimeBased());
        $this->assertNull($item->step_price);
    }
}
