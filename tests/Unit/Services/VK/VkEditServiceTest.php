<?php

namespace Tests\Unit\Services\VK;

use App\DTOs\Vk\VkUpdateDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\VK\VkEditService;
use App\Services\VK\VkMessageService;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class VkEditServiceTest extends TestCase
{
    private array $newMessagePayload;

    private array $editMessagePayload;

    public function setUp(): void
    {
        parent::setUp();

        $messageId = time();

        $this->newMessagePayload = [
            'group_id' => config('testing.vk_private.group_id'),
            'type' => 'message_new',
            'event_id' => '23ff3b705c7ee0ac3e762d40fa4016b88ed384a1',
            'v' => '5.199',
            'object' => [
                'message' => [
                    'date' => time(),
                    'from_id' => config('testing.vk_private.chat_id'),
                    'id' => $messageId,
                    'version' => time(),
                    'out' => 0,
                    'fwd_messages' => [],
                    'important' => false,
                    'is_hidden' => false,
                    'attachments' => [],
                    'conversation_message_id' => time(),
                    'text' => 'Test text',
                    'peer_id' => config('testing.vk_private.chat_id'),
                    'random_id' => 0,
                ],
            ],
            'secret' => config('testing.vk_private.secret'),
        ];

        $this->editMessagePayload = [
            'group_id' => config('testing.vk_private.group_id'),
            'type' => 'message_edit',
            'event_id' => '57ef84ad7140b134e155ad9cf86a2d62f0851f34',
            'v' => '5.199',
            'object' => [
                'date' => time(),
                'from_id' => config('testing.vk_private.chat_id'),
                'id' => $messageId,
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
        ];
    }

    public function test_edit_text_message(): void
    {
        $chatId = config('testing.vk_private.chat_id');
        $botUser = BotUser::getUserByChatId($chatId, 'vk');

        Message::where('bot_user_id', $botUser->id)->delete();

        // новое сообщение
        $request = Request::create('api/vk/bot', 'POST', $this->newMessagePayload);
        $dto = VkUpdateDto::fromRequest($request);

        (new VkMessageService($dto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'platform' => 'vk',
        ]);
        // ---------------

        // изменение сообщения
        $request = Request::create('api/vk/bot', 'POST', $this->editMessagePayload);
        $newMessageDto = VkUpdateDto::fromRequest($request);

        (new VkEditService($newMessageDto))->handleUpdate();

        $this->app->make('queue')->connection('sync');
    }
}
