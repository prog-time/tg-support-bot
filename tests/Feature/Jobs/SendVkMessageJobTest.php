<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\Jobs\SendMessage\SendVkMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\VkBot\VkMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\Mocks\Vk\Answer\VkAnswerDtoMock;
use Tests\TestCase;

class SendVkMessageJobTest extends TestCase
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
            $typeMessage = 'outgoing';
            $textMessage = 'Тестовое сообщение';
            $dto = VkAnswerDtoMock::getDto();

            // Мокаем ответ от VK
            $mockTelegramMethods = \Mockery::mock(VkMethods::class);
            $mockTelegramMethods->shouldReceive('sendQueryVk')->andReturn($dto);

            $queryParams = VkTextMessageDto::from([
                'methodQuery' => 'messages.send',
                'peer_id' => $this->botUser->chat_id,
                'message' => $textMessage,
            ]);

            $job = new SendVkMessageJob(
                $this->botUser->id,
                $this->dto,
                $queryParams,
                $mockTelegramMethods
            );
            $job->handle();

            $this->assertDatabaseHas('messages', [
                'bot_user_id' => $this->botUser->id,
                'message_type' => $typeMessage,
                'platform' => 'vk',
            ]);
        } finally {
            if ($this->botUser->topic_id) {
                DeleteForumTopic::execute($this->botUser);
            }
        }
    }
}
