<?php

namespace App\Models;

use App\Enums\FinanceCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A contract or other fixed cost/income that repeats every interval_months,
 * starting with the month of start_date.
 */
class RecurringExpense extends Model
{
    /** @use HasFactory<\Database\Factories\RecurringExpenseFactory> */
    use HasFactory;

    public const INTERVALS = [1 => 'monatlich', 3 => 'quartalsweise', 6 => 'halbjährlich', 12 => 'jährlich'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'scope',
        'category',
        'amount',
        'interval_months',
        'share_percent',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $appends = ['category_label', 'interval_label', 'own_amount', 'monthly_amount', 'yearly_amount', 'next_due_date', 'is_active'];

    protected function casts(): array
    {
        return [
            'category' => FinanceCategory::class,
            'amount' => 'decimal:2',
            'interval_months' => 'integer',
            'share_percent' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RecurringPayment::class)->orderBy('period');
    }

    /**
     * New conditions from a month on: this contract ends the day before and continues as a
     * new one, so earlier months keep their values. Confirmed payments from then on move along.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function continueFrom(Carbon $from, array $attributes): self
    {
        $from = $from->copy()->startOfMonth();

        return DB::transaction(function () use ($from, $attributes) {
            $next = $this->user->recurringExpenses()->create([
                ...$this->only($this->fillable),
                'end_date' => $this->end_date,
                ...$attributes,
                'start_date' => $from,
            ]);

            $this->payments()->where('period', '>=', $from->toDateString())->update(['recurring_expense_id' => $next->id]);
            $this->update(['end_date' => $from->copy()->subDay()]);

            return $next;
        });
    }

    /**
     * Own share of an amount as debited, e.g. half of a shared subscription.
     */
    public function ownShareOf(float $amount): float
    {
        return round($amount * $this->share_percent / 100, 2);
    }

    /**
     * Due months of a year that lie in the past or are the current month (1-12).
     *
     * @return array<int, int>
     */
    public function dueMonthsUntilNow(int $year): array
    {
        $last = $year < today()->year ? 12 : ($year === today()->year ? today()->month : 0);

        return array_values(array_filter(range(1, $last), fn ($month) => $this->isDueIn($year, $month)));
    }

    /**
     * Is a payment due in the given month?
     */
    public function isDueIn(int $year, int $month): bool
    {
        $months = ($year * 12 + $month) - ($this->start_date->year * 12 + $this->start_date->month);

        if ($months < 0 || $months % $this->interval_months !== 0) {
            return false;
        }

        return $this->end_date === null || Carbon::create($year, $month, 1)->lte($this->end_date);
    }

    /**
     * The own share per payment as it flows each due month.
     */
    protected function ownAmount(): Attribute
    {
        return Attribute::get(fn () => $this->ownShareOf((float) $this->amount));
    }

    /**
     * Spread over the months, e.g. a yearly subscription of 120 € costs 10 € a month.
     */
    protected function monthlyAmount(): Attribute
    {
        return Attribute::get(fn () => round($this->own_amount / $this->interval_months, 2));
    }

    protected function yearlyAmount(): Attribute
    {
        return Attribute::get(fn () => round($this->own_amount * 12 / $this->interval_months, 2));
    }

    protected function nextDueDate(): Attribute
    {
        return Attribute::get(function () {
            $date = $this->start_date->copy();
            while ($date->lt(today()->startOfMonth())) {
                $date->addMonthsNoOverflow($this->interval_months);
            }

            return $this->end_date && $date->gt($this->end_date) ? null : $date->toDateString();
        });
    }

    protected function isActive(): Attribute
    {
        return Attribute::get(fn () => $this->start_date->lte(today()->endOfMonth()) && ($this->end_date === null || $this->end_date->gte(today()->startOfMonth())));
    }

    protected function categoryLabel(): Attribute
    {
        return Attribute::get(fn () => $this->category?->label());
    }

    protected function intervalLabel(): Attribute
    {
        return Attribute::get(fn () => self::INTERVALS[$this->interval_months] ?? "alle {$this->interval_months} Monate");
    }
}
