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
                    'id' => time(),
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'last_name' => 'Testov',
                    'username' => 'usertest',
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => time() + 100,
                    'first_name' => 'Test 2',
                    'last_name' => 'Testov 2',
                    'username' => 'usertest_2',
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
