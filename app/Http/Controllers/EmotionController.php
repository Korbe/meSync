<?php

namespace App\Http\Controllers;

use App\Models\Emotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EmotionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Emotions/Index', [
            "emotions" => Auth::user()->emotions
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Emotions/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'score' => 'required|integer|min:0|max:10',
            'primary' => 'required|string',
            'secondary' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $emotion = Emotion::create([
            'user_id' => Auth::id(),
            'score' => $validated['score'],
            'primary' => $validated['primary'],
            'secondary' => $validated['secondary'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('emotions.index')->with('success', 'Emotion successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Emotion $emotion)
    {
        if ($emotion->user_id !== Auth::id())
            abort(403);

        return Inertia::render('Emotions/Show', [
            'emotion' => $emotion
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Emotion $emotion)
    {
        if ($emotion->user_id !== Auth::id())
            abort(403);

        return Inertia::render('Emotions/Edit', [
            'emotion' => $emotion
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Emotion $emotion)
    {
        if ($emotion->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'score' => 'sometimes|integer|min:0|max:10',
            'primary' => 'sometimes|string',
            'secondary' => 'sometimes|string',
            'description' => 'nullable|string',
        ]);

        $emotion->update($validated);

        return response()->json($emotion);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Emotion $emotion)
    {
        if ($emotion->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $emotion->delete();

        return redirect()->route('emotions.index')->with('success', 'Emotion deleted successfully');
    }

    public function EmotionsMapping()
    {
        $emotions = [
            PrimaryEmotion::Glücklich->value => [
                SecondaryEmotion::Freudig->value,
                SecondaryEmotion::Dankbar->value,
                SecondaryEmotion::Neugierig->value,
                SecondaryEmotion::Ausgeglichen->value,
                SecondaryEmotion::Begeistert->value,
                SecondaryEmotion::Leidenschaftlich->value,
                SecondaryEmotion::Angezogen->value,
                SecondaryEmotion::Erschöpft->value,
                
            ],
            PrimaryEmotion::Traurig->value => [
                SecondaryEmotion::Melancholisch->value,
                SecondaryEmotion::Frustriert->value,
                SecondaryEmotion::Nachdenklich->value,
                SecondaryEmotion::Deprimiert->value,
                SecondaryEmotion::Erschöpft->value,
                SecondaryEmotion::Gekränkt->value,
                SecondaryEmotion::Enttäuscht->value,
            ],
            PrimaryEmotion::Wütend->value => [
                SecondaryEmotion::Frustriert->value,
                SecondaryEmotion::Genervt->value,
                SecondaryEmotion::Verärgert->value,
                SecondaryEmotion::Gereizt->value,
                SecondaryEmotion::Gekränkt->value,
                SecondaryEmotion::Enttäuscht->value,
                SecondaryEmotion::Explosiv->value,
            ],
            PrimaryEmotion::Überfordert->value => [
                SecondaryEmotion::Erschöpft->value,
                SecondaryEmotion::Verwirrt->value,
                SecondaryEmotion::Frustriert->value,
                SecondaryEmotion::Gestresst->value,
                SecondaryEmotion::Überwältigt->value,
            ],
            PrimaryEmotion::Ängstlich->value => [
                SecondaryEmotion::Verwirrt->value,
                SecondaryEmotion::Genervt->value,
                SecondaryEmotion::Nachdenklich->value,
                SecondaryEmotion::Erschöpft->value,
                SecondaryEmotion::Panisch->value,

            ],
            PrimaryEmotion::Angewidert->value => [
                SecondaryEmotion::Abgestossen->value,
                SecondaryEmotion::Genervt->value,
                SecondaryEmotion::Schockiert->value,
            ],
            PrimaryEmotion::Neutral->value => [
                SecondaryEmotion::Ausgeglichen->value,
                SecondaryEmotion::Okay->value,
                SecondaryEmotion::Reflektiert->value,
                SecondaryEmotion::Nachdenklich->value,
            ],
        ];

        // Konvertiere das Mapping in das gewünschte Format
        $formattedEmotions = [];

        foreach ($emotions as $primaryKey => $secondaryKeys) {
            $formattedEmotions[] = [
                'label' => PrimaryEmotion::from($primaryKey)->name, // Deutscher Name
                'value' => $primaryKey, // Englischer Wert
                'options' => array_map(fn($key) => [
                    'label' => SecondaryEmotion::from($key)->name, // Deutscher Name
                    'value' => $key // Englischer Wert
                ], $secondaryKeys)
            ];
        }

        return response()->json($formattedEmotions);
    }
}
