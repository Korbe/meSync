<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_are_mapped_to_form_fields_without_duplicates(): void
    {
        $address = ['type' => 'house', 'housenumber' => '1', 'street' => 'Hauptplatz', 'city' => 'Villach', 'postcode' => '9500', 'country' => 'Österreich'];
        Http::fake(['photon.komoot.io/*' => Http::response(['features' => [
            ['properties' => $address],
            ['properties' => $address + ['name' => 'Gelateria Hauptplatz']],   // same address, a shop in the building
            ['properties' => ['type' => 'street', 'name' => 'Ringmauergasse', 'city' => 'Villach', 'postcode' => '9500', 'country' => 'Österreich']],
        ]])]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('address.search', ['q' => 'Hauptplatz 1 Villach']))
            ->assertOk()
            ->assertExactJson([
                ['name' => null, 'street' => 'Hauptplatz 1', 'zip' => '9500', 'city' => 'Villach', 'country' => 'Österreich'],
                ['name' => null, 'street' => 'Ringmauergasse', 'zip' => '9500', 'city' => 'Villach', 'country' => 'Österreich'],
            ]);
    }

    public function test_service_failure_returns_no_suggestions(): void
    {
        Http::fake(['photon.komoot.io/*' => Http::response('', 503)]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('address.search', ['q' => 'Hauptplatz']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_guests_cannot_search(): void
    {
        $this->getJson(route('address.search', ['q' => 'Hauptplatz']))->assertUnauthorized();
    }
}
