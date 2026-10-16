<?php

namespace App\Models;

use App\Enums\MaintenanceContractType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceContract extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceContractFactory> */
    use HasFactory;

    /** Days before the due date in which an invoice counts as "due soon". */
    public const DUE_SOON_DAYS = 30;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'type',
        'name',
        'url',
        'monthly_price',
        'start_date',
        'next_invoice_date',
        'end_date',
        'notes',
        'monitor_enabled',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'type_label',
        'annual_price',
        'status',
    ];

    protected static function booted(): void
    {
        // Monitoring switched off (toggle or form): forget the status, it would be stale later
        static::saving(function (MaintenanceContract $contract) {
            if ($contract->isDirty('monitor_enabled') && ! $contract->monitor_enabled) {
                $contract->forceFill([
                    'monitor_status' => null,
                    'monitor_status_since' => null,
                    'monitor_failures' => 0,
                    'monitor_error' => null,
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => MaintenanceContractType::class,
            'monthly_price' => 'decimal:2',
            'start_date' => 'date:Y-m-d',
            'next_invoice_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'monitor_enabled' => 'boolean',
            'monitor_status_since' => 'datetime',
            'monitor_checked_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'ssl_warning_sent_for' => 'date:Y-m-d',
            'reminder_sent_for' => 'date:Y-m-d',
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

    public function siteChecks(): HasMany
    {
        return $this->hasMany(SiteCheck::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(MaintenanceInvoice::class)->latest('period_start');
    }

    /**
     * Contracts that still have invoices to send (not ended before the next due date).
     */
    /**
     * Contracts whose website should be checked: monitoring on, URL set, not ended.
     */
    public function scopeMonitored(Builder $query): void
    {
        $query->where('monitor_enabled', true)->whereNotNull('url')->where('url', '!=', '')->billable();
    }

    public function scopeBillable(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereNull('end_date')->orWhereColumn('next_invoice_date', '<=', 'end_date');
        });
    }

    public function isBillable(): bool
    {
        return $this->end_date === null || $this->next_invoice_date->lte($this->end_date);
    }

    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn () => $this->type->label());
    }

    protected function annualPrice(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->monthly_price * 12, 2));
    }

    /**
     * ended | overdue | due_soon | active
     */
    protected function status(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->isBillable()) {
                return 'ended';
            }

            $today = Carbon::today();

            return match (true) {
                $this->next_invoice_date->lt($today) => 'overdue',
                $this->next_invoice_date->lte($today->copy()->addDays(self::DUE_SOON_DAYS)) => 'due_soon',
                default => 'active',
            };
        });
    }

    /**
     * Record the yearly invoice for the current period and move on to the next one.
     */
    public function markInvoiced(Carbon $invoicedAt, ?string $invoiceNumber = null): MaintenanceInvoice
    {
        return DB::transaction(function () use ($invoicedAt, $invoiceNumber) {
            $periodStart = $this->next_invoice_date->copy();

            $invoice = $this->invoices()->create([
                'invoiced_at' => $invoicedAt,
                'period_start' => $periodStart,
                'period_end' => $periodStart->copy()->addYear()->subDay(),
                'amount' => $this->annual_price,
                'invoice_number' => $invoiceNumber,
            ]);

            $this->update(['next_invoice_date' => $periodStart->copy()->addYear()]);

            return $invoice;
        });
    }

    /**
     * Undo the most recent invoice, e.g. after a misclick.
     */
    public function undoInvoice(MaintenanceInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $this->update(['next_invoice_date' => $invoice->period_start]);
            $invoice->delete();
        });
    }
}
