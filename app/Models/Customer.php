<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title_prefix',
        'first_name',
        'last_name',
        'title_suffix',
        'company_name',
        'company_suffix',
        'street',
        'zip',
        'city',
        'country',
        'email',
        'phone',
        'uid_number',
        'notes',
        'interests',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'full_name',
        'company',
        'display_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maintenanceContracts(): HasMany
    {
        return $this->hasMany(MaintenanceContract::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Snapshot of the address block stored on invoices,
     * e.g. name "Markus Kirisits - FCG Villach".
     *
     * @return array<string, string|null>
     */
    public function toRecipient(): array
    {
        return [
            'name' => implode(' - ', array_filter([$this->full_name, $this->company])),
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
            'uid_number' => $this->uid_number,
        ];
    }

    /**
     * Name with titles, e.g. "Dr. Max Mustermann, MSc".
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function () {
            $name = trim(implode(' ', array_filter([$this->title_prefix, $this->first_name, $this->last_name])));

            if ($name !== '' && $this->title_suffix) {
                $name .= ', '.$this->title_suffix;
            }

            return $name;
        });
    }

    /**
     * Company with legal form, e.g. "Musterfirma GmbH".
     */
    protected function company(): Attribute
    {
        return Attribute::get(fn () => trim(implode(' ', array_filter([$this->company_name, $this->company_suffix]))));
    }

    /**
     * Best name to show in lists: the person, or the company if there is no person.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->full_name ?: $this->company);
    }
}
