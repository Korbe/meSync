<?php

namespace App\Http\Controllers;

use App\Services\FinanceOverview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    /**
     * Year overview month by month, like the household book spreadsheet.
     */
    public function index(Request $request, FinanceOverview $overview): Response
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
            'scope' => ['nullable', Rule::in(FinanceOverview::SCOPES)],
        ]);

        $user = $request->user();
        $year = (int) ($validated['year'] ?? today()->year);
        $firstYear = collect([
            $user->financeEntries()->min('date'),
            $user->recurringExpenses()->min('start_date'),
        ])->filter()->map(fn ($date) => (int) substr($date, 0, 4))->min() ?? today()->year;

        return Inertia::render('Finance/Overview', [
            'overview' => $overview->year($user, $year, $validated['scope'] ?? 'all'),
            'years' => range(max(today()->year + 1, $year), min($firstYear, $year)),
            'isEmpty' => ! $user->financeEntries()->exists() && ! $user->recurringExpenses()->exists(),
        ]);
    }
}
