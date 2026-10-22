<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Handwritten signature for offers. Stored privately and only shown to its owner;
 * the PDF embeds it directly.
 */
class ProfileSignatureController extends Controller
{
    public function show(Request $request): BinaryFileResponse
    {
        $signature = $request->user()->getFirstMedia('signature');
        abort_unless($signature && is_file($signature->getPath()), 404);

        return response()->file($signature->getPath(), [
            'Content-Type' => $signature->mime_type,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'signature.required' => 'Bitte wähle ein Bild aus.',
            'signature.image' => 'Die Unterschrift muss ein Bild sein.',
            'signature.mimes' => 'Erlaubt sind PNG, JPG und WebP.',
            'signature.max' => 'Die Unterschrift darf maximal 2 MB groß sein.',
        ]);

        $request->user()
            ->addMediaFromRequest('signature')
            ->toMediaCollection('signature');

        return back()->with('success', 'Unterschrift wurde gespeichert.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->clearMediaCollection('signature');

        return back()->with('success', 'Unterschrift wurde entfernt.');
    }
}
