<?php

namespace App\Services\VK;

use App\Actions\Telegram\SendMessage;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TGTextMessageDto;
use App\DTOs\Vk\VkUpdateDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\ActionService\Send\ToTgMessageService;
use App\Services\TgTopicService;

class VkMessageService extends ToTgMessageService
{
    protected string $source = 'vk';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    protected TgTopicService $tgTopicService;

    public function __construct(VkUpdateDto $update)
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
        if ($this->update->type === 'message_new') {
            if (!empty($this->update->listFileUrl)) {
                $resultQuery = $this->sendDocument();
            } elseif (!empty($this->update->text)) {
                $resultQuery = $this->sendMessage();
            } elseif (!empty($this->update->geo)) {
                $resultQuery = $this->sendLocation();
            }

            if (empty($resultQuery->ok)) {
                throw new \Exception('Ошибка отправки запроса!');
            }

            $this->saveMessage($resultQuery);
            $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                'message_thread_id' => $this->botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.incoming'),
            ]));

            echo 'ok';
        }
    }

    /**
     * Send document
     *
     * @return TelegramAnswerDto
     */
    protected function sendDocument(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendDocument';
        $this->messageParamsDTO->document = $this->update->listFileUrl[0];

        $this->messageParamsDTO->caption = $this->update->text ?? '';
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Send location
     *
     * @return TelegramAnswerDto
     */
    protected function sendLocation(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendLocation';
        $this->messageParamsDTO->latitude = $this->update->geo['coordinates']['latitude'];
        $this->messageParamsDTO->longitude = $this->update->geo['coordinates']['longitude'];
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Send text message
     *
     * @return TelegramAnswerDto
     */
    protected function sendMessage(): TelegramAnswerDto
    {
        $this->messageParamsDTO->text = $this->update->text;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    protected function sendPhoto(): TelegramAnswerDto
    {
        return TelegramAnswerDto::fromData([
            'ok' => false,
            'error_code' => 500,
            'result' => 'Метод sendPhoto не поддерживается!',
        ]);
    }

    protected function sendSticker(): TelegramAnswerDto
    {
        return TelegramAnswerDto::fromData([
            'ok' => false,
            'error_code' => 500,
            'result' => 'Метод sendSticker не поддерживается!',
        ]);
    }

    protected function sendContact(): TelegramAnswerDto
    {
        return TelegramAnswerDto::fromData([
            'ok' => false,
            'error_code' => 500,
            'result' => 'Метод sendContact не поддерживается!',
        ]);
    }

    protected function sendVideoNote(): TelegramAnswerDto
    {
        return TelegramAnswerDto::fromData([
            'ok' => false,
            'error_code' => 500,
            'result' => 'Метод sendVideoNote не поддерживается!',
        ]);
    }

    protected function sendVoice(): TelegramAnswerDto
    {
        return TelegramAnswerDto::fromData([
            'ok' => false,
            'error_code' => 500,
            'result' => 'Метод sendVoice не поддерживается!',
        ]);
    }

    /**
     * Save message in DB
     *
     * @param TelegramAnswerDto $resultQuery
     *
     * @return void
     */
    protected function saveMessage(mixed $resultQuery): void
    {
        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->source,
            'message_type' => $this->typeMessage,
            'from_id' => $this->update->id,
            'to_id' => $resultQuery->message_id,
        ]);
    }
}
