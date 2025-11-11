<?php

namespace App\Services\ActionService\Send;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Services\TgTopicService;
use phpDocumentor\Reflection\Exception;

/**
 * Class FromTgMessageService
 * Класс для работы с сообщениями из TG в "Источник"
 */
abstract class FromTgMessageService extends TemplateMessageService
{
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

                $groupId = config('traffic_source.settings.telegram.group_id');
                $queryParams = [
                    'chat_id' => $groupId,
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
