<?php

namespace Tests\Feature\Jobs;

use App\DTOs\Ai\AiRequestDto;
use App\Jobs\SendMessage\SendAiTelegramMessageJob;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\Ai\AiAssistantService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendAiTelegramMessageJobTest extends TestCase
{
    private ?BotUser $botUser;

    private string $baseProviderUrl;

    private string $provider;

    private string $telegramAiToken;

    private int $groupId;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();

        Queue::fake();

        $this->groupId = time();
        config(['traffic_source.settings.telegram.group_id' => $this->groupId]);

        $this->telegramAiToken = 'test_ai_token';
        config(['traffic_source.settings.telegram_ai.token' => $this->telegramAiToken]);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');

        $this->provider = 'gigachat';
        $this->baseProviderUrl = config('ai.providers.gigachat.base_url');
    }

    public function test_success_send_creates_message_record(): void
    {
        $managerTextMessage = 'Напиши приветствие';
        $answerMessage = 'Привет! Я здесь, чтобы помочь тебе с проектом TG Support Bot. 123';

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => time(),
                    'from' => [
                        'id' => time(),
                        'is_bot' => true,
                        'first_name' => 'Prog-Time |Администратор сайта',
                        'username' => 'prog_time_bot',
                    ],
                    'chat' => [
                        'id' => time(),
                        'first_name' => 'Test',
                        'last_name' => 'Testov',
                        'username' => 'usertest',
                        'type' => 'private',
                    ],
                    'date' => time(),
                    'text' => $managerTextMessage,
                ],
            ]),
        ]);

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
            ]),
        ]);

        $dtoParams = TelegramUpdateDtoMock::getDtoParams();
        $dtoParams['message']['text'] = $managerTextMessage;
        $dto = TelegramUpdateDtoMock::getDto($dtoParams);

        $aiRequest = new AiRequestDto(
            message: $managerTextMessage,
            userId: $this->botUser->id,
            platform: 'telegram',
            provider: $this->provider,
            forceEscalation: false
        );

        $aiService = new AiAssistantService();
        $aiResponse = $aiService->processMessage($aiRequest);

        $job = new SendAiTelegramMessageJob(
            $this->botUser->id,
            $dto,
            $managerTextMessage,
            $aiResponse->response
        );
        $job->handle();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(2, $pushed);

        $jobData = $pushed[0]['job'];
        $this->assertEquals($this->botUser->id, $jobData->botUserId);
        $this->assertEquals('editMessageText', $jobData->queryParams->methodQuery);
        $this->assertEquals($this->telegramAiToken, $jobData->queryParams->token);
        $this->assertEquals($this->groupId, $jobData->queryParams->chat_id);

        $jobData = $pushed[1]['job'];
        $this->assertEquals($this->botUser->id, $jobData->botUserId);
        $this->assertEquals('deleteMessage', $jobData->queryParams->methodQuery);
        $this->assertEquals($this->botUser->chat_id, $jobData->queryParams->chat_id);
    }
}
