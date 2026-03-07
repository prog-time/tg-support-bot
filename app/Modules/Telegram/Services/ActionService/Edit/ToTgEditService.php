<?php

namespace App\Modules\Telegram\Services\ActionService\Edit;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
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

    public function __construct(mixed $update)
    {
        $this->update = $update;

        $chatId = $this->update->chatId ?? $this->update->from_id;
        $this->botUser = BotUser::getUserByChatId($chatId, $this->source);

        if (empty($this->botUser)) {
            throw new Exception('User does not exist!');
        }

        $this->messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'typeSource' => 'private',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
        ]);
    }
}
