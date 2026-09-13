<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Savv\Models\Subscription;

class SubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        $subscriptions = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('renewal_at')
            ->get();

        return view('subscriptions.index', ['subscriptions' => $subscriptions]);
    }
}
