<?php

namespace App\Services\TgExternal;

use App\Actions\Telegram\GetFile;
use App\DTOs\Redis\WebhookMessageDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TelegramUpdateDto;
use App\Helpers\TelegramHelper;
use App\Models\Message;
use App\Services\ActionService\Send\FromTgMessageService;
use App\Services\Webhook\WebhookService;
use Illuminate\Support\Facades\Log;

class TgExternalMessageService extends FromTgMessageService
{
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->typeQuery === 'message') {
                $resultData = [
                    'source' => $this->botUser->externalUser->source,
                    'external_id' => $this->botUser->externalUser->external_id,
                    'message_type' => 'outgoing',

                    'to_id' => time(),
                    'from_id' => $this->update->messageId,

                    'dop_params' => null,

                    'text' => $this->update->text,
                    'file_path' => null,
                    'date' => date('d.m.Y H:i'),
                ];

                if (!empty($this->update->fileId)) {
                    $resultData = array_merge($resultData, $this->sendDocument());
                }

                if (!empty($this->update->rawData['message']['location'])) {
                    $resultData = array_merge($resultData, $this->sendLocation());
                }

                if (!empty($this->update->rawData['message']['contact'])) {
                    $resultData = array_merge($resultData, $this->sendContact());
                }

                $webhookUrl = $this->botUser->externalUser->externalSource->webhook_url;
                $messageData = WebhookMessageDto::fromArray($resultData);
                WebhookService::sendWebhookMessage($webhookUrl, $messageData);

                $this->saveMessage($messageData);

                $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                    'message_thread_id' => $this->botUser->topic_id,
                    'icon_custom_emoji_id' => __('icons.outgoing'),
                ]));
            } else {
                throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
            }
        } catch (\Exception $exception) {
            Log::info('Webhook sent', ['exception' => $exception]);
        }
    }

    protected function sendPhoto(): array
    {
        return [];
    }

    protected function sendSticker(): mixed
    {
        return null;
    }

    protected function sendLocation(): array
    {
        return [
            'location' => $this->update->location,
        ];
    }

    protected function sendMessage(): mixed
    {
        return null;
    }

    protected function sendContact(): array
    {
        $contactData = $this->update->rawData['message']['contact'];

        $textMessage = "Контакт: \n";
        if (!empty($contactData['first_name'])) {
            $textMessage .= "Имя: {$contactData['first_name']}\n";
        }

        if (!empty($contactData['phone_number'])) {
            $textMessage .= "Телефон: {$contactData['phone_number']}\n";
        }

        return [
            'text' => $textMessage,
        ];
    }

    protected function sendDocument(): mixed
    {
        try {
            $fileData = GetFile::execute($this->update->fileId);

            if (empty($fileData->rawData['result']['file_path'])) {
                throw new \Exception('Не удалось получить файл');
            }

            $localFilePath = $fileData->rawData['result']['file_path'];
            $fullFilePath = TelegramHelper::getFilePath($localFilePath);
            return [
                'file_path' => $fullFilePath,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function sendVideoNote(): mixed
    {
        return null;
    }

    protected function sendVoice(): mixed
    {
        return null;
    }

    /**
     * Save message in DB
     *
     * @param mixed $resultQuery
     *
     * @return void
     */
    protected function saveMessage(mixed $resultQuery): void
    {
        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'outgoing',
            'from_id' => $resultQuery->fromId,
            'to_id' => $resultQuery->toId,
        ]);
    }
}
