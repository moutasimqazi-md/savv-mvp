<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Savv\Services\AuditLogger;

class AccountController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load([
            'orders.items', 'orders.shipments.events', 'orders.returns.items',
            'orders.refunds', 'orders.invoices', 'consents', 'providerConnections',
        ]);

        AuditLogger::record('account.exported', $user->id);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'user' => $user->only(['name', 'email']),
            'orders' => $user->orders,
            'consents' => $user->consents,
            'provider_connections' => $user->providerConnections,
        ])->withHeaders([
            'Content-Disposition' => 'attachment; filename="savv-mvp-export.json"',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required']]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Incorrect password.']);
        }

        AuditLogger::record('account.deleted', $user->id);

        Auth::guard('web')->logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Your Savv MVP account has been deleted.');
    }
}
