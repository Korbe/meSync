<?php

namespace App\Services;

use App\Enums\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\InventoryItem;
use App\Models\InvoicePayment;
use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The year at a glance, month by month like a household book spreadsheet:
 * income, fixed costs (contracts) and variable expenses, grouped by category.
 *
 * Business scope also includes data the app already knows: payments received
 * for invoices as revenue and inventory purchases as expenses. All amounts are
 * what actually flowed (gross).
 */
class FinanceOverview
{
    public const SCOPES = ['all', 'private', 'business'];

    /**
     * @return array<string, mixed>
     */
    public function year(User $user, int $year, string $scope = 'all'): array
    {
        $lines = collect()
            ->merge($this->contractLines($user, $year, $scope))
            ->merge($this->entryLines($user, $year, $scope))
            ->merge($scope === 'private' ? [] : $this->invoiceLines($user, $year))
            ->merge($scope === 'private' ? [] : $this->inventoryLines($user, $year));

        $groups = [
            $this->group('income', 'Einnahmen', $lines->where('group', 'income')),
            $this->group('fixed', 'Fixkosten & Verträge', $lines->where('group', 'fixed')),
            $this->group('variable', 'Variable Ausgaben', $lines->where('group', 'variable')),
        ];

        $income = $groups[0]['totals'];
        $expenses = $this->sum([$groups[1]['totals'], $groups[2]['totals']]);
        $surplus = array_map(fn ($in, $out) => round($in - $out, 2), $income, $expenses);

        // Open contract payments per month (due until now, not confirmed yet)
        $open = array_fill(0, 12, 0);
        foreach ($lines->where('source', 'contract') as $line) {
            foreach ($line['status'] as $index => $status) {
                $open[$index] += $status === 'open' ? 1 : 0;
            }
        }

        return [
            'year' => $year,
            'scope' => $scope,
            'open' => $open,
            'groups' => $groups,
            'summary' => [
                'income' => $income,
                'fixed' => $groups[1]['totals'],
                'variable' => $groups[2]['totals'],
                'expenses' => $expenses,
                'surplus' => $surplus,
                'totals' => [
                    'income' => round(array_sum($income), 2),
                    'fixed' => round(array_sum($groups[1]['totals']), 2),
                    'variable' => round(array_sum($groups[2]['totals']), 2),
                    'expenses' => round(array_sum($expenses), 2),
                    'surplus' => round(array_sum($surplus), 2),
                ],
            ],
        ];
    }

