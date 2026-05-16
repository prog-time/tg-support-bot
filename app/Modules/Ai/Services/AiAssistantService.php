<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Contracts\AiProviderInterface;
use App\Modules\Ai\DTOs\AiRequestDto;
use App\Modules\Ai\DTOs\AiResponseDto;
use Illuminate\Support\Facades\Log;

class AiAssistantService
{
    private AiProviderInterface $provider;

    private array $providers = [];

    public function __construct(private readonly AiChatHistoryService $historyService)
    {
        $this->initializeProviders();
    }

    /**
     * Process user message through AI assistant.
     *
     * @param AiRequestDto $request Request DTO
     *
     * @return AiResponseDto|null AI response DTO
     */
    public function processMessage(AiRequestDto $request): ?AiResponseDto
    {
        try {
            $this->provider = $this->getDefaultProvider($request->provider);

            $context = $this->historyService->buildForBotUser($request->userId, $request->message);

            $requestWithContext = new AiRequestDto(
                message: $request->message,
                userId: $request->userId,
                platform: $request->platform,
                context: $context,
                provider: $request->provider,
                maxConfidence: $request->maxConfidence,
                forceEscalation: $request->forceEscalation
            );

            return $this->provider->processMessage($requestWithContext);
        } catch (\Throwable $e) {
            Log::channel('loki')->error($e->getMessage(), ['source' => 'ai_error']);

            return null;
        }
    }

    /**
     * Generate a direct reply text for auto-reply mode (AI_AUTO_REPLY=true).
     *
     * Uses the same provider selection and context window logic as processMessage().
     * The reply text is returned as a plain string so the caller can dispatch
     * a send job without creating an AiMessage draft record.
     *
     * @param int    $userId      Bot user ID (used for context lookup)
     * @param string $platform    Platform identifier (e.g. 'telegram')
     * @param string $userMessage Incoming user message text
     *
     * @return string|null Generated reply text, or null on failure
     */
    public function generateReply(int $userId, string $platform, string $userMessage): ?string
    {
        try {
            $this->provider = $this->getDefaultProvider(null);

            $context = $this->historyService->buildForBotUser($userId, $userMessage);

            $request = new AiRequestDto(
                message: $userMessage,
                userId: $userId,
                platform: $platform,
                context: $context,
                provider: config('ai.default_provider', 'openai'),
                forceEscalation: false
            );

            $response = $this->provider->processMessage($request);

            if ($response === null) {
                return null;
            }

            return $response->response;
        } catch (\Throwable $e) {
            Log::channel('loki')->error($e->getMessage(), ['source' => 'ai_generate_reply_error']);

            return null;
        }
    }

    /**
     * Initialize available AI providers.
     *
     * @return void
     */
    private function initializeProviders(): void
    {
        $this->providers['openai'] = new OpenAiProvider();
        $this->providers['deepseek'] = new DeepSeekProvider();
        $this->providers['gigachat'] = new GigaChatProvider();
    }

    /**
     * Get default provider.
     *
     * @param string|null $nameProvider
     *
     * @return AiProviderInterface
     *
     * @throws \Exception
     */
    private function getDefaultProvider(string|null $nameProvider = null): AiProviderInterface
    {
        $defaultProvider = $nameProvider ?? config('ai.default_provider', 'openai');

        if (isset($this->providers[$defaultProvider]) && $this->providers[$defaultProvider]->isAvailable()) {
            return $this->providers[$defaultProvider];
        }

        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        throw new \Exception('No AI providers available');
    }
}
