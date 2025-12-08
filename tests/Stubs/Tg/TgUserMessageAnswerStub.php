<?php

namespace Tests\Stubs\Tg;

use App\DTOs\TelegramUpdateDto;

class TgUserMessageAnswerStub extends TelegramUpdateDto
{
    /**
     * @return array
     */
    public static function getDtoParams(): array
    {
        return [
            'ok' => true,
            'result' => [
                'message_id' => time(),
                'from' => [
                    'id' => config('testing.tg_private.chat_id'),
                    'is_bot' => false,
                    'first_name' => config('testing.tg_private.first_name'),
                    'last_name' => config('testing.tg_private.last_name'),
                    'username' => config('testing.tg_private.username'),
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => config('testing.tg_group.chat_id'),
                    'title' => 'Prog-Time',
                    'is_forum' => true,
                    'type' => 'supergroup',
                ],
                'date' => time(),
                'edit_date' => time(),
                'text' => 'Тестовое сообщение',
                'message_thread_id' => 0,
            ],
        ];
    }
}
