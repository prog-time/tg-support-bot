<?php

namespace App\Modules\Ok\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate Odnoklassniki webhooks by the secret in the URL path:
 * `POST /api/ok/bot/{secret}`.
 *
 * The OK documentation describes no request signature, so this secret is the
 * only proof of origin. An empty configured secret therefore rejects every
 * request instead of leaving the endpoint open. A failure on our side answers
 * 502, so OK redelivers the event instead of it being treated as forbidden.
 */
class OkQuery
{
    /**
     * @param Request                                                                          $request
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $configured = (string) app(SettingsService::class)->get('ok.webhook_secret');
        } catch (\Throwable) {
            $this->safeLog('error', 'OkQuery: cannot read the webhook secret', [
                'source' => 'ok_webhook_settings_unavailable',
            ]);

            return response()->json(['message' => 'Service unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        if ($configured === '' || !hash_equals($configured, (string) $request->route('secret', ''))) {
            $this->safeLog('warning', 'OkQuery: webhook rejected', [
                'source' => 'ok_webhook_rejected',
                'secret_configured' => $configured !== '',
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Access is forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Write a log line without ever letting the logger break the request.
     *
     * @param string               $level
     * @param string               $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    private function safeLog(string $level, string $message, array $context): void
    {
        try {
            Log::channel('app')->log($level, $message, $context);
        } catch (\Throwable) {
            return;
        }
    }
}
