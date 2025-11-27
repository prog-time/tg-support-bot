<?php

namespace Tests\Mocks\Tg;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use Illuminate\Support\Facades\Request;

class TelegramUpdate_AiAcceptDtoMock extends TelegramUpdateDto
{
    /**
     * @param BotUser|null $botUser
     *
     * @return array
     */
    public static function getDtoParams(?BotUser $botUser = null): array
    {
        $dataParams = TelegramUpdateDto_GroupMock::getDtoParams()['message'];

        $dataParams['update_id'] = time();
        $dataParams['callback_query'] = $dataParams;
        $dataParams['callback_query']['data'] = 'ai_message_send_3228';

        $dataParams['callback_query']['message'] = [
            'reply_to_message' => [
                'message_id' => 3054,
                'from' => [
                    'id' => 6213858185,
                    'is_bot' => true,
                    'first_name' => 'Prog-Time |Администратор сайта',
                    'username' => 'prog_time_bot',
                ],
                'chat' => [
                    'id' => -1002635013459,
                    'title' => 'Prog-Time | Чаты',
                    'is_forum' => true,
                    'type' => 'supergroup',
                ],
                'date' => time(),
                'message_thread_id' => $dataParams['message_thread_id'],
                'is_topic_message' => true,
            ],
            'text' => '📄 Инструкция: напиши приветственное сообщение 🤖 Ответ от AI: Добро пожаловать в TG Support Bot!',
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '✅ Отправить',
                            'callback_data' => 'ai_message_send_3228',
                        ],
                        [
                            'text' => '❌ Отменить',
                            'callback_data' => 'ai_message_delete_3228',
                        ],
                    ],
                    [
                        [
                            'text' => '📝 Редактировать ответ',
                            'switch_inline_query_current_chat' =>
                                "ai_message_edit_3228 \n\nДобро пожаловать в TG Support Bot!",
                        ],
                    ],
                ],
            ],
            'is_topic_message' => true,
        ];

        return $dataParams;
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
