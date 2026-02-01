<?php

namespace App\Services\ActionService\Send;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use phpDocumentor\Reflection\Exception;

/**
 * Class FromTgMessageService
 * Class for working with messages from TG to "Source".
 */
abstract class FromTgMessageService extends TemplateMessageService
{
    public function __construct(TelegramUpdateDto $update)
    {
        $this->update = $update;
        $this->botUser = BotUser::getOrCreateByTelegramUpdate($this->update);

        if (empty($this->botUser)) {
            throw new Exception('User does not exist!');
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
                throw new Exception('This request type is not supported!');
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
