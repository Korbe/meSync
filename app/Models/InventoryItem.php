<?php

namespace App\Models;

use App\Enums\InventoryCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class InventoryItem extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\InventoryItemFactory> */
    use HasFactory;
    use InteractsWithMedia;

    /**
     * Net purchase price up to which an item counts as GWG (geringwertiges Wirtschaftsgut)
     * and is fully deductible in the year of purchase.
     */
    public const GWG_LIMIT = 1000;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'category',
        'serial_number',
        'supplier',
        'purchased_at',
        'price',
        'tax_rate',
        'useful_life_years',
        'status',
        'retired_at',
        'sale_price',
        'notes',
    ];

    protected $appends = ['category_label', 'is_gwg', 'gross_price', 'book_value'];

    protected function casts(): array
    {
        return [
            'category' => InventoryCategory::class,
            'purchased_at' => 'date',
            'retired_at' => 'date',
            'price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'useful_life_years' => 'integer',
        ];
    }

    /**
     * Purchase receipt (PDF or photo), stored privately and only served through the app.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipt')
            ->singleFile()
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Yearly depreciation (lineare AfA) with the Austrian half-year rule: bought in the
     * second half of the year, only half of the yearly amount counts in the first year.
     * GWG are written off completely in the year of purchase. Ends with the year before
     * the item was sold or disposed of.
     *
     * @return array<int, array{year: int, depreciation: float, book_value: float}>
     */
    public function depreciationSchedule(): array
    {
        $price = (float) $this->price;
        $year = $this->purchased_at->year;
        $lastYear = $this->retired_at ? $this->retired_at->year - 1 : PHP_INT_MAX;

        if ($this->is_gwg || ! $this->useful_life_years) {
            return $year <= $lastYear ? [['year' => $year, 'depreciation' => $price, 'book_value' => 0.0]] : [];
        }

        $yearly = $price / $this->useful_life_years;
        $amount = $this->purchased_at->month <= 6 ? $yearly : $yearly / 2;
        $value = $price;
        $schedule = [];

        while ($value > 0.005 && $year <= $lastYear) {
            $depreciation = round(min($amount, $value), 2);
            $value = round($value - $depreciation, 2);
            $schedule[] = ['year' => $year, 'depreciation' => $depreciation, 'book_value' => $value];

            $year++;
            $amount = $yearly;
        }

        return $schedule;
    }

    /**
     * Book value at the end of the given year (default: current year).
     */
    public function bookValueAt(?int $year = null): float
    {
        $year ??= today()->year;

        if ($year < $this->purchased_at->year) {
            return 0.0;
        }

        $value = (float) $this->price;
        foreach ($this->depreciationSchedule() as $row) {
            if ($row['year'] > $year) {
                break;
            }
            $value = $row['book_value'];
        }

        return $value;
    }

    protected function categoryLabel(): Attribute
    {
        return Attribute::get(fn () => $this->category?->label());
    }

    protected function isGwg(): Attribute
    {
        return Attribute::get(fn () => (float) $this->price <= self::GWG_LIMIT);
    }

    protected function grossPrice(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->price * (1 + (float) $this->tax_rate / 100), 2));
    }

    /**
     * Current book value; sold or disposed items have none.
     */
    protected function bookValue(): Attribute
    {
        return Attribute::get(fn () => $this->isActive() ? $this->bookValueAt() : 0.0);
    }
}
