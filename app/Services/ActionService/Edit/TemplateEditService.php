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
     * @return mixed
     *
     * @throws \Exception
     */
    abstract public function handleUpdate(): mixed;

    /**
     * @return mixed
     */
    abstract protected function editMessageText(): mixed;

    /**
     * @return mixed
     */
    abstract protected function editMessageCaption(): mixed;
}
