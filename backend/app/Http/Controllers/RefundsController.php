<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Savv\Models\Refund;

class RefundsController extends Controller
{
    public function index(Request $request): View
    {
        $refunds = Refund::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('order')
            ->orderByDesc('initiated_at')
            ->paginate(20);

        return view('refunds.index', ['refunds' => $refunds]);
    }
}
