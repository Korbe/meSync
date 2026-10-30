<?php

namespace App\Models;

use App\Enums\MaintenanceContractType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Offer for a website or web app: project items plus the first 12 months of maintenance,
 * which are always part of it. The project invoice creates the maintenance contract, which
 * waits for the go-live and is billed on its own from then on.
 */
class Offer extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'project_id',
        'type',
        'number',
        'title',
        'url',
        'offer_date',
        'valid_until',
        'tax_rate',
        'package',
        'features',
        'integrations',
        'other_function',
        'content_delivery',
        'payment_terms',
        'reference_customer',
        'maintenance_package',
        'maintenance_monthly_price',
        'intro',
        'notes',
        'recipient',
        'issuer',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'type_label',
        'project_subtotal',
        'maintenance_annual_price',
        'subtotal',
        'tax_amount',
        'total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceContractType::class,
            'offer_date' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'tax_rate' => 'decimal:2',
            'features' => 'array',
            'integrations' => 'array',
            'reference_customer' => 'boolean',
            'maintenance_monthly_price' => 'decimal:2',
            'recipient' => 'array',
            'issuer' => 'array',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(OfferItem::class)->orderBy('position');
    }

    public function maintenanceContract(): BelongsTo
    {
        return $this->belongsTo(MaintenanceContract::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Sent or decided offers stay as the customer got them; changes go into a copy.
     */
    public function isLocked(): bool
    {
        return $this->sent_at !== null || $this->accepted_at !== null || $this->declined_at !== null;
    }

    /**
     * Next number in the "A-YYYY-NNN" sequence of the given user and year.
     */
    public static function nextNumberFor(User $user, int $year): string
    {
        $highest = $user->offers()
            ->where('number', 'like', "A-{$year}-%")
            ->pluck('number')
            ->map(fn (string $number) => (int) Str::afterLast($number, '-'))
            ->max() ?? 0;

        return sprintf('A-%d-%03d', $year, $highest + 1);
    }

    /**
     * Filter by status: open (undecided, still valid) | accepted | declined | expired.
     */
    public function scopeWhereStatus(Builder $query, string $status): void
    {
        $undecided = fn (Builder $query) => $query->whereNull('accepted_at')->whereNull('declined_at');

        match ($status) {
            'open' => $undecided($query)->where('valid_until', '>=', today()->toDateString()),
            'accepted' => $query->whereNotNull('accepted_at'),
            'declined' => $query->whereNotNull('declined_at'),
            'expired' => $undecided($query)->where('valid_until', '<', today()->toDateString()),
            default => null,
        };
    }

    /**
     * Accepted by the customer. The maintenance contract follows with the invoice.
     */
    public function accept(): void
    {
        $this->forceFill(['accepted_at' => now(), 'declined_at' => null])->save();
    }

    /**
     * Project billed: the maintenance contract is created and waits for the go-live (no start,
     * nothing due). No monitoring yet, the site isn't live and would be reported as down.
     */
    public function billWith(Invoice $invoice): MaintenanceContract
    {
        return DB::transaction(function () use ($invoice) {
            $contract = $this->user->maintenanceContracts()->create([
                'customer_id' => $this->customer_id,
                'project_id' => $this->project_id,
                'type' => $this->type,
                'name' => $this->title,
                'url' => $this->url,
                'monthly_price' => $this->maintenance_monthly_price,
                'start_date' => null,
                'next_invoice_date' => null,
                'notes' => "Aus Angebot {$this->number}",
            ]);

            $this->forceFill(['invoice_id' => $invoice->id, 'maintenance_contract_id' => $contract->id])->save();

            return $contract;
        });
    }

    public function decline(): void
    {
        $this->forceFill(['declined_at' => now()])->save();
    }

    /**
     * Billed only when the work is done ("100 % bei Fertigstellung"). Otherwise (prepayment,
     * 50/50) the project invoice comes right after the acceptance.
     */
    public function billsOnCompletion(): bool
    {
        return $this->payment_terms === 'on_completion';
    }

    /**
     * Back to undecided, as long as no invoice was written for it.
     */
    public function canReopen(): bool
    {
        return ($this->accepted_at !== null || $this->declined_at !== null) && $this->invoice_id === null;
    }

    public function reopen(): void
    {
        $this->forceFill(['accepted_at' => null, 'declined_at' => null])->save();
    }

    /**
     * Unsent copy with a new number and today's date, e.g. to revise a sent offer.
     */
    public function duplicate(): Offer
    {
        return DB::transaction(function () {
            $today = today();

            $copy = $this->user->offers()->create([
                ...$this->only([
                    'customer_id', 'project_id', 'type', 'title', 'url', 'tax_rate', 'package', 'features', 'integrations', 'other_function',
                    'content_delivery', 'payment_terms', 'reference_customer', 'maintenance_package', 'maintenance_monthly_price',
                    'intro', 'notes', 'recipient', 'issuer',
                ]),
                'number' => self::nextNumberFor($this->user, $today->year),
                'offer_date' => $today,
                'valid_until' => $today->copy()->addDays(config('offers.valid_days')),
            ]);

            foreach ($this->items as $item) {
                $copy->items()->create($item->only(['position', 'description', 'quantity', 'unit', 'unit_price', 'list_price']));
            }

            return $copy;
        });
    }

    /**
     * Items for the project invoice of an accepted offer. The maintenance is billed separately
     * once the contract starts with the go-live.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toInvoiceItems(): array
    {
        return $this->items->map(fn (OfferItem $item) => $item->only(['description', 'quantity', 'unit', 'unit_price']))->all();
    }

    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn () => $this->type->label());
    }

    protected function projectSubtotal(): Attribute
    {
        return Attribute::get(fn () => round($this->items->sum(fn (OfferItem $item) => $item->line_total), 2));
    }

    protected function maintenanceAnnualPrice(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->maintenance_monthly_price * 12, 2));
    }

    /**
     * Project plus the first 12 months of maintenance, net.
     */
    protected function subtotal(): Attribute
    {
        return Attribute::get(fn () => round($this->project_subtotal + $this->maintenance_annual_price, 2));
    }

    protected function taxAmount(): Attribute
    {
        return Attribute::get(fn () => round($this->subtotal * (float) $this->tax_rate / 100, 2));
    }

    /**
     * VAT of the project alone; the rest of the total VAT belongs to the maintenance, so both
     * parts add up to the total to the cent.
     */
    public function projectTaxAmount(): float
    {
        return round($this->project_subtotal * (float) $this->tax_rate / 100, 2);
    }

    /**
     * Sent before the minimum term and renewal clause existed: keeps the old conditions.
     */
    public function hasContractTerms(): bool
    {
        return (int) ($this->terms_version ?? 2) >= 2;
    }

    protected function total(): Attribute
    {
        return Attribute::get(fn () => round($this->subtotal + $this->tax_amount, 2));
    }

    /**
     * accepted | declined | expired | sent | draft
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->declined_at !== null => 'declined',
            $this->valid_until->lt(today()) => 'expired',
            $this->sent_at !== null => 'sent',
            default => 'draft',
        });
    }

    /**
     * File name like "Angebot A-2026-003-Musterfirma GmbH.pdf".
     */
    public function fileName(): string
    {
        $name = preg_replace('/[^\pL\pN \-_.&]+/u', '', $this->recipient['name'] ?? '');

        return trim('Angebot '.$this->number.'-'.$name, '-').'.pdf';
    }
}