    /**
     * Contracts in the months they are due, with the own share. Months confirmed against
     * the bank statement use the amount actually debited; due months up to now that are
     * not confirmed yet are "open", later ones "plan".
     */
    private function contractLines(User $user, int $year, string $scope): Collection
    {
        return $this->scoped($user->recurringExpenses(), $scope)
            ->whereYear('start_date', '<=', $year)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereYear('end_date', '>=', $year))
            ->with(['payments' => fn ($query) => $query->where('period', 'like', "{$year}-%")])
            ->get()
            ->map(function (RecurringExpense $contract) use ($year) {
                $payments = $contract->payments->keyBy(fn ($payment) => (int) substr($payment->period, 5, 2));
                $values = [];
                $status = [];

                foreach (range(1, 12) as $month) {
                    $payment = $payments->get($month);
                    $due = $contract->isDueIn($year, $month);

                    $values[] = $payment ? $contract->ownShareOf((float) $payment->amount) : ($due ? $contract->own_amount : 0.0);
                    $status[] = match (true) {
                        $payment !== null => 'confirmed',
                        ! $due => null,
                        $this->isFuture($year, $month) => 'plan',
                        default => 'open',
                    };
                }

                return [
                    ...$this->line($contract->type === 'income' ? 'income' : 'fixed', $contract->category, $contract->name, $values, 'contract', $contract->scope),
                    'id' => $contract->id,
                    'status' => $status,
                    'open' => count(array_keys($status, 'open', true)),
                ];
            });
    }

    private function isFuture(int $year, int $month): bool
    {
        return $year * 12 + $month > today()->year * 12 + today()->month;
    }

    /**
     * Bookings, summed per description so repeated shops form one row like in the spreadsheet.
     */
    private function entryLines(User $user, int $year, string $scope): Collection
    {
        return $this->scoped($user->financeEntries(), $scope)
            ->whereYear('date', $year)
            ->get()
            ->groupBy(fn (FinanceEntry $entry) => implode('|', [$entry->type, $entry->category->value, $entry->scope, mb_strtolower(trim($entry->description))]))
            ->map(function (Collection $entries) {
                $first = $entries->first();
                $values = array_fill(0, 12, 0.0);
                foreach ($entries as $entry) {
                    $values[$entry->date->month - 1] += (float) $entry->amount;
                }

                return $this->line($first->type === 'income' ? 'income' : 'variable', $first->category, $first->description, $values, 'entry', $first->scope, $entries->count());
            })
            ->values();
    }

    private function invoiceLines(User $user, int $year): Collection
    {
        $payments = InvoicePayment::whereIn('invoice_id', $user->invoices()->select('id'))
            ->whereYear('paid_at', $year)
            ->get();

        if ($payments->isEmpty()) {
            return collect();
        }

        $values = array_fill(0, 12, 0.0);
        foreach ($payments as $payment) {
            $values[$payment->paid_at->month - 1] += (float) $payment->amount;
        }

        return collect([$this->line('income', FinanceCategory::Revenue, 'Zahlungseingänge aus Rechnungen', $values, 'invoices', 'business', $payments->count())]);
    }

    private function inventoryLines(User $user, int $year): Collection
    {
        $items = $user->inventoryItems()->whereYear('purchased_at', $year)->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $values = array_fill(0, 12, 0.0);
        foreach ($items as $item) {
            $values[$item->purchased_at->month - 1] += $item->gross_price;
        }

        return collect([$this->line('variable', FinanceCategory::Hardware, 'Anschaffungen aus dem Inventar', $values, 'inventory', 'business', $items->count())]);
    }

    /**
     * Group lines by category with subtotals, categories sorted by their total.
     *
     * @return array<string, mixed>
     */
    private function group(string $key, string $label, Collection $lines): array
    {
        $categories = $lines
            ->groupBy(fn ($line) => $line['category'])
            ->map(function (Collection $lines, string $category) {
                $values = $this->sum($lines->pluck('values')->all());

                return [
                    'category' => $category,
                    'label' => FinanceCategory::from($category)->label(),
                    'values' => $values,
                    'total' => round(array_sum($values), 2),
                    'open' => $lines->sum(fn ($line) => $line['open'] ?? 0),
                    'lines' => $lines->sortByDesc('total')->values()->all(),
                ];
            })
            ->filter(fn ($category) => $category['total'] != 0 || collect($category['lines'])->contains('source', 'contract'))
            ->sortByDesc('total')
            ->values();

        $totals = $this->sum($categories->pluck('values')->all());

        return [
            'key' => $key,
            'label' => $label,
            'open' => $categories->sum('open'),
            'categories' => $categories->all(),
            'totals' => $totals,
            'total' => round(array_sum($totals), 2),
        ];
    }

    /**
     * @param  array<int, float>  $values
     * @return array<string, mixed>
     */
    private function line(string $group, FinanceCategory $category, string $label, array $values, string $source, string $scope, int $count = 1): array
    {
        $values = array_map(fn ($value) => round($value, 2), $values);

        return [
            'group' => $group,
            'category' => $category->value,
            'label' => $label,
            'values' => $values,
            'total' => round(array_sum($values), 2),
            'source' => $source,
            'scope' => $scope,
            'count' => $count,
        ];
    }

    /**
     * @param  array<int, array<int, float>>  $rows
     * @return array<int, float>
     */
    private function sum(array $rows): array
    {
        $totals = array_fill(0, 12, 0.0);
        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $totals[$index] += $value;
            }
        }

        return array_map(fn ($value) => round($value, 2), $totals);
    }

    private function scoped($query, string $scope)
    {
        return $scope === 'all' ? $query : $query->where('scope', $scope);
    }
}
