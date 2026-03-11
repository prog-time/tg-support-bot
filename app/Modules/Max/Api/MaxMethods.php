<?php

namespace App\Modules\Max\Api;

use App\Modules\Max\DTOs\MaxAnswerDto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxBotApi\Config;
use MaxBotApi\MaxClient;

class MaxMethods
{
    /**
     * Send a request to Max API via SDK.
     *
     * @param string $methodQuery
     * @param array  $params
     *
     * @return MaxAnswerDto
     */
    public function sendQuery(string $methodQuery, array $params): MaxAnswerDto
    {
        try {
            $client = new MaxClient(new Config(
                token: config('traffic_source.settings.max.token'),
            ));

            $messageId = match ($methodQuery) {
                'sendMessage' => $client->messages->send(
                    text: $params['text'] ?? '',
                    userId: $params['user_id'] ?? null,
                )->messageId,
                'sendImage' => $this->sendImageMessage(
                    userId: $params['user_id'],
                    fileToken: $params['file_token'],
                    text: $params['text'] ?? '',
                ),
                'sendFile' => $this->sendFileMessage(
                    userId: $params['user_id'],
                    fileToken: $params['file_token'],
                    text: $params['text'] ?? '',
                ),
                'sendAudio' => $this->sendAudioMessage(
                    userId: $params['user_id'],
                    fileToken: $params['file_token'],
                ),
                default => throw new \RuntimeException("Unknown method: {$methodQuery}", 1),
            };

            return MaxAnswerDto::fromData([
                'response_code' => 200,
                'response' => $messageId,
            ]);
        } catch (\Throwable $e) {
            $isRetryable = str_contains($e->getMessage(), 'attachment.not.ready');

            Log::channel('loki')->log(
                $isRetryable ? 'info' : 'error',
                'MaxMethods::sendQuery failed | ' . get_class($e) . ': ' . $e->getMessage(),
                [
                    'methodQuery' => $methodQuery,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return MaxAnswerDto::fromData([
                'response_code' => 500,
                'error_message' => $e->getCode() === 1
                    ? $e->getMessage()
                    : 'Request sending error',
                'response' => null,
            ]);
        }
    }

    /**
     * Send a message with an image attachment via Max API.
     *
     * @param int    $userId    Target Max user ID.
     * @param string $fileToken Upload token received from the Max upload server.
     * @param string $text      Optional caption text.
     *
     * @return string Message ID returned by the API.
     *
     * @throws \RuntimeException On API or network error.
     */
    private function sendImageMessage(int $userId, string $fileToken, string $text = ''): string
    {
        $token = config('traffic_source.settings.max.token');
        $baseUrl = 'https://platform-api.max.ru';

        $body = [
            'text' => $text,
            'attachments' => [
                [
                    'type' => 'image',
                    'payload' => ['token' => $fileToken],
                ],
            ],
        ];

        $response = Http::withHeaders(['Authorization' => $token])
            ->post("{$baseUrl}/messages?user_id={$userId}", $body);

        Log::channel('loki')->info('MaxMethods::sendImageMessage response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Max sendImage failed: ' . $response->body(), 1);
        }

        $data = $response->json();

        return $data['message']['body']['mid'] ?? '';
    }

    /**
     * Send a message with an audio attachment via Max API.
     *
     * @param int    $userId    Target Max user ID.
     * @param string $fileToken Upload token received from the Max upload server.
     *
     * @return string Message ID returned by the API.
     *
     * @throws \RuntimeException On API or network error.
     */
    private function sendAudioMessage(int $userId, string $fileToken): string
    {
        $token = config('traffic_source.settings.max.token');
        $baseUrl = 'https://platform-api.max.ru';

        $body = [
            'attachments' => [
                [
                    'type' => 'audio',
                    'payload' => ['token' => $fileToken],
                ],
            ],
        ];

        $response = Http::withHeaders(['Authorization' => $token])
            ->post("{$baseUrl}/messages?user_id={$userId}", $body);

        Log::channel('loki')->info('MaxMethods::sendAudioMessage response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Max sendAudio failed: ' . $response->body(), 1);
        }

        $data = $response->json();

        return $data['message']['body']['mid'] ?? '';
    }

    /**
     * Send a message with a file attachment via Max API.
     *
     * @param int    $userId    Target Max user ID.
     * @param string $fileToken Upload token received from the Max upload server.
     * @param string $text      Optional caption text.
     *
     * @return string Message ID returned by the API.
     *
     * @throws \RuntimeException On API or network error.
     */
    private function sendFileMessage(int $userId, string $fileToken, string $text = ''): string
    {
        $token = config('traffic_source.settings.max.token');
        $baseUrl = 'https://platform-api.max.ru';

        $body = [
            'text' => $text,
            'attachments' => [
                [
                    'type' => 'file',
                    'payload' => ['token' => $fileToken],
                ],
            ],
        ];

        $response = Http::withHeaders(['Authorization' => $token])
            ->post("{$baseUrl}/messages?user_id={$userId}", $body);

        Log::channel('loki')->info('MaxMethods::sendFileMessage response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Max sendFile failed: ' . $response->body(), 1);
        }

        $data = $response->json();

        return $data['message']['body']['mid'] ?? '';
    }
}
