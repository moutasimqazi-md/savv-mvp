<?php

namespace Savv\Tests\Unit\Services;

use Savv\Enums\NormalizedStatus;
use Savv\Services\StatusNormalizer;
use Savv\Tests\TestCase;

class StatusNormalizerTest extends TestCase
{
    /** @dataProvider statusProvider */
    public function test_normalizes_provider_wording(string $original, NormalizedStatus $expected): void
    {
        $this->assertSame($expected, StatusNormalizer::normalize($original));
    }

    public static function statusProvider(): array
    {
        return [
            ['Order Placed', NormalizedStatus::Placed],
            ['Order confirmed', NormalizedStatus::Confirmed],
            ['Preparing your order', NormalizedStatus::Processing],
            ['Ready to ship', NormalizedStatus::Packed],
            ['Shipped', NormalizedStatus::Shipped],
            ['Out for delivery', NormalizedStatus::OutForDelivery],
            ['Delivered', NormalizedStatus::Delivered],
            ['Order cancelled', NormalizedStatus::Cancelled],
            ['Return requested', NormalizedStatus::ReturnRequested],
            ['Return approved', NormalizedStatus::ReturnApproved],
            ['Pickup scheduled', NormalizedStatus::ReturnPickupScheduled],
            ['Return picked up', NormalizedStatus::ReturnPickedUp],
            ['Return received', NormalizedStatus::ReturnReceived],
            ['Return completed', NormalizedStatus::ReturnCompleted],
            ['Return rejected', NormalizedStatus::ReturnRejected],
            ['Refund initiated', NormalizedStatus::RefundProcessing],
            ['Refund completed', NormalizedStatus::Refunded],
            ['Some brand new wording nobody has seen', NormalizedStatus::Unknown],
            ['', NormalizedStatus::Unknown],
        ];
    }

    public function test_null_status_is_unknown(): void
    {
        $this->assertSame(NormalizedStatus::Unknown, StatusNormalizer::normalize(null));
    }

    public function test_more_specific_phrases_win_over_generic_ones(): void
    {
        // Contains both "return" and "rejected" - must match the specific
        // "return rejected" rule, not the generic "return requested" one.
        $this->assertSame(NormalizedStatus::ReturnRejected, StatusNormalizer::normalize('Your return was rejected'));
    }
}
