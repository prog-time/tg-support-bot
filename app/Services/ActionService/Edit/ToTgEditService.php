<?php

namespace App\Services\ActionService\Edit;

use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Services\TgTopicService;
use phpDocumentor\Reflection\Exception;

/**
 * Class ToTgEditService
 */
abstract class ToTgEditService extends TemplateEditService
{
    protected string $typeMessage = '';

    protected string $source = 'telegram';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    protected TgTopicService $tgTopicService;

    public function __construct(mixed $update)
    {
        $this->update = $update;
        $this->tgTopicService = new TgTopicService();

        $chatId = $this->update->chatId ?? $this->update->from_id;

        dump($chatId);

        $this->botUser = BotUser::getUserByChatId($chatId, $this->source);

        dump($this->botUser);

        if (empty($this->botUser)) {
            throw new Exception('Пользователя не существует!');
        }

        $this->messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'typeSource' => 'private',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
        ]);
    }
}
