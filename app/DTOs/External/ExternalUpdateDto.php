<?php

namespace App\DTOs\External;

use Spatie\LaravelData\Data;

/**
 * @property string                       $source
 * @property string                       $external_user_id
 * @property string|null                  $text
 * @property ExternalAttachmentDto[]|null $attachment
 */
class ExternalUpdateDto extends Data
{
    /**
     * @param string                       $source
     * @param string                       $external_user_id
     * @param string|null                  $text
     * @param ExternalAttachmentDto[]|null $attachment
     */
    public function __construct(
        public string $source,
        public string $external_user_id,
        public ?string $text,
        public ?array $attachment,
    ) {
    }

    /**
     * @param array $data
     *
     * @return self|null
     */
    public static function fromArray(array $data): ?self
    {
        try {
            $listAttachments = null;
            return new self(
                source: $data['source'],
                external_user_id: $data['external_user_id'],
                text: $data['text'] ?? null,
                attachment: $listAttachments,
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}
