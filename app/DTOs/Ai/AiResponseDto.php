<?php

declare(strict_types=1);

namespace App\DTOs\Ai;

class AiResponseDto
{
    /**
     * Конструктор DTO для AI-ответа
     *
     * @param string $response        Текст ответа AI
     * @param float  $confidenceScore Показатель уверенности (0.0 - 1.0)
     * @param bool   $shouldEscalate  Нужно ли эскалировать к оператору
     * @param string $provider        Использованный AI-провайдер
     * @param string $modelUsed       Использованная модель
     * @param int    $tokensUsed      Количество использованных токенов
     * @param float  $responseTime    Время ответа в секундах
     * @param array  $metadata        Дополнительные метаданные
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
     * Создать DTO из массива данных
     *
     * @param array $data Массив данных
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
     * Преобразовать DTO в массив
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
     * Проверить, достаточно ли уверенности для автоответа
     *
     * @return bool
     */
    public function isConfident(): bool
    {
        return $this->confidenceScore >= config('ai.confidence_threshold', 0.8);
    }
}
