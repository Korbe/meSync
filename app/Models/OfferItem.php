<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferItem extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'position',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'list_price',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'list_price' => 'decimal:2',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Regular price above the offered one: printed struck through as a discount.
     */
    public function hasDiscount(): bool
    {
        return $this->list_price !== null && (float) $this->list_price > (float) $this->unit_price;
    }

    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->quantity * (float) $this->unit_price, 2));
    }
}
