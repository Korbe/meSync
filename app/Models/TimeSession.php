<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stopwatch run for on-site work. The time lives on the server: finished phases are
 * summed up in "seconds", a running phase is counted from "running_since".
 */
class TimeSession extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'catalog_item_id',
        'invoice_id',
        'started_at',
        'running_since',
        'seconds',
        'stopped_at',
        'billed_minutes',
        'notes',
    ];

    protected $appends = ['status', 'elapsed_seconds', 'minutes', 'price'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'running_since' => 'datetime',
            'stopped_at' => 'datetime',
            'seconds' => 'integer',
            'billed_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function pause(): void
    {
        if ($this->running_since) {
            $this->update(['seconds' => $this->elapsed_seconds, 'running_since' => null]);
        }
    }

    public function resume(): void
    {
        if (! $this->running_since && ! $this->stopped_at) {
            $this->update(['running_since' => now()]);
        }
    }

    public function stop(): void
    {
        $this->pause();
        $this->update(['stopped_at' => now()]);
    }

    /**
     * running | paused | stopped | billed
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->invoice_id !== null => 'billed',
            $this->stopped_at !== null => 'stopped',
            $this->running_since !== null => 'running',
            default => 'paused',
        });
    }

    protected function elapsedSeconds(): Attribute
    {
        return Attribute::get(fn () => $this->seconds + ($this->running_since ? (int) $this->running_since->diffInSeconds(now()) : 0));
    }

    /**
     * Billed duration: the corrected one, else the measured time rounded up to full minutes.
     */
    protected function minutes(): Attribute
    {
        return Attribute::get(fn () => $this->billed_minutes ?? max(1, (int) ceil($this->elapsed_seconds / 60)));
    }

    protected function price(): Attribute
    {
        return Attribute::get(fn () => $this->catalogItem?->priceForMinutes($this->minutes));
    }

    /**
     * Invoice item for this session, e.g. "PC-Hilfe vor Ort", the notes as bullet points
     * and "21.10.2026 · Dauer: 1 Std. 15 Min.".
     *
     * @return array<string, mixed>
     */
    public function toInvoiceItem(): array
    {
        $hours = intdiv($this->minutes, 60);
        $rest = $this->minutes % 60;
        $duration = trim(($hours ? "{$hours} Std. " : '').($rest || ! $hours ? "{$rest} Min." : ''));

        $notes = collect(preg_split('/\R/', (string) $this->notes))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(fn ($line) => preg_match('/^[-*•]\s/u', $line) ? $line : "- {$line}");

        return [
            'description' => collect([$this->catalogItem?->name ?? 'Arbeitszeit'])
                ->merge($notes)
                ->push($this->started_at->format('d.m.Y').' · Dauer: '.$duration)
                ->implode("\n"),
            'quantity' => 1,
            'unit' => 'Stück',
            'unit_price' => $this->price ?? 0,
        ];
    }
}
