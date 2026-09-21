<?php

namespace Savv\Enums;

enum Provider: string
{
    case AmazonUs = 'amazon_us';
    case Claude = 'claude';
    case Walmart = 'walmart';

    public function label(): string
    {
        return match ($this) {
            self::AmazonUs => 'Amazon US',
            self::Claude => 'Claude',
            self::Walmart => 'Walmart',
        };
    }

    /**
     * What kind of data an import session for this provider produces -
     * drives which extraction/validation/confirmation pipeline runs (see
     * Savv\Jobs\ScanImportSession and Savv\Jobs\ConfirmImportSession).
     */
    public function kind(): ProviderKind
    {
        return match ($this) {
            self::AmazonUs, self::Walmart => ProviderKind::Orders,
            self::Claude => ProviderKind::Subscription,
        };
    }
}
