<?php

namespace Tests\Stubs\Services\ActionService\Edit;

use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Services\ActionService\Edit\TemplateEditService;
use App\Services\TgTopicService;
use Tests\Traits\HasGettersForHasTemplate;

class TemplateEditServiceStub extends TemplateEditService
{
    use HasGettersForHasTemplate;

    protected string $typeMessage = '';

    protected string $source = 'telegram';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    protected TgTopicService $tgTopicService;

    /**
     * @return mixed
     */
    public function handleUpdate(): mixed
    {
        return null;
    }

    /**
     * @return mixed
     */
    protected function editMessageText(): mixed
    {
        return null;
    }

    /**
     * @return mixed
     */
    protected function editMessageCaption(): mixed
    {
        return null;
    }
}
