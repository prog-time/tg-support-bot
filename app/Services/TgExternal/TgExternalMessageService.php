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
use App\Services\ActionService\Send\FromTgMessageService;
use phpDocumentor\Reflection\Exception;

class TgExternalMessageService extends FromTgMessageService
{
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return ExternalMessageAnswerDto
     */
    public function handleUpdate(): ExternalMessageAnswerDto
    {
        try {
            if ($this->update->typeQuery !== 'message') {
                throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
            }

            $resultData = [
                'source' => $this->botUser->externalUser->source,
                'external_id' => $this->botUser->externalUser->external_id,
                'message' => [
                    'content_type' => 'text',
                    'message_type' => 'outgoing',
                    'to_id' => time(),
                    'from_id' => $this->update->messageId,
                    'text' => $this->update->text ?? $this->update->caption,
                    'date' => date('d.m.Y H:i'),
                    'file_url' => null,
                    'file_id' => null,
                    'file_type' => null,
                ],
            ];

            if (!empty($this->update->fileId)) {
                $resultData['message'] = array_merge($resultData['message'], $this->sendDocument());
            } elseif (!empty($this->update->rawData['message']['location'])) {
                $resultData['message'] = array_merge($resultData['message'], $this->sendLocation());
            } elseif (!empty($this->update->rawData['message']['contact'])) {
                $resultData['message'] = array_merge($resultData['message'], $this->sendContact());
            }

            $webhookUrl = $this->botUser->externalUser->externalSource->webhook_url;
            $messageData = WebhookMessageDto::fromArray($resultData);
            $saveMessageData = $this->saveMessage($messageData);
            if (!empty($webhookUrl)) {
                SendWebhookMessage::dispatch($webhookUrl, [
                    'type_query' => 'send_message',
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
            dump($e->getMessage());
            return ExternalMessageAnswerDto::from([
                'status' => false,
                'error' => $e->getCode() === 1 ? $e->getMessage() : 'Ошибка обработки запроса!',
            ]);
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
            if (empty($this->update->fileId)) {
                throw new Exception('Параметр fileId не найден!');
            }

            if (!empty($this->update->rawData['message']['photo'])) {
                $fileType = 'photo';
            } else {
                $fileType = 'document';
            }

            return [
                'content_type' => 'file',
                'file_id' => $this->update->fileId,
                'file_url' => TelegramHelper::getFilePublicPath($this->update->fileId),
                'file_type' => $fileType,
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
     * @return ExternalMessageAnswerDto
     */
    protected function saveMessage(mixed $resultQuery): ExternalMessageAnswerDto
    {
        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'outgoing',
            'from_id' => $resultQuery->message->from_id,
            'to_id' => $resultQuery->message->to_id,
        ]);

        $message->externalMessage()->create([
            'text' => $resultQuery->message->text ?? null,
            'file_id' => $resultQuery->message->file_id ?? null,
            'file_type' => $resultQuery->message->file_type ?? null,
        ]);

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
