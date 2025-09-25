<?php

namespace Tests\Traits;

use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Services\TgTopicService;

trait HasGettersForHasTemplate
{
    public function getTypeMessage(): string
    {
        return $this->typeMessage;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getUpdate(): mixed
    {
        return $this->update;
    }

    public function getBotUser(): ?BotUser
    {
        return $this->botUser;
    }

    public function getMessageParamsDTO(): TGTextMessageDto
    {
        return $this->messageParamsDTO;
    }

    public function getTgTopicService(): TgTopicService
    {
        return $this->tgTopicService;
    }
}
