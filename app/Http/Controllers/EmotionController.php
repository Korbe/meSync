<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Emotion;
use Illuminate\Http\Request;
use App\Enums\PrimaryEmotion;
use Illuminate\Support\Carbon;
use App\Enums\SecondaryEmotion;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Emotion\EmotionRequest;
use App\Http\Requests\Emotion\EmotionFilterRequest;

class EmotionController extends Controller
{
    public function index(EmotionFilterRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Get the earliest and latest timestamps from emotions
        $dateRange = $user->emotions()
            ->selectRaw('MIN(created_at) as earliest, MAX(created_at) as latest')
            ->first();

        // Retrieve validated data from the request
        $validated = $request->validated();

        // Parse filter dates from validated data or use defaults
        $startDate = isset($validated['startDate'])
            ? Carbon::parse($validated['startDate'])->startOfDay()
            : Carbon::parse($dateRange->earliest)->startOfDay();

        $endDate = isset($validated['endDate'])
            ? Carbon::parse($validated['endDate'])->endOfDay()
            : Carbon::parse($dateRange->latest)->endOfDay();

        $emotionsQuery = $user->emotions()
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Check if 'description' is present in the validated request data
        if (isset($validated['onlyWithDescription']) && filter_var($validated['onlyWithDescription'], FILTER_VALIDATE_BOOLEAN)) {
            $emotionsQuery->whereNotNull('description')->where('description', '!=', '');
        }

        $emotions = $emotionsQuery
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Add translation fields: primary_label and secondary_label
        $emotions->getCollection()->transform(function ($emotion) {
            $emotion->primary_label = __('emotions.primary.' . $emotion->primary->value);
            $emotion->secondary_label = __('emotions.secondary.' . $emotion->secondary->value);
            return $emotion;
        });

        return Inertia::render('Emotions/Index', [
            'emotions' => $emotions,
            'dateRange' => [
                'startDate' => $startDate,
                'endDate' => $endDate
            ]
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
    public function store(EmotionRequest $request)
    {
        $validated = $request->validated();

        $emotion = Emotion::create([
            'user_id' => Auth::id(),
            'score' => $validated['score'],
            'primary' => $validated['primary'],
            'secondary' => $validated['secondary'],
            'description' => $validated['description'] ?? null,
        ]);

        //return redirect("asdfasdf");//
        return redirect()->route('emotions.index')->with('success', 'Emotion successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Emotion $emotion)
    {
        if ($emotion->user_id !== Auth::id())
            abort(403);

        $primaryTranslation = __('emotions.primary.' . $emotion->primary->value);
        $secondaryTranslation = __('emotions.secondary.' . $emotion->secondary->value);

        return Inertia::render('Emotions/Show', [
            'emotion' => $emotion,
            'primaryLabel' => $primaryTranslation,
            'secondaryLabel' => $secondaryTranslation,
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
    public function update(EmotionRequest $request, Emotion $emotion)
    {
        if ($emotion->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        $emotion->update($validated);

        return redirect()->route('emotions.show', $emotion->id)->with('success', 'Emotion updated successfully');
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
            PrimaryEmotion::Happy->value => [
                SecondaryEmotion::Joyful->value,
                SecondaryEmotion::Grateful->value,
                SecondaryEmotion::Curious->value,
                SecondaryEmotion::Balanced->value,
                SecondaryEmotion::Enthusiastic->value,
                SecondaryEmotion::Passionate->value,
                SecondaryEmotion::Attracted->value,
                SecondaryEmotion::Exhausted->value,
            ],
            PrimaryEmotion::Sad->value => [
                SecondaryEmotion::Melancholic->value,
                SecondaryEmotion::Frustrated->value,
                SecondaryEmotion::Thoughtful->value,
                SecondaryEmotion::Depressed->value,
                SecondaryEmotion::Exhausted->value,
                SecondaryEmotion::Offended->value,
                SecondaryEmotion::Disappointed->value,
            ],
            PrimaryEmotion::Angry->value => [
                SecondaryEmotion::Frustrated->value,
                SecondaryEmotion::Annoyed->value,
                SecondaryEmotion::Irritated->value,
                SecondaryEmotion::Agitated->value,
                SecondaryEmotion::Offended->value,
                SecondaryEmotion::Disappointed->value,
                SecondaryEmotion::Explosive->value,
            ],
            PrimaryEmotion::Overwhelmed->value => [
                SecondaryEmotion::Exhausted->value,
                SecondaryEmotion::Confused->value,
                SecondaryEmotion::Frustrated->value,
                SecondaryEmotion::Stressed->value,
                SecondaryEmotion::Overwhelmed->value,
            ],
            PrimaryEmotion::Fearful->value => [
                SecondaryEmotion::Confused->value,
                SecondaryEmotion::Annoyed->value,
                SecondaryEmotion::Thoughtful->value,
                SecondaryEmotion::Exhausted->value,
                SecondaryEmotion::Panicky->value,
            ],
            PrimaryEmotion::Disgusted->value => [
                SecondaryEmotion::Repulsed->value,
                SecondaryEmotion::Annoyed->value,
                SecondaryEmotion::Shocked->value,
            ],
            PrimaryEmotion::Neutral->value => [
                SecondaryEmotion::Balanced->value,
                SecondaryEmotion::Okay->value,
                SecondaryEmotion::Reflective->value,
                SecondaryEmotion::Thoughtful->value,
            ],
        ];

        // Konvertiere das Mapping in das gewünschte Format
        $formattedEmotions = [];

        foreach ($emotions as $primaryKey => $secondaryKeys) {
            $formattedEmotions[] = [
                'label' => __('emotions.primary.' . PrimaryEmotion::from($primaryKey)->value),
                'value' => $primaryKey, // Englischer Wert
                'options' => array_map(fn($key) => [
                    'label' => __('emotions.secondary.' . SecondaryEmotion::from($key)->value),
                    'value' => $key // Englischer Wert
                ], $secondaryKeys)
            ];
        }

        return response()->json($formattedEmotions);
    }
}
