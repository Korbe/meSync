<?php

namespace App\Http\Controllers;

use App\Enums\FinanceCategory;
use App\Models\FinanceEntry;
use App\Support\Amount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class FinanceEntryController extends Controller
{
    /**
     * Bookings of one month, plus the contracts due in that month for the full picture.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'scope' => 'nullable|in:all,private,business',
        ]);

        $user = $request->user();
        $month = Carbon::createFromFormat('Y-m-d', ($validated['month'] ?? today()->format('Y-m')).'-01')->startOfDay();
        $scope = $validated['scope'] ?? 'all';

        $entries = $user->financeEntries()
            ->when($scope !== 'all', fn ($query) => $query->where('scope', $scope))
            ->whereBetween('date', [$month, $month->copy()->endOfMonth()])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $allContracts = $user->recurringExpenses()
            ->when($scope !== 'all', fn ($query) => $query->where('scope', $scope))
            ->with(['payments' => fn ($query) => $query->where('period', '>=', today()->subMonths(12)->startOfMonth()->toDateString())])
            ->orderBy('name')
            ->get();

        // Due in this month, with the confirmation against the bank statement if there is one
        $period = $month->toDateString();
        $contracts = $allContracts
            ->filter(fn ($contract) => $contract->isDueIn($month->year, $month->month))
            ->map(fn ($contract) => [
                ...$contract->toArray(),
                'payment' => $contract->payments->firstWhere('period', $period),
            ])
            ->values();

        // Finished months of the last year with contracts not confirmed yet (the current month is still running)
        $openMonths = collect(range(12, 1))
            ->map(fn ($back) => today()->startOfMonth()->subMonths($back))
            ->map(fn (Carbon $date) => [
                'month' => $date->format('Y-m'),
                'count' => $allContracts->filter(fn ($contract) => $contract->isDueIn($date->year, $date->month)
                    && $contract->start_date->lte($date->copy()->endOfMonth())
                    && ! $contract->payments->contains('period', $date->toDateString()))->count(),
            ])
            ->filter(fn ($open) => $open['count'] > 0 && $open['month'] !== $month->format('Y-m'))
            ->values();

        return Inertia::render('Finance/Entries', [
            'month' => $month->format('Y-m'),
            'scope' => $scope,
            'entries' => $entries,
            'contracts' => $contracts,
            'openMonths' => $openMonths,
            'categories' => FinanceCategory::options(),
            // Previously used descriptions with their last category, for quick entry
            'suggestions' => $user->financeEntries()
                ->latest('date')
                ->limit(500)
                ->get(['description', 'category', 'scope', 'type'])
                ->unique(fn ($entry) => mb_strtolower($entry->description))
                ->map(fn ($entry) => $entry->only(['description', 'scope', 'type']) + ['category' => $entry->category->value])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $entry = $request->user()->financeEntries()->create($this->validated($request));

        return back()->with('success', "„{$entry->description}“ wurde gebucht.");
    }

    public function update(Request $request, FinanceEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $entry);

        $entry->update($this->validated($request));

        return back()->with('success', 'Buchung wurde gespeichert.');
    }

    public function destroy(FinanceEntry $entry): RedirectResponse
    {
        Gate::authorize('delete', $entry);

        $entry->delete();

        return back()->with('success', 'Buchung wurde gelöscht.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'type' => 'required|in:expense,income',
            'scope' => 'required|in:private,business',
            'category' => ['required', Rule::enum(FinanceCategory::class)],
            'amount' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ], [
            'description.required' => 'Wofür? Bitte eine Beschreibung angeben.',
            'amount.required' => 'Bitte einen Betrag angeben.',
        ]);

        try {
            $amount = Amount::parse($validated['amount']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => 'Bitte einen gültigen Betrag angeben, z. B. 12,50 oder 5,92+6,50.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Der Betrag muss größer als 0 sein.']);
        }

        return [
            ...$validated,
            'amount' => $amount,
            'amount_input' => Amount::isFormula($validated['amount']) ? $validated['amount'] : null,
        ];
    }
}
