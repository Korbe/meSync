<?php

namespace App\Models;

use App\Enums\CustomerInterest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A mail to several customers: all, by interest or picked by hand. Customers who
 * unsubscribed and customers without an email address are left out.
 */
class Newsletter extends Model
{
    public const AUDIENCES = [
        'all' => 'Alle Kunden',
        'interests' => 'Nach Interesse',
        'selected' => 'Einzelne Kunden',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'subject',
        'body',
        'audience',
        'interests',
        'customer_ids',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
            'customer_ids' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NewsletterRecipient::class);
    }

    /**
     * Sent newsletters stay as they went out.
     */
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * Customers who get this newsletter now.
     *
     * @return Builder<Customer>
     */
    public function audienceQuery(): Builder
    {
        return $this->user->customers()->getQuery()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNull('newsletter_opt_out_at')
            ->when($this->audience === 'interests', fn (Builder $query) => $query->where(function (Builder $query) {
                foreach ($this->interests ?? [] as $interest) {
                    $query->orWhereJsonContains('interests', CustomerInterest::from($interest)->value);
                }
                // No interest picked: nobody
                $query->orWhereRaw('1 = 0');
            }))
            ->when($this->audience === 'selected', fn (Builder $query) => $query->whereIn('id', $this->customer_ids ?? []));
    }
}
