<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\External\ExternalMessageDtoMock;
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

            $queryParams = TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => config('testing.tg_group.chat_id'),
                'message_thread_id' => $this->botUser->topic_id,
                'text' => '👋 Тест из SendExternalTelegramMessageJob @ ' . now(),
            ]);

            $job = new SendExternalTelegramMessageJob($this->botUser, $this->dto, $queryParams, $typeMessage);
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
