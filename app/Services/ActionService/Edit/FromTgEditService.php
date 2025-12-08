<?php

namespace App\Services\ActionService\Edit;

use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use phpDocumentor\Reflection\Exception;

/**
 * Class FromTgEditService
 */
abstract class FromTgEditService extends TemplateEditService
{
    public function __construct(mixed $update)
    {
        $this->update = $update;
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
     * Редактирование сообщения
     *
     * @return void
     */
    abstract protected function editMessageText(): void;

    /**
     * Редактирование сообщения с фото или документом
     *
     * @return void
     */
    abstract protected function editMessageCaption(): void;
}
