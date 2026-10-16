<?php

namespace App\Http\Controllers;

use App\Models\RecurringExpense;
use App\Support\Amount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Confirming contract payments against the bank statement, month by month.
 */
class RecurringPaymentController extends Controller
{
    /**
     * Confirm one month, with the amount actually debited. With "adopt" a different
     * amount becomes the contract's new price from this month on.
     */
    public function store(Request $request, RecurringExpense $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        $period = $this->period($request);
        $amount = $this->amount($request->input('amount', (string) $contract->amount));

        if (! $contract->isDueIn($period->year, $period->month)) {
            throw ValidationException::withMessages(['amount' => 'In diesem Monat ist für den Vertrag keine Zahlung fällig.']);
        }

        if ($request->boolean('adopt') && $amount !== (float) $contract->amount) {
            $contract = $period->isSameMonth($contract->start_date)
                ? tap($contract)->update(['amount' => $amount])
                : $contract->continueFrom($period, ['amount' => $amount]);
        }

        $contract->payments()->updateOrCreate(
            ['period' => $period->toDateString()],
            ['amount' => $amount, 'confirmed_at' => now()],
        );

        return back()->with('success', "„{$contract->name}“ für ".$period->format('m/Y').' bestätigt.');
    }

    public function destroy(Request $request, RecurringExpense $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        $contract->payments()->where('period', $this->period($request)->toDateString())->delete();

        return back()->with('success', "Bestätigung für „{$contract->name}“ zurückgenommen.");
    }

    /**
     * Confirm all open contracts of a month with their planned amounts.
     */
    public function confirmAll(Request $request): RedirectResponse
    {
        $period = $this->period($request);
        $scope = $request->input('scope');

        $contracts = $request->user()->recurringExpenses()
            ->when(in_array($scope, ['private', 'business'], true), fn ($query) => $query->where('scope', $scope))
            ->whereDoesntHave('payments', fn ($query) => $query->where('period', $period->toDateString()))
            ->get()
            ->filter(fn (RecurringExpense $contract) => $contract->isDueIn($period->year, $period->month));

        foreach ($contracts as $contract) {
            $contract->payments()->create(['period' => $period->toDateString(), 'amount' => $contract->amount, 'confirmed_at' => now()]);
        }

        return back()->with('success', "{$contracts->count()} Verträge für ".$period->format('m/Y').' bestätigt.');
    }

    private function period(Request $request): Carbon
    {
        $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        return Carbon::createFromFormat('Y-m-d', $request->input('period').'-01')->startOfDay();
    }

    private function amount(string $input): float
    {
        try {
            $amount = Amount::parse($input);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => 'Bitte einen gültigen Betrag angeben.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Der Betrag muss größer als 0 sein.']);
        }

        return $amount;
    }
}
