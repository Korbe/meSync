<?php

namespace App\Models;

use App\Enums\MaintenanceContractType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'project_id',
        'type',
        'name',
        'url',
        'monthly_price',
        'start_date',
        'next_invoice_date',
        'end_date',
        'notes',
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

    protected function casts(): array
    {
        return [
            'type' => MaintenanceContractType::class,
            'monthly_price' => 'decimal:2',
            'start_date' => 'date:Y-m-d',
            'next_invoice_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Uptime monitoring of its website, if there is one.
     */
    public function monitor(): HasOne
    {
        return $this->hasOne(Monitor::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(MaintenanceInvoice::class)->latest('period_start');
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
     * The yearly invoice is due: overdue, today or within the next DUE_SOON_DAYS days.
     */
    public function isDue(): bool
    {
        return in_array($this->status, ['overdue', 'due_today', 'due_soon'], true);
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
     * Go-live: the contract starts and its first year is due right away (own invoice,
     * separate from the project invoice of the offer).
     */
    public function start(Carbon $date): void
    {
        $this->update(['start_date' => $date, 'next_invoice_date' => $date]);
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
     * Monitoring settings from the contract form: the monitor follows name, customer and address
     * of the contract. Without an address there is nothing to check, the monitor goes.
     *
     * @param  array<string, mixed>  $settings  enabled, auth, auth_user, auth_password (empty = keep)
     */
    public function syncMonitor(array $settings = []): ?Monitor
    {
        if (blank($this->url)) {
            $this->monitor?->delete();
            $this->setRelation('monitor', null);

            return null;
        }

        $monitor = $this->monitor ?? $this->monitor()->make()->forceFill(['user_id' => $this->user_id]);
        $monitor->fill([
            'customer_id' => $this->customer_id,
            'name' => $this->name,
            'url' => $this->url,
            ...array_filter($settings, fn ($value, $key) => $key !== 'auth_password' || filled($value), ARRAY_FILTER_USE_BOTH),
        ]);

        if (($settings['auth'] ?? $monitor->auth) !== 'basic') {
            $monitor->fill(['auth_user' => null, 'auth_password' => null]);
        }

        $monitor->save();
        $this->setRelation('monitor', $monitor);

        return $monitor;
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
