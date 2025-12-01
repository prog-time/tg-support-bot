<?php

namespace App\DTOs;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * Отправка сообщения в TG
 *
 * @property string      $methodQuery
 * @property string|null $typeSource
 * @property int         $chat_id
 * @property int|null    $message_id
 * @property int|null    $message_thread_id
 * @property string|null $text
 * @property string|null $caption
 * @property string|null $parse_mode
 * @property array|null  $reply_markup
 * @property array|null  $reply_parameters
 * @property array|null  $contact
 * @property string|null $file_id
 * @property string|null $photo
 * @property string|null $document
 * @property string|null $voice
 * @property string|null $sticker
 * @property string|null $video_note
 * @property array|null  $media
 * @property float|null  $latitude
 * @property float|null  $longitude
 */
class TGTextMessageDto extends Data
{
    public function __construct(
        public string         $methodQuery,
        public ?string        $token,
        public ?string        $typeSource,
        public int            $chat_id,
        public ?int           $message_id,
        public ?int           $message_thread_id,
        public ?string        $text,
        public ?string        $caption,
        public ?string        $parse_mode = 'html',
        public ?array         $reply_markup = null,
        public ?array         $reply_parameters = null,
        public ?array         $contact = null,
        public ?string        $file_id = null,
        public ?string        $photo = null,
        public ?string        $document = null,
        public ?UploadedFile  $uploaded_file = null,
        public ?string        $uploaded_file_path = null,
        public ?string        $voice = null,
        public ?string        $sticker = null,
        public ?string        $video_note = null,
        public ?array         $media = null,
        public ?float         $latitude = null,
        public ?float         $longitude = null,
        public int|string|null         $icon_custom_emoji_id = null,
    ) {
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $dataMessage = array_filter(parent::toArray(), fn ($value) => !is_null($value));
        unset($dataMessage['methodQuery']);

        if (!empty($dataMessage['typeSource'])) {
            unset($dataMessage['typeSource']);
        }

        if (!empty($dataMessage['token'])) {
            unset($dataMessage['token']);
        }

        if (!empty($dataMessage['media'])) {
            $dataMessage = array_merge($dataMessage, $this->prepareMedia($dataMessage['media']));
        }

        $jsonParams = [
            'reply_markup',
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

    /**
     * @param array $media
     *
     * @return array
     */
    private function prepareMedia(array $media): array
    {
        $mediaData = [];
        if (!empty($media)) {
            foreach ($media as $key => $item) {
                $fileCode = 'file' . $key;
                $mediaData['media'][] = [
                    'type' => 'document',
                    'media' => 'attach://' . $fileCode,
                ];
                $mediaData[$fileCode] = new \CURLFile($item['file']);
            }
        }
        return $mediaData;
    }
}
