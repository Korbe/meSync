<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Address suggestions while typing (customer form). Uses Photon (OpenStreetMap, no API key),
 * results near Villach first. Runs through the server, so the browser talks to no third party.
 */
class AddressSearchController extends Controller
{
    private const URL = 'https://photon.komoot.io/api/';

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->validate(['q' => 'required|string|min:3|max:200'])['q']);

        $suggestions = Cache::remember('address:'.md5(mb_strtolower($query)), now()->addDay(), function () use ($query) {
            try {
                $features = Http::timeout(5)->get(self::URL, [
                    'q' => $query,
                    'lang' => 'de',
                    'limit' => 8,
                    'lat' => 46.61,
                    'lon' => 13.85,
                ])->throw()->json('features', []);
            } catch (\Throwable) {
                return null;
            }

            return collect($features)
                ->map(fn (array $feature) => $this->toAddress($feature['properties'] ?? []))
                ->filter(fn (array $address) => $address['street'] || $address['city'])
                ->unique(fn (array $address) => $address['street'].'|'.$address['zip'].'|'.$address['city'])
                ->take(5)
                ->values()
                ->all();
        });

        // null (service failed) isn't cached, so the next keystroke tries again
        return response()->json($suggestions ?? []);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, string|null>
     */
    private function toAddress(array $properties): array
    {
        $street = $properties['street'] ?? (($properties['type'] ?? null) === 'street' ? ($properties['name'] ?? null) : null);

        return [
            'name' => ($properties['type'] ?? null) === 'house' ? ($properties['name'] ?? null) : null,
            'street' => $street ? trim($street.' '.($properties['housenumber'] ?? '')) : null,
            'zip' => $properties['postcode'] ?? null,
            'city' => $properties['city'] ?? $properties['town'] ?? $properties['village'] ?? $properties['locality'] ?? null,
            'country' => $properties['country'] ?? null,
        ];
    }
}
