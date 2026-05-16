<?php

namespace Tests\Unit\Modules\Ai\Services;

use App\Modules\Ai\Services\AiBotApi;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiBotApiTest extends TestCase
{
    public function test_send_uses_ai_bot_token(): void
    {
        config(['traffic_source.settings.telegram_ai.token' => 'AI_BOT_TOKEN_FAKE']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ]),
        ]);

        $result = (new AiBotApi())->send('sendMessage', ['chat_id' => 1, 'text' => 'hi']);

        $this->assertTrue($result->ok);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/botAI_BOT_TOKEN_FAKE/');
        });
    }

    public function test_send_returns_answer_dto_on_failure(): void
    {
        config(['traffic_source.settings.telegram_ai.token' => 'AI_BOT_TOKEN_FAKE']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request',
            ], 400),
        ]);

        $result = (new AiBotApi())->send('sendMessage', ['chat_id' => 1, 'text' => 'hi']);

        $this->assertFalse($result->ok);
    }
}
