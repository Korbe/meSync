<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Digestion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Digestion\DigestionFilterRequest;
use App\Http\Requests\Digestion\DigestionRequest;

class DigestionController extends Controller
{
    public function index(DigestionFilterRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Get the earliest and latest timestamps
        $dateRange = $user->digestions()
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

        $query = $user->digestions()
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Check if 'description' is present in the validated request data
        if (isset($validated['onlyWithDescription']) && filter_var($validated['onlyWithDescription'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull('description')->where('description', '!=', '');
        }

        $digestions = $query
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Digestion/Index', [
            'digestions' => $digestions,
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
        return Inertia::render('Digestion/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DigestionRequest $request)
    {
        $validated = $request->validated();

        $digestion = Digestion::create([
            'user_id' => Auth::id(),
            'score' => $validated['score'],
            'description' => $validated['description'] ?? null,
        ]);

        //return redirect("asdfasdf");//
        return redirect()->route('digestions.index')->with('success', 'Digestion successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Digestion $digestion)
    {
        if ($digestion->user_id !== Auth::id())
            abort(403);

        return Inertia::render('Digestion/Show', [
            'digestion' => $digestion,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Digestion $digestion)
    {
        if ($digestion->user_id !== Auth::id())
            abort(403);

        return Inertia::render('Digestion/Edit', [
            'digestion' => $digestion
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DigestionRequest $request, Digestion $digestion)
    {
        if ($digestion->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        $digestion->update($validated);

        return redirect()->route('digestions.show', $digestion->id)->with('success', 'Digestion updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Digestion $digestion)
    {
        if ($digestion->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $digestion->delete();

        return redirect()->route('digestions.index')->with('success', 'Digestion deleted successfully');
    }
}
