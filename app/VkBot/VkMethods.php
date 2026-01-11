<?php

namespace App\VkBot;

use App\DTOs\Vk\VkAnswerDto;
use Illuminate\Support\Facades\Http;

class VkMethods
{
    /**
     * Отправка запроса в VK
     *
     * @param string $methodQuery
     * @param array  $params
     *
     * @return VkAnswerDto
     */
    public static function sendQueryVk(string $methodQuery, array $params): VkAnswerDto
    {
        try {
            $queryParams = array_merge($params, [
                'access_token' => config('traffic_source.settings.vk.token'),
                'v' => '5.199',
                'random_id' => random_int(1, PHP_INT_MAX),
            ]);

            $response = Http::asForm()->post(
                'https://api.vk.com/method/' . $methodQuery,
                $queryParams
            );

            $resultQuery = $response->json();

            if (!empty($resultQuery['error']['error_msg'])) {
                throw new \RuntimeException($resultQuery['error']['error_msg'], 1);
            }

            return VkAnswerDto::fromData($resultQuery);
        } catch (\Throwable $e) {
            return VkAnswerDto::fromData([
                'response_code' => 500,
                'response' => 0,
                'error_message' => $e->getCode() === 1
                    ? $e->getMessage()
                    : 'Ошибка отправки запроса',
            ]);
        }
    }
}
