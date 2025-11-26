<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\TelegramBot\TelegramMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\Mocks\Tg\Answer\TelegramAnswerDtoMock;
use Tests\TestCase;

class SendExternalTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private ExternalMessageDto $dto;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->dto = ExternalMessageDtoMock::getDto();
        $this->botUser = (new BotUser())->getExternalBotUser($this->dto);
    }

    protected function tearDown(): void
    {
        if (isset($this->botUser->topic_id)) {
            DeleteForumTopic::execute($this->botUser);
        }

        parent::tearDown();
    }

    public function test_send_message_for_group(): void
    {
        try {
            $typeMessage = 'incoming';

            $textMessage = '👋 Тест из SendExternalTelegramMessageJob @ ' . now();
            $dtoParams = TelegramAnswerDtoMock::getDtoParams();

            $dtoParams['result']['text'] = $textMessage;
            $dto = TelegramAnswerDtoMock::getDto($dtoParams);

            $mockTelegramMethods = \Mockery::mock(TelegramMethods::class);
            $mockTelegramMethods->shouldReceive('sendQueryTelegram')->andReturn($dto);

            $queryParams = TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => config('testing.tg_group.chat_id'),
                'message_thread_id' => $this->botUser->topic_id,
                'text' => $textMessage,
            ]);

            $job = new SendExternalTelegramMessageJob(
                $this->botUser->id,
                $this->dto,
                $queryParams,
                $typeMessage,
                $mockTelegramMethods
            );
            $job->handle();

            $this->assertDatabaseHas('messages', [
                'bot_user_id' => $this->botUser->id,
                'message_type' => $typeMessage,
                'platform' => $this->dto->source,
            ]);
        } finally {
            if ($this->botUser->topic_id) {
                DeleteForumTopic::execute($this->botUser);
            }
        }
    }
}
