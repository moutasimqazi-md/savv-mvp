<?php

namespace Savv\Support;

use Savv\Enums\NormalizedStatus;
use Savv\Enums\SubscriptionStatus;

final class StatusPresenter
{
    /**
     * Delivery milestones an order moves through, in order. Used by the
     * order detail stepper.
     */
    public const DELIVERY_STEPS = ['Ordered', 'Packed', 'Shipped', 'Out for delivery', 'Delivered'];

    public static function tone(NormalizedStatus|SubscriptionStatus|null $status): string
    {
        if ($status instanceof SubscriptionStatus) {
            return match ($status) {
                SubscriptionStatus::Active => 'success',
                SubscriptionStatus::Trialing => 'info',
                SubscriptionStatus::PastDue => 'danger',
                SubscriptionStatus::Cancelled, SubscriptionStatus::Unknown => 'neutral',
            };
        }

        if ($status instanceof NormalizedStatus) {
            return match ($status) {
                NormalizedStatus::Delivered,
                NormalizedStatus::ReturnCompleted,
                NormalizedStatus::Refunded => 'success',

                NormalizedStatus::Packed,
                NormalizedStatus::Shipped,
                NormalizedStatus::OutForDelivery => 'info',

                NormalizedStatus::ReturnRequested,
                NormalizedStatus::ReturnApproved,
                NormalizedStatus::ReturnPickupScheduled,
                NormalizedStatus::ReturnPickedUp,
                NormalizedStatus::ReturnReceived,
                NormalizedStatus::RefundProcessing => 'warning',

                NormalizedStatus::Cancelled,
                NormalizedStatus::ReturnRejected => 'danger',

                default => 'neutral',
            };
        }

        return 'neutral';
    }

    /**
     * Index into DELIVERY_STEPS, or null when the order has left the
     * delivery path entirely (cancelled, returned, refunded).
     */
    public static function deliveryStep(NormalizedStatus $status): ?int
    {
        return match ($status) {
            NormalizedStatus::Placed, NormalizedStatus::Confirmed, NormalizedStatus::Processing => 0,
            NormalizedStatus::Packed => 1,
            NormalizedStatus::Shipped => 2,
            NormalizedStatus::OutForDelivery => 3,
            NormalizedStatus::Delivered => 4,
            default => null,
        };
    }
}
