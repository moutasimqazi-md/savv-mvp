<?php

namespace Savv\Enums;

enum NormalizedStatus: string
{
    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case ReturnRequested = 'return_requested';
    case ReturnApproved = 'return_approved';
    case ReturnPickupScheduled = 'return_pickup_scheduled';
    case ReturnPickedUp = 'return_picked_up';
    case ReturnReceived = 'return_received';
    case ReturnCompleted = 'return_completed';
    case ReturnRejected = 'return_rejected';
    case RefundProcessing = 'refund_processing';
    case Refunded = 'refunded';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::OutForDelivery => 'Out for delivery',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }
}
