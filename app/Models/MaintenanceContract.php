<?php

namespace App\Models;

use App\Enums\MaintenanceContractType;
use Illuminate\Contracts\Encryption\DecryptException;
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
        'monitor_auth',
        'monitor_auth_user',
        'monitor_auth_password',
    ];

    /**
     * The password for protected websites never leaves the server.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'monitor_auth_password',
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
        'has_monitor_password',
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
            'monitor_auth_password' => 'encrypted',
            'monitor_status_since' => 'datetime',
            'monitor_checked_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'ssl_warning_sent_for' => 'date:Y-m-d',
            'reminder_sent_for' => 'date:Y-m-d',
            'due_notice_sent_for' => 'date:Y-m-d',
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

    /**
     * Running contracts with invoices still to send: started, and not ended before the next due date.
     */
    public function scopeBillable(Builder $query): void
    {
        $query->whereNotNull('next_invoice_date')->where(function (Builder $query) {
            $query->whereNull('end_date')->orWhereColumn('next_invoice_date', '<=', 'end_date');
        });
    }

    public function isBillable(): bool
    {
        return ! $this->isPending() && ($this->end_date === null || $this->next_invoice_date->lte($this->end_date));
    }

    /**
     * Created with the invoice of an offer: waits for the go-live, nothing is due yet.
     */
    public function isPending(): bool
    {
        return $this->start_date === null;
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('start_date');
    }

    /**
     * Go-live: the contract starts. The first year was already billed with the invoice of
     * the offer, so it is recorded as invoiced and the next invoice is due a year later.
     */
    public function start(Carbon $date, ?Invoice $invoice = null): void
    {
        DB::transaction(function () use ($date, $invoice) {
            $this->update(['start_date' => $date, 'next_invoice_date' => $date]);

            if (! $invoice) {
                return;
            }

            $this->markInvoiced($invoice->invoice_date, $invoice->number)->update(['invoice_id' => $invoice->id]);

            // Not sent yet: the invoice gets the real period instead of "12 Monate ab Go-Live"
            if ($invoice->sent_at === null && ! $invoice->isLocked()) {
                $period = 'Zeitraum: '.$date->format('d.m.Y').' – '.$date->copy()->addYear()->subDay()->format('d.m.Y');

                foreach ($invoice->items()->where('description', 'like', '%Zeitraum: 12 Monate ab Go-Live%')->get() as $item) {
                    $lines = collect(explode("\n", $item->description))
                        ->reject(fn (string $line) => trim($line) === config('offers.hints.maintenance_start'))
                        ->map(fn (string $line) => trim($line) === 'Zeitraum: 12 Monate ab Go-Live' ? $period : $line);

                    $item->update(['description' => $lines->implode("\n")]);
                }
            }
        });
    }

    /**
     * The invoice that billed the first year (the one of the offer), if any.
     */
    public function firstYearInvoice(): ?Invoice
    {
        return Invoice::where('maintenance_contract_id', $this->id)->oldest('id')->first();
    }

    /**
     * Password for HTTP Basic Auth, or null when none is stored or it can't be decrypted
     * (e.g. a backup restored on a server with a different APP_KEY).
     */
    public function monitorPassword(): ?string
    {
        try {
            return $this->monitor_auth_password;
        } catch (DecryptException) {
            return null;
        }
    }

    protected function hasMonitorPassword(): Attribute
    {
        // Raw value: tells whether a password is stored without decrypting it
        return Attribute::get(fn () => filled($this->attributes['monitor_auth_password'] ?? null));
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
     * pending | ended | overdue | due_today | due_soon | active
     */
    protected function status(): Attribute
    {
        return Attribute::get(function () {
            if ($this->isPending()) {
                return 'pending';
            }

            if (! $this->isBillable()) {
                return 'ended';
            }

            $today = Carbon::today();

            return match (true) {
                $this->next_invoice_date->lt($today) => 'overdue',
                $this->next_invoice_date->isSameDay($today) => 'due_today',
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
