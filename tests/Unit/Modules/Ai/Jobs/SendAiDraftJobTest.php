<?php

namespace Tests\Unit\Modules\Ai\Jobs;

use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Ai\DTOs\AiResponseDto;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Services\AiAssistantService;
use App\Modules\Ai\Services\AiBotApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendAiDraftJobTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    private string $aiToken;

    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiToken = 'ai_test_token_456';
        $this->groupId = -100987654321;

        config([
            'traffic_source.settings.telegram_ai.token' => $this->aiToken,
            'traffic_source.settings.telegram.group_id' => $this->groupId,
            'ai.default_provider' => 'openai',
        ]);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 77;
        $this->botUser->save();
    }

    public function test_posts_draft_and_persists_ai_message(): void
    {
        $aiResponseText = 'Black tea is fine for the demo';
        $aiResponse = new AiResponseDto(
            response: $aiResponseText,
            confidenceScore: 0.9,
            shouldEscalate: false,
            provider: 'openai',
            modelUsed: 'gpt-4',
            tokensUsed: 10,
            responseTime: 0.5,
        );

        $aiService = $this->createMock(AiAssistantService::class);
        $aiService->method('processMessage')->willReturn($aiResponse);

        Http::fake([
            'https://api.telegram.org/bot' . $this->aiToken . '/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 321,
                    'chat' => ['id' => $this->groupId, 'type' => 'supergroup'],
                    'date' => time(),
                    'text' => $aiResponseText,
                ],
            ], 200),
            'https://api.telegram.org/bot' . $this->aiToken . '/editMessageReplyMarkup' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        $updateDto = TelegramUpdateDtoMock::getDto();
        $job = new SendAiDraftJob($this->botUser->id, $updateDto, 'user question');

        $job->handle(new AiBotApi(), $aiService);

        $this->assertDatabaseHas('ai_messages', [
            'bot_user_id' => $this->botUser->id,
            'message_id' => 321,
            'text_ai' => $aiResponseText,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), $this->aiToken . '/sendMessage');
        });
    }

    public function test_does_not_throw_when_ai_returns_null(): void
    {
        $aiService = $this->createMock(AiAssistantService::class);
        $aiService->method('processMessage')->willReturn(null);

        $updateDto = TelegramUpdateDtoMock::getDto();
        $job = new SendAiDraftJob($this->botUser->id, $updateDto, 'user question');

        $job->handle(new AiBotApi(), $aiService);

        $this->assertSame(0, AiMessage::count());
    }
}
