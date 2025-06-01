<?php

namespace App\Services\External;

use App\DTOs\External\ExternalMessageAnswerDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Models\ExternalUser;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TGTextMessageDto;
use App\DTOs\TelegramAnswerDto;
use App\Services\TgTopicService;
use App\Actions\Telegram\SendMessage;
use App\DTOs\External\ExternalMessageDto;

class ExternalMessageService
{
    protected string $typeMessage = '';
    protected ExternalMessageDto $update;
    protected TgTopicService $tgTopicService;
    protected ?BotUser $botUser;
    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(ExternalMessageDto $update) {
        $this->update = $update;
        $this->tgTopicService = new TgTopicService();

        $this->botUser = $this->getBotUser($this->update);

        if (empty($this->botUser)) {
            throw new \Exception('Пользователя не существует!');
        }

        $this->messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'typeSource' => 'private',
            'chat_id' => env('TELEGRAM_GROUP_ID'),
            'message_thread_id' => $this->botUser->topic_id,
        ]);
    }

    /**
     * Get user data
     *
     * @param ExternalMessageDto $updateData
     * @return BotUser|null
     */
    private function getBotUser(ExternalMessageDto $updateData): ?BotUser
    {
        try {
            $externalUser = ExternalUser::where([
                'external_id' => $updateData->external_id,
                'source' => $updateData->source
            ])->first();

            dump($externalUser);

            if (!empty($externalUser)) {
                $botUser = BotUser::where([
                    'chat_id' => $externalUser->id,
                    'platform' => 'external_source',
                ])->first();
            } else {
                $externalUser = ExternalUser::create([
                    'external_id' => $updateData->external_id,
                    'source' => $updateData->source
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
     * @throws \Exception
     */
    public function handleUpdate(): ExternalMessageAnswerDto
    {
        try {
            if (empty($this->update->attachments) && empty($this->update->text)) {
                throw new \Exception("Неизвестный тип события!", 1);
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
                    throw new \Exception("Ошибка отправки запроса!", 1);
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
     * Send document
     * @return TelegramAnswerDto
     */
    protected function sendDocument(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendDocument';
        $this->messageParamsDTO->caption = $this->update->text;

        foreach ($this->update->attachments as $attachment) {
            try {
                $this->messageParamsDTO->document = $attachment->url;
                $resultQuery = SendMessage::execute($this->botUser, $this->messageParamsDTO);

                if (empty($resultQuery->ok)) {
                    throw new \Exception("Ошибка отправки запроса!");
                }
            } catch (\Exception $e) {
                break;
            }
        }

        return $resultQuery;
    }

    /**
     * Send list document
     *
     * @return TelegramAnswerDto
     */
    protected function sendListDocument(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendDocument';

        foreach ($this->update->attachments as $attachment) {
            try {
                $this->messageParamsDTO->document = $attachment->url;
                $resultQuery = SendMessage::execute($this->botUser, $this->messageParamsDTO);

                if (empty($resultQuery->ok)) {
                    throw new \Exception("Ошибка отправки запроса!");
                }
            } catch (\Exception $e) {
                break;
            }
        }

        if (!empty($this->update->text)) {
            $resultQuery = SendMessage::execute($this->botUser, TGTextMessageDto::from([
                'methodQuery' =>'sendMessage',
                'typeSource' => 'private',
                'chat_id' => env('TELEGRAM_GROUP_ID'),
                'message_thread_id' => $this->botUser->topic_id,
                'text' => $this->update->text,
            ]));
        }

        return $resultQuery;
    }

    /**
     * Send text message
     * @return TelegramAnswerDto
     */
    protected function sendMessage(): TelegramAnswerDto
    {
        $this->messageParamsDTO->text = $this->update->text;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Save message in DB
     * @param TelegramAnswerDto $resultQuery
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

        return ExternalMessageAnswerDto::from([
            'status' => true,
            'message_id' => $messageData['from_id'],
        ]);
    }

}
