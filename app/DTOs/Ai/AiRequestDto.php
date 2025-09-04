<?php

declare(strict_types=1);

namespace App\DTOs\Ai;

class AiRequestDto
{
    /**
     * Конструктор DTO для AI-запроса
     * 
     * @param string $message Сообщение пользователя
     * @param int $userId ID пользователя
     * @param string $platform Платформа (telegram, vk, etc.)
     * @param array $context Контекст предыдущих сообщений
     * @param string $provider AI-провайдер для использования
     * @param float|null $maxConfidence Максимальная уверенность для автоответа
     * @param bool $forceEscalation Принудительная эскалация к оператору
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
     * Создать DTO из массива данных
     * 
     * @param array $data Массив данных
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
     * Преобразовать DTO в массив
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
