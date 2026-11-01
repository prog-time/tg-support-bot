<?php

namespace Tests\Unit\Modules\Telegram\Jobs;

use App\Models\BotUser;
use App\Modules\Ai\DTOs\AiResponseDto;
use App\Modules\Ai\Services\AiAssistantService;
use App\Modules\Telegram\Jobs\SendAiTelegramMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendAiTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_labelled_plain_draft_with_the_main_bot(): void
    {
        Queue::fake();
        app(SettingsService::class)->set('telegram.token', 'main_bot_token');

        $botUser = BotUser::getUserByChatId(time(), 'telegram');
        $botUser->update(['topic_id' => 77]);

        Http::fake(['*/sendMessage' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 321, 'message_thread_id' => 77, 'text' => 'draft'],
        ])]);

        $aiService = $this->createMock(AiAssistantService::class);
        $aiService->method('processMessage')->willReturn(new AiResponseDto(
            response: 'answer',
            confidenceScore: 0.9,
            shouldEscalate: false,
            provider: 'openai',
            modelUsed: 'gpt-4',
            tokensUsed: 10,
            responseTime: 0.5,
        ));

        $update = TelegramUpdateDtoMock::getDto();
        $update->text = '/ai_generate напиши приветствие';

        (new SendAiTelegramMessageJob($botUser->id, $update, 'напиши приветствие', '<b>Привет</b>'))
            ->handle(null, $aiService);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'botmain_bot_token/sendMessage')
            && $request['text'] === "📄 Инструкция: \nнапиши приветствие  \n\n🤖 AI:\nПривет"
            && !isset($request['parse_mode']));

        Queue::assertPushed(SendTelegramMessageJob::class, fn ($job) => $job->queryParams->token === null
            && $job->queryParams->methodQuery === 'editMessageText');
    }
}
