<?php

namespace App\Http\Controllers;

use App\Actions\Telegram\SendContactMessage;
use App\Actions\Telegram\SendStartMessage;
use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use App\Services\Tg\TgEditMessageService;
use App\Services\Tg\TgMessageService;
use App\Services\TgExternal\TgExternalEditService;
use App\Services\TgExternal\TgExternalMessageService;
use App\Services\TgTopicService;
use App\Services\TgVk\TgVkEditService;
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

    /**
     * @return void
     *
     * @throws \Exception
     */
    public function bot_query(): void
    {
        $this->checkBotQuery();
        if (!$this->dataHook->isBot) {
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
        switch ($this->dataHook->typeQuery) {
            case 'message':
                if ($this->dataHook->text === '/contact' && $this->isSupergroup()) {
                    (new SendContactMessage())->executeByTgUpdate($this->dataHook);
                } elseif ($this->dataHook->text === '/start' && !$this->isSupergroup()) {
                    (new SendStartMessage())->execute($this->dataHook);
                } else {
                    (new TgMessageService($this->dataHook))->handleUpdate();
                }
                break;

            case 'edited_message':
                (new TgEditMessageService($this->dataHook))->handleUpdate();
                break;

            default:
                throw new \Exception("Неизвестный тип события: {$this->dataHook->typeQuery}");
        }
    }

    /**
     * Controller vk message
     *
     * @return void
     */
    private function controllerPlatformVk(): void
    {
        switch ($this->dataHook->typeQuery) {
            case 'message':
                (new TgVkMessageService($this->dataHook))->handleUpdate();
                break;

            case 'edited_message':
                (new TgVkEditService($this->dataHook))->handleUpdate();
                break;

            default:
                throw new \Exception("Неизвестный тип события: {$this->dataHook->typeQuery}");
        }
    }

    /**
     * Controller external message
     *
     * @return void
     */
    private function controllerExternalPlatform(): void
    {
        switch ($this->dataHook->typeQuery) {
            case 'message':
                (new TgExternalMessageService($this->dataHook))->handleUpdate();
                break;

            case 'edited_message':
                (new TgExternalEditService($this->dataHook))->handleUpdate();
                break;

            default:
                throw new \Exception("Неизвестный тип события: {$this->dataHook->typeQuery}");
        }
    }
}
