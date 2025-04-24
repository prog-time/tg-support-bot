<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

class TelegramTopicDto extends Data
{
    public function __construct(
        public int|string      $message_thread_id,
        public ?string  $name,
        public ?string  $icon_color,
        public ?string  $icon_custom_emoji_id
    ) {}

    public static function fromData(array $dataTopic): self
    {
        return new self(
            message_thread_id: $dataTopic['message_thread_id'],
            name: $dataTopic['name'] ?? null,
            icon_color: $dataTopic['icon_color'] ?? null,
            icon_custom_emoji_id: $dataTopic['icon_custom_emoji_id'] ?? null,
        );
    }

    public function toArray(): array
    {
        $dataMessage = array_filter(parent::toArray(), fn($value) => !is_null($value));
        unset($dataMessage['methodQuery']);

        $jsonParams = [
            'reply_markup'
        ];
        foreach ($jsonParams as $jsonParam) {
            if (!empty($dataMessage[$jsonParam])) {
                if (is_array($dataMessage[$jsonParam])) {
                    $dataMessage[$jsonParam] = json_encode($dataMessage[$jsonParam]);
                }
            }
        }

        return $dataMessage;
    }
}
