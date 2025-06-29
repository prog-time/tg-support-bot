<?php

namespace App\Middleware;

use App\Logging\LokiLogger;
use Closure;
use Illuminate\Http\Request;
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
                throw new Exception('Secret-Token указан неверно!');
            }

            if ($receivedToken !== config('traffic_source.settings.telegram.secret_key')) {
                throw new Exception('Secret-Token указан неверно!');
            }

            $this->sendRequestInLoki($request);
            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Access is forbidden',
                'error' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    private function sendRequestInLoki(Request $request): void
    {
        $logger = new LokiLogger();
        $logger->log('tg_request', json_encode($request->all()));
    }
}
