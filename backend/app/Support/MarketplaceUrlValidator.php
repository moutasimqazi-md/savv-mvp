<?php

namespace Savv\Support;

final class MarketplaceUrlValidator
{
    /**
     * Only HTTPS links on an approved marketplace host are ever accepted for
     * display or storage. Everything else - including javascript:, data:,
     * file:, and unapproved domains - is rejected.
     *
     * $isImage widens the allowlist to include the marketplaces' real image
     * CDN subdomains (e.g. m.media-amazon.com), which are never the same
     * host as the clickable order/product/invoice links. Images are never
     * navigated to by clicking, so this widening is safe without loosening
     * the link allowlist used everywhere else.
     */
    public static function isAllowed(?string $url, bool $isImage = false): bool
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
        $allowedHosts = $isImage
            ? array_merge(config('savv.allowed_marketplace_hosts', []), config('savv.allowed_image_hosts', []))
            : config('savv.allowed_marketplace_hosts', []);

        return array_key_exists($host, $allowedHosts);
    }

    public static function sanitizeOrNull(?string $url, bool $isImage = false): ?string
    {
        return self::isAllowed($url, $isImage) ? $url : null;
    }

    public static function providerForHost(string $host): ?string
    {
        return config('savv.allowed_marketplace_hosts')[strtolower($host)] ?? null;
    }
}
