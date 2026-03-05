<?php

namespace App\Modules\Vk\Actions;

use App\Modules\Vk\Api\VkMethods;
use App\Modules\Vk\DTOs\VkAnswerDto;

/**
 * Get document upload server.
 */
class GetMessagesUploadServerVk
{
    /**
     * Get document upload server.
     *
     * @param int    $chat_id
     * @param string $typeMethod
     *
     * @return VkAnswerDto
     */
    public static function execute(int $chat_id, string $typeMethod = 'doc'): VkAnswerDto
    {
        try {
            $methodQuery = $typeMethod . '.getMessagesUploadServer';
            $dataQuery = [
                'peer_id' => $chat_id,
            ];
            return VkMethods::sendQueryVk($methodQuery, $dataQuery);
        } catch (\Throwable $e) {
            return VkAnswerDto::fromData([
                'response_code' => 500,
                'error_message' => 'System error',
                'response' => 0,
            ]);
        }
    }
}
