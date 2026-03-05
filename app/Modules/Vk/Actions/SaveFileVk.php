<?php

namespace App\Modules\Vk\Actions;

use App\Modules\Vk\Api\VkMethods;
use App\Modules\Vk\DTOs\VkAnswerDto;

/**
 * Save file.
 */
class SaveFileVk
{
    /**
     * Save file.
     *
     * @param string $typeFile
     * @param array  $dataQuery
     *
     * @return VkAnswerDto
     */
    public static function execute(string $typeFile, array $dataQuery): VkAnswerDto
    {
        try {
            switch ($typeFile) {
                case 'photos':
                    $methodQuery = 'photos.saveMessagesPhoto';
                    break;

                case 'docs':
                    $methodQuery = 'docs.save';
                    break;
            }

            if (empty($methodQuery)) {
                throw new \Exception('File save method not found!', 1);
            }

            return VkMethods::sendQueryVk($methodQuery, $dataQuery);
        } catch (\Throwable $e) {
            return VkAnswerDto::fromData([
                'response_code' => 500,
                'response' => 0,
                'error_message' => $e->getCode() == 1 ? $e->getMessage() : 'Request sending error',
            ]);
        }
    }
}
