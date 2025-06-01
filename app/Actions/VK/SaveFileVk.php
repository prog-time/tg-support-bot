<?php

namespace App\Actions\VK;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\VK\VkAnswerDto;
use App\VkBot\VkMethods;

/**
 * Сохранение файла
 */
class SaveFileVk
{
    /**
     * Сохранение файла
     *
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

            if (empty($methodQuery)) {
                throw new \Exception('Метод для сохранения файла не найден!');
            }

            return VkMethods::sendQueryVk($methodQuery, $dataQuery);
        } catch (\Exception $e) {
            return null;
        }
    }

}
