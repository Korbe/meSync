<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One website check of a maintenance contract.
 */
class SiteCheck extends Model
{
    use Prunable;

    /** Days of history kept for uptime statistics and outages. */
    public const KEEP_DAYS = 90;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'checked_at',
        'up',
        'response_ms',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'up' => 'boolean',
            'response_ms' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MaintenanceContract::class, 'maintenance_contract_id');
    }

    public function prunable(): Builder
    {
        return static::where('checked_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
