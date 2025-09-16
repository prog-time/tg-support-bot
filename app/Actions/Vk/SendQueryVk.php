<?php

namespace App\Actions\Vk;

use App\DTOs\Vk\VkAnswerDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\VkBot\VkMethods;

/**
 * Отправка запроса в VK
 */
class SendQueryVk
{
    /**
     * Отправка запроса в VK
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
