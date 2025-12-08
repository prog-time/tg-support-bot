<?php

namespace Tests\Mocks\Tg\Answer;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;

class TelegramAnswerDtoMock extends TelegramUpdateDto
{
    /**
     * @param BotUser|null $botUser
     *
     * @return array
     */
    public static function getDtoParams(?BotUser $botUser = null): array
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
                    'id' => config('testing.tg_private.chat_id'),
                    'first_name' => config('testing.tg_private.first_name'),
                    'last_name' => config('testing.tg_private.last_name'),
                    'username' => config('testing.tg_private.username'),
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'Тестовое сообщение',
            ],
            'response_code' => 200,
        ];
    }

    /**
     * @param array $dtoParams
     *
     * @return TelegramAnswerDto|null
     */
    public static function getDto(array $dtoParams = []): ?TelegramAnswerDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        return TelegramAnswerDto::fromData($dtoParams);
    }
}
