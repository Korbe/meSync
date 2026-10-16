<?php

namespace App\Enums;

enum InventoryCategory: string
{
    case Computer = 'computer';
    case Mobile = 'mobile';
    case Network = 'network';
    case Peripherals = 'peripherals';
    case Tools = 'tools';
    case Office = 'office';
    case Software = 'software';
    case Vehicle = 'vehicle';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Computer => 'Computer & Laptops',
            self::Mobile => 'Smartphones & Tablets',
            self::Network => 'Netzwerk & Server',
            self::Peripherals => 'Monitore & Zubehör',
            self::Tools => 'Werkzeug & Messgeräte',
            self::Office => 'Büro & Möbel',
            self::Software => 'Software & Lizenzen',
            self::Vehicle => 'Fahrzeug',
            self::Other => 'Sonstiges',
        };
    }

    /**
     * Typical useful life in Austria, used as suggestion for the depreciation period.
     */
    public function usefulLifeYears(): int
    {
        return match ($this) {
            self::Computer, self::Mobile, self::Software => 3,
            self::Network, self::Peripherals => 4,
            self::Tools => 5,
            self::Vehicle => 8,
            self::Office => 10,
            self::Other => 5,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, years: int}>
     */
    public static function options(): array
    {
        return array_map(fn (self $category) => [
            'value' => $category->value,
            'label' => $category->label(),
            'years' => $category->usefulLifeYears(),
        ], self::cases());
    }
}
