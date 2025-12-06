<?php

namespace Tests\Feature\Jobs;

use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendAiResponseMessageJob;
use App\Jobs\SendMessage\SendAiTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendAiResponseMessageJobTest extends TestCase
{
    private ?BotUser $botUser;

    private string $baseProviderUrl;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();

        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'telegram');

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
