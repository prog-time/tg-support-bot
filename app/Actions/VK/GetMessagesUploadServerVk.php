<?php

namespace App\Actions\VK;

use App\DTOs\VK\VkAnswerDto;
use App\VkBot\VkMethods;

class GetMessagesUploadServerVk
{
    /**
     * @param int $chat_id
     * @param string $typeMethod
     * @return VkAnswerDto|null
     */
    public static function execute(int $chat_id, string $typeMethod = 'doc'): ?VkAnswerDto
    {
        try {
            $methodQuery = $typeMethod . '.getMessagesUploadServer';
            $dataQuery = [
                'peer_id' => $chat_id,
            ];
            return VkMethods::sendQueryVk($methodQuery, $dataQuery);
        } catch (\Exception $e) {
            return null;
        }
    }

}
