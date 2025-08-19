<?php

namespace App\DTOs\External;

use Spatie\LaravelData\Data;

class ExternalMessageResponseDto extends Data
{
    /**
     * @param int $message_id
     * @param string $message_type
     * @param string $date
     * @param string|null $content_type
     * @param string|null $file_url
     * @param string|null $text
     */
    public function __construct(
        public int $message_id,
        public string $message_type,
        public string $date,
        public ?string $content_type,
        public ?string $file_id,
        public ?string $file_url,
        public ?string $text,
    ) {
    }

    public static function fromArray(array $data): ?self
    {
        try {
            if (!empty($data['file_id'])) {
                $data['file_url'] = route('stream_file', [
                    'file_id' => $data['file_id']
                ]);
            }

            if (!empty($data['file_id'])) {
                $data['content_type'] = 'document';
            } else {
                $data['content_type'] = 'text';
            }

            return new self(
                message_id: $data['message_id'],
                message_type: $data['message_type'],
                date: $data['date'],
                content_type: $data['content_type'],
                file_id: $data['file_id'] ?? null,
                file_url: $data['file_url'] ?? null,
                text: $data['text'] ?? null,
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn($value) => !is_null($value));
    }
}
