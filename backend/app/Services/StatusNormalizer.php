<?php

namespace Savv\Services;

use Savv\Enums\NormalizedStatus;

/**
 * Maps a provider's free-text status wording to a Savv NormalizedStatus.
 * This is deliberately provider-agnostic keyword matching (not a DOM
 * selector), so it works the same regardless of which marketplace's page
 * produced the text. The original text is always kept alongside the
 * normalized value - see Order::$original_status.
 *
 * Keyword order matters: more specific phrases are matched before generic
 * ones (e.g. "return rejected" before "return").
 */
final class StatusNormalizer
{
    private const array RULES = [
        'return_rejected' => ['return rejected', 'return denied', 'return not approved'],
        'return_completed' => ['return completed', 'return closed', 'item returned'],
        'return_received' => ['return received', 'received at warehouse', 'received by seller'],
        'return_picked_up' => ['picked up', 'pickup completed', 'return picked'],
        'return_pickup_scheduled' => ['pickup scheduled', 'pickup arranged'],
        'return_approved' => ['return approved', 'return authorized'],
        'return_requested' => ['return requested', 'return initiated', 'replacement requested'],
        'refunded' => ['refund completed', 'refunded', 'refund successful'],
        'refund_processing' => ['refund initiated', 'refund in progress', 'refund processing'],
        'cancelled' => ['cancelled', 'canceled', 'order cancelled'],
        'out_for_delivery' => ['out for delivery'],
        'delivered' => ['delivered'],
        'shipped' => ['shipped', 'dispatched', 'on the way'],
        'packed' => ['packed', 'ready to ship'],
        'processing' => ['processing', 'preparing your order'],
        'confirmed' => ['confirmed', 'order confirmed'],
        'placed' => ['order placed', 'placed'],
    ];

    public static function normalize(?string $originalStatus): NormalizedStatus
    {
        if ($originalStatus === null || trim($originalStatus) === '') {
            return NormalizedStatus::Unknown;
        }

        $haystack = strtolower(trim($originalStatus));

        foreach (self::RULES as $normalized => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return NormalizedStatus::from($normalized);
                }
            }
        }

        return NormalizedStatus::Unknown;
    }
}
