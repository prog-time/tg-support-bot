<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;
use Symfony\Component\HttpFoundation\Response;

class TelegramQuery
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (empty($receivedToken)) {
                throw new Exception('Secret-Token is invalid!');
            }

            if ($receivedToken !== config('traffic_source.settings.telegram.secret_key')) {
                throw new Exception('Secret-Token is invalid!');
            }

            $this->sendRequestInLoki($request);
            return $next($request);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Access is forbidden',
                'error' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @param Request $request
     *
     * @return void
     */
    private function sendRequestInLoki(Request $request): void
    {
        Log::channel('loki')->info(json_encode($request->all()), ['source' => 'tg_request']);
    }
}
