<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;

class SendContactMessage
{
    public function __construct(
        private GetChat $getChat,
    ) {
    }

    public function execute(BotUser $botUser): void
    {
        $dto = $this->getQueryParams($botUser);
        SendTelegramSimpleQueryJob::dispatch($dto);
    }

    /**
     * @param BotUser $botUser
     *
     * @return TGTextMessageDto
     */
    public function getQueryParams(BotUser $botUser): TGTextMessageDto
    {
        return TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $botUser->topic_id,
            'text' => $this->buildText($botUser),
            'parse_mode' => 'html',
            'reply_markup' => [
                'inline_keyboard' => $this->buildKeyboard($botUser),
            ],
        ]);
    }

    /**
     * @param BotUser $botUser
     *
     * @return string
     */
    private function buildText(BotUser $botUser): string
    {
        $text = "<b>КОНТАКТНАЯ ИНФОРМАЦИЯ</b>\n";
        $text .= "Источник: {$botUser->platform}\n";
        $text .= "ID: <code>{$botUser->chat_id}</code>\n";

        if ($botUser->platform !== 'telegram') {
            return $text;
        }

        try {
            $chat = $this->getChat->execute($botUser->chat_id);
            $username = $chat->rawData['result']['username'] ?? null;

            if ($username) {
                $text .= "Пользователь: <code>{$username}</code>\n";
                $text .= "Ссылка: https://telegram.me/{$username}\n";
            }
        } catch (\Throwable) {
        }

        return $text;
    }

    /**
     * @param BotUser $botUser
     *
     * @return array
     */
    private function buildKeyboard(BotUser $botUser): array
    {
        $banButton = [
            'text' => $botUser->isBanned()
                ? __('messages.but_ban_user_false')
                : __('messages.but_ban_user_true'),
            'callback_data' => $botUser->isBanned()
                ? 'topic_user_ban_false'
                : 'topic_user_ban_true',
        ];

        return [
            [
                $banButton,
            ],
            [
                [
                    'text' => __('messages.but_close_topic'),
                    'callback_data' => 'close_topic',
                ],
            ],
        ];
    }
}
