<?php

namespace App\Modules\Telegram\Services\TgOk;

use App\Models\Message;
use App\Modules\Ok\DTOs\OkFileMessageDto;
use App\Modules\Ok\DTOs\OkTextMessageDto;
use App\Modules\Ok\Jobs\SendOkFileMessageJob;
use App\Modules\Ok\Jobs\SendOkSimpleMessageJob;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Telegram\Services\ActionService\Send\FromTgMessageService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

/**
 * Deliver a manager's reply from a forum topic to an OK user: text, photo,
 * document, voice, audio, video, video note, sticker, location and contact.
 */
class TgOkMessageService extends FromTgMessageService
{
    /**
     * @param TelegramUpdateDto $update
     */
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->typeQuery !== 'message') {
                throw new \Exception("Unknown event type: {$this->update->typeQuery}", 1);
            }

            $message = (array) ($this->update->rawData['message'] ?? []);
            $file = $this->detectFile($message);
            $text = (string) ($this->update->text ?? $this->update->caption ?? '');

            if ($file !== null) {
                $this->sendFile($file, $text);
            } elseif (!empty($message['location'])) {
                $this->sendLocationAttachment($message['location']);
            } elseif (!empty($message['contact'])) {
                $this->sendText($this->describeContact($message['contact']));
            } elseif (trim($text) !== '') {
                $this->sendText($text);
            } else {
                Log::channel('app')->warning('TgOkMessageService: unsupported message type, not sent', [
                    'source' => 'ok_reply_unsupported',
                    'bot_user_id' => $this->botUser->id,
                ]);
            }

            $this->markTopicAnswered();
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * Find the file of a Telegram message. An animated sticker has no image
     * form and is sent as its emoji.
     *
     * @param array $message
     *
     * @return array{id: string, mime: string, name: string}|null
     */
    private function detectFile(array $message): ?array
    {
        $sticker = $message['sticker'] ?? null;
        if (is_array($sticker) && empty($sticker['is_animated']) && empty($sticker['is_video'])) {
            return $this->fileData($sticker, 'image/webp', 'sticker.webp');
        }

        if (!empty($message['photo'])) {
            return $this->fileData((array) end($message['photo']), 'image/jpeg', 'photo.jpg');
        }

        foreach (['video' => ['video/mp4', 'video.mp4'], 'video_note' => ['video/mp4', 'video.mp4'], 'animation' => ['video/mp4', 'animation.mp4'], 'voice' => ['audio/ogg', 'voice.ogg'], 'audio' => ['audio/mpeg', 'audio.mp3'], 'document' => ['application/octet-stream', 'file']] as $key => [$mime, $name]) {
            if (!empty($message[$key])) {
                return $this->fileData((array) $message[$key], $mime, $name);
            }
        }

        return null;
    }

    /**
     * @param array  $item
     * @param string $mime
     * @param string $name
     *
     * @return array{id: string, mime: string, name: string}
     */
    private function fileData(array $item, string $mime, string $name): array
    {
        return [
            'id' => (string) ($item['file_id'] ?? ''),
            'mime' => (string) ($item['mime_type'] ?? $mime),
            'name' => (string) ($item['file_name'] ?? $name),
        ];
    }

    /**
     * @param array{id: string, mime: string, name: string} $file
     * @param string                                        $text
     *
     * @return void
     */
    private function sendFile(array $file, string $text): void
    {
        $message = $this->saveReply($text);

        SendOkFileMessageJob::dispatch(OkFileMessageDto::from([
            'chat_id' => (string) $this->botUser->chat_id,
            'text' => $text,
            'mime' => $file['mime'],
            'name' => $file['name'],
            'telegramFileId' => $file['id'],
            'messageId' => $message->id,
        ]));
    }

    /**
     * @param array $location
     *
     * @return void
     */
    private function sendLocationAttachment(array $location): void
    {
        $lat = $location['latitude'] ?? null;
        $lon = $location['longitude'] ?? null;

        $this->saveReply("📍 Геопозиция\nШирота: {$lat}\nДолгота: {$lon}");

        SendOkSimpleMessageJob::dispatch(
            OkTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $this->botUser->chat_id,
                'attachment' => ['type' => 'LOCATION', 'payload' => ['latitude' => $lat, 'longitude' => $lon]],
            ]),
        );
    }

    /**
     * @param array $contact
     *
     * @return string
     */
    private function describeContact(array $contact): string
    {
        $name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));

        return "📞 Контакт\nИмя: {$name}\nТелефон: " . ($contact['phone_number'] ?? '');
    }

    /**
     * @param string $text
     *
     * @return void
     */
    private function sendText(string $text): void
    {
        $this->saveReply($text);

        SendOkSimpleMessageJob::dispatch(
            OkTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $this->botUser->chat_id,
                'text' => $text,
            ]),
        );
    }

    /**
     * @param string $text
     *
     * @return Message
     */
    private function saveReply(string $text): Message
    {
        return Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => 'outgoing',
            'from_id' => $this->update->messageId ?? 0,
            'to_id' => 0,
            'text' => $text !== '' ? $text : null,
        ]);
    }

    /**
     * @return void
     */
    private function markTopicAnswered(): void
    {
        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'editForumTopic',
            'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'icon_custom_emoji_id' => __('icons.outgoing'),
        ]));
    }

    /**
     * Required by the base class; handleUpdate() routes the message itself.
     *
     * @return void
     */
    protected function sendMessage(): void
    {
    }

    /**
     * @return void
     */
    protected function sendPhoto(): void
    {
    }

    /**
     * @return void
     */
    protected function sendDocument(): void
    {
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
    }

    /**
     * @return void
     */
    protected function sendVoice(): void
    {
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
    }

    /**
     * @return void
     */
    protected function sendVideoNote(): void
    {
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
    }
}
