<?php

namespace App\Actions\VK;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\VkBot\VkMethods;
use phpDocumentor\Reflection\Exception;

class SendMessageVk
{
    /**
     * @param VkTextMessageDto $queryParams
     * @return TelegramAnswerDto|null
     */
    public static function execute(VkTextMessageDto $queryParams): ?TelegramAnswerDto
    {
        try {
            $dataQuery = $queryParams->toArray();
            $resultQuery = VkMethods::sendQueryVk($queryParams->methodQuery, $dataQuery);

            dump($resultQuery);

            return $resultQuery;
        } catch (\Exception $e) {
            return null;
        }
    }

}
