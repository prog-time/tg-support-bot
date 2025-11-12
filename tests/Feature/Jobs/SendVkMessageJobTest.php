<?php

namespace Tests\Feature\Jobs;

use App\DTOs\Vk\VkTextMessageDto;
use App\Jobs\SendMessage\SendVkMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\VK\VkMessageService;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\Mocks\Vk\VkUpdateDtoMock;
use Tests\TestCase;

class SendVkMessageJobTest extends TestCase
{
    public function test_send_message_for_user(): void
    {
        Message::truncate();

        $dtoVk = VkUpdateDtoMock::getDto();
        (new VkMessageService($dtoVk))->handleUpdate();

        $typeMessage = 'outgoing';
        $updateDto = TelegramUpdateDto_VKMock::getDto();

        $botUser = BotUser::getTelegramUserData($updateDto);

        $queryParams = VkTextMessageDto::from([
            'methodQuery' => 'messages.send',
            'peer_id' => $botUser->chat_id,
            'message' => 'Тестовое сообщение',
        ]);

        $job = new SendVkMessageJob($botUser, $updateDto, $queryParams);
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => $typeMessage,
            'platform' => 'vk',
        ]);
    }
}
