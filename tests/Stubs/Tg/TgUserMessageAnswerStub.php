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
                    'id' => time(),
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'last_name' => 'Testov',
                    'username' => 'usertest',
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => time(),
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
