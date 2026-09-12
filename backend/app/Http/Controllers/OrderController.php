<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Savv\Models\Order;
use Savv\Services\AuditLogger;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = Order::query()->where('user_id', $request->user()->id)->with('items');

        if ($provider = $request->string('provider')->toString()) {
            $query->where('provider', $provider);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('normalized_status', $status);
        }

        if ($from = $request->date('from')) {
            $query->where('ordered_at', '>=', $from);
        }

        if ($to = $request->date('to')) {
            $query->where('ordered_at', '<=', $to);
        }

        if ($minAmount = $request->input('min_amount')) {
            $query->where('total_minor', '>=', (int) round(((float) $minAmount) * 100));
        }

        if ($maxAmount = $request->input('max_amount')) {
            $query->where('total_minor', '<=', (int) round(((float) $maxAmount) * 100));
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('provider_order_id', 'like', "%{$search}%")
                    ->orWhereHas('items', fn ($i) => $i->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('shipments', fn ($s) => $s->where('tracking_number', 'like', "%{$search}%"));
            });
        }

        $sort = $request->string('sort')->toString() ?: 'newest';
        $query->orderBy(...match ($sort) {
            'oldest' => ['ordered_at', 'asc'],
            'expected_delivery' => ['expected_delivery_at', 'asc'],
            'highest_total' => ['total_minor', 'desc'],
            'lowest_total' => ['total_minor', 'asc'],
            'recently_updated' => ['updated_at', 'desc'],
            default => ['ordered_at', 'desc'],
        });

        $orders = $query->paginate(20)->withQueryString();

        return view('orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['items', 'shipments.events', 'returns.items', 'refunds', 'invoices', 'lastImportBatch']);

        return view('orders.show', ['order' => $order]);
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        $order->delete();

        AuditLogger::record('order.deleted', $request->user()->id, Order::class, $order->id, [], $request->ip());

        return redirect()->route('orders.index')->with('status', 'Order deleted.');
    }
}
