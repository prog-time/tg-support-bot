<?php

namespace Tests\Unit\Modules\Ai\Jobs;

use App\Models\BotUser;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\AiBotApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendAiReplyJobTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    private string $aiToken;

    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiToken = 'ai_test_token_123';
        $this->groupId = -100987654321;

        config([
            'traffic_source.settings.telegram_ai.token' => $this->aiToken,
            'traffic_source.settings.telegram.group_id' => $this->groupId,
        ]);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 55;
        $this->botUser->save();
    }

    public function test_sends_reply_using_ai_bot_token(): void
    {
        $replyText = 'Auto-reply from AI bot';

        Http::fake([
            'https://api.telegram.org/bot' . $this->aiToken . '/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 200,
                    'chat' => ['id' => $this->groupId, 'type' => 'supergroup'],
                    'date' => time(),
                    'text' => $replyText,
                ],
            ], 200),
        ]);

        $updateDto = TelegramUpdateDtoMock::getDto();
        $job = new SendAiReplyJob($this->botUser->id, $updateDto, $replyText);

        $aiBotApi = new AiBotApi();
        $job->handle($aiBotApi);

        Http::assertSent(function ($request) use ($replyText) {
            $body = json_decode($request->body(), true);

            return str_contains($request->url(), $this->aiToken . '/sendMessage')
                && $body['chat_id'] === $this->groupId
                && $body['message_thread_id'] === $this->botUser->topic_id
                && $body['text'] === $replyText;
        });
    }

    public function test_does_not_throw_when_telegram_returns_error(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request',
            ], 400),
        ]);

        $updateDto = TelegramUpdateDtoMock::getDto();
        $job = new SendAiReplyJob($this->botUser->id, $updateDto, 'some reply');

        $aiBotApi = new AiBotApi();

        // Should not throw — errors are logged and swallowed
        $job->handle($aiBotApi);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), $this->aiToken . '/sendMessage');
        });
    }
}
