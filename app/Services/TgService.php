<?php

namespace App\Services;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use phpDocumentor\Reflection\Exception;

class TgService
{
    /**
     * @var string
     */
    protected string $typeMessage = '';

    /**
     * @var string
     */
    protected string $source = 'telegram';

    /**
     * @var TelegramUpdateDto
     */
    protected TelegramUpdateDto $update;

    /**
     * @var BotUser|null
     */
    protected ?BotUser $botUser;

    /**
     * @var TGTextMessageDto
     */
    protected TGTextMessageDto $messageParamsDTO;

    /**
     * @var TgTopicService
     */
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
}
