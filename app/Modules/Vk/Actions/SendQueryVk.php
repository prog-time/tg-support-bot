<?php

namespace App\Modules\Vk\Actions;

use App\Modules\Vk\Api\VkMethods;
use App\Modules\Vk\DTOs\VkAnswerDto;
use App\Modules\Vk\DTOs\VkTextMessageDto;

/**
 * Send request to VK.
 */
class SendQueryVk
{
    /**
     * Send request to VK.
     *
     * @param VkTextMessageDto $queryParams
     *
     * @return VkAnswerDto|null
     */
    public function execute(VkTextMessageDto $queryParams): ?VkAnswerDto
    {
        try {
            $dataQuery = $queryParams->toArray();
            return VkMethods::sendQueryVk($queryParams->methodQuery, $dataQuery);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
