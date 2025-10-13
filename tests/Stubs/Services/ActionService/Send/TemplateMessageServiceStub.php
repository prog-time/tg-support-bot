<?php

namespace Tests\Stubs\Services\ActionService\Send;

use App\Services\ActionService\Send\TemplateMessageService;
use Tests\Traits\HasGettersForHasTemplate;

class TemplateMessageServiceStub extends TemplateMessageService
{
    use HasGettersForHasTemplate;

    public function handleUpdate(): mixed
    {
        return null;
    }

    protected function sendPhoto(): mixed
    {
        return null;
    }

    protected function sendDocument(): mixed
    {
        return null;
    }

    protected function sendLocation(): mixed
    {
        return null;
    }

    protected function sendVoice(): mixed
    {
        return null;
    }

    protected function sendSticker(): mixed
    {
        return null;
    }

    protected function sendVideoNote(): mixed
    {
        return null;
    }

    protected function sendContact(): mixed
    {
        return null;
    }

    protected function sendMessage(): mixed
    {
        return null;
    }

    protected function saveMessage(mixed $resultQuery): mixed
    {
        return null;
    }
}
