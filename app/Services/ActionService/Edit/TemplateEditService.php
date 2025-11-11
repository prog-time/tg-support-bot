<?php

namespace App\Services\ActionService\Edit;

use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\Services\TgTopicService;

/**
 * Class TemplateEditService
 */
abstract class TemplateEditService
{
    protected string $typeMessage = '';

    protected string $source = 'telegram';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    protected TgTopicService $tgTopicService;

    /**
     * @return void
     */
    abstract public function handleUpdate(): void;

    /**
     * @return void
     */
    abstract protected function editMessageText(): void;

    /**
     * @return void
     */
    abstract protected function editMessageCaption(): void;
}
