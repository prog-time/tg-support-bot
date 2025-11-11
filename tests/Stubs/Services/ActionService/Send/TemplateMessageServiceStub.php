<?php

namespace Tests\Stubs\Services\ActionService\Send;

use App\Services\ActionService\Send\TemplateMessageService;
use Tests\Traits\HasGettersForHasTemplate;

class TemplateMessageServiceStub extends TemplateMessageService
{
    use HasGettersForHasTemplate;

    public function handleUpdate(): void
    {
    }

    protected function sendPhoto(): void
    {
    }

    protected function sendDocument(): void
    {
    }

    protected function sendLocation(): void
    {
    }

    protected function sendVoice(): void
    {
    }

    protected function sendSticker(): void
    {
    }

    protected function sendVideoNote(): void
    {
    }

    protected function sendContact(): void
    {
    }

    protected function sendMessage(): void
    {
    }
}
