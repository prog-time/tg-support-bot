<?php

namespace Tests\Mocks\Tg;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use Illuminate\Support\Facades\Request;

class TelegramUpdate_AiButtonAction extends TelegramUpdateDto
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
            'callback_query' => [
                'id' => time(),
                'from' => [
                    'id' => config('testing.tg_private.chat_id'),
                    'is_bot' => false,
                    'first_name' => config('testing.tg_private.first_name'),
                    'last_name' => config('testing.tg_private.last_name'),
                    'username' => config('testing.tg_private.username'),
                    'language_code' => 'ru',
                ],
                'message' => [
                    'message_id' => time(),
                    'from' => [
                        'id' => time(),
                        'is_bot' => true,
                        'first_name' => 'Prog-Time AI',
                        'username' => 'prog_time_ai_bot',
                    ],
                    'chat' => [
                        'id' => config('testing.tg_group.chat_id'),
                        'title' => 'Prog-Time | Чаты',
                        'is_forum' => true,
                        'type' => 'supergroup',
                    ],
                    'date' => time(),
                    'edit_date' => time(),
                    'message_thread_id' => 0,
                    'reply_to_message' => [
                        'message_id' => time(),
                        'from' => [
                            'id' => time(),
                            'is_bot' => true,
                            'first_name' => 'Prog-Time |Администратор сайта',
                            'username' => 'prog_time_bot',
                        ],
                        'chat' => [
                            'id' => config('testing.tg_group.chat_id'),
                            'title' => 'Prog-Time | Чаты',
                            'is_forum' => true,
                            'type' => 'supergroup',
                        ],
                        'date' => time(),
                        'message_thread_id' => 0,
                        'forum_topic_created' => [
                            'name' => '#1424646511 (telegram)',
                            'icon_color' => 7322096,
                            'icon_custom_emoji_id' => '5417915203100613993',
                        ],
                        'is_topic_message' => true,
                    ],
                    'text' => "📄 Инструкция: \nнапиши приветственное сообщение  \n\n🤖 Ответ от AI: \nДобро пожаловать в TG Support Bot!",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => '✅ Отправить',
                                    'callback_data' => 'ai_message_send_' . time(),
                                ],
                                [
                                    'text' => '❌ Отменить',
                                    'callback_data' => 'ai_message_delete_' . time(),
                                ],
                            ],
                            [
                                [
                                    'text' => '📝 Редактировать ответ',
                                    'switch_inline_query_current_chat' => 'ai_message_edit_' . time() . " \n\nДобро пожаловать в TG Support Bot!",
                                ],
                            ],
                        ],
                    ],
                    'is_topic_message' => true,
                ],
                'chat_instance' => time(),
                'data' => 'ai_message_send_' . time(),
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
