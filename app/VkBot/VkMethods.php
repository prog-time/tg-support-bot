<?php

namespace App\VkBot;

class VkMethods {

    public static function sendQueryVk(string $methodQuery, array $params): VkAnswerDto
    {
        try {
            $accessToken = env('VK_TOKEN');

            $queryParams = array_merge($params, [
                'access_token' => $accessToken,
                'v' => '5.199',
                'random_id' => random_int(1, PHP_INT_MAX),
            ]);

            $url = 'https://api.vk.com/method/' . $methodQuery;
            $response = self::makeRequest($url, $queryParams);

            dd($response);

            if (isset($response['error'])) {
                throw new \RuntimeException('VK API Error: ' . json_encode($response['error']));
            }

            return $response['response'] ?? [];
        } catch (\Exception $e) {
            
        }
    }

    private static function makeRequest(string $url, array $params)
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
            
        }
    }

}
