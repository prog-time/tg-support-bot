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
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $secretCode = config('traffic_source.settings.vk.secret_key');
            if ($secretCode !== request()->secret) {
                throw new Exception('Secret-Key is invalid!');
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
        $dataRequest = json_encode($request->all());

        Log::channel('loki')->info($dataRequest, ['source' => 'vk_request']);
    }
}
