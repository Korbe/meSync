<?php

namespace App\Http\Controllers;

use App\Models\Digestion;
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

        $emotionStats = $this->getEmotionStatistics($startDate, $endDate);
        $digestionStats = $this->getDigestionStatistics($startDate, $endDate);
        $digestionFrequencyStats = $this->getDigestionFrequencyStatistics($startDate, $endDate);

        return Inertia::render('Dashboard/Dashboard', [
            'emotionStats' => [
                'average' => $emotionStats['average'],
                'timestamps' => $emotionStats['timestamps'],
                'scores' => $emotionStats['scores'],
            ],
            'digestionStats' => [
                'average' => $digestionStats['average'],
                'timestamps' => $digestionStats['timestamps'],
                'scores' => $digestionStats['scores'],
            ],
            'digestionFrequencyStats' => [
                'average' => $digestionFrequencyStats['average'],
                'timestamps' => $digestionFrequencyStats['timestamps'],
                'scores' => $digestionFrequencyStats['counts'],
            ],          
            'dateRange' => [
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString()
            ]
        ]);
    }

    private function getEmotionStatistics(Carbon $startDate, Carbon $endDate): array
    {
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

        return [
            'average' => $averageScore,
            'timestamps' => $timestamps,
            'scores' => $scores
        ];
    }

    private function getDigestionStatistics(Carbon $startDate, Carbon $endDate): array
    {
        $averageScore = round(
            Digestion::whereBetween('created_at', [$startDate, $endDate])->avg('score') / 2
        ) * 2;

        $grouped = Digestion::selectRaw('DATE(created_at) as date, AVG(score) as avg_score')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $timestamps = [];
        $scores = [];

        foreach ($grouped as $emotion) {
            $timestamps[] = Carbon::parse($emotion->date)->format('Y-m-d');
            $score = $emotion->avg_score;
            $roundedScore = ceil($score / 2) * 2;
            $scores[] = $roundedScore;
        }

        return [
            'average' => $averageScore,
            'timestamps' => $timestamps,
            'scores' => $scores
        ];
    }

    private function getDigestionFrequencyStatistics(Carbon $startDate, Carbon $endDate): array
    {
        // Gruppiere nach Datum und zähle die Einträge pro Tag
        $grouped = Digestion::selectRaw('DATE(created_at) as date, COUNT(*) as entry_count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
    
        $timestamps = [];
        $counts = [];
        $totalEntries = 0;
    
        foreach ($grouped as $entry) {
            // X-Achse: Datum
            $timestamps[] = Carbon::parse($entry->date)->format('Y-m-d');
            // Y-Achse: Anzahl der Einträge
            $counts[] = $entry->entry_count;
            $totalEntries += $entry->entry_count;
        }
    
        // Durchschnittliche Anzahl der Einträge pro Tag berechnen
        $average = count($grouped) > 0 ? round($totalEntries / count($grouped), 2) : 0;
    
        return [
            'average' => $average,
            'timestamps' => $timestamps,
            'counts' => $counts
        ];
    }
}
