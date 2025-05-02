<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;
use Symfony\Component\HttpFoundation\Response;

class VkQuery
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (!empty(env('VK_SECRET_CODE'))) {
                $secretCode = env('VK_SECRET_CODE') ?? '';
                if ($secretCode !== request()->secret) {
                    throw new Exception('Secret-Key указан неверно!');
                }
            }

            Log::debug(request()->getContent());

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Access is forbidden',
                'error' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }
}
