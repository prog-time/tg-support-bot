<?php

namespace App\Middleware;

use App\Logging\LokiLogger;
use App\Models\ExternalSourceAccessTokens;
use Closure;
use Illuminate\Http\Request;
use Mockery\Exception;
use Symfony\Component\HttpFoundation\Response;

class ApiQuery
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();
            if (empty($token)) {
                throw new Exception('Bearer Token не найден!');
            }

            $itemAccessToken = (new ExternalSourceAccessTokens())->where('token', $token)
                ->first()
                ?->external_source()
                ->first();

            if (!$itemAccessToken) {
                throw new Exception('Bearer Token указан неверно!');
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
        $logger->log('api_request', $dataRequest);
    }
}
