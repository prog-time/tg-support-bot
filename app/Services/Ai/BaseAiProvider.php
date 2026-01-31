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
     * Check if provider is available and properly configured.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }

    /**
     * Get provider name.
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return $this->providerName;
    }

    /**
     * Get model name.
     *
     * @return string
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * Get current rate limiting status.
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
     * Check rate limit for current provider.
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
     * Create AI response DTO.
     *
     * @param string $response Response text
     * @param float $confidenceScore Confidence score
     * @param bool $shouldEscalate Whether to escalate
     * @param int $tokensUsed Number of tokens
     * @param float $responseTime Response time
     * @param array $metadata Metadata
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
     * Determine if request should be escalated.
     *
     * @param float $confidenceScore Confidence score
     * @return bool
     */
    protected function shouldEscalate(float $confidenceScore): bool
    {
        $threshold = config('ai.confidence_threshold', 0.8);
        return $confidenceScore < $threshold;
    }

    /**
     * Build system prompt for AI.
     *
     * @return string
     */
    protected function buildSystemPrompt(): string
    {
        return Storage::disk('prompts')->get('basic.txt');
    }
}
