<?php

namespace App\Services\External;

use App\Actions\Telegram\SendMessage;
use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\Redis\WebhookMessageDto;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TGTextMessageDto;
use App\Helpers\TelegramHelper;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Models\Message;
use App\Services\Redis\RedisService;

class ExternalMessageService extends ExternalService
{
    public function __construct(ExternalMessageDto $update)
    {
        parent::__construct($update);

        $this->messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'typeSource' => 'private',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
        ]);
    }

    /**
     * Получение информации о пользователе
     *
     * @param ExternalMessageDto $updateData
     *
     * @return BotUser|null
     */
    protected function getBotUser(ExternalMessageDto $updateData): ?BotUser
    {
        try {
            $externalUser = ExternalUser::where([
                'external_id' => $updateData->external_id,
                'source' => $updateData->source,
            ])->first();

            if (!empty($externalUser)) {
                $botUser = BotUser::where([
                    'chat_id' => $externalUser->id,
                    'platform' => 'external_source',
                ])->first();
            } else {
                $externalUser = ExternalUser::create([
                    'external_id' => $updateData->external_id,
                    'source' => $updateData->source,
                ]);

                $botUser = BotUser::create([
                    'chat_id' => $externalUser->id,
                    'platform' => 'external_source',
                ]);

                $botUser->saveNewTopic();
            }

            return $botUser;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return ExternalMessageAnswerDto
     *
     * @throws \Exception
     */
    public function handleUpdate(): ExternalMessageAnswerDto
    {
        try {
            if (empty($this->update->attachments) && empty($this->update->text)) {
                throw new \Exception('Неизвестный тип события!', 1);
            } else {
                if (!empty($this->update->attachments)) {
                    if (count($this->update->attachments) > 1) {
                        $resultQuery = $this->sendListDocument();
                    } else {
                        $resultQuery = $this->sendDocument();
                    }
                } elseif (!empty($this->update->text)) {
                    $resultQuery = $this->sendMessage();
                }

                if (empty($resultQuery->ok)) {
                    throw new \Exception('Ошибка отправки запроса!', 1);
                }

                $saveMessageData = $this->saveMessage($resultQuery);

                $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                    'message_thread_id' => $this->botUser->topic_id,
                    'icon_custom_emoji_id' => __('icons.incoming'),
                ]));

                return $saveMessageData;
            }
        } catch (\Exception $e) {
            return ExternalMessageAnswerDto::from([
                'status' => false,
                'error' => $e->getCode() === 1 ? $e->getMessage() : 'Ошибка обработки запроса!',
            ]);
        }
    }

    /**
     * @return ?TelegramAnswerDto
     */
    protected function sendDocument(): ?TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendDocument';
        $this->messageParamsDTO->caption = $this->update->text;

        $resultQuery = null;
        foreach ($this->update->attachments as $attachment) {
            try {
                $this->messageParamsDTO->document = $attachment->url;
                $resultQuery = SendMessage::execute($this->botUser, $this->messageParamsDTO);

                if (empty($resultQuery->ok)) {
                    throw new \Exception('Ошибка отправки запроса!');
                }
            } catch (\Exception $e) {
                break;
            }
        }

        return $resultQuery;
    }

    /**
     * @return ?TelegramAnswerDto
     */
    protected function sendListDocument(): ?TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendDocument';

        $resultQuery = null;
        foreach ($this->update->attachments as $attachment) {
            try {
                $this->messageParamsDTO->document = $attachment->url;
                $resultQuery = SendMessage::execute($this->botUser, $this->messageParamsDTO);

                if (empty($resultQuery->ok)) {
                    throw new \Exception('Ошибка отправки запроса!');
                }
            } catch (\Exception $e) {
                break;
            }
        }

        if (!empty($this->update->text)) {
            $resultQuery = SendMessage::execute($this->botUser, TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'typeSource' => 'private',
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_thread_id' => $this->botUser->topic_id,
                'text' => $this->update->text,
            ]));
        }

        return $resultQuery;
    }

    /**
     * @return TelegramAnswerDto
     */
    protected function sendMessage(): TelegramAnswerDto
    {
        $this->messageParamsDTO->text = $this->update->text;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * @param TelegramAnswerDto $resultQuery
     *
     * @return ExternalMessageAnswerDto
     */
    protected function saveMessage(TelegramAnswerDto $resultQuery): ExternalMessageAnswerDto
    {
        $messageData = [
            'bot_user_id' => $this->botUser->id,
            'platform' => 'external_source',
            'message_type' => 'incoming',
            'from_id' => time(),
            'to_id' => $resultQuery->message_id,
        ];
        Message::create($messageData);

        if (!empty($resultQuery->rawData['ok'])) {
            $this->redisSaveMessage($resultQuery);
        }

        return ExternalMessageAnswerDto::from([
            'status' => true,
            'message_id' => $messageData['from_id'],
        ]);
    }

    /**
     * @param TelegramAnswerDto $resultQuery
     *
     * @return bool
     */
    private function redisSaveMessage(TelegramAnswerDto $resultQuery): bool
    {
        try {
            $fileId = TelegramHelper::extractFileId($resultQuery->rawData);

            $chatKey = "{$this->update->source}:{$this->update->external_id}";
            return (new RedisService())->saveMessage($chatKey, WebhookMessageDto::fromArray([
                'source' => $this->update->source,
                'external_id' => $this->update->external_id,
                'message_type' => 'incoming',
                'text' => $resultQuery->rawData['result']['text'] ?? $resultQuery->rawData['result']['caption'] ?? '',
                'file_id' => $fileId ?? null,
                'date' => date('Y-m-d H:i:s', $resultQuery->rawData['result']['date']),

                'to_id' => $resultQuery->message_id,
                'from_id' => $resultQuery->message_id,
            ]));
        } catch (\Exception $e) {
            return false;
        }
    }
}
