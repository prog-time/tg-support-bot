<?php

namespace App\Modules\External\Services;

use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Modules\External\DTOs\ExternalMessageDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;

abstract class ExternalService
{
    protected string $typeMessage = '';

    protected ExternalMessageDto $update;

    protected ?BotUser $botUser;

    protected ?ExternalUser $externalUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(ExternalMessageDto $update)
    {
        $this->update = $update;

        $this->botUser = (new BotUser())->getOrCreateExternalBotUser($this->update);

        if (empty($this->botUser)) {
            throw new \Exception('Пользователя не существует!');
        }
    }

    abstract public function handleUpdate(): void;
}
