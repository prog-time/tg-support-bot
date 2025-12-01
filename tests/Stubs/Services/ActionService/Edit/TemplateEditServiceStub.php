<?php

namespace Tests\Stubs\Services\ActionService\Edit;

use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Services\ActionService\Edit\TemplateEditService;
use Tests\Traits\HasGettersForHasTemplate;

class TemplateEditServiceStub extends TemplateEditService
{
    use HasGettersForHasTemplate;

    protected string $typeMessage = '';

    protected string $source = 'telegram';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function editMessageText(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function editMessageCaption(): void
    {
        //
    }
}
