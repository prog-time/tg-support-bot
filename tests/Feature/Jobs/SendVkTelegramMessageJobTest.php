<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\Jobs\SendMessage\SendVkMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

class SendVkTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private TelegramUpdateDto $dto;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->dto = TelegramUpdateDto_VKMock::getDto();
        $this->botUser = BotUser::getTelegramUserData($this->dto);
    }

    protected function tearDown(): void
    {
        if (isset($this->botUser->topic_id)) {
            DeleteForumTopic::execute($this->botUser);
        }

        parent::tearDown();
    }

    public function test_send_message_for_user(): void
    {
        try {
            // Готовим параметры VK-отправки
            $queryParams = VkTextMessageDto::from([
                'methodQuery' => 'messages.send',
                'peer_id' => $this->botUser->chat_id,
                'message' => 'Тестовое сообщение',
            ]);

            // Запускаем джобу отправки
            $job = new SendVkMessageJob($this->botUser, $this->dto, $queryParams);
            $job->handle();

            // Проверяем что исходящее сообщение записано в БД
            $this->assertDatabaseHas('messages', [
                'bot_user_id' => $this->botUser->id,
                'message_type' => 'outgoing',
                'platform' => 'vk',
            ]);
        } finally {
            if ($this->botUser->topic_id) {
                DeleteForumTopic::execute($this->botUser);
            }
        }
    }
}
