<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uptime and SSL monitoring of one web address: the website of a maintenance contract,
 * or standalone (e.g. an own app).
 */
class Monitor extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'url',
        'enabled',
        'auth',
        'auth_user',
        'auth_password',
    ];

    /**
     * The password for protected websites never leaves the server.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'auth_password',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'has_password',
    ];

    protected static function booted(): void
    {
        // Switched off: forget the status, it would be stale later
        static::saving(function (Monitor $monitor) {
            if ($monitor->isDirty('enabled') && ! $monitor->enabled) {
                $monitor->forceFill(['status' => null, 'status_since' => null, 'failures' => 0, 'error' => null]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'auth_password' => 'encrypted',
            'status_since' => 'datetime',
            'checked_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'ssl_warning_sent_for' => 'date:Y-m-d',
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

    public function siteChecks(): HasMany
    {
        return $this->hasMany(SiteCheck::class);
    }

    /**
     * Checked by the scheduler: switched on, and its contract (if any) is running.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('enabled', true)
            ->where(fn (Builder $query) => $query->whereNull('maintenance_contract_id')->orWhereHas('maintenanceContract', fn (Builder $query) => $query->billable()));
    }

    public function isActive(): bool
    {
        return $this->enabled && ($this->maintenance_contract_id === null || (bool) $this->maintenanceContract?->isBillable());
    }

    /**
     * Password for HTTP Basic Auth, or null when none is stored or it can't be decrypted
     * (e.g. a backup restored on a server with a different APP_KEY).
     */
    public function password(): ?string
    {
        try {
            return $this->auth_password;
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Where its details are: the contract, or the monitoring center for standalone monitors.
     */
    public function link(): string
    {
        return $this->maintenance_contract_id ? route('contracts.edit', $this->maintenance_contract_id) : route('monitoring.index');
    }

    protected function hasPassword(): Attribute
    {
        // Raw value: tells whether a password is stored without decrypting it
        return Attribute::get(fn () => filled($this->attributes['auth_password'] ?? null));
    }
}
