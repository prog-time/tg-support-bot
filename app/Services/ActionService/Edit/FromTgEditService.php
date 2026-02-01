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
     * Edit text message.
     *
     * @return void
     */
    abstract protected function editMessageText(): void;

    /**
     * Edit message with photo or document.
     *
     * @return void
     */
    abstract protected function editMessageCaption(): void;
}
