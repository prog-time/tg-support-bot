<?php

namespace App\Middleware;

use App\Logging\LokiLogger;
use Closure;
use Illuminate\Http\Request;
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
                throw new Exception('Secret-Key указан неверно!');
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

    /**
     * @param Request $request
     *
     * @return void
     */
    private function sendRequestInLoki(Request $request): void
    {
        $dataRequest = json_encode($request->all());

        $logger = new LokiLogger();
        $logger->log('vk_request', $dataRequest);
    }
}
