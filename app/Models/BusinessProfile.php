<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sender and bank details of a user, printed on invoices.
 */
class BusinessProfile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'brand_name',
        'street',
        'zip',
        'city',
        'country',
        'email',
        'phone',
        'website',
        'uid_number',
        'bank_name',
        'account_holder',
        'iban',
        'bic',
        'payment_terms_days',
        'default_tax_rate',
        'invoice_color',
    ];

    protected function casts(): array
    {
        return [
            'payment_terms_days' => 'integer',
            'default_tax_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Snapshot stored on each invoice.
     *
     * @return array<string, string|null>
     */
    /**
     * Name shown to customers in mails: the business designation if set, else the name.
     */
    public function displayName(): ?string
    {
        return $this->brand_name ?: $this->name;
    }

    public function toIssuer(): array
    {
        return $this->only([
            'name', 'street', 'zip', 'city', 'country', 'email', 'phone', 'website',
            'uid_number', 'bank_name', 'account_holder', 'iban', 'bic', 'invoice_color',
        ]);
    }
}
