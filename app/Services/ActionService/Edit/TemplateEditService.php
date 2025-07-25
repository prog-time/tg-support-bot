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
     *
     * @throws \Exception
     */
    abstract public function handleUpdate(): void;

    /**
     * Edit text message
     *
     * @return mixed
     */
    abstract protected function editMessageText(): mixed;

    /**
     * Edit message with photo or document
     *
     * @return mixed
     */
    abstract protected function editMessageCaption(): mixed;
}
