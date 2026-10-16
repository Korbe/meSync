<?php

namespace Database\Seeders;

use App\Enums\CustomerInterest;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    private const CITIES = [
        ['9500', 'Villach'], ['9020', 'Klagenfurt'], ['9220', 'Velden am Wörther See'], ['9520', 'Treffen'],
        ['9580', 'Drobollach'], ['9584', 'Finkenstein'], ['9586', 'Fürnitz'], ['9601', 'Arnoldstein'],
        ['9241', 'Wernberg'], ['9800', 'Spittal an der Drau'], ['9560', 'Feldkirchen'], ['9210', 'Pörtschach'],
    ];

    private const COMPANIES = [
        'Gasthof Seeblick', 'Elektro Huber', 'Tischlerei Wernig', 'Physio am Platz', 'Bäckerei Kogler',
        'Autohaus Moser', 'Weingut Sonnhang', 'Kanzlei Pirker', 'Friseur Haarmonie', 'Sportverein Drautal',
        'Café Mozart', 'Malerei Brandstätter', 'Zahnarztpraxis Ogris', 'Fitnessstudio Pulse', 'Blumen Rosi',
    ];

    private const NOTES = [
        'Bevorzugt Termine am Vormittag.', 'Rechnung bitte per E-Mail.', 'Über Empfehlung gekommen.',
        'Hat einen Hund – vorher anrufen.', 'Interesse an einem neuen Gaming-PC.', 'Nur telefonisch erreichbar.',
    ];

    public function run(User $user, int $count = 35): void
    {
        $faker = Faker::create('de_AT');
        $companies = collect(self::COMPANIES)->shuffle();

        for ($i = 0; $i < $count; $i++) {
            // About a third are businesses (websites, web apps), the rest private persons (PC help)
            $isCompany = $i % 3 === 0 && $companies->isNotEmpty();
            [$zip, $city] = $faker->randomElement(self::CITIES);
            $firstName = $faker->firstName();
            $lastName = $faker->lastName();

            $user->customers()->create([
                'title_prefix' => $faker->optional(0.15)->randomElement(['Dr.', 'Mag.', 'DI', 'Ing.']),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'title_suffix' => $faker->optional(0.1)->randomElement(['BSc', 'MSc', 'MBA']),
                'company_name' => $isCompany ? $companies->pop() : null,
                'company_suffix' => $isCompany ? $faker->randomElement(['GmbH', 'e.U.', 'OG', 'KG', null]) : null,
                'street' => $faker->streetName().' '.$faker->numberBetween(1, 80),
                'zip' => $zip,
                'city' => $city,
                'country' => 'Österreich',
                'email' => strtolower($this->ascii($firstName).'.'.$this->ascii($lastName)).'@example.at',
                'phone' => $faker->numerify('+43 6## #######'),
                'uid_number' => $isCompany ? $faker->optional(0.7)->numerify('ATU########') : null,
                'interests' => $faker->randomElements($isCompany
                    ? [CustomerInterest::Website->value, CustomerInterest::Webapp->value]
                    : [CustomerInterest::PcHelp->value, CustomerInterest::PcBuild->value, CustomerInterest::Website->value],
                    $faker->numberBetween(1, 2)),
                'notes' => $faker->optional(0.25)->randomElement(self::NOTES),
            ]);
        }
    }

    private function ascii(string $value): string
    {
        $value = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'Ae', 'Oe', 'Ue'], $value);

        return preg_replace('/[^a-z]/i', '', $value);
    }
}
