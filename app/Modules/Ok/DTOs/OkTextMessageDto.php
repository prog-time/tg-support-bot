<?php

namespace App\Modules\Ok\DTOs;

use Spatie\LaravelData\Data;

class OkTextMessageDto extends Data
{
    /**
     * @param string     $methodQuery Internal method name for OkMethods::sendQuery()
     * @param int|string $chat_id     OK chat id, `chat:...`
     * @param string     $text        Message text
     * @param array|null $attachment  OK attachment without a file, e.g. LOCATION
     */
    public function __construct(
        public string $methodQuery,
        public int|string $chat_id,
        public string $text = '',
        public ?array $attachment = null,
    ) {
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $data = array_filter(parent::toArray(), fn ($value) => !is_null($value));
        unset($data['methodQuery']);

        return $data;
    }
}
