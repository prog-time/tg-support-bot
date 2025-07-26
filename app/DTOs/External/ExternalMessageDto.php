<?php

namespace App\DTOs\External;

use Illuminate\Http\Request;
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
     * @param ?array $attachments
     */
    public function __construct(
        public string $source,
        public string $external_id,
        public ?string $text,
        public string|int|null $message_id,
        public ?array $attachments,
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

            $listAttachments = null;
            if (!empty($data['attachments'])) {
                $listAttachments = self::extractAttachment($data['attachments']);
            }

            return new self(
                source: $data['source'],
                external_id: $data['external_id'],
                text: $data['text'] ?? null,
                message_id: $data['message_id'] ?? null,
                attachments: $listAttachments,
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param array $data
     * @return ExternalAttachmentDto[]|null
     */
    private static function extractAttachment(array $data): ?array
    {
        $resultData = [];
        foreach ($data as $attachment) {
            try {
                if (!empty($attachment)) {
                    $resultData[] = new ExternalAttachmentDto(
                        url: $attachment['url'],
                        filename: $attachment['filename'],
                        mime: $attachment['mime'],
                    );
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return $resultData;
    }

}
