<?php

namespace App\Modules\Ok\DTOs;

use Spatie\LaravelData\Data;

/**
 * A file reply to an OK user. The file comes from a queue-safe local path or
 * from Telegram by file id.
 */
class OkFileMessageDto extends Data
{
    /**
     * @param string      $chat_id        OK chat id
     * @param string      $text           Text sent with the file
     * @param string      $mime           MIME type; decides the OK attachment type
     * @param string      $name           File name
     * @param string|null $path           Local file, deleted after sending
     * @param string|null $telegramFileId Telegram file to download
     * @param int|null    $messageId      messages.id that gets a local copy of the file
     */
    public function __construct(
        public string $chat_id,
        public string $text,
        public string $mime,
        public string $name,
        public ?string $path = null,
        public ?string $telegramFileId = null,
        public ?int $messageId = null,
    ) {
    }
}
