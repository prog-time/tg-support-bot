<?php

namespace Tests\Unit\Services\Ai;

use App\DTOs\Ai\AiRequestDto;
use App\Models\BotUser;
use App\Services\Ai\AiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeepSeekProviderTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    private string $provider;

    private string $baseProviderUrl;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        Config::set('ai.default_provider', 'deepseek');
        Config::set('ai.providers.deepseek.client_secret', 'test_123');

        $chatId = time();
        $this->botUser = BotUser::getUserByChatId($chatId, 'telegram');

        $this->provider = 'deepseek';
        $this->baseProviderUrl = config('ai.providers.deepseek.base_url');
    }

    public function test_successful_process_message(): void
    {
        $managerTextMessage = 'Напиши приветствие';
        $answerMessage = 'Привет! Я здесь, чтобы помочь тебе с проектом TG Support Bot.';

        Http::fake([
            $this->baseProviderUrl => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'deepseek-chat',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => $answerMessage,
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 20,
                    'total_tokens' => 140,
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
