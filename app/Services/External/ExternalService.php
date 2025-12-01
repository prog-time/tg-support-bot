<?php

namespace App\Services\External;

use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Models\ExternalUser;

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

        $this->botUser = (new BotUser())->getExternalBotUser($this->update);

        if (empty($this->botUser)) {
            throw new \Exception('Пользователя не существует!');
        }
    }

    abstract public function handleUpdate(): void;
}
