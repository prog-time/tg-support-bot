<?php

namespace Tests\Unit\Services\TgVk;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgVk\TgVkEditService;
use App\Services\TgVk\TgVkMessageService;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class TgVkEditServiceTest extends TestCase
{
    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        $chatId = config('testing.vk_private.chat_id');
        $botUser = BotUser::getUserByChatId($chatId, 'vk');

        $this->basicPayload = [
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
        ];
    }

    public function test_edit_text_message(): void
    {
        $chatId = config('testing.vk_private.chat_id');
        $botUser = BotUser::getUserByChatId($chatId, 'vk');

        // новое сообщение
        $newMessageDto = TelegramUpdateDto::fromRequest(
            Request::create('api/telegram/bot', 'POST', [
                'update_id' => time(),
                'message' => $this->basicPayload,
            ])
        );

        (new TgVkMessageService($newMessageDto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'platform' => 'vk',
        ]);

        $newMessageData = Message::where('bot_user_id', $botUser->id)
            ->orderBy('id', 'desc')
            ->first();
        // ---------------

        // изменение сообщения
        $editPayload = [
            'update_id' => time(),
            'edited_message' => $this->basicPayload,
        ];

        $editPayload['edited_message']['text'] = 'Новый текст сообщения';
        $editPayload['edited_message']['message_id'] = $newMessageData->from_id;
        $editPayload['edited_message']['message_thread_id'] = $botUser->topic_id;

        $newMessageDto = TelegramUpdateDto::fromRequest(
            Request::create('api/telegram/bot', 'POST', $editPayload)
        );

        (new TgVkEditService($newMessageDto))->handleUpdate();

        $this->app->make('queue')->connection('sync');
    }
}
