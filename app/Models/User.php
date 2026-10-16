<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use InteractsWithMedia;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * Login-free demo account with random data (see DatabaseSeeder).
     */
    public const DEMO_EMAIL = 'demo@mesync.test';

    protected static function booted(): void
    {
        // All business data goes with the user through the database foreign keys. Files
        // attached to that data don't, so delete those models one by one first: the
        // media library removes the receipt files on each model's delete.
        static::deleting(function (User $user) {
            $user->inventoryItems()->with('media')->get()->each->delete();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Company logo, e.g. for invoices. Only one logo per user.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/webp']);
    }

    public function emotions()
    {
        return $this->hasMany(Emotion::class);
    }

    public function digestions()
    {
        return $this->hasMany(Digestion::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function maintenanceContracts()
    {
        return $this->hasMany(MaintenanceContract::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function businessProfile()
    {
        return $this->hasOne(BusinessProfile::class);
    }

    public function catalogItems()
    {
        return $this->hasMany(CatalogItem::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function recurringExpenses()
    {
        return $this->hasMany(RecurringExpense::class);
    }

    public function financeEntries()
    {
        return $this->hasMany(FinanceEntry::class);
    }

    public function isDemo(): bool
    {
        return $this->email === self::DEMO_EMAIL;
    }

    /**
     * The demo address doesn't exist: no mails to the demo user (reminders, monitoring, ...).
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->isDemo() ? null : $this->email;
    }
}
