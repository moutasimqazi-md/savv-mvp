<?php

namespace Savv\Support;

use RuntimeException;

/**
 * Recursively scans a decoded JSON payload for keys that look like
 * credential/session material and refuses the whole payload if any are
 * found. This is a marketplace-data boundary (runner scan results, manual
 * entry, CSV import) - it never applies to Laravel's own session handling.
 */
final class ForbiddenFieldGuard
{
    /**
     * @throws RuntimeException when a forbidden key is present
     */
    public static function assertSafe(mixed $payload): void
    {
        $substrings = config('savv.forbidden_field_substrings', []);

        self::walk($payload, $substrings);
    }

    private static function walk(mixed $value, array $substrings): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized = strtolower($key);

                foreach ($substrings as $needle) {
                    if (str_contains($normalized, $needle)) {
                        throw new RuntimeException("Payload contains a forbidden field: \"{$key}\".");
                    }
                }
            }

            self::walk($item, $substrings);
        }
    }
}
