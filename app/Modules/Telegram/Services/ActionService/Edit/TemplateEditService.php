<?php

namespace App\Modules\Telegram\Services\ActionService\Edit;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;

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
