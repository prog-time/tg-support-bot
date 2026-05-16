<?php

namespace Tests\Unit\Modules\Ai\Services;

use App\Models\BotUser;
use App\Modules\Ai\DTOs\AiRequestDto;
use App\Modules\Ai\Services\AiAssistantService;
use App\Modules\Ai\Services\AiSystemPromptLoader;
use App\Modules\Ai\Services\GigaChatProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class GigaChatProviderTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    private string $provider;

    private string $baseProviderUrl;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        Config::set('ai.providers.gigachat.client_secret', 'test_secret');

        $loader = Mockery::mock(AiSystemPromptLoader::class);
        $loader->shouldReceive('render')->andReturn('System prompt');
        $this->app->instance(AiSystemPromptLoader::class, $loader);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');

        $this->provider = 'gigachat';
        $this->baseProviderUrl = config('ai.providers.gigachat.base_url');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_successful_process_message(): void
    {
        $managerTextMessage = 'Напиши приветствие';
        $answerMessage = 'Привет! Я здесь, чтобы помочь тебе с проектом TG Support Bot. 123';

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

        $aiRequest = new AiRequestDto(
            message: $managerTextMessage,
            userId: $this->botUser->id,
            platform: 'telegram',
            provider: $this->provider,
            forceEscalation: false
        );

        $aiService = $this->app->make(AiAssistantService::class);
        $aiResponse = $aiService->processMessage($aiRequest);

        $this->assertEquals($answerMessage, $aiResponse->response);
        $this->assertEquals($this->provider, $aiResponse->provider);
    }

    public function test_payload_messages_have_system_first_history_then_current_message(): void
    {
        Http::fake([
            'https://ngw.devices.sberbank.ru:9443/api/v2/oauth' => Http::response([
                'access_token' => 'test_access_token',
                'expires_at' => time() + 3600,
            ], 200),
            $this->baseProviderUrl . '/chat/completions' => Http::response([
                'choices' => [
                    ['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'ok'], 'finish_reason' => 'stop'],
                ],
                'usage' => ['total_tokens' => 1],
                'model' => 'GigaChat-2-Max',
            ], 200),
        ]);

        $aiRequest = new AiRequestDto(
            message: 'Текущее сообщение',
            userId: $this->botUser->id,
            platform: 'telegram',
            context: [
                ['role' => 'user', 'content' => 'Старое от пользователя'],
                ['role' => 'assistant', 'content' => 'Старый ответ'],
            ],
            provider: $this->provider,
        );

        (new GigaChatProvider())->processMessage($aiRequest);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            return count($messages) === 4
                && $messages[0]['role'] === 'system'
                && $messages[0]['content'] === 'System prompt'
                && $messages[1] === ['role' => 'user', 'content' => 'Старое от пользователя']
                && $messages[2] === ['role' => 'assistant', 'content' => 'Старый ответ']
                && $messages[3] === ['role' => 'user', 'content' => 'Текущее сообщение'];
        });
    }
}
