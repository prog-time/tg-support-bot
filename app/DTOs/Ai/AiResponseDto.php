<?php

declare(strict_types=1);

namespace App\DTOs\Ai;

class AiResponseDto
{
    /**
     * AI response DTO constructor.
     *
     * @param string $response        AI response text
     * @param float  $confidenceScore Confidence score (0.0 - 1.0)
     * @param bool   $shouldEscalate  Whether to escalate to operator
     * @param string $provider        Used AI provider
     * @param string $modelUsed       Used model
     * @param int    $tokensUsed      Number of tokens used
     * @param float  $responseTime    Response time in seconds
     * @param array  $metadata        Additional metadata
     */
    public function __construct(
        public readonly string $response,
        public readonly float $confidenceScore,
        public readonly bool $shouldEscalate,
        public readonly string $provider,
        public readonly string $modelUsed,
        public readonly int $tokensUsed,
        public readonly float $responseTime,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Create DTO from data array.
     *
     * @param array $data Data array
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            response: $data['response'],
            confidenceScore: $data['confidence_score'],
            shouldEscalate: $data['should_escalate'],
            provider: $data['provider'],
            modelUsed: $data['model_used'],
            tokensUsed: $data['tokens_used'],
            responseTime: $data['response_time'],
            metadata: $data['metadata'] ?? []
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
            'response' => $this->response,
            'confidence_score' => $this->confidenceScore,
            'should_escalate' => $this->shouldEscalate,
            'provider' => $this->provider,
            'model_used' => $this->modelUsed,
            'tokens_used' => $this->tokensUsed,
            'response_time' => $this->responseTime,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Check if confidence is sufficient for auto-reply.
     *
     * @return bool
     */
    public function isConfident(): bool
    {
        return $this->confidenceScore >= config('ai.confidence_threshold', 0.8);
    }
}
