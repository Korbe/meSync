<?php

namespace App\Models;

use App\Enums\FinanceCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single booking: variable expense or income.
 */
class FinanceEntry extends Model
{
    /** @use HasFactory<\Database\Factories\FinanceEntryFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'date',
        'description',
        'type',
        'scope',
        'category',
        'amount',
        'amount_input',
        'notes',
    ];

    protected $appends = ['category_label'];

    protected function casts(): array
    {
        return [
            // Plain date: as ISO time stamp it would be shifted to UTC (the day before)
            'date' => 'date:Y-m-d',
            'category' => FinanceCategory::class,
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function categoryLabel(): Attribute
    {
        return Attribute::get(fn () => $this->category?->label());
    }
}
