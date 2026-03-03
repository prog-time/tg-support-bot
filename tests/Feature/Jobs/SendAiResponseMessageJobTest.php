<?php

namespace Tests\Feature\Jobs;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendAiResponseMessageJob;
use App\Modules\Telegram\Jobs\SendAiTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendAiResponseMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    private string $baseProviderUrl;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $chatId = time();
        $this->botUser = BotUser::getUserByChatId($chatId, 'telegram');

        $this->baseProviderUrl = config('ai.providers.gigachat.base_url');
    }

    public function test_success_send_creates_message_record(): void
    {
        $managerTextMessage = 'Напиши приветствие';
        $answerMessage = 'Привет! Я здесь, чтобы помочь тебе с проектом TG Support Bot. 123';

        $dtoParams = TelegramUpdateDtoMock::getDtoParams();

        $dtoParams['message']['text'] = $managerTextMessage;
        $dto = TelegramUpdateDtoMock::getDto($dtoParams);

        Http::fake([
            'https://ngw.devices.sberbank.ru:9443/api/v2/oauth' => Http::response([
                'access_token' => 'test_access_token',
                'expires_at' => time() + 3600,
            ], 200),
            $this->baseProviderUrl . '/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $answerMessage,
                            'role' => 'assistant',
                        ],
                        'index' => 0,
                        'finish_reason' => 'stop',
                    ],
                ],
                'created' => time(),
                'model' => 'GigaChat-2-Max:2.0.28.2',
                'object' => 'chat.completion',
                'usage' => [
                    'prompt_tokens' => 1303,
                    'completion_tokens' => 16,
                    'total_tokens' => 1319,
                    'precached_prompt_tokens' => 1,
                ],
            ], 200),
        ]);

        $params = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $this->botUser->chat_id,
            'text' => $managerTextMessage,
        ]);

        $job = new SendAiResponseMessageJob(
            $this->botUser->id,
            $dto,
            $params,
        );
        $job->handle();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendAiTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $jobData = $pushed[0]['job'];
        $this->assertEquals($managerTextMessage, $jobData->managerTextMessage);
        $this->assertEquals($answerMessage, $jobData->aiTextMessage);
    }
}
