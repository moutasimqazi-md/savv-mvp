<?php

namespace Savv\Services;

use Savv\Enums\SubscriptionStatus;

final class SubscriptionStatusNormalizer
{
    private const array RULES = [
        'past_due' => ['past due', 'payment failed', 'payment declined'],
        'cancelled' => ['cancelled', 'canceled', 'expired', 'ended'],
        'trialing' => ['trial', 'free trial'],
        'active' => ['active', 'current plan', 'renews', 'subscribed'],
    ];

    public static function normalize(?string $originalStatus): SubscriptionStatus
    {
        if ($originalStatus === null || trim($originalStatus) === '') {
            return SubscriptionStatus::Unknown;
        }

        $haystack = strtolower(trim($originalStatus));

        foreach (self::RULES as $normalized => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return SubscriptionStatus::from($normalized);
                }
            }
        }

        return SubscriptionStatus::Unknown;
    }
}
