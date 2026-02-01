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

class OpenAiProviderTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    private string $provider;

    private string $baseProviderUrl;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        Config::set('ai.default_provider', 'openai');
        Config::set('ai.providers.openai.api_key', 'test_123');

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');

        $this->provider = 'openai';
        $this->baseProviderUrl = config('ai.providers.openai.base_url');
    }

    public function test_successful_process_message(): void
    {
        $managerTextMessage = 'Напиши приветствие';
        $answerMessage = 'Привет! Я здесь, чтобы помочь тебе с проектом TG Support Bot.';

        Http::fake([
            $this->baseProviderUrl . '/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4o-mini',
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
                    'prompt_tokens' => 42,
                    'completion_tokens' => 18,
                    'total_tokens' => 60,
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
