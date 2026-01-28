<?php

namespace App\DTOs;

use App\Enums\TelegramError;
use App\Helpers\TelegramHelper;

/**
 * TelegramAnswerDto
 *
 * @property bool    $ok,
 * @property ?int    $message_id,
 * @property ?int    $response_code,
 * @property ?int    $message_thread_id,
 * @property ?int    $date,
 * @property ?string $message,
 * @property ?string $type_error,
 * @property ?array  $rawData            = null
 */
class TelegramAnswerDto
{
    public function __construct(
        public bool $ok,
        public bool $isTopicNotFound = false,
        public ?int $message_id = null,
        public ?int $chat_id = null,
        public ?int $response_code = null,
        public ?int $message_thread_id = null,
        public ?int $date = null,
        public ?string $message = null,
        public ?string $type_error = null,
        public ?string $text = null,
        public ?string $fileId = null,
        public ?array $rawData = null
    ) {
    }

    /**
     * @param array       $dataAnswer
     * @param string|null $methodQuery
     *
     * @return null|self
     */
    public static function fromData(array $dataAnswer, string $methodQuery = null): ?self
    {
        try {
            if (empty($dataAnswer)) {
                throw new \Exception('Пустой массив с ответом!');
            }

            $result = $dataAnswer['result'] ?? [];

            $dataMessage = empty($result) ? [] : [
                'message' => $result,
            ];

            $responseCode = 200;
            if (!empty($dataAnswer['response_code'])) {
                $responseCode = $dataAnswer['response_code'];
            } elseif (!empty($dataAnswer['error_code'])) {
                $responseCode = $dataAnswer['error_code'];
            } elseif ($dataAnswer['ok'] === false) {
                $responseCode = 500;
            }

            $typeError = self::exactTypeError($dataAnswer['description'] ?? '');
            $isTopicNotFound = in_array($typeError, ['TOPIC_ID_INVALID', 'TOPIC_DELETED', 'TOPIC_NOT_FOUND']) ? true : false;

            return new self(
                ok: $dataAnswer['ok'] ?? false,
                isTopicNotFound: $isTopicNotFound,
                message_id: $result['message_id'] ?? null,
                chat_id: $result['chat']['id'] ?? null,
                response_code: $responseCode,
                message_thread_id: $result['message_thread_id'] ?? null,
                date: $result['date'] ?? null,
                message: $result['message'] ?? null,
                type_error: $typeError,
                text: $result['text'] ?? $result['caption'] ?? null,
                fileId: $methodQuery === 'getChat' ? null : TelegramHelper::extractFileId($dataMessage),
                rawData: $dataAnswer,
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Получение кода ошибки
     *
     * @param string $textError
     *
     * @return string|null
     */
    private static function exactTypeError(string $textError): ?string
    {
        try {
            $error = TelegramError::fromResponse($textError);
            return $error->name;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
