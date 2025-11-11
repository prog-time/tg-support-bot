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
        public ?int $message_id,
        public ?int $chat_id,
        public ?int $response_code,
        public ?int $message_thread_id,
        public ?int $date,
        public ?string $message,
        public ?string $type_error,
        public ?string $text,
        public ?string $fileId,
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

            return new self(
                ok: $dataAnswer['ok'] ?? false,
                message_id: $result['message_id'] ?? null,
                chat_id: $result['chat']['id'] ?? null,
                response_code: $dataAnswer['response_code'] ?? $dataAnswer['error_code'] ?? 200,
                message_thread_id: $result['message_thread_id'] ?? null,
                date: $result['date'] ?? null,
                message: $result['message'] ?? null,
                type_error: self::exactTypeError($dataAnswer['description'] ?? ''),
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
