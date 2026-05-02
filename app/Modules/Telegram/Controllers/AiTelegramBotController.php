<?php

namespace App\Modules\Telegram\Controllers;

use App\Models\BotUser;
use App\Modules\Ai\Actions\AiAcceptMessage;
use App\Modules\Ai\Actions\AiCancelMessage;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Http\Request;

class AiTelegramBotController
{
    private TelegramUpdateDto $dataHook;

    protected ?string $platform;

    public function __construct(Request $request)
    {
        $dataHook = TelegramUpdateDto::fromRequest($request);
        if (empty($dataHook)) {
            abort(200);
        }
        $this->dataHook = $dataHook;

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
                    app(AiAcceptMessage::class)->execute($this->dataHook);
                } elseif (preg_match('/(ai_message_cancel_)[0-9]+/', $this->dataHook->callbackData)) {
                    app(AiCancelMessage::class)->execute($this->dataHook);
                }
            }
        }
    }
}
