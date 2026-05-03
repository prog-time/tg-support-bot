<?php

namespace App\Modules\Ai\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AiBotQuery
{
    /**
     * Handle an incoming request.
     *
     * Validates the X-Telegram-Bot-Api-Secret-Token header against
     * the TELEGRAM_AI_BOT_SECRET configuration value.
     *
     * @param Request                                                                          $request
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (empty($receivedToken)) {
                throw new \RuntimeException('Secret-Token is missing!');
            }

            if ($receivedToken !== config('traffic_source.settings.telegram_ai.secret')) {
                throw new \RuntimeException('Secret-Token is invalid!');
            }

            Log::channel('loki')->info(json_encode($request->all()), ['source' => 'ai_bot_request']);

            return $next($request);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Access is forbidden',
            ], Response::HTTP_FORBIDDEN);
        }
    }
}
