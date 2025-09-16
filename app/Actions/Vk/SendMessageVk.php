<?php

namespace App\Actions\Vk;

use App\DTOs\Vk\VkAnswerDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\VkBot\VkMethods;

/**
 * Отправка сообщения в VK
 */
class SendMessageVk
{
    /**
     * Отправка сообщения в VK
     *
     * @param VkTextMessageDto $queryParams
     *
     * @return VkAnswerDto|null
     */
    public static function execute(VkTextMessageDto $queryParams): ?VkAnswerDto
    {
        try {
            $dataQuery = $queryParams->toArray();
            return VkMethods::sendQueryVk($queryParams->methodQuery, $dataQuery);
        } catch (\Exception $e) {
            return null;
        }
    }
}
