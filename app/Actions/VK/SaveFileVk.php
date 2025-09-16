<?php

namespace App\Actions\VK;

use App\DTOs\Vk\VkAnswerDto;
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
                throw new \Exception('Метод для сохранения файла не найден!', 1);
            }

            return VkMethods::sendQueryVk($methodQuery, $dataQuery);
        } catch (\Exception $e) {
            return VkAnswerDto::fromData([
                'response_code' => 500,
                'response' => 0,
                'error_message' => $e->getCode() == 1 ? $e->getMessage() : 'Ошибка отправки запроса',
            ]);
        }
    }
}
