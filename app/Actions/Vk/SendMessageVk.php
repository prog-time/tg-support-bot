<?php

namespace App\Actions\Vk;

use App\DTOs\Vk\VkAnswerDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\VkBot\VkMethods;

/**
 * Send message to VK.
 */
class SendMessageVk
{
    /**
     * Send message to VK.
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
        } catch (\Throwable $e) {
            return null;
        }
    }
}
