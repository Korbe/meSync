<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Terms (AGB) and privacy policy as PDF. Stored privately; every offer mail carries them along.
 */
class ProfileDocumentController extends Controller
{
    public function show(Request $request, string $document): BinaryFileResponse
    {
        $media = $request->user()->getFirstMedia($document);
        abort_unless($media && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }

    public function store(Request $request, string $document): RedirectResponse
    {
        $label = User::DOCUMENTS[$document];

        $request->validate(['file' => 'required|file|mimes:pdf|max:10240'], [
            'file.required' => 'Bitte wähle ein PDF aus.',
            'file.mimes' => "Die {$label} muss ein PDF sein.",
            'file.max' => 'Das PDF darf maximal 10 MB groß sein.',
        ]);

        $request->user()->addMediaFromRequest('file')->toMediaCollection($document);

        return back()->with('success', "{$label} wurde gespeichert – sie geht ab jetzt mit jedem Angebot mit.");
    }

    public function destroy(Request $request, string $document): RedirectResponse
    {
        $request->user()->clearMediaCollection($document);

        return back()->with('success', User::DOCUMENTS[$document].' wurde entfernt.');
    }
}
