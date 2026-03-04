<?php

namespace Tests\Feature\Jobs;

use App\DTOs\Vk\VkTextMessageDto;
use App\Jobs\SendMessage\SendVkMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Telegram\Actions\DeleteForumTopic;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\VkBot\VkMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->dto = TelegramUpdateDto_VKMock::getDto();

        $chatId = time();
        $this->botUser = BotUser::getUserByChatId($chatId, 'vk');

        $jobTopicCreate = new TopicCreateJob(
            $this->botUser->id,
        );
        $jobTopicCreate->handle();

        $this->botUser->refresh();
    }

    protected function tearDown(): void
    {
        if (isset($this->botUser->topic_id)) {
            app(DeleteForumTopic::class)->execute($this->botUser);
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
            ]);
        } finally {
            if ($this->botUser->topic_id) {
                app(DeleteForumTopic::class)->execute($this->botUser);
            }
        }
    }
}
