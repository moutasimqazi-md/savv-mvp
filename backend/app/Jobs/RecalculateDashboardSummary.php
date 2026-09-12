<?php

namespace Savv\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Savv\Enums\NormalizedStatus;
use Savv\Models\Order;

class RecalculateDashboardSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private readonly int $userId) {}

    public static function cacheKey(int $userId): string
    {
        return "dashboard-summary:{$userId}";
    }

    public function handle(): void
    {
        $orders = Order::query()->where('user_id', $this->userId);

        $summary = [
            'total_orders' => (clone $orders)->count(),
            'arriving_soon' => (clone $orders)
                ->whereNotNull('expected_delivery_at')
                ->where('expected_delivery_at', '>=', now())
                ->where('expected_delivery_at', '<=', now()->addDays(3))
                ->count(),
            'active_deliveries' => (clone $orders)->whereIn('normalized_status', [
                NormalizedStatus::Shipped->value, NormalizedStatus::OutForDelivery->value, NormalizedStatus::Packed->value,
            ])->count(),
            'active_returns' => (clone $orders)->whereIn('normalized_status', [
                NormalizedStatus::ReturnRequested->value, NormalizedStatus::ReturnApproved->value,
                NormalizedStatus::ReturnPickupScheduled->value, NormalizedStatus::ReturnPickedUp->value,
                NormalizedStatus::ReturnReceived->value,
            ])->count(),
            'pending_refunds' => (clone $orders)->where('normalized_status', NormalizedStatus::RefundProcessing->value)->count(),
            'spending_this_month_minor' => (clone $orders)
                ->whereBetween('ordered_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('total_minor'),
        ];

        Cache::put(self::cacheKey($this->userId), $summary, now()->addHours(6));
    }
}
