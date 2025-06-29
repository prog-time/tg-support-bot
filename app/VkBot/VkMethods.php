<?php

namespace App\VkBot;

use App\DTOs\VK\VkAnswerDto;

class VkMethods
{
    /**
     * Send query in VK
     *
     * @param string $methodQuery
     * @param array  $params
     *
     * @return VkAnswerDto
     */
    public static function sendQueryVk(string $methodQuery, array $params): VkAnswerDto
    {
        try {
            $accessToken = config('traffic_source.settings.vk.token');

            $queryParams = array_merge($params, [
                'access_token' => $accessToken,
                'v' => '5.199',
                'random_id' => random_int(1, PHP_INT_MAX),
            ]);

            $url = 'https://api.vk.com/method/' . $methodQuery;
            $resultQuery = self::makeRequest($url, $queryParams);

            if (empty($resultQuery)) {
                throw new \RuntimeException('VK API Error: Запрос без результата!');
            }

            if (isset($resultQuery['error'])) {
                throw new \RuntimeException('VK API Error: ' . json_encode($resultQuery['error']));
            }

            return VkAnswerDto::fromData($resultQuery);
        } catch (\Exception $e) {
            return VkAnswerDto::fromData([
                'response' => 0,
            ]);
        }
    }

    /**
     * @param string $url
     * @param array  $params
     *
     * @return mixed
     */
    private static function makeRequest(string $url, array $params): mixed
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
            ]);
            $result = curl_exec($curl);

            if ($result === false) {
                throw new \RuntimeException('Curl error: ' . curl_error($curl));
            }

            curl_close($curl);

            return json_decode($result, true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
