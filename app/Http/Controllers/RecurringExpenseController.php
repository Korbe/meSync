<?php

namespace App\Http\Controllers;

use App\Enums\FinanceCategory;
use App\Models\RecurringExpense;
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

class RecurringExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $contracts = $request->user()->recurringExpenses()->orderBy('name')->get();

        return Inertia::render('Finance/Contracts/Index', [
            'contracts' => $contracts,
            'scope' => in_array($request->query('scope'), ['private', 'business'], true) ? $request->query('scope') : 'all',
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Finance/Contracts/Create', [
            'categories' => FinanceCategory::options(),
            'intervals' => $this->intervals(),
            'scope' => in_array($request->query('scope'), ['private', 'business'], true) ? $request->query('scope') : 'private',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $contract = $request->user()->recurringExpenses()->create($this->validated($request));

        return to_route('finance.contracts.index')->with('success', "„{$contract->name}“ wurde angelegt.");
    }

    public function edit(RecurringExpense $contract): Response
    {
        Gate::authorize('update', $contract);

        return Inertia::render('Finance/Contracts/Update', [
            'contract' => $contract,
            'payments' => $contract->payments()->latest('period')->limit(24)->get(),
            'categories' => FinanceCategory::options(),
            'intervals' => $this->intervals(),
        ]);
    }

    /**
     * With "change_from" a new price applies only from that month on: the contract is
     * ended the day before and continued as a new one, so past months keep the old price.
     */
    public function update(Request $request, RecurringExpense $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        $validated = $this->validated($request);
        $changeFrom = $request->filled('change_from') ? Carbon::parse($request->input('change_from'))->startOfMonth() : null;

        if ($changeFrom && $changeFrom->gt($contract->start_date)) {
            $request->validate(['change_from' => 'date|after:start_date'], ['change_from.after' => 'Das Datum muss nach dem Beginn liegen.']);

            $next = $contract->continueFrom($changeFrom, $validated);

            return to_route('finance.contracts.edit', $next)->with('success', 'Neuer Betrag gilt ab '.$changeFrom->format('m/Y').', die Monate davor bleiben unverändert.');
        }

        $contract->update($validated);

        return to_route('finance.contracts.edit', $contract)->with('success', 'Vertrag wurde gespeichert.');
    }

    public function destroy(RecurringExpense $contract): RedirectResponse
    {
        Gate::authorize('delete', $contract);

        $contract->delete();

        return to_route('finance.contracts.index')->with('success', 'Vertrag wurde gelöscht.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:expense,income',
            'scope' => 'required|in:private,business',
            'category' => ['required', Rule::enum(FinanceCategory::class)],
            'amount' => 'required|string|max:255',
            'interval_months' => ['required', Rule::in(array_keys(RecurringExpense::INTERVALS))],
            'share_percent' => 'required|integer|min:1|max:100',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:2000',
        ], [
            'name.required' => 'Bitte gib eine Bezeichnung an.',
            'amount.required' => 'Bitte gib einen Betrag an.',
            'start_date.required' => 'Ab wann wird bezahlt?',
            'end_date.after_or_equal' => 'Das Ende muss nach dem Beginn liegen.',
        ]);

        try {
            $validated['amount'] = Amount::parse($validated['amount']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => 'Bitte einen gültigen Betrag angeben, z. B. 9,99.']);
        }

        if ($validated['amount'] <= 0) {
            throw ValidationException::withMessages(['amount' => 'Der Betrag muss größer als 0 sein.']);
        }

        return $validated;
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function intervals(): array
    {
        return collect(RecurringExpense::INTERVALS)->map(fn ($label, $months) => ['value' => $months, 'label' => ucfirst($label)])->values()->all();
    }
}
