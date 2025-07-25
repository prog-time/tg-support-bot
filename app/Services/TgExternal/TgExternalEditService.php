<?php

namespace App\Services\TgExternal;

use App\DTOs\Redis\WebhookMessageDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TelegramUpdateDto;
use App\Helpers\TelegramHelper;
use App\Models\Message;
use App\Services\ActionService\Edit\FromTgEditService;
use App\Services\Webhook\WebhookService;

class TgExternalEditService extends FromTgEditService
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
        if ($this->update->typeQuery === 'edited_message') {
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

            if (!empty($this->update->rawData['edited_message']['photo']) || !empty($this->update->rawData['edited_message']['document'])) {
                $resultData = array_merge($resultData, $this->editMessageCaption());
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
    }

    /**
     * Edit message
     *
     * @return array
     */
    protected function editMessageText(): array
    {
        return [
            'text' => $this->update->text ?? '',
        ];
    }

    /**
     * Edit message with photo or document
     *
     * @return array
     */
    protected function editMessageCaption(): array
    {
        try {
            return [
                'file_path' => TelegramHelper::getFilePublicPath($this->update->fileId),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function saveMessage(mixed $resultQuery): void
    {
        $whereData = [
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'outgoing',
            'from_id' => $resultQuery->fromId,
        ];
        Message::where($whereData)->update([
            'updated_at' => now(),
        ]);
    }
}
