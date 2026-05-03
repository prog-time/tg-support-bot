<?php

namespace Tests\Unit\Modules\Ai\Middleware;

use App\Modules\Ai\Middleware\AiBotQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class AiBotQueryTest extends TestCase
{
    private AiBotQuery $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new AiBotQuery();
    }

    public function test_valid_secret_passes_request(): void
    {
        $secret = 'valid_secret_token';
        config(['traffic_source.settings.telegram_ai.secret' => $secret]);

        $request = Request::create('/api/ai-bot/webhook', 'POST');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', $secret);

        $called = false;
        $next = function ($req) use (&$called) {
            $called = true;

            return response()->noContent();
        };

        $this->middleware->handle($request, $next);

        $this->assertTrue($called, 'Next middleware should be called with a valid secret token');
    }

    public function test_missing_secret_returns_403(): void
    {
        config(['traffic_source.settings.telegram_ai.secret' => 'expected_secret']);

        $request = Request::create('/api/ai-bot/webhook', 'POST');
        // No X-Telegram-Bot-Api-Secret-Token header

        $next = function ($req) {
            return response()->noContent();
        };

        /** @var \Illuminate\Http\JsonResponse $response */
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function test_invalid_secret_returns_403(): void
    {
        config(['traffic_source.settings.telegram_ai.secret' => 'correct_secret']);

        $request = Request::create('/api/ai-bot/webhook', 'POST');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'wrong_secret');

        $next = function ($req) {
            return response()->noContent();
        };

        /** @var \Illuminate\Http\JsonResponse $response */
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
}
