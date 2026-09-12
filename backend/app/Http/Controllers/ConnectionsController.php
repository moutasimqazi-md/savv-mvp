<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Savv\Enums\Provider;
use Savv\Models\Consent;
use Savv\Models\ImportSession;
use Savv\Models\ProviderConnection;
use Savv\Services\AuditLogger;
use Savv\Services\ImportSessionService;

class ConnectionsController extends Controller
{
    public const CONSENT_VERSION = '2026-09-v1';

    public function index(Request $request): View
    {
        $user = $request->user();

        $connections = ProviderConnection::query()->where('user_id', $user->id)->get()->keyBy('provider');
        $activeImportSession = ImportSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', array_map(fn ($s) => $s->value, \Savv\Enums\ImportSessionStatus::activeStatuses()))
            ->first();

        return view('connections.index', [
            'connections' => $connections,
            'activeImportSession' => $activeImportSession,
            'providers' => Provider::cases(),
        ]);
    }

    public function start(Request $request, string $provider, ImportSessionService $importSessionService): RedirectResponse
    {
        $providerEnum = Provider::tryFrom($provider);

        if (! $providerEnum) {
            abort(404);
        }

        $request->validate([
            'accept_consent' => ['accepted'],
        ], [
            'accept_consent.accepted' => 'You must accept the consent notice to continue.',
        ]);

        $user = $request->user();

        $consent = Consent::create([
            'user_id' => $user->id,
            'provider' => $providerEnum,
            'consent_version' => self::CONSENT_VERSION,
            'accepted_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        AuditLogger::record('consent.accepted', $user->id, Consent::class, $consent->id, [
            'provider' => $providerEnum->value,
        ], $request->ip());

        try {
            $session = $importSessionService->start($user, $providerEnum, $consent);
        } catch (\Throwable $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }

        return redirect()->route('imports.show', $session);
    }
}
