<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contract payment of one month, confirmed against the bank statement.
 */
class RecurringPayment extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'period',
        'amount',
        'confirmed_at',
    ];

    /**
     * "period" stays a plain "Y-m-d" string (first of the month), so lookups by month
     * work the same on every database.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }
}
