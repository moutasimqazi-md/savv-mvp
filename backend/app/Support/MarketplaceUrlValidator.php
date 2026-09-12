<?php

namespace Savv\Support;

final class MarketplaceUrlValidator
{
    /**
     * Only HTTPS links on an approved marketplace host are ever accepted for
     * display or storage. Everything else - including javascript:, data:,
     * file:, and unapproved domains - is rejected.
     */
    public static function isAllowed(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        if (strlen($url) > config('savv.imports.max_url_length', 2048)) {
            return false;
        }

        $parts = parse_url($url);

        if (! $parts || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        return array_key_exists($host, config('savv.allowed_marketplace_hosts', []));
    }

    public static function sanitizeOrNull(?string $url): ?string
    {
        return self::isAllowed($url) ? $url : null;
    }

    public static function providerForHost(string $host): ?string
    {
        return config('savv.allowed_marketplace_hosts')[strtolower($host)] ?? null;
    }
}
