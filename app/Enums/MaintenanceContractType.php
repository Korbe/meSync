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
     * Scope of services, printed on the contract information PDF.
     *
     * @return array<int, string>
     */
    public function services(): array
    {
        return match ($this) {
            self::Website => [
                'Technische Wartung und Sicherheitsupdates',
                'Betreuung von Hosting, Domain und SSL-Zertifikat',
                'Regelmäßige Datensicherung',
                'Kleine inhaltliche Änderungen nach Absprache',
                'Persönlicher Ansprechpartner bei Problemen',
            ],
            self::Webapp => [
                'Technische Wartung, Sicherheits- und Framework-Updates',
                'Betreuung von Server, Datenbank und SSL-Zertifikat',
                'Regelmäßige Datensicherung',
                'Fehlerbehebung im laufenden Betrieb',
                'Persönlicher Ansprechpartner bei Problemen',
            ],
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
