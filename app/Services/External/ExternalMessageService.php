<?php

namespace App\Services\External;

use App\Actions\Telegram\SendMessage;
use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\External\ExternalMessageResponseDto;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Models\Message;

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
                    'platform' => $updateData->source,
                ])->first();
            } else {
                $externalUser = ExternalUser::create([
                    'external_id' => $updateData->external_id,
                    'source' => $updateData->source,
                ]);

                $botUser = BotUser::create([
                    'chat_id' => $externalUser->id,
                    'platform' => $updateData->source,
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
            if (empty($this->update->text)) {
                throw new \Exception('Текст сообщения не найден!', 1);
            }

            $resultQuery = $this->sendMessage();
            if (empty($resultQuery->ok)) {
                throw new \Exception('Ошибка отправки запроса!', 1);
            }

            $saveMessageData = $this->saveMessage($resultQuery);

            $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                'message_thread_id' => $this->botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.incoming'),
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
     * @return TelegramAnswerDto
     */
    protected function sendMessage(): TelegramAnswerDto
    {
        return SendMessage::execute($this->botUser, TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'typeSource' => 'private',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'text' => $this->update->text,
        ]));
    }

    /**
     * @param TelegramAnswerDto $resultQuery
     *
     * @return ExternalMessageAnswerDto
     */
    protected function saveMessage(TelegramAnswerDto $resultQuery): ExternalMessageAnswerDto
    {
        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'incoming',
            'from_id' => time(),
            'to_id' => $resultQuery->message_id,
        ]);
        $message->externalMessage()->create([
            'text' => $resultQuery->text,
            'file_id' => $resultQuery->fileId,
        ]);

        return ExternalMessageAnswerDto::from([
            'status' => true,
            'result' => ExternalMessageResponseDto::from([
                'message_type' => 'incoming',
                'to_id' => $message->to_id,
                'from_id' => $message->from_id,
                'text' => $message->externalMessage->text,
                'date' => $message->created_at->format('d.m.Y H:i:s'),
                'content_type' => $message->file_type ?? 'text',
                'file_id' => $message->externalMessage->file_id,
                'file_url' => $message->externalMessage->file_url,
                'file_type' => $message->externalMessage->file_type,
            ]),
        ]);
    }
}
