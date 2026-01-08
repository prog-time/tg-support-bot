<?php

namespace Tests\Mocks\Tg;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use Illuminate\Support\Facades\Request;

class TelegramUpdateDto_VKMock extends TelegramUpdateDto
{
    /**
     * @param BotUser|null $botUser
     *
     * @return array
     */
    public static function getDtoParams(?BotUser $botUser = null): array
    {
        if (!$botUser) {
            $botUser = BotUser::getUserByChatId(time(), 'vk');
        }

        return [
            'update_id' => time(),
            'message' => [
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
                    'title' => 'Prog-Time | Чаты',
                    'is_forum' => true,
                    'type' => 'supergroup',
                ],
                'date' => time(),
                'message_thread_id' => $botUser->topic_id,
                'text' => 'Тестовое сообщение',
                'is_topic_message' => true,
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
