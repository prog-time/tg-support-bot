<?php

namespace App\Modules\Avito\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate incoming Avito webhooks via a secret embedded in the URL path:
 * `POST /api/avito/bot/{secret}`.
 *
 * Avito faithfully calls back the exact URL registered at subscription time, so
 * a self-chosen secret in the path is reliable authentication that does NOT
 * depend on Avito's (undocumented) request signing. `avito:set-webhook`
 * registers the URL with the secret automatically. If `avito.webhook_secret`
 * is empty, the endpoint is left open (and a warning is logged) — local testing
 * only; set a secret in production.
 *
 * Failure modes are deliberately kept apart, because conflating them loses
 * messages: a rejected secret is 403 (Avito should NOT retry), while anything
 * broken on our side — settings store unreachable, cache down — is 502 so the
 * delivery is retried instead of being written off as "forbidden". An earlier
 * catch-all returned 403 for every error, so a read-only log directory was
 * reported to Avito as an authentication failure and the message was dropped.
 */
class AvitoQuery
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $configured = (string) app(SettingsService::class)->get('avito.webhook_secret');
        } catch (\Throwable $e) {
            // Our infrastructure, not the caller's credentials.
            $this->safeLog('error', 'AvitoQuery: cannot read the webhook secret', [
                'source' => 'avito_webhook_settings_unavailable',
                'exception' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Service unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        $provided = (string) $request->route('secret', '');

        if ($configured === '') {
            $this->safeLog('warning', 'AvitoQuery: no webhook secret configured — endpoint is unauthenticated', [
                'source' => 'avito_webhook_unverified',
            ]);
        } elseif (!hash_equals($configured, $provided)) {
            $this->safeLog('warning', 'AvitoQuery: webhook secret mismatch', [
                'source' => 'avito_webhook_rejected',
                'ip' => $request->ip(),
            ]);

            // Generic body on purpose: the previous version echoed the caught
            // exception message, which leaked internal filesystem paths to the
            // caller.
            return response()->json(['message' => 'Access is forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->logRequest($request);

        return $next($request);
    }

    /**
     * Log the incoming webhook body.
     *
     * @param Request $request
     *
     * @return void
     */
    private function logRequest(Request $request): void
    {
        $this->safeLog('info', 'AvitoQuery: incoming webhook', [
            'source' => 'avito_request',
            'body' => $request->all(),
        ]);
    }

    /**
     * Write a log line without ever letting the logger break the request.
     *
     * Monolog throws when the daily log file cannot be opened (a root-owned file
     * left behind by an artisan run is enough). Diagnostics must never decide
     * whether a webhook is delivered.
     *
     * @param string               $level
     * @param string               $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('app')->log($level, $message, $context);
        } catch (\Throwable) {
            // Nothing to do — logging is best-effort by design here.
        }
    }
}
