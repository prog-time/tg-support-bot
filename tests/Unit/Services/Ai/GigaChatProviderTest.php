<?php

namespace Tests\Unit\Services\Ai;

use App\DTOs\Ai\AiRequestDto;
use App\Models\BotUser;
use App\Services\Ai\AiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');

        $this->provider = 'gigachat';
        $this->baseProviderUrl = config('ai.providers.gigachat.base_url');
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

        $aiService = new AiAssistantService();
        $aiResponse = $aiService->processMessage($aiRequest);

        $this->assertEquals($answerMessage, $aiResponse->response);
        $this->assertEquals($this->provider, $aiResponse->provider);
    }
}
