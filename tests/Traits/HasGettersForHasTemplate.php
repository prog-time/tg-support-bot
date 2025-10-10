<?php

namespace Tests\Traits;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Services\TgTopicService;

trait HasGettersForHasTemplate
{
    public function getTypeMessage(): string
    {
        return $this->typeMessage;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getUpdate(): mixed
    {
        return $this->update;
    }

    public function getBotUser(): ?BotUser
    {
        return $this->botUser;
    }

    public function getMessageParamsDTO(): TGTextMessageDto
    {
        return $this->messageParamsDTO;
    }

    public function getTgTopicService(): TgTopicService
    {
        return $this->tgTopicService;
    }

    public function getTelegramAnswerDto(): TelegramAnswerDto
    {
        return new TelegramAnswerDto(
            ok: true,
            message_id: 12345,
            response_code: 200,
            message_thread_id: 67890,
            date: time(),
            message: 'Тестовое сообщение',
            type_error: null,
            text: 'Текст сообщения',
            fileId: '4Jo7ehKTx1Vd99h4',
            rawData: [
                'ok' => true,
                'result' => [
                    'message_id' => 12345,
                    'message_thread_id' => 67890,
                    'date' => time(),
                    'message' => 'Тестовое сообщение',
                    'text' => 'Текст сообщения',
                    'caption' => 'Подпись файла',
                ],
                'response_code' => 200,
                'description' => '', // Можно подставить ошибку для проверки exactTypeError
            ]
        );
    }
}
