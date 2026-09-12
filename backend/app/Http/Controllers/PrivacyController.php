<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Savv\Models\Consent;
use Savv\Models\ProviderConnection;

class PrivacyController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('privacy.index', [
            'consents' => Consent::query()->where('user_id', $user->id)->orderByDesc('accepted_at')->get(),
            'connections' => ProviderConnection::query()->where('user_id', $user->id)->get(),
            'retentionDays' => $user->retentionDays(),
        ]);
    }
}
