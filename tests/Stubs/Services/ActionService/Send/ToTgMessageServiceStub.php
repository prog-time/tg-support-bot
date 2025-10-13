<?php

namespace Tests\Stubs\Services\ActionService\Send;

use App\DTOs\TelegramAnswerDto;
use App\Services\ActionService\Send\ToTgMessageService;
use Tests\Traits\HasGettersForHasTemplate;

class ToTgMessageServiceStub extends ToTgMessageService
{
    use HasGettersForHasTemplate;

    /**
     * @return mixed
     */
    public function handleUpdate(): mixed
    {
        return null;
    }

    /**
     * Отправка изображения
     *
     * @return TelegramAnswerDto
     */
    protected function sendPhoto(): TelegramAnswerDto
    {
        return $this->getTelegramAnswerDto();
    }

    /**
     * Отправка документа
     *
     * @return TelegramAnswerDto
     */
    protected function sendDocument(): TelegramAnswerDto
    {
        return $this->getTelegramAnswerDto();
    }

    /**
     * Отправка геолокации
     *
     * @return TelegramAnswerDto
     */
    protected function sendLocation(): TelegramAnswerDto
    {
        return $this->getTelegramAnswerDto();
    }

    /**
     * Отправка голосового сообщения
     *
     * @return TelegramAnswerDto
     */
    protected function sendVoice(): TelegramAnswerDto
    {
        return $this->getTelegramAnswerDto();
    }

    /**
     * Отправка стикеров
     *
     * @return TelegramAnswerDto
     */
    protected function sendSticker(): TelegramAnswerDto
    {
        return $this->getTelegramAnswerDto();
    }

    /**
     * Отправка видео-сообщения
     *
     * @return TelegramAnswerDto
     */
    protected function sendVideoNote(): TelegramAnswerDto
    {
        return $this->getTelegramAnswerDto();
    }

    /**
     * Отправка контакта
     *
     * @return TelegramAnswerDto
     */
    protected function sendContact(): TelegramAnswerDto
    {
        return $this->getTelegramAnswerDto();
    }

    /**
     * Отправка текстового сообщения
     *
     * @return TelegramAnswerDto
     */
    protected function sendMessage(): TelegramAnswerDto
    {
        return $this->getTelegramAnswerDto();
    }

    /**
     * Сохранения сообщения
     *
     * @param TelegramAnswerDto $resultQuery
     *
     * @return mixed
     */
    protected function saveMessage(mixed $resultQuery): mixed
    {
        return null;
    }
}
