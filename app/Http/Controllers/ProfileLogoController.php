<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileLogoController extends Controller
{
    /**
     * Upload or replace the user's company logo (single file collection).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'logo.required' => 'Bitte wähle ein Bild aus.',
            'logo.image' => 'Das Logo muss ein Bild sein.',
            'logo.mimes' => 'Erlaubt sind PNG, JPG und WebP.',
            'logo.max' => 'Das Logo darf maximal 2 MB groß sein.',
        ]);

        $request->user()
            ->addMediaFromRequest('logo')
            ->toMediaCollection('logo');

        return back()->with('success', 'Logo wurde gespeichert.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->clearMediaCollection('logo');

        return back()->with('success', 'Logo wurde entfernt.');
    }
}
