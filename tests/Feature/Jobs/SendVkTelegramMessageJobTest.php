<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\TGTextMessageDto;
use App\DTOs\Vk\VkUpdateDto;
use App\Jobs\SendMessage\SendVkTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\TelegramBot\TelegramMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\Answer\TelegramAnswerDtoMock;
use Tests\Mocks\Vk\VkUpdateDtoMock;
use Tests\TestCase;

class SendVkTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private VkUpdateDto $dto;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->dto = VkUpdateDtoMock::getDto();
        $this->botUser = BotUser::getUserByChatId($this->dto->from_id, 'vk');
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
            $dtoParams = TelegramAnswerDtoMock::getDtoParams();

            $dtoParams['result']['text'] = $textMessage;
            $dto = TelegramAnswerDtoMock::getDto($dtoParams);

            // Мокаем ответ от VK
            $mockTelegramMethods = \Mockery::mock(TelegramMethods::class);
            $mockTelegramMethods->shouldReceive('sendQueryTelegram')->andReturn($dto);

            // Готовим параметры VK-отправки
            $queryParams = TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $this->botUser->chat_id,
                'text' => $textMessage,
            ]);

            $job = new SendVkTelegramMessageJob(
                $this->botUser->id,
                $this->dto,
                $queryParams,
                $typeMessage,
                $mockTelegramMethods
            );
            $job->handle();

            // Проверяем что исходящее сообщение записано в БД
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
