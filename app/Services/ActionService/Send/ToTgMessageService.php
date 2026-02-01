<?php

namespace App\Services\ActionService\Send;

use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use phpDocumentor\Reflection\Exception;

/**
 * Class ToTgMessageService
 * Class for working with messages from "Source" to TG.
 */
abstract class ToTgMessageService extends TemplateMessageService
{
    protected string $typeMessage = '';

    protected string $source = 'telegram';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(mixed $update)
    {
        try {
            $this->update = $update;

            $this->typeMessage = 'incoming';

            $chatId = $this->update->chatId ?? $this->update->from_id;

            $this->botUser = BotUser::getUserByChatId($chatId, $this->source);
            if (empty($this->botUser)) {
                throw new Exception('User does not exist!');
            }

            $this->messageParamsDTO = TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'typeSource' => 'private',
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_thread_id' => $this->botUser->topic_id,
            ]);
        } catch (Exception $e) {
            die();
        }
    }

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
