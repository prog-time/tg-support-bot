<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

/**
 * TelegramTopicDto
 *
 * @property int|string $message_thread_id,
 * @property ?string $name,
 * @property ?string $icon_color,
 * @property ?string $icon_custom_emoji_id
 */
class TelegramTopicDto extends Data
{
    /**
     * @param int|string $message_thread_id,
     * @param?string $name,
     * @param?string $icon_color,
     * @param?string $icon_custom_emoji_id
     */
    public function __construct(
        public int|string $message_thread_id,
        public ?string $name,
        public ?string $icon_color,
        public ?string $icon_custom_emoji_id
    ) {}

    /**
     * @param array $dataTopic
     * @return self
     */
    public static function fromData(array $dataTopic): self
    {
        return new self(
            message_thread_id: $dataTopic['message_thread_id'],
            name: $dataTopic['name'] ?? null,
            icon_color: $dataTopic['icon_color'] ?? null,
            icon_custom_emoji_id: $dataTopic['icon_custom_emoji_id'] ?? null,
        );
    }

    /**
     * @return array
     */
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
