<?php

namespace App\Enums;

enum FinanceCategory: string
{
    // Income
    case Salary = 'salary';
    case Revenue = 'revenue';
    case Sales = 'sales';
    case SideJob = 'side_job';
    case Interest = 'interest';
    case Refunds = 'refunds';
    case OtherIncome = 'other_income';

    // Expenses
    case Housing = 'housing';
    case Insurance = 'insurance';
    case Investments = 'investments';
    case Subscriptions = 'subscriptions';
    case Groceries = 'groceries';
    case Tobacco = 'tobacco';
    case Health = 'health';
    case Clothing = 'clothing';
    case Mobility = 'mobility';
    case Leisure = 'leisure';
    case Travel = 'travel';
    case Shopping = 'shopping';
    case Cash = 'cash';
    case Software = 'software';
    case Hosting = 'hosting';
    case Hardware = 'hardware';
    case Office = 'office';
    case Marketing = 'marketing';
    case Education = 'education';
    case Taxes = 'taxes';
    case Fees = 'fees';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Gehalt',
            self::Revenue => 'Umsatz',
            self::Sales => 'Verkäufe',
            self::SideJob => 'Nebenjob',
            self::Interest => 'Zinsen & Erträge',
            self::Refunds => 'Rückerstattungen & Zuschüsse',
            self::OtherIncome => 'Sonstige Einnahmen',
            self::Housing => 'Wohnen',
            self::Insurance => 'Versicherungen',
            self::Investments => 'Sparen & Investieren',
            self::Subscriptions => 'Verträge & Abos',
            self::Groceries => 'Lebensmittel & Haushalt',
            self::Tobacco => 'Trafik & Tabakwaren',
            self::Health => 'Gesundheit',
            self::Clothing => 'Kleidung',
            self::Mobility => 'Mobilität',
            self::Leisure => 'Freizeit & Entertainment',
            self::Travel => 'Urlaub',
            self::Shopping => 'Einkäufe',
            self::Cash => 'Bargeld',
            self::Software => 'Software & Lizenzen',
            self::Hosting => 'Hosting & Domains',
            self::Hardware => 'Hardware & Geräte',
            self::Office => 'Büro & Material',
            self::Marketing => 'Marketing & Werbung',
            self::Education => 'Weiterbildung',
            self::Taxes => 'Steuern & Abgaben',
            self::Fees => 'Bank & Gebühren',
            self::Other => 'Sonstiges',
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::Salary, self::Revenue, self::Sales, self::SideJob, self::Interest, self::Refunds, self::OtherIncome => 'income',
            default => 'expense',
        };
    }

    /**
     * Where the category is typically used; the form lists these first.
     *
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return match ($this) {
            self::Salary, self::SideJob, self::Investments, self::Groceries, self::Tobacco, self::Clothing, self::Travel, self::Cash => ['private'],
            self::Revenue, self::Software, self::Hosting, self::Hardware, self::Office, self::Marketing => ['business'],
            default => ['private', 'business'],
        };
    }

    /**
     * @return array<int, array{value: string, label: string, type: string, scopes: array<int, string>}>
     */
    public static function options(): array
    {
        return array_map(fn (self $category) => [
            'value' => $category->value,
            'label' => $category->label(),
            'type' => $category->type(),
            'scopes' => $category->scopes(),
        ], self::cases());
    }
}
