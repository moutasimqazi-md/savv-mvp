<?php

namespace Savv\Services;

final class TextSanitizer
{
    /**
     * Strips tags/scripts, normalizes whitespace and Unicode, and truncates
     * to the configured max field length. Every extracted string is treated
     * as untrusted input, regardless of source.
     */
    public static function clean(?string $value, ?int $maxLength = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $maxLength ??= (int) config('savv.imports.max_field_length', 500);

        $normalized = \Normalizer::isNormalized($value) ? $value : (\Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value);
        $stripped = strip_tags($normalized);
        $collapsed = trim(preg_replace('/\s+/u', ' ', $stripped) ?? $stripped);

        return mb_substr($collapsed, 0, $maxLength);
    }

    public static function isEmpty(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
