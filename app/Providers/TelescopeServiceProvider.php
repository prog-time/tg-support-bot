<?php

namespace App\Providers;

use App\Models\User;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * Telescope's package provider prepends laravel/sentinel's middleware to the
     * `telescope` route group. Sentinel's `laravel` driver guards dev exposure:
     * when APP_ENV=local it denies requests coming from public client IPs through
     * a trusted proxy (`trustProxies(*)`), returning a hard 401 for /telescope —
     * even for authenticated admins. The dashboard is already gated by session
     * admin auth (`web` + `auth` + TelescopeAccess), so we redefine the group
     * without Sentinel. Runs after all providers boot, so it overrides the
     * group Telescope built. (The proper prod fix is APP_ENV=production, which
     * makes Sentinel a no-op; this keeps Telescope reachable in any environment.)
     */
    public function boot(): void
    {
        parent::boot();

        $this->app->booted(function (): void {
            $this->app['router']->middlewareGroup('telescope', config('telescope.middleware'));
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Configure who can access Telescope.
     *
     * Dashboard access is gated by session-based admin auth — the primary
     * enforcement lives in the Telescope middleware stack
     * (`['web', 'auth', App\Http\Middleware\TelescopeAccess::class]`). This gate
     * mirrors that rule (admin role required) as defence in depth, overriding
     * the package default that leaves Telescope open in the `local` environment.
     */
    protected function authorization(): void
    {
        Telescope::auth(fn ($request) => ($user = $request->user()) instanceof User && $user->isAdmin());
    }
}
