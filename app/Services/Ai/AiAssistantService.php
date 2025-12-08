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
        $this->provider = $this->getDefaultProvider();
    }

    /**
     * Обработать сообщение пользователя через AI-помощника.
     *
     * @param AiRequestDto $request DTO с данными запроса
     *
     * @return AiResponseDto|null DTO с ответом AI
     */
    public function processMessage(AiRequestDto $request): ?AiResponseDto
    {
        try {
            // Получить контекст пользователя
            $context = $this->getUserContext($request->userId, $request->platform);

            // Обновить запрос с контекстом
            $requestWithContext = new AiRequestDto(
                message: $request->message,
                userId: $request->userId,
                platform: $request->platform,
                context: $context,
                provider: $request->provider,
                maxConfidence: $request->maxConfidence,
                forceEscalation: $request->forceEscalation
            );

            // Обработать через AI-провайдер
            $response = $this->provider->processMessage($requestWithContext);

            // Обновить контекст пользователя
            $this->updateUserContext($request->userId, $request->platform, $request->message, $response);

            return $response;
        } catch (\Exception $e) {
            (new LokiLogger())->log('ai_error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Инициализировать доступных AI-провайдеров.
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
     * Получить провайдера по умолчанию.
     *
     * @return AiProviderInterface
     *
     * @throws \Exception
     */
    private function getDefaultProvider(): AiProviderInterface
    {
        $defaultProvider = config('ai.default_provider', 'openai');

        if (isset($this->providers[$defaultProvider]) && $this->providers[$defaultProvider]->isAvailable()) {
            return $this->providers[$defaultProvider];
        }

        // Резервный вариант - первый доступный провайдер
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        throw new \Exception('No AI providers available');
    }

    /**
     * Получить контекст беседы пользователя.
     *
     * @param int    $userId   ID пользователя
     * @param string $platform Платформа
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
     * Обновить контекст беседы пользователя.
     *
     * @param int           $userId      ID пользователя
     * @param string        $platform    Платформа
     * @param string        $userMessage Сообщение пользователя
     * @param AiResponseDto $response    Ответ AI
     */
    private function updateUserContext(int $userId, string $platform, string $userMessage, AiResponseDto $response): void
    {
        $cacheKey = "ai_context_{$platform}_{$userId}";
        $context = Cache::get($cacheKey, []);

        // Добавить сообщение пользователя
        $context[] = [
            'role' => 'user',
            'content' => $userMessage,
            'timestamp' => now()->timestamp,
        ];

        // Добавить ответ AI
        $context[] = [
            'role' => 'assistant',
            'content' => $response->response,
            'timestamp' => now()->timestamp,
        ];

        // Оставить только последние сообщения
        $maxContext = config('ai.max_context_messages', 10);
        $context = array_slice($context, -$maxContext);

        Cache::put($cacheKey, $context, now()->addHours(24));
    }
}
