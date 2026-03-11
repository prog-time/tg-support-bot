<?php

namespace App\Modules\Telegram\Services\TgMax;

use App\Helpers\TelegramHelper;
use App\Modules\Max\Actions\UploadFileMax;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use App\Modules\Max\Jobs\SendMaxMessageJob;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\Services\ActionService\Send\FromTgMessageService;
use Illuminate\Support\Facades\Log;

class TgMaxMessageService extends FromTgMessageService
{
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

            $text = $this->update->text ?? $this->update->caption ?? null;

            if (!empty($this->update->fileId) && $this->update->fileType === 'photo') {
                $this->sendPhoto();
            } elseif (!empty($this->update->fileId) && $this->update->fileType === 'document') {
                $this->sendDocument();
            } elseif (!empty($this->update->fileId) && $this->update->fileType === 'voice') {
                $this->sendVoice();
            } elseif ($this->update->fileType === 'contact' && !empty($this->update->contact)) {
                $this->sendContact();
            } elseif (!empty($text)) {
                $this->sendMessage($text);
            }
        } catch (\Throwable $e) {
            Log::channel('loki')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * @param string $text
     *
     * @return void
     */
    protected function sendMessage(string $text = ''): void
    {
        SendMaxMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            MaxTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'user_id' => (int) $this->botUser->chat_id,
                'text' => $text,
            ]),
        );
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function sendPhoto(): void
    {
        $telegramFileUrl = TelegramHelper::getFileTelegramPath($this->update->fileId);
        if (empty($telegramFileUrl)) {
            throw new \Exception('Failed to get Telegram file URL', 1);
        }

        $filename = basename(parse_url($telegramFileUrl, PHP_URL_PATH)) . '.jpg';
        $token = app(UploadFileMax::class)->execute($telegramFileUrl, $filename, 'image');

        if (empty($token)) {
            throw new \Exception('Failed to upload image to Max', 1);
        }

        SendMaxMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            MaxTextMessageDto::from([
                'methodQuery' => 'sendImage',
                'user_id' => (int) $this->botUser->chat_id,
                'text' => $this->update->caption ?? '',
                'file_token' => $token,
            ]),
        );
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function sendDocument(): void
    {
        $telegramFileUrl = TelegramHelper::getFileTelegramPath($this->update->fileId);
        if (empty($telegramFileUrl)) {
            throw new \Exception('Failed to get Telegram file URL', 1);
        }

        $filename = $this->update->rawData['message']['document']['file_name']
            ?? basename(parse_url($telegramFileUrl, PHP_URL_PATH));

        $token = app(UploadFileMax::class)->execute($telegramFileUrl, $filename, 'file');
        if (empty($token)) {
            throw new \Exception('Failed to upload file to Max', 1);
        }

        SendMaxMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            MaxTextMessageDto::from([
                'methodQuery' => 'sendFile',
                'user_id' => (int) $this->botUser->chat_id,
                'text' => $this->update->caption ?? '',
                'file_token' => $token,
            ]),
        );
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
        //
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function sendVoice(): void
    {
        $telegramFileUrl = TelegramHelper::getFileTelegramPath($this->update->fileId);
        if (empty($telegramFileUrl)) {
            throw new \Exception('Failed to get Telegram file URL', 1);
        }

        $filename = basename(parse_url($telegramFileUrl, PHP_URL_PATH)) . '.ogg';
        $token = app(UploadFileMax::class)->execute($telegramFileUrl, $filename, 'audio');

        if (empty($token)) {
            throw new \Exception('Failed to upload audio to Max', 1);
        }

        SendMaxMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            MaxTextMessageDto::from([
                'methodQuery' => 'sendAudio',
                'user_id' => (int) $this->botUser->chat_id,
                'file_token' => $token,
            ]),
        );
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendVideoNote(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
        $contact = $this->update->contact;

        $firstName = $contact['first_name'] ?? '';
        $lastName = $contact['last_name'] ?? '';
        $phone = $contact['phone_number'] ?? '';

        $name = trim("{$firstName} {$lastName}");
        $text = "📞 Контакт\nИмя: {$name}\nТелефон: {$phone}";

        SendMaxMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            MaxTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'user_id' => (int) $this->botUser->chat_id,
                'text' => $text,
            ]),
        );
    }
}
