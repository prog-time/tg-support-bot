<?php

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * DTO для запроса из Telegram
 *
 * @property int $updateId
 * @property bool $isBot
 * @property bool $editedTopicStatus
 * @property bool $pinnedMessageStatus
 * @property string $typeQuery
 * @property string $typeSource
 * @property int|null $chatId
 * @property array|null $replyToMessage
 * @property int|null $messageThreadId
 * @property int|null $messageId
 * @property int|null $callbackId
 * @property string|null $text
 * @property array|null $entities
 * @property string|null $caption
 * @property string|null $fileId
 * @property string|null $username
 * @property string|null $callbackData
 * @property array|null $location
 * @property array|null $rawData
*/
readonly class TelegramUpdateDto
{

    /**
     * @param int $updateId
     * @param bool $isBot
     * @param bool $editedTopicStatus
     * @param bool $pinnedMessageStatus
     * @param string $typeQuery
     * @param string $typeSource
     * @param int|null $chatId
     * @param array|null $replyToMessage
     * @param int|null $messageThreadId
     * @param int|null $messageId
     * @param int|null $callbackId
     * @param string|null $text
     * @param array|null $entities
     * @param string|null $caption
     * @param string|null $fileId
     * @param string|null $username
     * @param string|null $callbackData
     * @param array|null $location
     * @param array|null $rawData
     */
    public function __construct(
        public int     $updateId,
        public bool    $isBot = false,
        public bool    $editedTopicStatus = false,
        public bool    $pinnedMessageStatus = false,
        public string  $typeQuery,
        public string  $typeSource,
        public ?int    $chatId = null,
        public ?array  $replyToMessage,
        public ?int    $messageThreadId = null,
        public ?int    $messageId = null,
        public ?int    $callbackId = null,
        public ?string $text = null,
        public ?array  $entities = null,
        public ?string $caption = null,
        public ?string $fileId = null,
        public ?string $username = null,
        public ?string $callbackData = null,
        public ?array  $location,
        public ?array  $rawData
    ) {}

    /**
     * @param Request $request
     * @return self|null
     */
    public static function fromRequest(Request $request): ?self
    {
        try {
            $data = $request->all();
            $type = self::detectType($data);

            if ($type === 'unknown') {
                throw new \Exception('Данный тип запроса не поддерживается!');
            }

            $editedTopicStatus = !empty($data['message']['forum_topic_edited']);
            $pinnedMessageStatus = !empty($data['message']['pinned_message']);
            return new self(
                updateId: $data['update_id'] ?? 0,
                isBot: $data[$type]['from']['is_bot'],
                editedTopicStatus: $editedTopicStatus,
                pinnedMessageStatus: $pinnedMessageStatus,
                typeQuery: $type,
                typeSource: $data[$type]['chat']['type'],
                chatId: self::extractChatId($data, $type),
                replyToMessage: self::extractReplayToMessage($data),
                messageThreadId: self::extractMessageThreadId($data, $type),
                messageId: $data[$type]['message_id'] ?? null,
                callbackId: $data['callback_query']['id'] ?? null,
                text: $data[$type]['text'] ?? null,
                entities: $data[$type]['entities'] ?? $data[$type]['caption_entities'] ?? null,
                caption: $data[$type]['caption'] ?? null,
                fileId: self::extractFileId($data),
                username: $data[$type]['from']['username'] ?? null,
                callbackData: $data['callback_query']['data'] ?? null,
                location: $data['message']['location'] ?? null,
                rawData: $data // Сохраняем весь запрос, если вдруг понадобится
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param array $data
     * @return string
     */
    private static function detectType(array $data): string
    {
        $types = [
            'message',
            'edited_message',
            'callback_query',
            'inline_query',
            'chat_member',
        ];

        foreach ($types as $type) {
            if (array_key_exists($type, $data)) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * @param array $data
     * @return array|null
     */
    private static function extractReplayToMessage(array $data): ?array
    {
        return $data['message']['reply_to_message'] ?? null;
    }

    /**
     * @param array $data
     * @param string $type
     * @return int|null
     */
    private static function extractChatId(array $data, string $type): ?int
    {
        return $data[$type]['chat']['id'] ?? $data['callback_query']['message']['chat']['id'] ?? null;
    }

    /**
     * @param array $data
     * @return string|null
     */
    private static function extractFileId(array $data): ?string
    {
        if (!empty($data['message']['photo'])) {
            $fileId = end($data['message']['photo'])['file_id'];
        } else if (!empty($data['message']['document'])) {
            $fileId = $data['message']['document']['file_id'];
        } else if (!empty($data['message']['voice'])) {
            $fileId = $data['message']['voice']['file_id'];
        } else if (!empty($data['message']['sticker'])) {
            $fileId = $data['message']['sticker']['file_id'];
        } else if (!empty($data['message']['video_note'])) {
            $fileId = $data['message']['video_note']['file_id'];
        }

        return $fileId ?? null;
    }

    /**
     * @param array $data
     * @param string $type
     * @return int|null
     */
    private static function extractMessageThreadId(array $data, string $type): ?int
    {
        return $data[$type]['message_thread_id'] ?? $data['callback_query']['message']['message_thread_id'] ?? null;
    }
}
