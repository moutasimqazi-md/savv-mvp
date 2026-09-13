<?php

namespace Savv\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Savv\Models\ImportBatch;
use Savv\Models\ImportSession;
use Savv\Models\Order;
use Savv\Models\OrderReturn;
use Savv\Models\Refund;
use Savv\Policies\ImportBatchPolicy;
use Savv\Policies\ImportSessionPolicy;
use Savv\Policies\OrderPolicy;
use Savv\Policies\OrderReturnPolicy;
use Savv\Policies\RefundPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Model::shouldBeStrict(! $this->app->isProduction());

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderReturn::class, OrderReturnPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(ImportBatch::class, ImportBatchPolicy::class);
        Gate::policy(ImportSession::class, ImportSessionPolicy::class);

        // Login throttling is handled inside AuthenticatedSessionController
        // (keyed by email+IP, returning a form validation error) rather than
        // a throttle:* middleware, so a locked-out user sees a normal error
        // instead of a bare 429 response.

        // Starting an isolated Chromium process is expensive; keep this tight.
        RateLimiter::for('import-session-start', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('import-session-action', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
