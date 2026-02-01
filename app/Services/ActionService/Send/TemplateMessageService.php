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
     * Send photo.
     *
     * @return void
     */
    abstract protected function sendPhoto(): void;

    /**
     * Send document.
     *
     * @return void
     */
    abstract protected function sendDocument(): void;

    /**
     * Send location.
     *
     * @return void
     */
    abstract protected function sendLocation(): void;

    /**
     * Send voice message.
     *
     * @return void
     */
    abstract protected function sendVoice(): void;

    /**
     * Send sticker.
     *
     * @return void
     */
    abstract protected function sendSticker(): void;

    /**
     * Send video note.
     *
     * @return void
     */
    abstract protected function sendVideoNote(): void;

    /**
     * Send contact.
     *
     * @return void
     */
    abstract protected function sendContact(): void;

    /**
     * Send text message.
     *
     * @return void
     */
    abstract protected function sendMessage(): void;
}
