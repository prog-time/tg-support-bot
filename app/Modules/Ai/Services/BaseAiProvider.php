<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Contracts\AiProviderInterface;
use App\Modules\Ai\DTOs\AiRequestDto;
use App\Modules\Ai\DTOs\AiResponseDto;
use App\Services\Settings\SettingsService;

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
        $this->config = $this->buildProviderConfig($providerName);
        $this->apiKey = $this->config['api_key'] ?? $this->config['client_secret'] ?? '';
        $this->baseUrl = $this->config['base_url'] ?? '';
        $this->modelName = $this->config['model'] ?? '';
    }

    /**
     * Build provider config array from SettingsService for the given provider name.
     *
     * @param string $providerName
     *
     * @return array<string, mixed>
     */
    private function buildProviderConfig(string $providerName): array
    {
        $settings = app(SettingsService::class);
        $prefix = "ai.{$providerName}_";

        $keys = [
            'api_key', 'client_id', 'client_secret',
            'base_url', 'model', 'max_tokens', 'temperature', 'path_cert', 'scope',
        ];

        $config = [];
        foreach ($keys as $key) {
            $value = $settings->get($prefix . $key);
            if ($value !== null) {
                $config[$key] = $value;
            }
        }

        return $config;
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
     * Create AI response DTO.
     *
     * @param string $response        Response text
     * @param float  $confidenceScore Confidence score
     * @param bool   $shouldEscalate  Whether to escalate
     * @param int    $tokensUsed      Number of tokens
     * @param float  $responseTime    Response time
     * @param array  $metadata        Metadata
     *
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
     *
     * @return bool
     */
    protected function shouldEscalate(float $confidenceScore): bool
    {
        $threshold = (float) (app(SettingsService::class)->get('ai.confidence_threshold') ?? 0.8);
        return $confidenceScore < $threshold;
    }

    /**
     * Build the system prompt for the given request.
     *
     * Delegates to {@see AiSystemPromptLoader::render()}, which reads the
     * plain-text prompt file verbatim (no templating). The loader is resolved
     * through the container so its per-request memoization is shared across
     * providers.
     *
     * @param AiRequestDto $request
     *
     * @return string
     */
    protected function buildSystemPrompt(AiRequestDto $request): string
    {
        return app(AiSystemPromptLoader::class)->render();
    }
}
