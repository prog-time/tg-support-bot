<?php

namespace Tests\Unit\Services\TgVk;

use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgVk\TgVkEditService;
use App\Services\TgVk\TgVkMessageService;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

class TgVkEditServiceTest extends TestCase
{
    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->botUser = BotUser::getUserByChatId(config('testing.vk_private.chat_id'), 'vk');
    }

    public function test_edit_text_message(): void
    {
        Message::truncate();

        // новое сообщение
        $newMessageDto = TelegramUpdateDto_VKMock::getDto();
        (new TgVkMessageService($newMessageDto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
            'platform' => 'vk',
        ];
        $this->assertDatabaseHas('messages', $whereMessageParams);

        $newMessageData = Message::where($whereMessageParams)->first();
        // ---------------

        // изменение сообщения
        $editPayload = [
            'update_id' => time(),
            'edited_message' => TelegramUpdateDto_VKMock::getDtoParams()['message'],
        ];

        $editPayload['edited_message']['text'] = 'Новый текст сообщения';
        $editPayload['edited_message']['message_id'] = $newMessageData->from_id;
        $editPayload['edited_message']['message_thread_id'] = $this->botUser->topic_id;

        $newMessageDto = TelegramUpdateDto_VKMock::getDto($editPayload);
        (new TgVkEditService($newMessageDto))->handleUpdate();
    }
}
