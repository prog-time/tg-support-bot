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
     * Send photo
     *
     * @return mixed
     */
    abstract protected function sendPhoto(): mixed;

    /**
     * Send document
     *
     * @return mixed
     */
    abstract protected function sendDocument(): mixed;

    /**
     * Send location
     *
     * @return mixed
     */
    abstract protected function sendLocation(): mixed;

    /**
     * Send voice
     *
     * @return mixed
     */
    abstract protected function sendVoice(): mixed;

    /**
     * Send sticker
     *
     * @return mixed
     */
    abstract protected function sendSticker(): mixed;

    /**
     * Send video note
     *
     * @return mixed
     */
    abstract protected function sendVideoNote(): mixed;

    /**
     * Send contact info
     *
     * @return mixed
     */
    abstract protected function sendContact(): mixed;

    /**
     * Send text message
     *
     * @return mixed
     */
    abstract protected function sendMessage(): mixed;

    /**
     * Save message
     *
     * @param TelegramAnswerDto $resultQuery
     *
     * @return void
     */
    abstract protected function saveMessage(mixed $resultQuery): void;
}
