<?php

namespace App\Modules\Max\Services;

use App\Models\BotUser;
use App\Modules\Max\DTOs\MaxUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendMaxTelegramMessageJob;
use App\Modules\Telegram\Services\ActionService\Send\ToTgMessageService;
use Illuminate\Support\Facades\Log;

class MaxMessageService extends ToTgMessageService
{
    protected string $source = 'max';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(MaxUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * Handle an incoming Max update and route it to the appropriate send method.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->type !== 'message_created') {
                throw new \Exception('Unknown event type', 1);
            }

            Log::channel('loki')->info('MaxMessageService: incoming update', [
                'text' => $this->update->text,
                'listFileUrl' => $this->update->listFileUrl,
                'listAttachments' => $this->update->listAttachments,
                'rawAttachments' => $this->update->rawData['message']['body']['attachments'] ?? [],
            ]);

            if (!empty($this->update->listAttachments)) {
                $this->sendAttachments();
            } elseif (!empty($this->update->text)) {
                $this->sendMessage();
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
     * Dispatch a separate job for each attachment.
     * The text caption is attached to the first attachment only.
     *
     * @return void
     */
    protected function sendAttachments(): void
    {
        $caption = $this->update->text ?? '';
        $isFirst = true;

        foreach ($this->update->listAttachments as $attachment) {
            $type = $attachment['type'] ?? null;
            $url = $attachment['file_id'] ?? null;

            if (empty($url) || empty($type)) {
                continue;
            }

            $currentCaption = $isFirst ? $caption : '';
            $isFirst = false;

            switch ($type) {
                case 'photo':
                    $this->dispatchPhoto($url, $currentCaption);
                    break;

                case 'document':
                    $this->dispatchDocument($url, $currentCaption, $attachment['file_name'] ?? null);
                    break;

                case 'voice':
                    $this->dispatchVoice($url);
                    break;

                case 'video':
                    // Telegram Bot API sendVideo does not support direct URL uploads;
                    // forward as a document instead so the file is delivered.
                    $this->dispatchDocument($url, $currentCaption, $attachment['file_name'] ?? null);
                    break;

                default:
                    Log::channel('loki')->warning('MaxMessageService: unsupported attachment type', [
                        'type' => $type,
                        'url' => $url,
                    ]);
                    break;
            }
        }
    }

    /**
     * Dispatch a photo to the Telegram group topic.
     *
     * @param string $url
     * @param string $caption
     *
     * @return void
     */
    protected function dispatchPhoto(string $url, string $caption = ''): void
    {
        $dto = TGTextMessageDto::from([
            'methodQuery' => 'sendPhoto',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'photo' => $url,
            'caption' => $caption !== '' ? $caption : null,
        ]);

        Log::channel('loki')->info('MaxMessageService: dispatchPhoto', [
            'photo' => $url,
            'caption' => $caption,
        ]);

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );
    }

    /**
     * Dispatch a document (or video forwarded as document) to the Telegram group topic.
     *
     * @param string      $url
     * @param string      $caption
     * @param string|null $fileName
     *
     * @return void
     */
    protected function dispatchDocument(string $url, string $caption = '', ?string $fileName = null): void
    {
        $dto = TGTextMessageDto::from([
            'methodQuery' => 'sendDocument',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'document' => $url,
            'caption' => $caption !== '' ? $caption : null,
        ]);

        Log::channel('loki')->info('MaxMessageService: dispatchDocument', [
            'document' => $url,
            'caption' => $caption,
            'fileName' => $fileName,
        ]);

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );
    }

    /**
     * Dispatch a voice message to the Telegram group topic.
     *
     * @param string $url
     *
     * @return void
     */
    protected function dispatchVoice(string $url): void
    {
        $dto = TGTextMessageDto::from([
            'methodQuery' => 'sendVoice',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'voice' => $url,
        ]);

        Log::channel('loki')->info('MaxMessageService: dispatchVoice', [
            'voice' => $url,
        ]);

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );
    }

    /**
     * Send a plain text message to the Telegram group topic.
     *
     * @return void
     */
    protected function sendMessage(): void
    {
        $dto = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'text' => $this->update->text,
        ]);

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );
    }

    /**
     * Not used for Max → TG direction (Max sends photos via listAttachments).
     *
     * @return void
     */
    protected function sendPhoto(): void
    {
        //
    }

    /**
     * Not used for Max → TG direction (Max sends documents via listAttachments).
     *
     * @return void
     */
    protected function sendDocument(): void
    {
        //
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
    protected function sendContact(): void
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
    protected function sendVoice(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
        //
    }
}
