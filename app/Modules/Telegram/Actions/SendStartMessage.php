<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Services\Button\ButtonParser;
use App\Services\Button\KeyboardBuilder;

/**
 * Action: Send start message in Telegram
 */
class SendStartMessage
{
    public function __construct(
        private TelegramMethods $telegramMethods,
        private ButtonParser $buttonParser,
        private KeyboardBuilder $keyboardBuilder,
    ) {}

    /**
     * Send start message
     *
     * @param TelegramUpdateDto $update
     * @return void
     */
    public function handle(TelegramUpdateDto $update): void
    {
        $this->telegramMethods->sendQueryTelegram('deleteMessage', [
            'chat_id' => $update->chatId,
            'message_id' => $update->messageId,
        ]);

        if ($update->typeSource !== 'private') {
            return;
        }

        $parsedMessage = $this->buttonParser->parse(__('messages.start'));
        $keyboard = $this->keyboardBuilder->buildTelegramKeyboard($parsedMessage);

        $messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $update->chatId,
            'message_thread_id' => $update->messageThreadId,
            'text' => $parsedMessage->text,
            'parse_mode' => 'html',
            'reply_markup' => $keyboard,
        ]);

        $botUser = BotUser::getOrCreateByTelegramUpdate($update);

        SendTelegramMessageJob::dispatch(
            $botUser->id,
            $update,
            $messageParamsDTO,
            'outgoing'
        );
    }
}
