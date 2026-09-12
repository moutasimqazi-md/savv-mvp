<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Savv\Models\OrderReturn;

class ReturnsController extends Controller
{
    public function index(Request $request): View
    {
        $returns = OrderReturn::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('order')
            ->orderByDesc('requested_at')
            ->paginate(20);

        return view('returns.index', ['returns' => $returns]);
    }
}
