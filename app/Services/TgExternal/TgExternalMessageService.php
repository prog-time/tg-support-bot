<?php

namespace App\Services\TgExternal;

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
     * @return WebhookMessageDto|null
     */
    public function handleUpdate(): ?WebhookMessageDto
    {
        try {
            if ($this->update->typeQuery !== 'message') {
                throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
            }

            $resultData = [
                'source' => $this->botUser->externalUser->source,
                'external_id' => $this->botUser->externalUser->external_id,
                'message_type' => 'outgoing',

                'to_id' => time(),
                'from_id' => $this->update->messageId,

                'dop_params' => null,

                'text' => $this->update->text,
                'file_path' => null,
                'file_id' => null,
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

            return $messageData;
        } catch (\Exception $exception) {
            Log::info('Webhook sent', ['exception' => $exception]);

            return null;
        }
    }

    /**
     * @return array
     */
    protected function sendPhoto(): array
    {
        return [];
    }

    /**
     * @return mixed
     */
    protected function sendSticker(): mixed
    {
        return null;
    }

    /**
     * @return array
     */
    protected function sendLocation(): array
    {
        return [
            'location' => $this->update->location,
        ];
    }

    /**
     * @return mixed
     */
    protected function sendMessage(): mixed
    {
        return null;
    }

    /**
     * @return string[]
     */
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

    /**
     * @return mixed
     */
    protected function sendDocument(): mixed
    {
        try {
            return [
                'file_id' => $this->update->fileId,
                'file_path' => TelegramHelper::getFilePublicPath($this->update->fileId),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @return mixed
     */
    protected function sendVideoNote(): mixed
    {
        return null;
    }

    /**
     * @return mixed
     */
    protected function sendVoice(): mixed
    {
        return null;
    }

    /**
     * @param mixed $resultQuery
     *
     * @return void
     */
    protected function saveMessage(mixed $resultQuery): void
    {
        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'outgoing',
            'from_id' => $resultQuery->fromId,
            'to_id' => $resultQuery->toId,
        ]);

        $message->externalMessage()->create([
            'text' => $resultQuery->text ?? null,
            'file_id' => $resultQuery->fileId ?? null,
        ]);
    }
}
