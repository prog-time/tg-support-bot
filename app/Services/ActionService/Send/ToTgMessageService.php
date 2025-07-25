<?php

namespace App\Services\ActionService\Send;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Services\TgTopicService;
use phpDocumentor\Reflection\Exception;

/**
 * Class ToTgMessageService
 * Класс для работы с сообщениями из "Источник" в TG
 */
abstract class ToTgMessageService extends TemplateMessageService
{
    protected string $typeMessage = '';

    protected string $source = 'telegram';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    protected TgTopicService $tgTopicService;

    public function __construct(mixed $update)
    {
        try {
            $this->update = $update;
            $this->tgTopicService = new TgTopicService();

            $this->typeMessage = 'incoming';

            $chatId = $this->update->chatId ?? $this->update->from_id;

            $this->botUser = BotUser::getUserByChatId($chatId, $this->source);
            if (empty($this->botUser)) {
                throw new Exception('Пользователя не существует!');
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
     *
     * @throws \Exception
     */
    abstract public function handleUpdate(): void;

    /**
     * Send photo
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendPhoto(): TelegramAnswerDto;

    /**
     * Send document
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendDocument(): TelegramAnswerDto;

    /**
     * Send location
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendLocation(): TelegramAnswerDto;

    /**
     * Send voice
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendVoice(): TelegramAnswerDto;

    /**
     * Send sticker
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendSticker(): TelegramAnswerDto;

    /**
     * Send video note
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendVideoNote(): TelegramAnswerDto;

    /**
     * Send contact info
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendContact(): TelegramAnswerDto;

    /**
     * Send text message
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendMessage(): TelegramAnswerDto;

    /**
     * Save message in DB
     *
     * @param TelegramAnswerDto $resultQuery
     *
     * @return void
     */
    abstract protected function saveMessage(mixed $resultQuery): void;
}
