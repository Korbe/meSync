<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The company logo (single file collections): "logo" for invoices, offers and mails,
 * "logo_dark" with light lettering for mails in dark mode. The route sets the collection.
 */
class ProfileLogoController extends Controller
{
    public function store(Request $request, string $collection = 'logo'): RedirectResponse
    {
        $request->validate([
            $collection => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            "{$collection}.required" => 'Bitte wähle ein Bild aus.',
            "{$collection}.image" => 'Das Logo muss ein Bild sein.',
            "{$collection}.mimes" => 'Erlaubt sind PNG, JPG und WebP.',
            "{$collection}.max" => 'Das Logo darf maximal 2 MB groß sein.',
        ]);

        $request->user()
            ->addMediaFromRequest($collection)
            ->toMediaCollection($collection);

        return back()->with('success', 'Logo wurde gespeichert.');
    }

    public function destroy(Request $request, string $collection = 'logo'): RedirectResponse
    {
        $request->user()->clearMediaCollection($collection);

        return back()->with('success', 'Logo wurde entfernt.');
    }
}
