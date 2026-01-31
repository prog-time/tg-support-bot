<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\DTOs\Ai\AiRequestDto;
use App\DTOs\Ai\AiResponseDto;

interface AiProviderInterface
{
    /**
     * Process user message and generate AI response.
     *
     * @param AiRequestDto $request Request DTO
     * @return AiResponseDto|null AI response DTO
     */
    public function processMessage(AiRequestDto $request): ?AiResponseDto;

    /**
     * Check if provider is available and properly configured.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get provider name.
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Get model name.
     *
     * @return string
     */
    public function getModelName(): string;

    /**
     * Get current rate limiting status.
     *
     * @return array
     */
    public function getRateLimitStatus(): array;
}
