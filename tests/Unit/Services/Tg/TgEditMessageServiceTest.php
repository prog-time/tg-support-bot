<?php

namespace Tests\Unit\Services\Tg;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\Tg\TgEditMessageService;
use App\Services\Tg\TgMessageService;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class TgEditMessageServiceTest extends TestCase
{
    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();

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
                'id' => config('testing.tg_private.chat_id'),
                'first_name' => config('testing.tg_private.first_name'),
                'last_name' => config('testing.tg_private.last_name'),
                'username' => config('testing.tg_private.username'),
                'type' => 'private',
            ],
            'date' => time(),
            'edit_date' => time(),
            'text' => 'Тестовое сообщение',
        ];
    }

    public function test_edit_text_message(): void
    {
        $chatId = config('testing.tg_private.chat_id');
        $botUser = BotUser::getUserByChatId($chatId, 'telegram');

        // новое сообщение
        $newMessageDto = TelegramUpdateDto::fromRequest(
            Request::create('api/telegram/bot', 'POST', [
                'update_id' => time(),
                'message' => $this->basicPayload,
            ])
        );
        (new TgMessageService($newMessageDto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'platform' => 'telegram',
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
        $editPayload['edited_message']['chat']['id'] = $botUser->chat_id;
        $editPayload['edited_message']['message_thread_id'] = $botUser->topic_id;

        $newMessageDto = TelegramUpdateDto::fromRequest(
            Request::create('api/telegram/bot', 'POST', $editPayload)
        );
        (new TgEditMessageService($newMessageDto))->handleUpdate();

        $this->app->make('queue')->connection('sync');
    }

    public function test_edit_caption_message(): void
    {
        $chatId = config('testing.tg_private.chat_id');
        $botUser = BotUser::getUserByChatId($chatId, 'telegram');

        $basicPayload = $this->basicPayload;
        $basicPayload['photo'] = [
            [
                'file_id' => config('testing.tg_file.photo'),
                'file_unique_id' => 'AQAD854DoEp9',
                'file_size' => 59609,
                'width' => 684,
                'height' => 777,
            ],
        ];

        // новое сообщение
        $newMessagePayload = [
            'update_id' => time(),
            'message' => $basicPayload,
        ];
        $newMessagePayload['message']['caption'] = $newMessagePayload['message']['text'];
        unset($newMessagePayload['message']['text']);


        $newMessageDto = TelegramUpdateDto::fromRequest(
            Request::create('api/telegram/bot', 'POST', $newMessagePayload)
        );
        (new TgMessageService($newMessageDto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'platform' => 'telegram',
        ]);

        $newMessageData = Message::where('bot_user_id', $botUser->id)
            ->orderBy('id', 'desc')
            ->first();
        // ---------------

        // изменение сообщения
        $editPayload = [
            'update_id' => time(),
            'edited_message' => $basicPayload,
        ];
        unset($editPayload['edited_message']['text']);

        $editPayload['edited_message']['caption'] = 'Новый текст сообщения';
        $editPayload['edited_message']['message_id'] = $newMessageData->from_id;
        $editPayload['edited_message']['chat']['id'] = $botUser->chat_id;
        $editPayload['edited_message']['message_thread_id'] = $botUser->topic_id;

        $newMessageDto = TelegramUpdateDto::fromRequest(
            Request::create('api/telegram/bot', 'POST', $editPayload)
        );
        (new TgEditMessageService($newMessageDto))->handleUpdate();

        $this->app->make('queue')->connection('sync');
    }
}
