<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\DTOs\Ai\AiRequestDto;
use App\DTOs\Ai\AiResponseDto;

interface AiProviderInterface
{
    /**
     * Обработать сообщение пользователя и сгенерировать AI-ответ.
     *
     * @param AiRequestDto $request DTO с данными запроса
     * @return AiResponseDto|null DTO с ответом AI
     */
    public function processMessage(AiRequestDto $request): ?AiResponseDto;

    /**
     * Проверить, доступен ли провайдер и правильно настроен.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Получить название провайдера.
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Получить название используемой модели.
     *
     * @return string
     */
    public function getModelName(): string;

    /**
     * Получить текущий статус rate limiting.
     *
     * @return array
     */
    public function getRateLimitStatus(): array;
}
