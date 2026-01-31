<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\Ai\AiProviderInterface;
use App\DTOs\Ai\AiRequestDto;
use App\DTOs\Ai\AiResponseDto;
use App\Logging\LokiLogger;
use Illuminate\Support\Facades\Cache;

class AiAssistantService
{
    private AiProviderInterface $provider;

    private array $providers = [];

    public function __construct()
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

            $context = $this->getUserContext($request->userId, $request->platform);

            $requestWithContext = new AiRequestDto(
                message: $request->message,
                userId: $request->userId,
                platform: $request->platform,
                context: $context,
                provider: $request->provider,
                maxConfidence: $request->maxConfidence,
                forceEscalation: $request->forceEscalation
            );

            $response = $this->provider->processMessage($requestWithContext);

            $this->updateUserContext($request->userId, $request->platform, $request->message, $response);

            return $response;
        } catch (\Throwable $e) {
            (new LokiLogger())->log('ai_error', [
                'error' => $e->getMessage(),
            ]);

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

    /**
     * Get user conversation context.
     *
     * @param int    $userId   User ID
     * @param string $platform Platform
     *
     * @return array
     */
    private function getUserContext(int $userId, string $platform): array
    {
        $cacheKey = "ai_context_{$platform}_{$userId}";
        $context = Cache::get($cacheKey, []);

        $maxContext = config('ai.max_context_messages', 10);
        return array_slice($context, -$maxContext);
    }

    /**
     * Update user conversation context.
     *
     * @param int           $userId      User ID
     * @param string        $platform    Platform
     * @param string        $userMessage User message
     * @param AiResponseDto $response    AI response
     */
    private function updateUserContext(int $userId, string $platform, string $userMessage, AiResponseDto $response): void
    {
        $cacheKey = "ai_context_{$platform}_{$userId}";
        $context = Cache::get($cacheKey, []);

        $context[] = [
            'role' => 'user',
            'content' => $userMessage,
            'timestamp' => now()->timestamp,
        ];

        $context[] = [
            'role' => 'assistant',
            'content' => $response->response,
            'timestamp' => now()->timestamp,
        ];

        $maxContext = config('ai.max_context_messages', 10);
        $context = array_slice($context, -$maxContext);

        Cache::put($cacheKey, $context, now()->addHours(24));
    }
}
