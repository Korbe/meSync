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
}
