<?php

namespace Savv\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Savv\Http\Controllers\Controller;
use Savv\Models\ImportSession;
use Savv\Services\AuditLogger;
use Savv\Services\ImportSessionService;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, ImportSessionService $importSessionService): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            $activeSession = ImportSession::query()
                ->where('user_id', $user->id)
                ->whereIn('status', array_map(fn ($s) => $s->value, \Savv\Enums\ImportSessionStatus::activeStatuses()))
                ->first();

            if ($activeSession) {
                $importSessionService->cancel($activeSession);
            }

            AuditLogger::record('auth.logout', $user->id);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
