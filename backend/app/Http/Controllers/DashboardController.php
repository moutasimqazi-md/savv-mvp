<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Savv\Enums\SubscriptionStatus;
use Savv\Jobs\RecalculateDashboardSummary;
use Savv\Models\Order;
use Savv\Models\Subscription;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $summary = Cache::get(RecalculateDashboardSummary::cacheKey($user->id));

        if ($summary === null) {
            (new RecalculateDashboardSummary($user->id))->handle();
            $summary = Cache::get(RecalculateDashboardSummary::cacheKey($user->id));
        }

        $recentOrders = Order::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->orderByDesc('ordered_at')
            ->limit(6)
            ->get();

        $upcomingRenewals = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('normalized_status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->whereNotNull('renewal_at')
            ->orderBy('renewal_at')
            ->limit(4)
            ->get();

        return view('dashboard.index', [
            'summary' => $summary,
            'recentOrders' => $recentOrders,
            'upcomingRenewals' => $upcomingRenewals,
            // The summary sums minor units across providers; label it with the
            // currency the user's most recent order actually used.
            'spendingCurrency' => $recentOrders->first()?->currency ?? 'INR',
        ]);
    }
}
