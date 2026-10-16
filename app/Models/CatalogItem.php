<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItem extends Model
{
    /** @use HasFactory<\Database\Factories\CatalogItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'unit',
        'unit_price',
        'pricing',
        'base_minutes',
        'step_minutes',
        'step_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'base_minutes' => 'integer',
            'step_minutes' => 'integer',
            'step_price' => 'decimal:2',
        ];
    }

    public function isTimeBased(): bool
    {
        return $this->pricing === 'time';
    }

    /**
     * Price for a duration: base price covers the first base_minutes,
     * every started step after that costs step_price.
     */
    public function priceForMinutes(int $minutes): float
    {
        if (! $this->isTimeBased()) {
            return (float) $this->unit_price;
        }

        $extraSteps = (int) ceil(max(0, $minutes - $this->base_minutes) / max(1, $this->step_minutes));

        return round((float) $this->unit_price + $extraSteps * (float) $this->step_price, 2);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
