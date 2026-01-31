<?php

declare(strict_types=1);

namespace App\DTOs\Ai;

class AiRequestDto
{
    /**
     * AI request DTO constructor.
     *
     * @param string $message User message
     * @param int $userId User ID
     * @param string $platform Platform (telegram, vk, etc.)
     * @param array $context Previous messages context
     * @param string $provider AI provider to use
     * @param float|null $maxConfidence Maximum confidence for auto-reply
     * @param bool $forceEscalation Force escalation to operator
     */
    public function __construct(
        public readonly string $message,
        public readonly int $userId,
        public readonly string $platform,
        public readonly array $context = [],
        public readonly string $provider = 'openai',
        public readonly ?float $maxConfidence = null,
        public readonly bool $forceEscalation = false
    ) {
    }

    /**
     * Create DTO from data array.
     *
     * @param array $data Data array
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'],
            userId: $data['user_id'],
            platform: $data['platform'],
            context: $data['context'] ?? [],
            provider: $data['provider'] ?? 'openai',
            maxConfidence: $data['max_confidence'] ?? null,
            forceEscalation: $data['force_escalation'] ?? false
        );
    }

    /**
     * Convert DTO to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'user_id' => $this->userId,
            'platform' => $this->platform,
            'context' => $this->context,
            'provider' => $this->provider,
            'max_confidence' => $this->maxConfidence,
            'force_escalation' => $this->forceEscalation,
        ];
    }
}
