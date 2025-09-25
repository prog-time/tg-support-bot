<?php

namespace App\Http\Controllers;

use App\Actions\Ai\AiAcceptMessage;
use App\Actions\Ai\AiCancelMessage;
use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use Illuminate\Http\Request;

class AiTelegramBotController
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
     * @return void
     *
     * @throws \Exception
     */
    public function bot_query(): void
    {
        if (!$this->dataHook->isBot && $this->isSupergroup()) {
            if ($this->dataHook->typeQuery === 'callback_query') {
                if (preg_match('/(ai_message_send_)[0-9]+/', $this->dataHook->callbackData)) {
                    (new AiAcceptMessage())->execute($this->dataHook);
                } elseif (preg_match('/(ai_message_cancel_)[0-9]+/', $this->dataHook->callbackData)) {
                    (new AiCancelMessage())->execute($this->dataHook);
                }
            }
        }
    }
}
