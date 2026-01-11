<?php

namespace Tests\Mocks\Tg;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use Illuminate\Support\Facades\Request;

class TelegramUpdateDto_GroupMock extends TelegramUpdateDto
{
    public static function getDtoParams(): array
    {
        $botUser = BotUser::getUserByChatId(time(), 'telegram');

        return [
            'update_id' => time(),
            'message' => [
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
                'message_thread_id' => $botUser->topic_id,
            ],
        ];
    }

    public static function getDto(array $dtoParams = []): TelegramUpdateDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        $request = Request::create('api/telegram/bot', 'POST', $dtoParams);
        return TelegramUpdateDto::fromRequest($request);
    }
}
