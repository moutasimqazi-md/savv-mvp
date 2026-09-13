<?php

namespace Savv\Support;

final class Currency
{
    private const SYMBOLS = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    public static function symbol(?string $code): string
    {
        return self::SYMBOLS[strtoupper((string) $code)] ?? (strtoupper((string) $code) . ' ');
    }

    public static function format(?int $minor, ?string $code, string $fallback = '-'): string
    {
        if ($minor === null) {
            return $fallback;
        }

        return self::symbol($code) . number_format($minor / 100, 2);
    }
}
