<?php

namespace App\Services\ActionService\Send;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Services\TgTopicService;

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

    protected TgTopicService $tgTopicService;

    /**
     * @return void
     *
     * @throws \Exception
     */
    abstract public function handleUpdate(): void;

    /**
     * Отправка изображения
     *
     * @return mixed
     */
    abstract protected function sendPhoto(): mixed;

    /**
     * Отправка документа
     *
     * @return mixed
     */
    abstract protected function sendDocument(): mixed;

    /**
     * Отправка геолокации
     *
     * @return mixed
     */
    abstract protected function sendLocation(): mixed;

    /**
     * Отправка голосового сообщения
     *
     * @return mixed
     */
    abstract protected function sendVoice(): mixed;

    /**
     * Отправка стикеров
     *
     * @return mixed
     */
    abstract protected function sendSticker(): mixed;

    /**
     * Отправка видео-сообщения
     *
     * @return mixed
     */
    abstract protected function sendVideoNote(): mixed;

    /**
     * Отправка контакта
     *
     * @return mixed
     */
    abstract protected function sendContact(): mixed;

    /**
     * Отправка текстового сообщения
     *
     * @return mixed
     */
    abstract protected function sendMessage(): mixed;

    /**
     * Сохранения сообщения
     *
     * @param TelegramAnswerDto $resultQuery
     *
     * @return void
     */
    abstract protected function saveMessage(mixed $resultQuery): void;
}
