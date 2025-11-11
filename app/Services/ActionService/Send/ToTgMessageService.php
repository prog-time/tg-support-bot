<?php

namespace App\Services\ActionService\Send;

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
