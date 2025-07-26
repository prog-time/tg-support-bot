<?php

namespace App\Services\Redis;

use App\DTOs\Redis\WebhookMessageDto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisService
{
    protected int $ttl = 86400;

    protected int $maxMessages = 100;

    /**
     * Получение ключа для Redis
     *
     * @param string $chatKey
     *
     * @return string
     */
    protected function getRedisKey(string $chatKey): string
    {
        return "chat:$chatKey";
    }

    /**
     * Сохранение сообщения в Redis
     *
     * @param string            $chatKey
     * @param WebhookMessageDto $message
     *
     * @return bool
     */
    public function saveMessage(string $chatKey, WebhookMessageDto $message): bool
    {
        try {
            $key = $this->getRedisKey($chatKey);

            Redis::rpush($key, json_encode($message->toArray()));

            // Обрезаем список до 100 последних сообщений
            Redis::ltrim($key, -$this->maxMessages, -1);

            // Обновляем TTL
            Redis::expire($key, $this->ttl);

            return true;
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    /**
     * Получение списка сообщений из Redis
     *
     * @param string $chatKey
     *
     * @return array
     */
    public function getMessages(string $chatKey): array
    {
        $key = $this->getRedisKey($chatKey);

        $messages = Redis::lrange($key, 0, -1);
        return array_map(
            fn ($m) => WebhookMessageDto::fromArray(
                json_decode($m, true)
            ),
            $messages
        );
    }
}
