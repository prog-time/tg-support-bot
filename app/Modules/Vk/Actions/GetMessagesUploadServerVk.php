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
     * @param int                  $chat_id
     * @param string               $typeMethod
     * @param array<string, mixed> $extraParams Additional query params (e.g. ['type' => 'audio_message'])
     *
     * @return VkAnswerDto
     */
    public function execute(int $chat_id, string $typeMethod = 'doc', array $extraParams = []): VkAnswerDto
    {
        try {
            $methodQuery = $typeMethod . '.getMessagesUploadServer';
            $dataQuery = array_merge(['peer_id' => $chat_id], $extraParams);

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
