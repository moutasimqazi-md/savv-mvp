<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Savv\Jobs\RecalculateDashboardSummary;
use Savv\Models\Order;

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
            ->limit(10)
            ->get();

        return view('dashboard.index', [
            'summary' => $summary,
            'recentOrders' => $recentOrders,
        ]);
    }
}
