<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One check of a monitored website.
 */
class SiteCheck extends Model
{
    use Prunable;

    /** Days of history kept: as long as the monitoring center looks back (uptime, outages). */
    public const KEEP_DAYS = 30;

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

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function prunable(): Builder
    {
        return static::where('checked_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
