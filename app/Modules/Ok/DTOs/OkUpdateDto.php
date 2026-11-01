<?php

namespace App\Modules\Ok\DTOs;

use Illuminate\Http\Request;

/**
 * Parsed Odnoklassniki webhook event, as documented for graph.user.subscribe:
 * {
 *   "webhookType": "MESSAGE_CREATED",
 *   "sender": {"user_id": "user:123"},
 *   "recipient": {"chat_id": "chat:C3ecb9d02a600"},
 *   "message": {"text": "...", "seq": 1, "mid": "mid.1", "attachments": []},
 *   "timestamp": 1700000000000
 * }
 *
 * `chatId` is the conversation key and the address of every reply.
 */
readonly class OkUpdateDto
{
    public const TYPE_MESSAGE = 'MESSAGE_CREATED';

    public const TYPE_SYSTEM = 'CHAT_SYSTEM';

    public const SYSTEM_CHAT_STARTED = 'CHAT_STARTED';

    /**
     * @param string                      $event_id    Stable id used to drop redelivered events
     * @param string                      $type        webhookType
     * @param string                      $chatId      recipient.chat_id
     * @param string                      $senderId    sender.user_id
     * @param string|null                 $senderName  sender.name, when OK sends it
     * @param string|null                 $text        message.text
     * @param array<int, OkAttachmentDto> $attachments message.attachments
     * @param string|null                 $systemType  type of a CHAT_SYSTEM event
     * @param array                       $rawData     Original payload
     */
    public function __construct(
        public string $event_id,
        public string $type,
        public string $chatId,
        public string $senderId,
        public ?string $senderName,
        public ?string $text,
        public array $attachments,
        public ?string $systemType,
        public array $rawData,
    ) {
    }

    /**
     * @param Request $request
     *
     * @return self|null Null when the event has no chat to work with
     */
    public static function fromRequest(Request $request): ?self
    {
        $data = $request->all();
        $chatId = $data['recipient']['chat_id'] ?? null;

        if (!is_string($chatId) || $chatId === '') {
            return null;
        }

        $type = (string) ($data['webhookType'] ?? '');
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];
        $text = $message['text'] ?? null;
        $timestamp = (string) ($data['timestamp'] ?? '');

        return new self(
            event_id: (string) ($message['mid'] ?? "{$type}:{$chatId}:{$timestamp}"),
            type: $type,
            chatId: $chatId,
            senderId: (string) ($data['sender']['user_id'] ?? ''),
            senderName: isset($data['sender']['name']) ? (string) $data['sender']['name'] : null,
            text: is_string($text) && trim($text) !== '' ? $text : null,
            attachments: self::parseAttachments($message),
            systemType: isset($data['type']) ? (string) $data['type'] : null,
            rawData: $data,
        );
    }

    /**
     * @param array $message
     *
     * @return array<int, OkAttachmentDto>
     */
    private static function parseAttachments(array $message): array
    {
        $items = (array) ($message['attachments'] ?? []);

        if (isset($message['attachment'])) {
            $items[] = $message['attachment'];
        }

        return array_values(array_map(
            fn (array $item) => OkAttachmentDto::fromArray($item),
            array_filter($items, 'is_array'),
        ));
    }

    /**
     * Numeric sender id for the integer messages.from_id column.
     *
     * @return int
     */
    public function numericSenderId(): int
    {
        $id = preg_replace('/^user:/', '', $this->senderId);

        return ctype_digit((string) $id) ? (int) $id : 0;
    }
}
