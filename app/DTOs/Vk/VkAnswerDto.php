<?php

namespace App\DTOs\Vk;

use App\Enums\VkError;

readonly class VkAnswerDto
{
    public function __construct(
        public int $response_code,
        public ?string $error_type,
        public ?string $error_message,
        public int|array $response,
    ) {
    }

    /**
     * @param array $dataAnswer
     *
     * @return self
     */
    public static function fromData(array $dataAnswer): self
    {
        if (empty($dataAnswer['response_code'])) {
            $dataAnswer['response_code'] = 200;
        }

        if (!empty($dataAnswer['error_message'])) {
            $typeError = self::exactTypeError($dataAnswer['error_message']);
        }

        return new self(
            response_code: $dataAnswer['response_code'],
            error_type: $typeError ?? null,
            error_message: $dataAnswer['error_message'] ?? null,
            response: $dataAnswer['response'],
        );
    }

    /**
     * Get error code.
     *
     * @param string $textError
     *
     * @return string|null
     */
    private static function exactTypeError(string $textError): ?string
    {
        try {
            $error = VkError::fromResponse($textError);
            return $error->name;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
