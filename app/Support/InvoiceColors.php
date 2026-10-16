<?php

namespace App\Support;

/**
 * Derives the shades used on the invoice PDF from one accent color.
 */
class InvoiceColors
{
    public const DEFAULT = '#1fadae';

    /**
     * @return array{base: string, dark: string, ink: string, tint: string, tintStrong: string, border: string, onBase: string, onBaseMuted: string}
     */
    public static function palette(?string $hex): array
    {
        $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $hex) ? strtolower($hex) : self::DEFAULT;

        // Pick the text color with the higher contrast ratio (WCAG): dark text on light accents
        $luminance = self::luminance($hex);
        $light = ($luminance + 0.05) / 0.05 > 1.05 / ($luminance + 0.05);

        return [
            'base' => $hex,                                   // bars, lines, title number
            'dark' => self::mix($hex, '#000000', 0.2),        // emphasized text (due date, amount)
            'ink' => self::mix($hex, '#000000', 0.6),         // dark text on tinted boxes
            'tint' => self::mix($hex, '#ffffff', 0.92),       // light box backgrounds
            'tintStrong' => self::mix($hex, '#ffffff', 0.8),  // box borders
            'border' => self::mix($hex, '#ffffff', 0.75),
            // Text on the colored "amount due" bar must stay readable
            'onBase' => $light ? '#0a0a0a' : '#ffffff',
            'onBaseMuted' => $light ? self::mix($hex, '#000000', 0.7) : self::mix($hex, '#ffffff', 0.85),
        ];
    }

    /**
     * Mix two hex colors; $amount = share of the second color (0..1).
     */
    public static function mix(string $from, string $to, float $amount): string
    {
        [$r1, $g1, $b1] = self::rgb($from);
        [$r2, $g2, $b2] = self::rgb($to);

        return sprintf('#%02x%02x%02x',
            round($r1 + ($r2 - $r1) * $amount),
            round($g1 + ($g2 - $g1) * $amount),
            round($b1 + ($b2 - $b1) * $amount),
        );
    }

    /**
     * Relative luminance (WCAG), 0 = black, 1 = white.
     */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $channel) {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * @return array{int, int, int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
