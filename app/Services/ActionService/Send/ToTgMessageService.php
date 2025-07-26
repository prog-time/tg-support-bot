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
     * Отправка изображения
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendPhoto(): TelegramAnswerDto;

    /**
     * Отправка документа
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendDocument(): TelegramAnswerDto;

    /**
     * Отправка геолокации
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendLocation(): TelegramAnswerDto;

    /**
     * Отправка голосового сообщения
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendVoice(): TelegramAnswerDto;

    /**
     * Отправка стикеров
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendSticker(): TelegramAnswerDto;

    /**
     * Отправка видео-сообщения
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendVideoNote(): TelegramAnswerDto;

    /**
     * Отправка контакта
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendContact(): TelegramAnswerDto;

    /**
     * Отправка текстового сообщения
     *
     * @return TelegramAnswerDto
     */
    abstract protected function sendMessage(): TelegramAnswerDto;

    /**
     * Сохранения сообщения
     *
     * @param TelegramAnswerDto $resultQuery
     *
     * @return void
     */
    abstract protected function saveMessage(mixed $resultQuery): void;
}
