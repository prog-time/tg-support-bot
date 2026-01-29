<?php

namespace App\DTOs\External;

use Spatie\LaravelData\Data;

class ExternalMessageResponseDto extends Data
{
    /**
     * @param string      $message_type
     * @param int         $to_id
     * @param int         $from_id
     * @param string|null $text
     * @param string      $date
     * @param string|null $content_type
     * @param string|null $file_id
     * @param string|null $file_url
     * @param string|null $file_type
     * @param array|null  $buttons      Кнопки в формате массива ButtonDto
     */
    public function __construct(
        public string $message_type,
        public int $to_id,
        public int $from_id,
        public ?string $text,
        public string $date,
        public ?string $content_type,
        public ?string $file_id,
        public ?string $file_url,
        public ?string $file_type,
        public ?array $buttons = null,
    ) {
    }

    public static function fromArray(array $data): ?self
    {
        try {
            if (!empty($data['file_id'])) {
                $data['file_url'] = route('stream_file', [
                    'file_id' => $data['file_id'],
                ]);
            }

            if (!empty($data['file_id'])) {
                $data['content_type'] = 'file';
            } else {
                $data['content_type'] = 'text';
            }

            return new self(
                message_type: $data['message_type'],
                to_id: $data['to_id'],
                from_id: $data['from_id'],
                text: $data['text'] ?? null,
                date: $data['date'],
                content_type: $data['content_type'],
                file_id: $data['file_id'] ?? null,
                file_url: $data['file_url'] ?? null,
                file_type: $data['file_type'] ?? null,
                buttons: $data['buttons'] ?? null,
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn ($value) => !is_null($value));
    }

    public function toArrayFull(): array
    {
        return parent::toArray();
    }
}
