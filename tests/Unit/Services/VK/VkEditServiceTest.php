<?php

namespace Tests\Unit\Services\VK;

use App\Models\BotUser;
use App\Models\Message;
use App\Services\VK\VkEditService;
use App\Services\VK\VkMessageService;
use Tests\Mocks\Vk\VkUpdateDtoMock;
use Tests\TestCase;

class VkEditServiceTest extends TestCase
{
    public function test_edit_text_message(): void
    {
        Message::truncate();
        $this->app->make('queue')->connection('sync');

        $chatId = config('testing.vk_private.chat_id');
        $botUser = BotUser::getUserByChatId($chatId, 'vk');

        // новое сообщение
        $dtoNewMessage = VkUpdateDtoMock::getDto();
        (new VkMessageService($dtoNewMessage))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $whereParams = [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'platform' => 'vk',
        ];
        $this->assertDatabaseHas('messages', $whereParams);

        $messageData = Message::where('bot_user_id', $whereParams)->first();
        // ---------------

        // изменение сообщения
        $dtoUpdateMessage = VkUpdateDtoMock::getDto([
            'group_id' => config('testing.vk_private.group_id'),
            'type' => 'message_edit',
            'event_id' => '57ef84ad7140b134e155ad9cf86a2d62f0851f34',
            'v' => '5.199',
            'object' => [
                'date' => time(),
                'from_id' => config('testing.vk_private.chat_id'),
                'id' => $messageData->from_id,
                'version' => time(),
                'out' => 1,
                'fwd_messages' => [],
                'important' => false,
                'is_hidden' => false,
                'attachments' => [],
                'conversation_message_id' => time(),
                'text' => 'Test text, new!',
                'update_time' => 1762859837,
                'peer_id' => config('testing.vk_private.chat_id'),
                'random_id' => 0,
            ],
            'secret' => config('testing.vk_private.secret'),
        ]);

        (new VkEditService($dtoUpdateMessage))->handleUpdate();
    }
}
