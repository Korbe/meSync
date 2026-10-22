<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Invoice extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'project_id',
        'maintenance_contract_id',
        'cancels_invoice_id',
        'number',
        'invoice_date',
        'due_date',
        'tax_rate',
        'prices_include_tax',
        'paid_amount',
        'paid_in_cash',
        'notes',
        'recipient',
        'issuer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'subtotal',
        'tax_amount',
        'total',
        'amount_due',
        'received',
        'outstanding',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'tax_rate' => 'decimal:2',
            'prices_include_tax' => 'boolean',
            'paid_amount' => 'decimal:2',
            'paid_in_cash' => 'boolean',
            'sent_at' => 'datetime',
            'recipient' => 'array',
            'issuer' => 'array',
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

    public function maintenanceContract(): BelongsTo
    {
        return $this->belongsTo(MaintenanceContract::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The maintenance billing entry this invoice was created for (if any).
     */
    public function maintenanceInvoice(): HasOne
    {
        return $this->hasOne(MaintenanceInvoice::class);
    }

    /**
     * For a cancellation invoice (Stornorechnung): the invoice it cancels.
     */
    public function cancels(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'cancels_invoice_id');
    }

    /**
     * The cancellation invoice that cancelled this invoice, if any.
     */
    public function cancellation(): HasOne
    {
        return $this->hasOne(Invoice::class, 'cancels_invoice_id');
    }

    public function isCancellation(): bool
    {
        return $this->cancels_invoice_id !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancellation()->exists();
    }

    /**
     * Cancelled invoices and cancellation invoices must not be changed anymore.
     */
    public function isLocked(): bool
    {
        return $this->isCancellation() || $this->isCancelled();
    }

    /**
     * Create the cancellation invoice: same recipient/issuer snapshot and
     * items with negated prices, so both invoices add up to zero.
     */
    public function cancel(): Invoice
    {
        $today = today();

        $cancellation = $this->user->invoices()->create([
            'customer_id' => $this->customer_id,
            'cancels_invoice_id' => $this->id,
            'number' => self::nextNumberFor($this->user, $today->year),
            'invoice_date' => $today,
            'due_date' => $today,
            'tax_rate' => $this->tax_rate,
            'prices_include_tax' => (bool) $this->prices_include_tax,
            'paid_amount' => 0,
            'notes' => null, // the PDF and the app show the reference to the original invoice
            'recipient' => $this->recipient,
            'issuer' => $this->issuer,
        ]);

        foreach ($this->items as $item) {
            $cancellation->items()->create([
                'position' => $item->position,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => -1 * (float) $item->unit_price,
            ]);
        }

        return $cancellation;
    }

    /**
     * An existing invoice (e.g. written before meSync) as PDF. When attached it replaces the
     * generated PDF everywhere. Stored privately and only served through the app.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('original')
            ->singleFile()
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    /*
     * Amounts and payment status as SQL, for filtering and sums in the database (paginated
     * lists). Same rounding as the subtotal/tax/outstanding attributes below. The items are
     * net, or gross for invoices with prices_include_tax (PC help, PC builds).
     */

    private static function sqlItems(): string
    {
        return '(SELECT COALESCE(SUM(ROUND(ii.quantity * ii.unit_price, 2)), 0) FROM invoice_items ii WHERE ii.invoice_id = invoices.id)';
    }

    public static function sqlTax(): string
    {
        $items = self::sqlItems();

        return "(CASE WHEN invoices.prices_include_tax THEN ROUND({$items} * invoices.tax_rate / (100 + invoices.tax_rate), 2)"
            ." ELSE ROUND({$items} * invoices.tax_rate / 100, 2) END)";
    }

    /**
     * Net amount.
     */
    public static function sqlSubtotal(): string
    {
        return '(CASE WHEN invoices.prices_include_tax THEN '.self::sqlItems().' - '.self::sqlTax().' ELSE '.self::sqlItems().' END)';
    }

    public static function sqlOutstanding(): string
    {
        return '('.self::sqlSubtotal().' + '.self::sqlTax().' - invoices.paid_amount'
            .' - (SELECT COALESCE(SUM(ip.amount), 0) FROM invoice_payments ip WHERE ip.invoice_id = invoices.id))';
    }

    public static function sqlCancelled(): string
    {
        return '(invoices.cancels_invoice_id IS NOT NULL OR EXISTS (SELECT 1 FROM invoices ci WHERE ci.cancels_invoice_id = invoices.id))';
    }

    /**
     * Filter by payment status: paid | unpaid (open, partial, overdue) | overdue | cancelled.
     */
    public function scopeWhereStatus(Builder $query, string $status): void
    {
        $notCancelled = 'NOT '.self::sqlCancelled();
        $due = self::sqlOutstanding().' > 0.004';

        match ($status) {
            'cancelled' => $query->whereRaw(self::sqlCancelled()),
            'paid' => $query->whereRaw("{$notCancelled} AND ".self::sqlOutstanding().' <= 0.004'),
            'unpaid' => $query->whereRaw("{$notCancelled} AND {$due}"),
            'overdue' => $query->whereRaw("{$notCancelled} AND {$due}")->where('due_date', '<', today()->toDateString()),
            default => null,
        };
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * Next number in the "YYYY-NNN" sequence of the given user and year.
     */
    public static function nextNumberFor(User $user, int $year): string
    {
        $highest = $user->invoices()
            ->where('number', 'like', "{$year}-%")
            ->pluck('number')
            ->map(fn (string $number) => (int) Str::afterLast($number, '-'))
            ->max() ?? 0;

        return sprintf('%d-%03d', $year, $highest + 1);
    }

    /**
     * Sum of the items as entered: net, or gross when the prices include the tax.
     */
    private function itemsTotal(): float
    {
        return round($this->items->sum(fn (InvoiceItem $item) => $item->line_total), 2);
    }

    /**
     * Net amount. With gross prices the contained tax is taken out.
     */
    protected function subtotal(): Attribute
    {
        return Attribute::get(fn () => $this->prices_include_tax
            ? round($this->itemsTotal() - $this->tax_amount, 2)
            : $this->itemsTotal());
    }

    protected function taxAmount(): Attribute
    {
        $rate = (float) $this->tax_rate;

        return Attribute::get(fn () => $this->prices_include_tax
            ? round($this->itemsTotal() * $rate / (100 + $rate), 2)
            : round($this->itemsTotal() * $rate / 100, 2));
    }

    protected function total(): Attribute
    {
        return Attribute::get(fn () => round($this->subtotal + $this->tax_amount, 2));
    }

    protected function amountDue(): Attribute
    {
        return Attribute::get(fn () => round($this->total - (float) $this->paid_amount, 2));
    }

    /**
     * Sum of payments received after the invoice was sent.
     */
    protected function received(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->payments->sum('amount'), 2));
    }

    protected function outstanding(): Attribute
    {
        return Attribute::get(fn () => max(0, round($this->amount_due - $this->received, 2)));
    }

    /**
     * cancelled | paid | overdue | partial | open
     */
    protected function paymentStatus(): Attribute
    {
        return Attribute::get(function () {
            if ($this->isCancellation() || $this->cancellation) {
                return 'cancelled';
            }

            if ($this->outstanding <= 0) {
                return 'paid';
            }

            if ($this->due_date->lt(today())) {
                return 'overdue';
            }

            return $this->received > 0 ? 'partial' : 'open';
        });
    }

    /**
     * File name like "2026-013-Musterfirma GmbH.pdf".
     */
    public function fileName(): string
    {
        $name = preg_replace('/[^\pL\pN \-_.&]+/u', '', $this->recipient['name'] ?? '');

        return trim($this->number.'-'.$name, '-').'.pdf';
    }
}
