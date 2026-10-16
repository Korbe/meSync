<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class BusinessProfileSeeder extends Seeder
{
    /**
     * Existing company data is never overwritten.
     */
    public function run(User $user): void
    {
        if ($user->businessProfile()->exists()) {
            return;
        }

        $user->businessProfile()->create([
            'name' => $user->name,
            'street' => 'Hauptplatz 1',
            'zip' => '9500',
            'city' => 'Villach',
            'country' => 'Österreich',
            'email' => $user->email,
            'phone' => '+43 664 1234567',
            'website' => 'korbitsch.at',
            'bank_name' => 'Kärntner Sparkasse',
            'account_holder' => $user->name,
            'iban' => 'AT611904300234573201',
            'bic' => 'KSPKAT2KXXX',
            'payment_terms_days' => 14,
            'default_tax_rate' => 20,
            'invoice_color' => '#1fadae',
        ]);
    }
}
