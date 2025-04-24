<?php

namespace App\DTOs;

readonly class TelegramAnswerDto
{
    public function __construct(
        public bool     $ok,
        public ?int     $message_id,
        public ?int     $error_code,
        public ?int     $message_thread_id,
        public ?int     $date,
        public ?string  $message,
        public ?string  $type_error,
        public ?array   $rawData = null
    ) {}

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
     * Get type error
     * @param string $textError
     * @return string|null
     */
    private static function exactTypeError(string $textError): ?string
    {
        $typeError = null;
        if (preg_match('/(can\'t parse entities)/', $textError)) {
            $typeError = 'markdown';
        }

        return $typeError;
    }

}
