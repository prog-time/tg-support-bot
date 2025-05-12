<?php

namespace App\Actions\VK;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\VK\VkAnswerDto;
use App\VkBot\VkMethods;

class SaveFileVk
{
    /**
     * @param string $typeFile
     * @param array $dataQuery
     * @return TelegramAnswerDto|null
     */
    public static function execute(string $typeFile, array $dataQuery): ?VkAnswerDto
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
            return VkMethods::sendQueryVk($methodQuery, $dataQuery);
        } catch (\Exception $e) {
            return null;
        }
    }

}
