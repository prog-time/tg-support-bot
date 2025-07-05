<?php

namespace App\Http\Controllers;

use App\Actions\Telegram\SendContactMessage;
use App\Actions\Telegram\SendStartMessage;
use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use App\Services\External\ExternalUpdateService;
use App\Services\TgEditedMessageService;
use App\Services\TgMessageService;
use App\Services\TgTopicService;
use App\Services\TgVk\TgVkMessageService;
use Illuminate\Http\Request;

class TelegramBotController
{
    private TelegramUpdateDto $dataHook;

    protected ?string $platform;

    public function __construct(Request $request)
    {
        $dataHook = TelegramUpdateDto::fromRequest($request);
        $this->dataHook = !empty($dataHook) ? $dataHook : die();

        if ($this->dataHook->typeSource === 'private') {
            $this->platform = 'telegram';
        } else {
            $this->platform = BotUser::getPlatformByTopicId($this->dataHook->messageThreadId);
        }
    }

    /**
     * Check type source
     *
     * @return bool
     */
    protected function isSupergroup(): bool
    {
        return $this->dataHook->typeSource === 'supergroup';
    }

    /**
     * Check message
     *
     * @return void
     */
    protected function checkBotQuery(): void
    {
        if ($this->dataHook->pinnedMessageStatus) {
            die();
        }
    }

    public function bot_query(): void
    {
        $this->checkBotQuery();
        if (!$this->dataHook->isBot) {
            if ($this->dataHook->typeQuery === 'message') {
                if ($this->dataHook->text === '/contact' && $this->isSupergroup()) {
                    (new SendContactMessage())->executeByTgUpdate($this->dataHook);
                } elseif ($this->dataHook->text === '/start' && !$this->isSupergroup()) {
                    (new SendStartMessage())->execute($this->dataHook);
                } else {
                    switch ($this->platform) {
                        case 'telegram':
                            $this->controllerPlatformTg();
                            break;

                        case 'vk':
                            $this->controllerPlatformVk();
                            break;

                        default:
                            $this->controllerExternalPlatform();
                            break;
                    }
                }
            } elseif ($this->dataHook->typeQuery === 'edited_message') {
                (new TgEditedMessageService($this->dataHook))->handleUpdate();
            }
        } else {
            if ($this->dataHook->editedTopicStatus) {
                TgTopicService::deleteNoteInTopic($this->dataHook->messageId);
            }
        }
    }

    /**
     * Controller tg message
     *
     * @return void
     */
    private function controllerPlatformTg(): void
    {
        (new TgMessageService($this->dataHook))->handleUpdate();
    }

    /**
     * Controller vk message
     *
     * @return void
     */
    private function controllerPlatformVk(): void
    {
        (new TgVkMessageService($this->dataHook))->handleUpdate();
    }

    /**
     * Controller external message
     *
     * @return void
     */
    private function controllerExternalPlatform(): void
    {
        (new ExternalUpdateService($this->dataHook))->handleUpdate();
    }
}
