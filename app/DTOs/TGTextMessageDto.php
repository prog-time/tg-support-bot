<?php

namespace App\DTOs;

use \CURLFile;
use Spatie\LaravelData\Data;

class TGTextMessageDto extends Data
{
    public function __construct(
        public string   $methodQuery,
        public ?string  $typeSource,
        public int      $chat_id,
        public ?int     $message_id,
        public ?int     $message_thread_id,
        public ?string  $text,
        public ?string  $caption,
        public ?string  $parse_mode = 'html',
        public ?array   $reply_markup,
        public ?array   $reply_parameters,

        public ?array   $contact,

        public ?string  $file_id,
        public ?string  $photo,
        public ?string  $document,
        public ?string  $voice,
        public ?string  $sticker,
        public ?string  $video_note,

        public ?float  $latitude,
        public ?float  $longitude,
    ) {}

    /**
     * @return array
     */
    public function toArray(): array
    {
        $dataMessage = array_filter(parent::toArray(), fn($value) => !is_null($value));
        unset($dataMessage['methodQuery']);

        if (!empty($dataMessage['typeSource'])) {
            unset($dataMessage['typeSource']);
        }

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
