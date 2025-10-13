<?php

namespace App\Services\TgExternal;

use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageResponseDto;
use App\DTOs\Redis\WebhookMessageDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TelegramUpdateDto;
use App\Helpers\TelegramHelper;
use App\Jobs\SendWebhookMessage;
use App\Models\Message;
use App\Services\ActionService\Edit\FromTgEditService;

class TgExternalEditService extends FromTgEditService
{
    private Message $messageData;

    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);

        $this->messageData = Message::where([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'outgoing',
            'from_id' => $this->update->messageId,
        ])->first();

        $this->messageData->externalMessage->text = $this->update->text;
    }

    /**
     * @return ExternalMessageAnswerDto
     */
    public function handleUpdate(): ExternalMessageAnswerDto
    {
        try {
            if ($this->update->typeQuery !== 'edited_message') {
                throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
            }

            $resultData = [
                'source' => $this->botUser->externalUser->source,
                'external_id' => $this->botUser->externalUser->external_id,
                'message' => $this->getDto()->toArrayFull(),
            ];

            if (!empty($this->update->rawData['edited_message']['photo']) || !empty($this->update->rawData['edited_message']['document'])) {
                $resultData = array_merge($resultData, $this->editMessageCaption());
            }

            $webhookUrl = $this->botUser->externalUser->externalSource->webhook_url;
            $messageData = WebhookMessageDto::fromArray($resultData);

            $saveMessageData = $this->saveMessage($messageData);

            if (!empty($webhookUrl)) {
                SendWebhookMessage::dispatch($webhookUrl, [
                    'type_query' => 'edit_message',
                    'externalId' => $messageData->externalId,
                    'message' => $saveMessageData->result->toArray(),
                ]);
            }

            $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                'message_thread_id' => $this->botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.outgoing'),
            ]));

            return $saveMessageData;
        } catch (\Exception $e) {
            return ExternalMessageAnswerDto::from([
                'status' => false,
                'error' => $e->getCode() === 1 ? $e->getMessage() : 'Ошибка обработки запроса!',
            ]);
        }
    }

    /**
     * @return array
     */
    protected function editMessageText(): array
    {
        return [
            'text' => $this->update->text ?? '',
        ];
    }

    /**
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

    /**
     * @return ExternalMessageResponseDto
     */
    private function getDto(): ExternalMessageResponseDto
    {
        return ExternalMessageResponseDto::from([
            'message_type' => 'outgoing',
            'to_id' => $this->messageData->to_id,
            'from_id' => $this->messageData->from_id,
            'text' => $this->messageData->externalMessage->text,
            'date' => $this->messageData->created_at->format('d.m.Y H:i:s'),
            'content_type' => $this->messageData->file_type ?? 'text' ,
            'file_id' => $this->messageData->externalMessage->file_id,
            'file_url' => $this->messageData->externalMessage->file_url,
            'file_type' => $this->messageData->externalMessage->file_type,
        ]);
    }

    /**
     * @param mixed $resultQuery
     *
     * @return ExternalMessageAnswerDto
     */
    protected function saveMessage(mixed $resultQuery): ExternalMessageAnswerDto
    {
        $message = Message::where([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'outgoing',
            'from_id' => $resultQuery->message->from_id,
            'to_id' => $resultQuery->message->to_id,
        ])->first();

        $message->externalMessage->text = $resultQuery->message->text;
        $message->externalMessage->file_id = $resultQuery->message->file_id;
        $message->externalMessage->file_type = $resultQuery->message->file_type;

        $message->externalMessage->save();

        $message->updated_at = now();
        $message->save();

        return ExternalMessageAnswerDto::from([
            'status' => true,
            'result' => ExternalMessageResponseDto::from([
                'message_type' => 'outgoing',
                'to_id' => $message->to_id,
                'from_id' => $message->from_id,
                'text' => $message->externalMessage->text,
                'date' => $message->created_at->format('d.m.Y H:i:s'),
                'content_type' => $message->file_type ?? 'text' ,
                'file_id' => $message->externalMessage->file_id,
                'file_url' => $message->externalMessage->file_url,
                'file_type' => $message->externalMessage->file_type,
            ]),
        ]);
    }
}
