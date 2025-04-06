<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mockery\Exception;
use Symfony\Component\HttpFoundation\Response;

class TelegramQuery
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (!empty(env('TELEGRAM_SECRET_KEY'))) {
                if (empty($receivedToken)) {
                    throw new Exception('Secret-Token указан неверно!');
                }

                if ($receivedToken !== env('TELEGRAM_SECRET_KEY')) {
                    throw new Exception('Secret-Token указан неверно!');
                }
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Access is forbidden',
                'error' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }
}
