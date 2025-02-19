<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Emotion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('startDate')
            ? Carbon::parse($request->input('startDate'))->startOfDay()
            : now()->subDays(30)->startOfDay();

        $endDate = $request->input('endDate')
            ? Carbon::parse($request->input('endDate'))->endOfDay()
            : now()->endOfDay();

        $averageScore = round(
            Emotion::whereBetween('created_at', [$startDate, $endDate])->avg('score') / 2
        ) * 2;

        $emotionsGrouped = Emotion::selectRaw('DATE(created_at) as date, AVG(score) as avg_score')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $timestamps = [];
        $scores = [];

        foreach ($emotionsGrouped as $emotion) {
            $timestamps[] = Carbon::parse($emotion->date)->format('Y-m-d');
            $score = $emotion->avg_score;
            $roundedScore = ceil($score / 2) * 2; // Runden auf die nächste gerade Zahl
            $scores[] = $roundedScore;
        }

        return Inertia::render('Dashboard/Dashboard', [
            "averageEmotionScore" => $averageScore,
            'timestamps' => $timestamps,
            'scores' => $scores,
            'dateRange' => [
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString()
            ]
        ]);
    }
}
