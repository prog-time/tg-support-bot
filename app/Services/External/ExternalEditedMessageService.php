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
use App\Services\TgTopicService;
use phpDocumentor\Reflection\Exception;

class ExternalEditedMessageService extends ExternalService
{
    protected string $typeMessage = '';

    protected ExternalMessageDto $update;

    protected TgTopicService $tgTopicService;

    protected ?BotUser $botUser;

    protected ?ExternalUser $externalUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(ExternalMessageDto $update)
    {
        parent::__construct($update);

        $this->messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'editTextMessage',
            'typeSource' => 'private',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
        ]);
    }

    /**
     * @return ExternalMessageAnswerDto
     *
     * @throws \Exception
     */
    public function handleUpdate(): ExternalMessageAnswerDto
    {
        try {
            if (!empty($this->update->text)) {
                $resultQuery = $this->editMessageText();
            } else {
                throw new \Exception('Неизвестный тип события!');
            }

            if (empty($resultQuery->ok)) {
                throw new \Exception($resultQuery->rawData['result'], 1);
            }

            $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                'message_thread_id' => $this->botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.incoming'),
            ]));

            return $this->saveMessage($resultQuery);
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
    private function editMessageText(): TelegramAnswerDto
    {
        try {
            $externalUser = ExternalUser::where([
                'external_id' => $this->update->external_id,
            ])->first();

            if (empty($externalUser)) {
                throw new Exception('Пользователь не найден!', 1);
            }

            $messageData = Message::where([
                'message_type' => 'incoming',
                'platform' => $externalUser->source,
                'from_id' => $this->update->message_id,
            ])->first();

            $toIdMessage = $messageData->to_id ?? null;
            if (empty($toIdMessage)) {
                throw new \Exception('Сообщение не найдено!', 1);
            }

            $this->messageParamsDTO->methodQuery = 'editMessageText';
            $this->messageParamsDTO->text = $this->update->text;
            $this->messageParamsDTO->message_id = $toIdMessage;

            $resultQuery = SendMessage::execute($this->botUser, $this->messageParamsDTO);

            $this->saveMessage($resultQuery);

            return $resultQuery;
        } catch (\Exception $e) {
            return TelegramAnswerDto::fromData([
                'ok' => false,
                'response_code' => 404,
                'result' => $e->getCode() === 1 ? $e->getMessage() : 'Ошибка отправки запроса',
            ]);
        }
    }

    /**
     * @param TelegramAnswerDto $resultQuery
     *
     * @return ExternalMessageAnswerDto
     */
    protected function saveMessage(TelegramAnswerDto $resultQuery): ExternalMessageAnswerDto
    {
        $message = Message::where([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'incoming',
            'to_id' => $resultQuery->message_id,
        ])->first();

        $message->externalMessage()->update([
            'text' => $resultQuery->text,
            'file_id' => $resultQuery->fileId,
        ]);

        $message->save();

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
