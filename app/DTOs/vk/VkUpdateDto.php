<?php

namespace App\DTOs\VK;

use Illuminate\Http\Request;

/**
 * DTO для запроса из VK
 *
 * @property string|null $secret
 * @property int $group_id
 * @property string $type
 * @property string $event_id
 * @property string $v
 * @property int $from_id
 * @property int $id
 * @property string|null $text
 * @property array|null $geo
 * @property array $rawData
 * @property array $listFileUrl
 */
readonly class VkUpdateDto
{
    /**
     * @param string|null $secret
     * @param int $group_id
     * @param string $type
     * @param string $event_id
     * @param string $v
     * @param int $from_id
     * @param int $id
     * @param string|null $text
     * @param array|null $geo
     * @param array $rawData
     * @param array $listFileUrl
     */
    public function __construct(
        public ?string $secret,
        public int $group_id,
        public string $type,
        public string $event_id,
        public string $v,
        public int $from_id,
        public int $id,
        public ?string $text,
        public ?array $geo,
        public array $rawData,
        public array $listFileUrl
    ) {}

    /**
     * @param Request $request
     * @return self|null
     */
    public static function fromRequest(Request $request): ?self
    {
        try {
            $data = $request->all();

            return new self(
                secret: $data['secret'] ?? "",
                group_id: $data['group_id'],
                type: $data['type'],
                event_id: $data['event_id'],
                v: $data['v'],
                from_id: $data['object']['message']['from_id'],
                id: $data['object']['message']['id'],
                text: $data['object']['message']['text'] ?? null,
                geo: $data['object']['message']['geo'] ?? null,
                rawData: $data,
                listFileUrl: self::getListUrlAttachments($data['object']['message']['attachments']),
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get attachments
     * @param array $attachments
     * @return array
     */
    private static function getListUrlAttachments(array $attachments): array
    {
        try {
            if (empty($attachments)) {
                return [];
            }

            $result = [];
            foreach ($attachments as $attachment) {
                $attachmentData = $attachment[$attachment['type']];
                switch ($attachment['type']) {
                    case 'photo':
                        $result[] = $attachmentData['orig_photo']['url'];
                        break;

                    case 'doc':
                    case 'graffiti':
                        $result[] = $attachmentData['url'];
                        break;

                    case 'audio_message':
                        $result[] = $attachmentData['link_mp3'];
                        break;
                }
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
