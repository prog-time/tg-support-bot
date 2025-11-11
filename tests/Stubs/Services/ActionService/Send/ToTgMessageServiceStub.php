<?php

namespace Tests\Stubs\Services\ActionService\Send;

use App\Services\ActionService\Send\ToTgMessageService;
use Tests\Traits\HasGettersForHasTemplate;

class ToTgMessageServiceStub extends ToTgMessageService
{
    use HasGettersForHasTemplate;

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
    }

    /**
     * Отправка изображения
     *
     * @return void
     */
    protected function sendPhoto(): void
    {
    }

    /**
     * Отправка документа
     *
     * @return void
     */
    protected function sendDocument(): void
    {
    }

    /**
     * Отправка геолокации
     *
     * @return void
     */
    protected function sendLocation(): void
    {
    }

    /**
     * Отправка голосового сообщения
     *
     * @return void
     */
    protected function sendVoice(): void
    {
    }

    /**
     * Отправка стикеров
     *
     * @return void
     */
    protected function sendSticker(): void
    {
    }

    /**
     * Отправка видео-сообщения
     *
     * @return void
     */
    protected function sendVideoNote(): void
    {
    }

    /**
     * Отправка контакта
     *
     * @return void
     */
    protected function sendContact(): void
    {
    }

    /**
     * Отправка текстового сообщения
     *
     * @return void
     */
    protected function sendMessage(): void
    {
    }
}
