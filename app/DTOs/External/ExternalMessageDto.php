<?php

namespace App\DTOs\External;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * DTO для сообщений через API
 *
 * @property string $source
 * @property string $external_id
 * @property string $text
 * @property string|int|null $message_id
 * @property ?array $attachments
 */
class ExternalMessageDto extends Data
{
    /**
     * @param string $source
     * @param string $external_id
     * @param ?string $text
     * @param string|int|null $message_id
     */
    public function __construct(
        public string $source,
        public string $external_id,
        public string|int|null $message_id,
        public ?string $text,
        public ?UploadedFile $uploaded_file,
    ) {
    }

    /**
     * @param Request $request
     * @return self|null
     */
    public static function fromRequest(Request $request): ?self
    {
        try {
            $data = $request->all();

            return new self(
                source: $data['source'],
                external_id: $data['external_id'],
                message_id: $data['message_id'] ?? null,
                text: $data['text'] ?? null,
                uploaded_file: $data['uploaded_file'] ?? null,
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}
