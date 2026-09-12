<?php

namespace Savv\Services;

final class DeduplicationService
{
    /**
     * Deterministic fallback identity for an order item when the provider
     * did not expose a stable item id. Never fingerprint on title alone.
     */
    public static function itemFingerprint(string $title, ?string $variant, int $quantity, ?int $lineTotalMinor): string
    {
        $normalizedTitle = self::normalizeForFingerprint($title);
        $normalizedVariant = self::normalizeForFingerprint((string) $variant);

        return hash('sha256', implode('|', [$normalizedTitle, $normalizedVariant, $quantity, $lineTotalMinor ?? 'null']));
    }

    private static function normalizeForFingerprint(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }
}
