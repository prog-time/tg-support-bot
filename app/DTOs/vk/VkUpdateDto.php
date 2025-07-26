<?php

namespace App\DTOs\Vk;

use Illuminate\Http\Request;

readonly class VkUpdateDto
{
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
    ) {
    }

    /**
     * @param Request $request
     *
     * @return self|null
     */
    public static function fromRequest(Request $request): ?self
    {
        try {
            $data = $request->all();
            return new self(
                secret: $data['secret'] ?? '',
                group_id: $data['group_id'],
                type: $data['type'],
                event_id: $data['event_id'],
                v: $data['v'],
                from_id: self::detectFromId($data),
                id: self::detectId($data),
                text: self::detectText($data),
                geo: $data['object']['message']['geo'] ?? null,
                rawData: $data,
                listFileUrl: self::getListUrlAttachments(self::detectAttachments($data)),
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param array $data
     *
     * @return int|null
     */
    private static function detectFromId(array $data): ?int
    {
        if (!empty($data['object']['message']['from_id'])) {
            $fromId = $data['object']['message']['from_id'];
        } elseif (!empty($data['object']['from_id'])) {
            $fromId = $data['object']['from_id'];
        }
        return $fromId ?? null;
    }

    /**
     * @param array $data
     *
     * @return int|null
     */
    private static function detectId(array $data): ?int
    {
        if (!empty($data['object']['message']['id'])) {
            $id = $data['object']['message']['id'];
        } elseif (!empty($data['object']['id'])) {
            $id = $data['object']['id'];
        }
        return $id ?? null;
    }

    /**
     * @param array $data
     *
     * @return string|null
     */
    private static function detectText(array $data): ?string
    {
        if (!empty($data['object']['message']['text'])) {
            $text = $data['object']['message']['text'];
        } elseif (!empty($data['object']['text'])) {
            $text = $data['object']['text'];
        }
        return $text ?? null;
    }

    /**
     * @param array $data
     *
     * @return array|null
     */
    private static function detectAttachments(array $data): ?array
    {
        if (!empty($data['object']['message']['attachments'])) {
            $attachments = $data['object']['message']['attachments'];
        } elseif (!empty($data['object']['attachments'])) {
            $attachments = $data['object']['attachments'];
        }
        return $attachments ?? null;
    }

    /**
     * Get attachments
     *
     * @param array $attachments
     *
     * @return array
     */
    private static function getListUrlAttachments(?array $attachments): array
    {
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
    }
}
