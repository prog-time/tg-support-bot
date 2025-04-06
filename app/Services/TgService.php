<?php

namespace App\Services;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use phpDocumentor\Reflection\Exception;

class TgService
{
    protected string $typeMessage = '';
    protected string $source = 'telegram';
    protected TelegramUpdateDto $update;
    protected ?BotUser $botUser;
    protected TGTextMessageDto $messageParamsDTO;
    protected TgTopicService $tgTopicService;

    public function __construct(TelegramUpdateDto $update) {
        $this->update = $update;
        $this->tgTopicService = new TgTopicService();
        $this->botUser = BotUser::getUserData($this->update, 'telegram');

        if (empty($this->botUser)) {
            throw new Exception('Пользователя не существует!');
        }

        switch ($update->typeSource) {
            case 'private':
                $this->typeMessage = 'incoming';
                $queryParams = [
                    'chat_id' => env('TELEGRAM_GROUP_ID'),
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
