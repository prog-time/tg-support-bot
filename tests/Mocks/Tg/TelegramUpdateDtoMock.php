<?php

namespace Tests\Mocks\Tg;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use Illuminate\Support\Facades\Request;

class TelegramUpdateDtoMock extends TelegramUpdateDto
{
    /**
     * @param BotUser|null $botUser
     *
     * @return array
     */
    public static function getDtoParams(?BotUser $botUser = null): array
    {
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
                    'first_name' => 'Test',
                    'last_name' => 'Testov',
                    'username' => 'usertest',
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'Тестовое сообщение',
            ],
        ];
    }

    /**
     * @param array $dtoParams
     *
     * @return TelegramUpdateDto
     */
    public static function getDto(array $dtoParams = []): TelegramUpdateDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        $request = Request::create('api/telegram/bot', 'POST', $dtoParams);
        return TelegramUpdateDto::fromRequest($request);
    }
}
