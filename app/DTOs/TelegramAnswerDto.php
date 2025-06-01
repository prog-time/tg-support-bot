<?php

namespace App\DTOs;

/**
 * TelegramAnswerDto
 *
 * @property  bool $ok,
 * @property  ?int $message_id,
 * @property  ?int $error_code,
 * @property  ?int $message_thread_id,
 * @property  ?int $date,
 * @property  ?string $message,
 * @property  ?string $type_error,
 * @property  ?array $rawData = null
 */
readonly class TelegramAnswerDto
{
    /**
     * @param bool $ok
     * @param int|null $message_id
     * @param int|null $error_code
     * @param int|null $message_thread_id
     * @param int|null $date
     * @param string|null $message
     * @param string|null $type_error
     * @param array|null $rawData
     */
    public function __construct(
        public bool $ok,
        public ?int $message_id,
        public ?int $error_code,
        public ?int $message_thread_id,
        public ?int $date,
        public ?string $message,
        public ?string $type_error,
        public ?array $rawData = null
    ) {}

    /**
     * @param array $dataAnswer
     * @return self
     */
    public static function fromData(array $dataAnswer): self
    {
        if (!empty($dataAnswer['ok'])) {
            $result = $dataAnswer['result'];
        }

        return new self(
            ok: $dataAnswer['ok'] ?? false,
            message_id: $result['message_id'] ?? null,
            error_code: $dataAnswer['error_code'] ?? 200,
            message_thread_id: $result['message_thread_id'] ?? null,
            date: $result['date'] ?? null,
            message: $result['message'] ?? null,
            type_error: self::exactTypeError($dataAnswer['description'] ?? ''),
            rawData: $dataAnswer,
        );
    }

    /**
     * Получение кода ошибки
     *
     * @param string $textError
     * @return string|null
     */
    private static function exactTypeError(string $textError): ?string
    {
        $typeError = null;
        if (preg_match('/(can\'t parse entities)/', $textError)) {
            $typeError = 'markdown';
        } else if (preg_match('/(InputMedia)/', $textError)) {
            $typeError = 'error media';
        } else if (preg_match('/( message is not modified)/', $textError)) {
            $typeError = 'message is not modified';
        }
        return $typeError;
    }

}
