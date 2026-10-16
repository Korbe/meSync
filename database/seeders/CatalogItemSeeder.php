<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CatalogItemSeeder extends Seeder
{
    public const ITEMS = [
        ['name' => 'PC-Hilfe vor Ort', 'description' => "Fehlersuche, Einrichtung und Beratung\n- erste 30 Minuten 50 €\n- danach je angefangene 15 Minuten 20 €", 'unit' => 'Stück', 'unit_price' => 50, 'pricing' => 'time', 'base_minutes' => 30, 'step_minutes' => 15, 'step_price' => 20],
        ['name' => 'PC-Hilfe per Fernwartung', 'description' => 'Hilfe per Fernzugriff', 'unit' => 'Stück', 'unit_price' => 40, 'pricing' => 'time', 'base_minutes' => 30, 'step_minutes' => 15, 'step_price' => 15],
        ['name' => 'Windows-Neuinstallation', 'description' => "Neuinstallation inkl. Treiber und Updates\n- Datensicherung vorher\n- Grundeinrichtung der Programme", 'unit' => 'Pauschale', 'unit_price' => 120],
        ['name' => 'Datenrettung', 'description' => 'Wiederherstellung von Daten einer defekten Festplatte', 'unit' => 'Pauschale', 'unit_price' => 150],
        ['name' => 'Virenentfernung', 'description' => 'Bereinigung und Absicherung des Systems', 'unit' => 'Pauschale', 'unit_price' => 89],
        ['name' => 'PC-Zusammenbau', 'description' => "Zusammenbau nach Wunsch\n- Kabelmanagement\n- Stresstest und BIOS-Einrichtung", 'unit' => 'Pauschale', 'unit_price' => 149],
        ['name' => 'Website Basic', 'description' => "Onepager mit bis zu 5 Abschnitten\n- responsives Design\n- Kontaktformular\n- Impressum und Datenschutz", 'unit' => 'Pauschale', 'unit_price' => 890],
        ['name' => 'Website Business', 'description' => "Mehrseitige Website mit CMS\n- bis zu 10 Seiten\n- SEO-Grundoptimierung", 'unit' => 'Pauschale', 'unit_price' => 1890],
        ['name' => 'Webanwendung – Entwicklung', 'description' => 'Individuelle Entwicklung', 'unit' => 'Stunde', 'unit_price' => 85],
        ['name' => 'Domain & Hosting Einrichtung', 'description' => 'Domainregistrierung, DNS und E-Mail-Postfächer', 'unit' => 'Pauschale', 'unit_price' => 79],
        ['name' => 'SSD 1 TB', 'description' => 'Samsung 990 EVO, inkl. Einbau', 'unit' => 'Stück', 'unit_price' => 109],
        ['name' => 'Arbeitsspeicher 16 GB', 'description' => 'DDR4 3200 MHz, inkl. Einbau', 'unit' => 'Stück', 'unit_price' => 59],
    ];

    /**
     * Items with an existing name are left alone.
     */
    public function run(User $user): void
    {
        foreach (self::ITEMS as $item) {
            $user->catalogItems()->firstOrCreate(['name' => $item['name']], $item);
        }
    }
}
