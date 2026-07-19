<?php

namespace App\Modules\Avito\DTOs;

use Illuminate\Http\Request;

/**
 * Parsed incoming Avito Messenger webhook update (v3 payload). Mirrors the
 * host's MaxUpdateDto role.
 *
 * Expected shape (Avito Messenger webhook v3):
 * {
 *   "id": "<event id>",
 *   "version": "v3.0.0",
 *   "timestamp": 1700000000,
 *   "payload": {
 *     "type": "message",
 *     "value": {
 *       "id": "<message id>",
 *       "chat_id": "<chat id>",
 *       "user_id": <account id>,
 *       "author_id": <sender id>,
 *       "type": "text",
 *       "content": { "text": "..." }
 *     }
 *   }
 * }
 *
 * `chatId` is the conversation key used to find/create the BotUser.
 * `fromRequest()` returns null on an unparseable payload so the controller acks.
 */
readonly class AvitoUpdateDto
{
    public function __construct(
        public string $event_id,
        public string $type,
        public string $chatId,
        public int|string $author_id,
        public string $message_id,
        public ?string $text,
        public array $rawData,
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
            $payload = $data['payload'] ?? [];
            $value = $payload['value'] ?? [];

            $chatId = (string) ($value['chat_id'] ?? '');
            if ($chatId === '') {
                return null;
            }

            return new self(
                event_id: (string) ($data['id'] ?? ($data['timestamp'] ?? '')),
                type: (string) ($payload['type'] ?? ''),
                chatId: $chatId,
                author_id: $value['author_id'] ?? 0,
                message_id: (string) ($value['id'] ?? ''),
                text: $value['content']['text'] ?? null,
                rawData: $data,
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
