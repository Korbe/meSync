<?php

namespace App\Enums;

enum MaintenanceContractType: string
{
    case Website = 'website';
    case Webapp = 'webapp';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Webapp => 'Webanwendung',
        };
    }

    /**
     * Standard monthly price in EUR, used to prefill new contracts.
     */
    public function defaultMonthlyPrice(): float
    {
        return match ($this) {
            self::Website => 39.0,
            self::Webapp => 59.0,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, price: float}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'price' => $type->defaultMonthlyPrice(),
        ], self::cases());
    }
}
