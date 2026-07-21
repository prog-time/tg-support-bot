<?php

namespace App\Modules\Max\DTOs;

use Illuminate\Http\Request;

readonly class MaxUpdateDto
{
    public function __construct(
        public string $event_id,
        public string $type,
        public int $from_id,
        public string $id,
        public ?string $text,
        public array $rawData,
        public array $listFileUrl,
        public array $listAttachments,
        public ?string $senderName = null,
        public ?string $senderUsername = null,
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

            $attachments = $data['message']['body']['attachments'] ?? [];

            $sender = $data['message']['sender'] ?? [];

            return new self(
                event_id: (string)($data['event_id'] ?? $data['timestamp']),
                type: $data['update_type'] ?? $data['type'],
                from_id: $data['message']['sender']['user_id'],
                id: $data['message']['body']['mid'] ?? '',
                text: $data['message']['body']['text'] ?? null,
                rawData: $data,
                listFileUrl: self::getListUrlAttachments($attachments),
                listAttachments: self::getListAttachments($attachments),
                // MAX delivers the sender's display name and @username right in the
                // webhook (per the Bot API sender object), so — unlike VK — no extra
                // API call is needed to label the conversation.
                senderName: isset($sender['name']) ? (string) $sender['name'] : null,
                senderUsername: isset($sender['username']) ? (string) $sender['username'] : null,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract file URLs from attachments.
     *
     * @param array $attachments
     *
     * @return array
     */
    private static function getListUrlAttachments(array $attachments): array
    {
        $result = [];

        foreach ($attachments as $attachment) {
            $url = $attachment['payload']['url'] ?? null;
            if ($url !== null) {
                $result[] = $url;
            }
        }

        return $result;
    }

    /**
     * Get structured attachments list with type and file_id.
     *
     * @param array $attachments
     *
     * @return array<int, array{type: string, file_id: string, file_name: string|null}>
     */
    private static function getListAttachments(array $attachments): array
    {
        $result = [];

        foreach ($attachments as $attachment) {
            $type = $attachment['type'] ?? null;
            $url = $attachment['payload']['url'] ?? null;

            if ($url === null) {
                continue;
            }

            switch ($type) {
                case 'image':
                    $result[] = [
                        'type' => 'photo',
                        'file_id' => $url,
                        'file_name' => null,
                    ];
                    break;

                case 'file':
                    $result[] = [
                        'type' => 'document',
                        'file_id' => $url,
                        'file_name' => $attachment['payload']['filename'] ?? null,
                    ];
                    break;

                case 'audio':
                    $result[] = [
                        'type' => 'voice',
                        'file_id' => $url,
                        'file_name' => null,
                    ];
                    break;

                case 'video':
                    $result[] = [
                        'type' => 'video',
                        'file_id' => $url,
                        'file_name' => null,
                    ];
                    break;
            }
        }

        return $result;
    }
}
