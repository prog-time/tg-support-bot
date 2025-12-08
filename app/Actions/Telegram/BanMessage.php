<?php

namespace App\Actions\Telegram;

use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;

class BanMessage
{
    /**
     * Сообщение о том, что пользователь заблокировал бота
     *
     * @param int   $botUserId
     * @param mixed $update
     *
     * @return void
     */
    public static function execute(int $botUserId, mixed $update): void
    {
        $botUser = BotUser::find($botUserId);

        SendTelegramMessageJob::dispatch(
            $botUser->id,
            $update,
            TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'typeSource' => 'supergroup',
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_thread_id' => $botUser->topic_id,
                'text' => __('messages.ban_bot'),
                'parse_mode' => 'html',
            ]),
            'incoming',
        );
    }
}
