<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Jobs\TopicCreateJob;
use App\Models\BotUser;
use App\Models\Message;
use App\TelegramBot\TelegramMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\Answer\TelegramAnswerDtoMock;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private TelegramUpdateDto $dto;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->dto = TelegramUpdateDtoMock::getDto();
        $this->botUser = BotUser::getTelegramUserData($this->dto);

        $jobTopicCreate = new TopicCreateJob(
            $this->botUser->id,
        );
        $jobTopicCreate->handle();
    }

    protected function tearDown(): void
    {
        if (isset($this->botUser->topic_id)) {
            DeleteForumTopic::execute($this->botUser);
        }

        parent::tearDown();
    }

    public function test_success_send_creates_message_record(): void
    {
        $typeMessage = 'outgoing';

        $textMessage = 'hello';
        $dtoParams = TelegramAnswerDtoMock::getDtoParams();

        $dtoParams['result']['text'] = $textMessage;
        $dto = TelegramAnswerDtoMock::getDto($dtoParams);

        // Мокаем ответ от Telegram
        $mockTelegramMethods = \Mockery::mock(TelegramMethods::class);
        $mockTelegramMethods->shouldReceive('sendQueryTelegram')->andReturn($dto);

        $params = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $this->botUser->chat_id,
            'text' => $textMessage,
        ]);

        $job = new SendTelegramMessageJob(
            $this->botUser->id,
            $this->dto,
            $params,
            $typeMessage,
            $mockTelegramMethods
        );
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'message_type' => $typeMessage,
            'platform' => 'telegram',
            'to_id' => $dto->message_id,
        ]);
    }
}
