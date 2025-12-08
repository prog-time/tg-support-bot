<?php

namespace App\Services\ActionService\Send;

use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;

/**
 * Class TemplateMessageService
 */
abstract class TemplateMessageService
{
    protected string $typeMessage = '';

    protected string $source = 'telegram';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    /**
     * @return void
     */
    abstract public function handleUpdate(): void;

    /**
     * Отправка изображения
     *
     * @return void
     */
    abstract protected function sendPhoto(): void;

    /**
     * Отправка документа
     *
     * @return void
     */
    abstract protected function sendDocument(): void;

    /**
     * Отправка геолокации
     *
     * @return void
     */
    abstract protected function sendLocation(): void;

    /**
     * Отправка голосового сообщения
     *
     * @return void
     */
    abstract protected function sendVoice(): void;

    /**
     * Отправка стикеров
     *
     * @return void
     */
    abstract protected function sendSticker(): void;

    /**
     * Отправка видео-сообщения
     *
     * @return void
     */
    abstract protected function sendVideoNote(): void;

    /**
     * Отправка контакта
     *
     * @return void
     */
    abstract protected function sendContact(): void;

    /**
     * Отправка текстового сообщения
     *
     * @return void
     */
    abstract protected function sendMessage(): void;
}
