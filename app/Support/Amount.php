<?php

namespace App\Support;

use InvalidArgumentException;

class Amount
{
    /**
     * Parse an amount like in a spreadsheet cell: "12,50", "1.234,50", "5,92+6,5+24" or "200-172,62".
     * A comma is the decimal separator; without a comma a dot is one.
     */
    public static function parse(?string $input): float
    {
        $input = trim(ltrim(str_replace([' ', '€'], '', (string) $input), '='));

        if ($input === '' || ! preg_match('/^[+-]?[\d.,]+([+-][\d.,]+)*$/', $input)) {
            throw new InvalidArgumentException('Ungültiger Betrag.');
        }

        preg_match_all('/([+-]?)([\d.,]+)/', $input, $terms, PREG_SET_ORDER);

        $sum = 0.0;
        foreach ($terms as [, $sign, $number]) {
            $number = str_contains($number, ',') ? str_replace(['.', ','], ['', '.'], $number) : $number;

            if (! is_numeric($number)) {
                throw new InvalidArgumentException('Ungültiger Betrag.');
            }

            $sum += $sign === '-' ? -(float) $number : (float) $number;
        }

        return round($sum, 2);
    }

    /**
     * True when the input is a calculation and not just a single number.
     */
    public static function isFormula(?string $input): bool
    {
        return (bool) preg_match('/\d\s*[+-]\s*\d/', (string) $input);
    }
}
