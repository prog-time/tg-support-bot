<?php

namespace App\Services;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use phpDocumentor\Reflection\Exception;

abstract class MessageService
{
    protected string $typeMessage = '';

    protected string $source = 'telegram';

    protected TelegramUpdateDto $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    protected TgTopicService $tgTopicService;

    public function __construct(TelegramUpdateDto $update)
    {
        $this->update = $update;
        $this->tgTopicService = new TgTopicService();
        $this->botUser = BotUser::getTelegramUserData($this->update);

        if (empty($this->botUser)) {
            throw new Exception('Пользователя не существует!');
        }

        switch ($update->typeSource) {
            case 'private':
                $this->typeMessage = 'incoming';
                $queryParams = [
                    'chat_id' => config('traffic_source.settings.telegram.group_id'),
                    'message_thread_id' => $this->botUser->topic_id,
                ];
                break;

            case 'supergroup':
                $this->typeMessage = 'outgoing';
                $queryParams = [
                    'chat_id' => $this->botUser->chat_id,
                ];
                break;

            default:
                throw new Exception('Данный тип запроса не поддерживается!');
        }

        $queryParams['methodQuery'] = 'sendMessage';
        $queryParams['typeSource'] = $update->typeSource;
        $this->messageParamsDTO = TGTextMessageDto::from($queryParams);
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
    abstract protected function saveMessage(TelegramAnswerDto $resultQuery): void;
}
