<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\Ai\AiProviderInterface;
use App\DTOs\Ai\AiResponseDto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

abstract class BaseAiProvider implements AiProviderInterface
{
    protected string $providerName;
    protected string $modelName;
    protected string $apiKey;
    protected string $baseUrl;
    protected array $config;

    public function __construct(string $providerName)
    {
        $this->providerName = $providerName;
        $this->config = config("ai.providers.{$providerName}", []);
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->baseUrl = $this->config['base_url'] ?? '';
        $this->modelName = $this->config['model'] ?? '';
    }

    /**
     * Проверить, доступен ли провайдер и правильно настроен.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }

    /**
     * Получить название провайдера.
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return $this->providerName;
    }

    /**
     * Получить название используемой модели.
     *
     * @return string
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * Получить текущий статус rate limiting.
     *
     * @return array
     */
    public function getRateLimitStatus(): array
    {
        $cacheKey = "ai_rate_limit_{$this->providerName}";
        $requests = Cache::get($cacheKey, 0);
        $limit = config("ai.rate_limit.requests_per_minute", 60);

        return [
            'current_requests' => $requests,
            'limit' => $limit,
            'remaining' => max(0, $limit - $requests),
            'reset_time' => now()->addMinute()->timestamp,
        ];
    }

    /**
     * Проверить rate limit для текущего провайдера.
     *
     * @return bool
     */
    protected function checkRateLimit(): bool
    {
        $cacheKey = "ai_rate_limit_{$this->providerName}";
        $requests = Cache::get($cacheKey, 0);
        $limit = config("ai.rate_limit.requests_per_minute", 60);

        if ($requests >= $limit) {
            return false;
        }

        Cache::put($cacheKey, $requests + 1, now()->addMinute());
        return true;
    }

    /**
     * Создать DTO ответа AI.
     *
     * @param string $response Текст ответа
     * @param float $confidenceScore Показатель уверенности
     * @param bool $shouldEscalate Нужно ли эскалировать
     * @param int $tokensUsed Количество токенов
     * @param float $responseTime Время ответа
     * @param array $metadata Метаданные
     * @return AiResponseDto
     */
    protected function createResponse(
        string $response,
        float $confidenceScore,
        bool $shouldEscalate,
        int $tokensUsed,
        float $responseTime,
        array $metadata = []
    ): AiResponseDto {
        return new AiResponseDto(
            response: $response,
            confidenceScore: $confidenceScore,
            shouldEscalate: $shouldEscalate,
            provider: $this->providerName,
            modelUsed: $this->modelName,
            tokensUsed: $tokensUsed,
            responseTime: $responseTime,
            metadata: $metadata
        );
    }

    /**
     * Определить, нужно ли эскалировать запрос.
     *
     * @param float $confidenceScore Показатель уверенности
     * @return bool
     */
    protected function shouldEscalate(float $confidenceScore): bool
    {
        $threshold = config('ai.confidence_threshold', 0.8);
        return $confidenceScore < $threshold;
    }

    /**
     * Построить системный промпт для AI.
     *
     * @return string
     */
    protected function buildSystemPrompt(): string
    {
        return Storage::disk('prompts')->get('basic.txt');
    }
}
