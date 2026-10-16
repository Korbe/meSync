<?php

namespace App\Enums;

enum CustomerInterest: string
{
    case PcHelp = 'pc_help';
    case Website = 'website';
    case Webapp = 'webapp';
    case PcBuild = 'pc_build';

    public function label(): string
    {
        return match ($this) {
            self::PcHelp => 'PC-Hilfe',
            self::Website => 'Websites',
            self::Webapp => 'Webanwendung',
            self::PcBuild => 'PC-Build',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $interest) => [
            'value' => $interest->value,
            'label' => $interest->label(),
        ], self::cases());
    }
}
