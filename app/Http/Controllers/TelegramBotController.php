<?php

namespace App\Http\Controllers;

use App\Actions\Telegram\SendContactMessage;
use App\Actions\Telegram\SendStartMessage;
use App\DTOs\TelegramUpdateDto;
use App\Services\TgEditedMessageService;
use App\Services\TgMessageService;
use App\Services\TgTopicService;
use Illuminate\Http\Request;

class TelegramBotController
{
    private TelegramUpdateDto $dataHook;

    public function __construct(Request $request)
    {
        $dataHook = TelegramUpdateDto::fromRequest($request);
        $this->dataHook = !empty($dataHook) ? $dataHook : die();
    }

    /**
     * Check type source
     * @return bool
     */
    protected function isSupergroup(): bool
    {
        return $this->dataHook->typeSource === 'supergroup';
    }

    /**
     * Check message
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
                if (!$this->dataHook->editedTopicStatus) {
                    if ($this->dataHook->text === '/contact' && $this->isSupergroup()) {
                        (new SendContactMessage())->executeByTgUpdate($this->dataHook);
                    } elseif ($this->dataHook->text === '/start' && !$this->isSupergroup()) {
                        (new SendStartMessage())->execute($this->dataHook);
                    } else {
                        (new TgMessageService($this->dataHook))->handleUpdate();
                    }
                } else {
                    TgTopicService::deleteNoteInTopic($this->dataHook->messageId);
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
}
