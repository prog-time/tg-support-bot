<?php

namespace App\Services\External;

use App\Actions\Telegram\SendMessage;
use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageDto;
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
                throw new \Exception('Ошибка отправки запроса!');
            }

            $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                'message_thread_id' => $this->botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.incoming'),
            ]));

            return ExternalMessageAnswerDto::from([
                'status' => true,
                'message_id' => time(),
            ]);
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
    private function editMessageText(): ?TelegramAnswerDto
    {
        try {
            $externalUser = ExternalUser::where([
                'external_id' => $this->update->external_id,
            ])->first();

            if (empty($externalUser)) {
                throw new Exception('External user not found!');
            }

            $messageData = Message::where([
                'message_type' => 'incoming',
                'platform' => $externalUser->source,
                'from_id' => $this->update->message_id,
            ])->first();

            $toIdMessage = $messageData->to_id ?? null;
            if (empty($toIdMessage)) {
                throw new \Exception('Сообщение не найдено!');
            }

            $this->messageParamsDTO->methodQuery = 'editMessageText';
            $this->messageParamsDTO->text = $this->update->text;
            $this->messageParamsDTO->message_id = $toIdMessage;

            return SendMessage::execute($this->botUser, $this->messageParamsDTO);
        } catch (\Exception $e) {
            return null;
        }
    }
}
